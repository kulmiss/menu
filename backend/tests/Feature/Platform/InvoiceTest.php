<?php

use App\Enums\Role;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Tenant;
use App\Notifications\InvoiceNotification;
use App\Support\Billing\Invoices;
use App\Support\PlatformSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/** Invoices and receipts (owner, 2026-11-13). */
function pay(Tenant $tenant, array $extra = []): array
{
    return test()->postJson(p("restaurants/{$tenant->id}/payments"), [
        'amount_minor' => 115000, 'method' => 'mobile_money', 'reference' => 'TX-1', 'paid_at' => now()->toDateString(), 'months' => 1, ...$extra,
    ], idem())->assertCreated()->json('data');
}

beforeEach(function () {
    Notification::fake();
    PlatformSettings::save(['billing_company' => 'Buna Tech PLC', 'billing_address' => 'Bole Road, Addis Ababa', 'billing_tax_id' => '0012345678', 'invoice_footer' => 'Thank you for your business.']);
});

it('issues a numbered invoice with every payment and e-mails it to the owner', function () {
    $admin = superAdmin();
    [$owner, $tenant] = member(Role::Owner);
    $this->actingAs($admin);

    $row = pay($tenant);
    $year = now()->year;
    expect($row['invoices'][0]['number'])->toBe("INV-{$year}-0001");
    $invoice = Invoice::sole();
    expect($invoice->status)->toBe('paid')
        ->and($invoice->total_minor)->toBe(115000)
        ->and($invoice->seller['name'])->toBe('Buna Tech PLC')
        ->and($invoice->seller['tax_id'])->toBe('0012345678')
        ->and($invoice->customer)->toMatchArray(['name' => $tenant->name, 'owner' => $owner->name, 'email' => $owner->email])
        ->and($invoice->emailed_at)->not->toBeNull();

    Notification::assertSentTo($owner, InvoiceNotification::class, function (InvoiceNotification $n) use ($owner, $invoice) {
        $mail = $n->toMail($owner);

        return $mail->rawAttachments[0]['name'] === "{$invoice->number}.pdf"
            && str_starts_with($mail->rawAttachments[0]['data'], '%PDF');
    });

    // The PDF itself: text from the invoice, not from today's settings.
    PlatformSettings::save(['billing_company' => 'Renamed Ltd']);
    $pdf = $this->get(p("invoices/{$invoice->id}/pdf"))->assertOk()->assertHeader('Content-Type', 'application/pdf')->getContent();
    expect($pdf)->toStartWith('%PDF')->toContain('Buna Tech PLC')->toContain("INV-{$year}-0001")->not->toContain('Renamed Ltd');
});

it('numbers invoices one after another without gaps, per prefix and year', function () {
    $this->actingAs(superAdmin());
    [, $a] = member(Role::Owner);
    [, $b] = member(Role::Owner);
    $year = now()->year;

    foreach ([$a, $b, $a] as $tenant) {
        pay($tenant);
    }
    expect(Invoice::orderBy('id')->pluck('number')->all())->toBe(["INV-{$year}-0001", "INV-{$year}-0002", "INV-{$year}-0003"]);

    // A failed save rolls the counter back: no hole in the numbers.
    try {
        DB::transaction(function () use ($a) {
            app(Invoices::class)->issue(Payment::where('tenant_id', $a->id)->first());
            throw new RuntimeException('disk full');
        });
    } catch (RuntimeException) {
    }
    pay($b);
    expect(Invoice::latest('id')->value('number'))->toBe("INV-{$year}-0004");

    // The counter row is locked while a number is taken, so parallel requests wait in line.
    $sql = [];
    DB::listen(function ($q) use (&$sql) {
        $sql[] = strtolower($q->sql);
    });
    pay($a);
    expect(collect($sql)->contains(fn ($s) => str_contains($s, 'invoice_sequences') && str_contains($s, 'for update')))->toBeTrue();

    // A new prefix starts its own series; next year starts again at 0001.
    PlatformSettings::save(['invoice_prefix' => 'MNU']);
    pay($a);
    expect(Invoice::latest('id')->value('number'))->toBe("MNU-{$year}-0001");
    $this->travelTo(now()->addYear()->startOfYear()->addDay());
    pay($b, ['paid_at' => now()->toDateString()]);
    expect(Invoice::latest('id')->value('number'))->toBe('MNU-'.($year + 1).'-0001')
        ->and(Invoice::count())->toBe(Invoice::distinct()->count('number'));
});

