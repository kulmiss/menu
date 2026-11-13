<?php

use App\Support\Legal\LegalText;
use App\Support\PlatformSettings;
use Illuminate\Support\Facades\DB;

function runLegalSeed(): void
{
    (require database_path('migrations/2026_11_14_300001_seed_legal_texts.php'))->up();
}

it('ships the default Terms and Privacy, the same as docs/legal', function () {
    foreach (LegalText::PAGES as $page) {
        $text = LegalText::default($page);
        expect($text)->toStartWith('Last updated: ')
            ->toContain('Kulmiss')
            ->toContain('info@kulmiss.com')
            ->and(file_get_contents(base_path("../docs/legal/{$page}.md")))->toBe($text);
        expect(LegalText::parse($text)['toc'])->not->toBeEmpty();
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
        ->assertSee('<h2 id="plans-and-payments">4. Plans and payments</h2>', false)
        ->assertSee('<a href="mailto:info@kulmiss.com">info@kulmiss.com</a>', false);
    $this->get('/privacy')->assertOk()
        ->assertSee('<h2 id="what-we-collect-from-guests">3. What we collect from guests</h2>', false)
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
    $this->actingAs(superAdmin())->postJson(p('settings/legal/cookies/reset'), [], idem())->assertNotFound();
});
