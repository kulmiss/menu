# Landing 1: backup (2026-11-22)

A copy of the home page templates as they were before Landing 2 was added:

- `landing.blade.php` (from `resources/views/site/landing.blade.php`)
- `landing/*.blade.php` (from `resources/views/site/landing/`)

Landing 1 itself was **not changed**: it is still `resources/views/site/landing.blade.php`
and stays the home page until the Super Admin picks Landing 2 (Settings → General →
Home page design). Its pictures are in `public/landing/` (shared, unchanged).

Also kept:

- Git tag `landing-v1-2026-11-22` (the code before Landing 2).
- `tests/Fixtures/landing-v1.html`: the rendered home page; `LandingGoldenTest` checks it
  is still the same, byte for byte.

These copies are not used by the app. To restore by hand: copy them back over
`resources/views/site/`.
