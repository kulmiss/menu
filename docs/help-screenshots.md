# Help screenshots

The Help centre's pictures (`backend/public/help/shots/`) are real screenshots, taken by scripts from the demo data. Re-take them after a screen changes. A test fails when an article uses a picture that is missing.

## Web admin and guest menu (phone + desktop)

```bash
cd backend
php artisan migrate:fresh --seed --seeder=DemoSeeder   # test computer only, never the live server
php artisan demo:photos                                 # real dish photos
php artisan serve                                       # keep it running
cd ../frontend
pnpm build                                              # the admin the screenshots show
node scripts/help-screenshots.mjs                       # all shots
node scripts/help-screenshots.mjs http://127.0.0.1:8000 dashboard,team   # only some
```

Each shot is made twice: `<name>-phone.webp` (390 px wide, a phone) and `<name>-desktop.webp` (1000 px wide). The list of shots, and what the script clicks before each picture, is at the top of `frontend/scripts/help-screenshots.mjs`. A new article picture: add it there, run the script, and use `![Alt](shot:<name>)` in the article.

## Mobile app (phone)

In the app repository (kulmiss/menu-admin-app):

```bash
flutter test --run-skipped -t store --update-goldens test/store/help_shots_test.dart
tool/help_shots.sh ../menu        # → menu/backend/public/help/shots/app-*.webp
```

Use them with `![Alt](appshot:app-home)`.
