<?php

use App\Support\Legal\LegalText;
use App\Support\PlatformSettings;

/** The default Privacy Policy describes the security changes; only an unedited text is updated. */
it('describes the security log, security e-mails and encrypted backups', function () {
    expect(LegalText::default('privacy'))
        ->toContain('security log')->toContain('deleted after 30 days')
        ->toContain('"was this you?"')->toContain('recovery codes only as secure hashes')
        ->toContain('Backups are encrypted')->toContain('**Security log:** 30 days.');
});

it('updates the stored Privacy Policy only when it is the unedited previous default', function () {
    $migration = require database_path('migrations/2026_11_16_100005_update_privacy_default_security.php');

    PlatformSettings::save(['privacy' => file_get_contents(database_path('legal-history/privacy-2026-11-15-v3.md'))]);
    $migration->up();
    expect(PlatformSettings::get('privacy'))->toBe(LegalText::default('privacy'));

    PlatformSettings::save(['privacy' => 'Our own text.']);
    $migration->up();
    expect(PlatformSettings::get('privacy'))->toBe('Our own text.');
});
