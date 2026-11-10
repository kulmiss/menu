<?php

namespace App\Rules;

use App\Tenancy\CurrentTenant;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Text in several languages: {"en": "…", "am": "…"}. Keys are language
 * codes, values strings up to `max` characters. When required, the
 * restaurant's default language must be filled in.
 */
class Translations implements ValidationRule
{
    public function __construct(private readonly bool $required = true, private readonly int $max = 120) {}

    public static function required(int $max = 120): self
    {
        return new self(true, $max);
    }

    public static function optional(int $max = 2000): self
    {
        return new self(false, $max);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null && ! $this->required) {
            return;
        }

        if (! is_array($value)) {
            $fail('menu.translations.format')->translate();

            return;
        }

        foreach ($value as $locale => $text) {
            if (! is_string($locale) || ! preg_match('/^[a-z]{2,3}(-[A-Za-z]{2,4})?$/', $locale)) {
                $fail('menu.translations.format')->translate();

                return;
            }
            if ($text !== null && ! is_string($text)) {
                $fail('menu.translations.format')->translate();

                return;
            }
            if (is_string($text) && mb_strlen($text) > $this->max) {
                $fail('menu.translations.too_long')->translate(['max' => $this->max]);

                return;
            }
        }

        if ($this->required) {
            $default = app(CurrentTenant::class)->get()->default_locale ?? 'en';
            if (trim((string) ($value[$default] ?? '')) === '') {
                $fail('menu.translations.default_missing')->translate([
                    'language' => strtoupper($default),
                ]);
            }
        }
    }
}