it('never edits or deletes an invoice: it is voided with a reason and a new one issued', function () {
    $this->actingAs(superAdmin());
    [$owner, $tenant] = member(Role::Owner);
    pay($tenant);
    $first = Invoice::sole();

    $this->postJson(p("invoices/{$first->id}/void"), ['reason' => 'x'], idem())->assertUnprocessable();
    $res = $this->postJson(p("invoices/{$first->id}/void"), ['reason' => 'Wrong amount', 'reissue' => true], idem())->assertOk()->json('data');
    expect($res['void']['status'])->toBe('void')->and($res['void']['void_reason'])->toBe('Wrong amount')
        ->and($res['new']['number'])->toBe('INV-'.now()->year.'-0002')->and($res['new']['replaces_id'])->toBe($first->id);
    $this->postJson(p("invoices/{$first->id}/void"), ['reason' => 'Again'], idem())->assertStatus(409);
    expect(Invoice::count())->toBe(2);

    $pdf = $this->get(p("invoices/{$first->id}/pdf"))->getContent();
    expect($pdf)->toContain('VOID')->toContain('Wrong amount');
    $pdf = $this->get(p('invoices/'.$res['new']['id'].'/pdf'))->getContent();
    expect($pdf)->toContain('replaces INV-'.now()->year.'-0001');

    // There is no route to change or delete one.
    $this->patchJson(p("invoices/{$first->id}"), [], idem())->assertNotFound();
    $this->deleteJson(p("invoices/{$first->id}"), [], idem())->assertNotFound();
});

it('lists, filters and exports invoices; resends one; VAT is shown as included', function () {
    PlatformSettings::save(['invoice_tax_rate' => 15, 'invoice_email' => false]);
    $this->actingAs(superAdmin());
    [$owner, $tenant] = member(Role::Owner);
    [, $other] = member(Role::Owner);
    pay($tenant);
    pay($other, ['amount_minor' => 50000]);
    Notification::assertNothingSent();

    $invoice = Invoice::where('tenant_id', $tenant->id)->sole();
    expect($invoice->tax_rate)->toBe('15.00')->and($invoice->subtotal_minor)->toBe(100000)->and($invoice->tax_minor)->toBe(15000);

    $this->getJson(p('invoices'))->assertOk()->assertJsonCount(2, 'data');
    $this->getJson(p('invoices?q='.urlencode($tenant->name)))->assertJsonCount(1, 'data')->assertJsonPath('data.0.tenant.id', $tenant->id);
    $this->getJson(p('invoices?q=0002'))->assertJsonCount(1, 'data')->assertJsonPath('data.0.tenant.id', $other->id);
    $this->getJson(p("invoices?tenant_id={$other->id}"))->assertJsonCount(1, 'data');
    $this->getJson(p('invoices?from='.now()->addDay()->toDateString()))->assertJsonCount(0, 'data');
    $this->getJson(p('invoices?status=void'))->assertJsonCount(0, 'data');

    $csv = $this->get(p('invoices.csv'))->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->getContent();
    expect($csv)->toContain('Number,Issued,Status')->toContain($invoice->number)->toContain('1000.00,15.00,150.00,1150.00,ETB');

    $this->postJson(p("invoices/{$invoice->id}/resend"), [], idem())->assertOk();
    Notification::assertSentTo($owner, InvoiceNotification::class);
});

it('shows Billing to the owner only, with their own invoices', function () {
    $admin = superAdmin();
    [$owner, $tenant] = member(Role::Owner);
    [$manager] = member(Role::Manager, $tenant);
    [, $other] = member(Role::Owner);
    $this->actingAs($admin);
    pay($tenant);
    pay($other);
    $mine = Invoice::where('tenant_id', $tenant->id)->sole();
    $theirs = Invoice::where('tenant_id', $other->id)->sole();
    app('auth')->forgetGuards();

    $this->actingAs($owner)->getJson(t($tenant, 'billing'))->assertOk()
        ->assertJsonPath('data.invoices.0.number', $mine->number)->assertJsonCount(1, 'data.invoices')
        ->assertJsonPath('data.paid_until', $tenant->fresh()->paid_until->toDateString());
    $this->get(t($tenant, "billing/invoices/{$mine->id}/pdf"))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->get(t($tenant, "billing/invoices/{$theirs->id}/pdf"))->assertNotFound();

    app('auth')->forgetGuards();
    $this->actingAs($manager)->getJson(t($tenant, 'billing'))->assertForbidden();
    $this->getJson(p('invoices'))->assertNotFound(); // the platform area doesn't exist for restaurants
});

it('saves the billing settings', function () {
    $this->actingAs(superAdmin())->putJson(p('settings'), [
        'billing_company' => ' Buna Tech PLC ', 'invoice_prefix' => 'mnu', 'invoice_tax_rate' => 15.5, 'invoice_email' => false,
    ], idem())->assertOk()
        ->assertJsonPath('data.billing_company', 'Buna Tech PLC')
        ->assertJsonPath('data.invoice_prefix', 'MNU')
        ->assertJsonPath('data.invoice_email', false);
    $this->putJson(p('settings'), ['invoice_prefix' => 'IN V'], idem())->assertUnprocessable();
    $this->putJson(p('settings'), ['invoice_tax_rate' => 120], idem())->assertUnprocessable();
});

it('backfills invoices for older payments without e-mailing anyone', function () {
    [, $tenant] = member(Role::Owner);
    Payment::create(['tenant_id' => $tenant->id, 'amount_minor' => 5000, 'currency' => 'ETB', 'method' => 'cash', 'paid_at' => now()->subMonths(2)->toDateString(), 'period_start' => now()->subMonths(2)->toDateString(), 'period_end' => now()->subMonth()->toDateString()]);
    $this->artisan('invoices:backfill')->assertSuccessful();
    $this->artisan('invoices:backfill')->assertSuccessful();
    expect(Invoice::count())->toBe(1);
    Notification::assertNothingSent();
});
