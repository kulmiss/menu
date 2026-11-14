<?php

namespace App\Support;

/**
 * Where the "Upgrade" hint points when a plan limit or feature is reached
 * (owner, 2026-11-13; payments are manual): the platform's WhatsApp number
 * and support e-mail from Platform settings.
 */
class UpgradeContact
{
    /** @return array{whatsapp: string|null, email: string|null} */
    public static function payload(): array
    {
        $phone = preg_replace('/\D+/', '', (string) PlatformSettings::get('upgrade_whatsapp'));
        $email = PlatformSettings::get('support_email');

        return [
            'whatsapp' => $phone !== '' ? 'https://wa.me/'.$phone : null,
            'email' => is_string($email) && $email !== '' ? $email : null,
        ];
    }
}
