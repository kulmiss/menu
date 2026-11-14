<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

/**
 * Refuses passwords from a local list of about 9,000 common and breached
 * passwords of 10+ characters (SECURITY_REPORT.md M3). The list is
 * resources/security/common-passwords.txt (NCSC top 100k via SecLists, MIT, plus
 * local patterns), so nothing is sent anywhere. Also refuses the e-mail address
 * or its name part, and the common "word + digits" variants of listed ones.
 */
class NotCommonPassword implements ValidationRule
{
    /** @var array<string, true>|null */
    private static ?array $list = null;

    public function __construct(private readonly ?string $email = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && self::isWeak($value, $this->email)) {
            $fail(__('security.password_common'));
        }
    }

    public static function isWeak(string $password, ?string $email = null): bool
    {
        $p = Str::lower(trim($password));
        $list = self::list();
        if (isset($list[$p])) {
            return true;
        }
        // "password2026!", "qwertyuiop1": a listed base with digits or symbols added.
        $base = (string) preg_replace('/[\d\W_]+$/u', '', $p);
        if ($base !== $p && strlen($base) >= 6 && (isset($list[$base]) || in_array($base, ['password', 'qwerty', 'letmein', 'welcome', 'admin', 'iloveyou', 'restaurant'], true))) {
            return true;
        }
        if ($email !== null && $email !== '') {
            $email = Str::lower(trim($email));
            $local = Str::before($email, '@');
            if ($p === $email || (strlen($local) >= 4 && str_contains($p, $local) && strlen($p) - strlen($local) <= 4)) {
                return true;
            }
        }

        // One character repeated, or a straight run like 1234567890 / abcdefghij.
        return preg_match('/^(.)\1+$/u', $p) === 1 || str_contains('01234567890123456789', $p) || str_contains('abcdefghijklmnopqrstuvwxyz', $p);
    }

    /** @return array<string, true> */
    private static function list(): array
    {
        if (self::$list === null) {
            $words = file(resource_path('security/common-passwords.txt'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            self::$list = array_fill_keys($words, true);
        }

        return self::$list;
    }
}
