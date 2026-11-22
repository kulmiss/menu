<?php

use App\Support\Legal\LegalText;
use App\Support\PlatformSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function runLegalSeed(): void
{
    (require database_path('migrations/2026_11_14_300001_seed_legal_texts.php'))->up();
}

it('ships the default Terms and Privacy, the same as docs/legal', function () {
    foreach (['terms', 'privacy'] as $page) {
        $text = LegalText::default($page);
        expect($text)->toStartWith('Last updated: ')
            ->toContain('Kulmiss')
            ->toContain('info@kulmiss.com')
            ->and(file_get_contents(base_path("../docs/legal/{$page}.md")))->toBe($text);
        expect(LegalText::parse($text)['toc'])->not->toBeEmpty();
    }
    // About us, Contact us and Cookie info (#230): sections, and the same copy in docs/legal.
    foreach (['about', 'contact', 'cookies'] as $page) {
        $text = LegalText::default($page);
        expect(LegalText::parse($text)['toc'])->not->toBeEmpty()
            ->and(file_get_contents(base_path("../docs/legal/{$page}.md")))->toBe($text);
    }
    expect(LegalText::default('terms'))->toContain('Ethiopia')->toContain('18 years');
    expect(LegalText::default('privacy'))->toContain('under 13')->toContain('1321/2024');
});

it('fills empty texts when migrating, and the pages show them', function () {
    // The migration already ran for this test database: both texts are there.
    expect(PlatformSettings::get('terms'))->toBe(LegalText::default('terms'))
        ->and(PlatformSettings::get('privacy'))->toBe(LegalText::default('privacy'));

    PlatformSettings::save(['terms' => '', 'privacy' => "  \n "]);
    runLegalSeed();
    expect(PlatformSettings::get('terms'))->toBe(LegalText::default('terms'))
        ->and(PlatformSettings::get('privacy'))->toBe(LegalText::default('privacy'));

    // Missing rows altogether.
    DB::table('platform_settings')->whereIn('key', ['terms', 'privacy'])->delete();
    runLegalSeed();
    expect(PlatformSettings::get('terms'))->toBe(LegalText::default('terms'));

    $this->get('/terms')->assertOk()
        ->assertSee('Last updated: 13 November 2026')
        ->assertSee('<details class="sec" id="plans-and-payments">', false)
        ->assertSee('<summary><h2>4. Plans and payments</h2>', false)
        ->assertSee('<a href="mailto:info@kulmiss.com">info@kulmiss.com</a>', false);
    $this->get('/privacy')->assertOk()
        ->assertSee('<details class="sec" id="what-we-collect-from-guests">', false)
        ->assertSee('Personal Data Protection Proclamation No. 1321/2024');
});

it('never overwrites texts that were edited, and is safe to run twice', function () {
    PlatformSettings::save(['terms' => 'Our own terms.', 'privacy' => '']);
    runLegalSeed();
    runLegalSeed();
    expect(PlatformSettings::get('terms'))->toBe('Our own terms.')
        ->and(PlatformSettings::get('privacy'))->toBe(LegalText::default('privacy'));
    expect(DB::table('platform_settings')->where('key', 'terms')->count())->toBe(1);
});

it('lets a Super Admin reset one text to the default', function () {
    PlatformSettings::save(['terms' => 'Edited.', 'privacy' => 'Edited too.']);
    [$owner] = member();

    $this->actingAs($owner)->postJson(p('settings/legal/terms/reset'), [], idem())->assertNotFound();
    $this->actingAs(superAdmin())->postJson(p('settings/legal/terms/reset'), [], idem())->assertOk()
        ->assertJsonPath('data.terms', LegalText::default('terms'));
    expect(PlatformSettings::get('terms'))->toBe(LegalText::default('terms'))
        ->and(PlatformSettings::get('privacy'))->toBe('Edited too.');
    $this->actingAs(superAdmin())->postJson(p('settings/legal/refunds/reset'), [], idem())->assertNotFound();
});

it('edits and resets the About us, Contact us and Cookie info pages too (#230)', function () {
    $admin = superAdmin();
    // Never edited: the editor shows the default text, like the page.
    $this->actingAs($admin)->getJson(p('settings'))->assertOk()
        ->assertJsonPath('data.about', LegalText::default('about'))
        ->assertJsonPath('data.cookies', LegalText::default('cookies'));
    $this->actingAs($admin)->putJson(p('settings'), ['about' => "## Who we are\nA small team.", 'contact' => 'Write to us.', 'cookies' => 'Only needed cookies.'], idem())->assertOk();
    expect(PlatformSettings::get('about'))->toBe("## Who we are\nA small team.");
    $this->get('/about')->assertOk()->assertSee('Who we are')->assertSee('A small team.');
    $this->get('/cookies')->assertOk()->assertSee('Only needed cookies.');
    $this->actingAs($admin)->postJson(p('settings/legal/contact/reset'), [], idem())->assertOk()
        ->assertJsonPath('data.contact', LegalText::default('contact'));
    expect(PlatformSettings::get('contact'))->toBe(LegalText::default('contact'));
    $this->actingAs($admin)->putJson(p('settings'), ['cookies' => str_repeat('x', 50001)], idem())->assertInvalid('cookies');
});

