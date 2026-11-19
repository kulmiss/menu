<?php

use App\Enums\Role;
use App\Models\Branch;
use App\Models\DiningTable;
use App\Models\Plan;
use App\Models\QrCode;
use App\Models\TenantAppearance;
use App\Support\Contrast;
use App\Support\Features;
use App\Support\PlatformSettings;
use App\Support\Print\PrintMaterials;
use App\Support\PublicMenu\PageTheme;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/** Page sizes in points, the number of pages, and all page text (streams inflated). */
function pdfFacts(string $pdf): array
{
    preg_match_all('#/MediaBox \[0 0 ([\d.]+) ([\d.]+)\]#', $pdf, $boxes, PREG_SET_ORDER);
    $text = '';
    preg_match_all('#/Length (\d+)>>\nstream\n#', $pdf, $streams, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
    foreach ($streams as $m) {
        $s = substr($pdf, $m[0][1] + strlen($m[0][0]), (int) $m[1][0]);
        $text .= (@gzuncompress($s) ?: $s)."\n";
    }

    return [
        'sizes' => array_map(fn ($b) => [round($b[1] / 72 * 25.4, 1), round($b[2] / 72 * 25.4, 1)], $boxes),
        'pages' => preg_match_all('#/Type /Page\b(?!s)#', $pdf),
        'text' => $text,
        'images' => substr_count($pdf, '/Subtype /Image'),
    ];
}

function printUrl($tenant, array $q): string
{
    return t($tenant, 'print-materials.pdf').'?'.http_build_query([
        'material' => 'poster_a4', 'design' => 'classic', 'theme' => 'light', ...$q,
    ]);
}

it('makes every material at its real paper size, with vector QR codes', function () {
    // 48 PDFs in a row: far more than the 30-a-minute download limit a person needs.
    RateLimiter::for('exports', fn () => Limit::none());
    [$owner, $tenant] = member(Role::Owner);
    $code = asTenant($tenant, fn () => QrCode::firstOrFail());

    $sizes = [
        'poster_a4' => [210.0, 297.0],
        'poster_a5' => [148.0, 210.0],
        'window' => [297.0, 210.0],
        'tent' => [210.0, 297.0],
        'sticker_round' => [210.0, 297.0],
        'sticker_square' => [210.0, 297.0],
    ];
    foreach ($sizes as $material => $size) {
        foreach (PrintMaterials::DESIGNS as $design) {
            foreach (['light', 'dark'] as $theme) {
                $res = $this->actingAs($owner)->get(printUrl($tenant, ['material' => $material, 'design' => $design, 'theme' => $theme, 'codes' => (string) $code->id]));
                $res->assertOk()->assertHeader('Content-Type', 'application/pdf');
                $facts = pdfFacts($res->getContent());
                expect($facts['pages'])->toBe(1, $material)
                    ->and($facts['images'])->toBe(0) // no logo here, and the code is drawn as squares
                    ->and(array_unique($facts['sizes'], SORT_REGULAR))->toEqual([$size]);
            }
        }
    }

    // A print shop gets 3 mm bleed on every side; tents and stickers stay exact.
    $bleed = pdfFacts($this->actingAs($owner)->get(printUrl($tenant, ['codes' => (string) $code->id, 'bleed' => 1]))->getContent());
    expect($bleed['sizes'][0])->toEqual([216.0, 303.0]);
    $bleed = pdfFacts($this->actingAs($owner)->get(printUrl($tenant, ['material' => 'window', 'codes' => (string) $code->id, 'bleed' => 1]))->getContent());
    expect($bleed['sizes'][0])->toEqual([303.0, 216.0]);
    $bleed = pdfFacts($this->actingAs($owner)->get(printUrl($tenant, ['material' => 'tent', 'codes' => (string) $code->id, 'bleed' => 1]))->getContent());
    expect($bleed['sizes'][0])->toEqual([210.0, 297.0]);
});

it('prints one face per table, the headline, the table name and "Menu by" only on plans with branding', function () {
    [$owner, $tenant] = member(Role::Owner);
    $branch = asTenant($tenant, fn () => Branch::firstOrFail());
    $tables = asTenant($tenant, fn () => collect(range(1, 5))->map(function ($n) use ($branch) {
        $table = DiningTable::create(['branch_id' => $branch->id, 'label' => (string) $n, 'sort' => $n]);
        QrCode::create(['code' => QrCode::newCode(), 'branch_id' => $branch->id, 'dining_table_id' => $table->id]);

        return $table;
    }));
    $ids = asTenant($tenant, fn () => QrCode::whereIn('dining_table_id', $tables->pluck('id'))->orderBy('id')->pluck('id'))->implode(',');

    // Five tables: posters one page each, tents two per page, stickers 12 per page.
    $poster = pdfFacts($this->actingAs($owner)->get(printUrl($tenant, ['codes' => $ids, 'headline' => 'Happy hour inside']))->getContent());
    expect($poster['pages'])->toBe(5)
        ->and($poster['text'])->toContain('(Happy hour inside)')
        ->and($poster['text'])->toContain('(Table 3)')
        ->and($poster['text'])->toContain('(Menu by ');
    expect(pdfFacts($this->actingAs($owner)->get(printUrl($tenant, ['material' => 'tent', 'codes' => $ids]))->getContent())['pages'])->toBe(3);
    $stickers = pdfFacts($this->actingAs($owner)->get(printUrl($tenant, ['material' => 'sticker_square', 'codes' => $ids]))->getContent());
    expect($stickers['pages'])->toBe(1)->and(substr_count($stickers['text'], '(Table '))->toBe(5);
    // One code fills a whole sticker sheet.
    $one = pdfFacts($this->actingAs($owner)->get(printUrl($tenant, ['material' => 'sticker_round', 'codes' => explode(',', $ids)[0]]))->getContent());
    expect(substr_count($one['text'], '(Table 1)'))->toBe(12);

    // The default headline, and no "Menu by" when the plan removes branding.
    $plan = Plan::create(['name' => ['en' => 'Pro'], 'slug' => 'pro-test', 'limits' => []]);
    DB::table('plan_features')->insert(['plan_id' => $plan->id, 'feature_key' => 'remove_branding', 'enabled' => true]);
    $tenant->forceFill(['plan_id' => $plan->id])->save();
    Features::forget();
    $pro = pdfFacts($this->actingAs($owner)->get(printUrl($tenant, ['codes' => explode(',', $ids)[0]]))->getContent());
    expect($pro['text'])->toContain('(Scan for our menu)')->and($pro['text'])->not->toContain('Menu by');
});

it('prints in the colours of the menu style ("Like my menu"), readable on every style', function () {
    foreach (PageTheme::STYLES as $style => $st) {
        foreach (['light', 'dark'] as $theme) {
            foreach (['#b4432a', '#fde047', '#0b1f3a'] as $brand) {
                $c = PrintMaterials::palette('style', $theme, PrintMaterials::rgb($brand), ['light' => $st['light'], 'dark' => $st['dark'], 'dark_only' => $st['dark_only'] ?? false]);
                $page = ($theme === 'dark' || ($st['dark_only'] ?? false)) ? $st['dark'] : $st['light'];
                expect($c['bg'])->toBe(PrintMaterials::rgb($page), "{$style} {$theme}")
                    ->and(Contrast::ratio(sprintf('#%02x%02x%02x', ...$c['text']), $page))->toBeGreaterThanOrEqual(7.0, "{$style} {$theme} text")
                    ->and(Contrast::ratio(sprintf('#%02x%02x%02x', ...$c['muted']), $page))->toBeGreaterThanOrEqual(4.5, "{$style} {$theme} muted")
                    ->and(Contrast::ratio(sprintf('#%02x%02x%02x', ...$c['accent']), $page))->toBeGreaterThanOrEqual(3.0, "{$style} {$theme} {$brand} headline");
            }
        }
    }

    [$owner, $tenant] = member(Role::Owner);
    $code = asTenant($tenant, function () {
        TenantAppearance::create([...TenantAppearance::DEFAULTS, 'style' => 'grill', 'primary_color' => '#e8622a']);

        return QrCode::firstOrFail();
    });
    $this->actingAs($owner)->getJson(t($tenant, 'print-materials'))->assertOk()
        ->assertJsonPath('data.surface', ['light' => PageTheme::STYLES['grill']['light'], 'dark' => PageTheme::STYLES['grill']['dark'], 'dark_only' => true]);
    $pdf = $this->actingAs($owner)->get(printUrl($tenant, ['material' => 'tent', 'design' => 'style', 'codes' => (string) $code->id]))->assertOk()->getContent();
    // The grill's charcoal page (#231d1a = 0.137 0.114 0.102) fills the faces.
    expect(pdfFacts($pdf)['text'])->toContain('0.137 0.114 0.102 rg');
});

it('checks who asks and what they ask for', function () {
    [$owner, $tenant] = member(Role::Owner);
    [$staff] = member(Role::Staff, $tenant);
    [$other] = member(Role::Owner);
    $code = asTenant($tenant, fn () => QrCode::firstOrFail());

    $this->actingAs($owner)->getJson(t($tenant, 'print-materials'))->assertOk()
        ->assertJsonPath('data.name', $tenant->name)
        ->assertJsonPath('data.headline', 'Scan for our menu')
        ->assertJsonPath('data.branding', 'Menu by '.PlatformSettings::get('name'));
    $this->actingAs($staff)->getJson(t($tenant, 'print-materials'))->assertForbidden();
    $this->actingAs($staff)->get(printUrl($tenant, ['codes' => (string) $code->id]))->assertForbidden();
    $this->actingAs($other)->get(printUrl($tenant, ['codes' => (string) $code->id]))->assertNotFound();
    $this->actingAs($owner)->getJson(printUrl($tenant, ['material' => 'billboard', 'codes' => (string) $code->id]))->assertInvalid('material');
    $this->actingAs($owner)->getJson(printUrl($tenant, ['headline' => str_repeat('x', 41), 'codes' => (string) $code->id]))->assertInvalid('headline');
    $this->actingAs($owner)->getJson(printUrl($tenant, ['codes' => '999999']))->assertNotFound();
    $this->actingAs($owner)->get(printUrl($tenant, ['codes' => (string) $code->id, 'inline' => 1]))
        ->assertHeader('Content-Disposition', 'inline; filename="'.Str::slug($tenant->name.' poster a4').'.pdf"');
});