it('replaces the first Privacy default (with Google Fonts) only when it was not edited', function () {
    $run = fn () => (require database_path('migrations/2026_11_14_400001_update_privacy_default.php'))->up();
    $old = file_get_contents(database_path('legal-history/privacy-2026-11-13-v1.md'));
    expect($old)->toContain('Google Fonts')
        ->and(LegalText::default('privacy'))->not->toContain('Google Fonts');

    PlatformSettings::save(['privacy' => str_replace("\n", "\r\n", $old)]); // saved from a Windows textarea
    $run();
    expect(PlatformSettings::get('privacy'))->toBe(LegalText::default('privacy'));

    PlatformSettings::save(['privacy' => $old."\nOur own extra line."]);
    $run();
    $run();
    expect(PlatformSettings::get('privacy'))->toEndWith('Our own extra line.');
});

it('loads the admin fonts from the app itself, never from Google', function () {
    $html = file_get_contents(base_path('../frontend/index.html'));
    $css = file_get_contents(base_path('../frontend/src/styles/fonts.css'));
    expect($html)->not->toContain('googleapis')->not->toContain('gstatic')
        ->and($css)->not->toContain('http');
    preg_match_all("#url\\('\\.\\./assets/fonts/([^']+)'\\)#", $css, $m);
    expect($m[1])->toHaveCount(16);
    foreach ($m[1] as $file) {
        expect(is_file(base_path('../frontend/src/assets/fonts/'.$file)))->toBeTrue($file);
    }
});

it('adds the last sign-in line to the Privacy default only when it was not edited (QUESTIONS #187)', function () {
    $run = fn () => (require database_path('migrations/2026_11_15_100001_last_sign_in.php'))->up();
    $old = file_get_contents(database_path('legal-history/privacy-2026-11-13-v2.md'));
    expect($old)->not->toContain('Last sign-in')
        ->and(LegalText::default('privacy'))->toContain('**Last sign-in:**')
        ->and(file_get_contents(base_path('../docs/legal/privacy.md')))->toBe(LegalText::default('privacy'));

    Schema::table('tenant_health', fn ($t) => $t->dropColumn('last_sign_in_at'));
    PlatformSettings::save(['privacy' => $old]);
    $run();
    expect(PlatformSettings::get('privacy'))->toBe(LegalText::default('privacy'));

    Schema::table('tenant_health', fn ($t) => $t->dropColumn('last_sign_in_at'));
    PlatformSettings::save(['privacy' => $old."\nOur own extra line."]);
    $run();
    expect(PlatformSettings::get('privacy'))->toEndWith('Our own extra line.');
});

it('never touches the Terms when other settings are saved (owner, 2026-11-21)', function () {
    $admin = superAdmin();
    $this->actingAs($admin)->putJson(p('settings'), ['terms' => "## Our terms\nEdited by hand."], idem())->assertOk();
    $before = DB::table('platform_settings')->where('key', 'terms')->first();

    $this->actingAs($admin)->putJson(p('settings'), ['name' => 'Menu Plus', 'support_email' => 'help@example.com', 'default_currency' => 'USD'], idem())->assertOk();

    expect(PlatformSettings::get('name'))->toBe('Menu Plus')
        ->and(PlatformSettings::get('terms'))->toBe("## Our terms\nEdited by hand.")
        ->and(DB::table('platform_settings')->where('key', 'terms')->first())->toEqual($before);
});

it('tells the editor when each text was last saved (owner, 2026-11-21)', function () {
    $admin = superAdmin();
    // An emptied text shows the default, so it counts as never saved.
    PlatformSettings::save(['cookies' => '']);
    DB::table('platform_settings')->where('key', 'about')->delete();
    $this->travelTo(now()->setDate(2026, 11, 12)->setTime(9, 30));
    $this->actingAs($admin)->putJson(p('settings'), ['terms' => 'New terms.'], idem())->assertOk();
    $this->travelBack();

    $this->actingAs($admin)->getJson(p('settings'))->assertOk()
        ->assertJsonPath('data.legal_updated_at.terms', '2026-11-12T09:30:00+00:00')
        ->assertJsonPath('data.legal_updated_at.cookies', null)
        ->assertJsonPath('data.legal_updated_at.about', null)
        ->assertJsonPath('data.cookies', LegalText::default('cookies'));
});
