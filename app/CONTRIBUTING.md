# Contributing

- Small pull requests into `develop` (never `main`). Tests in every PR. CI green (`dart format -l 160`, `flutter analyze`, `flutter test`). The owner merges.
- Decisions go into [QUESTIONS.md](QUESTIONS.md) as M-numbers.
- Texts through `context.l` (`lib/l10n/app_en.arb`). Tap targets at least 48 dp. Light and dark. Start-up stays fast: nothing new before the first frame.

## Docs rule: change the feature, change its docs in the same PR

| You changed… | Update |
|---|---|
| A screen, a button name or a rule | the help article in kulmiss/menu `backend/resources/help/` (its `app:` paths for the **?** button), in a PR next to this one |
| How a screen looks | the screenshots: `flutter test --run-skipped -t store --update-goldens test/store/help_shots_test.dart`, `tool/help_shots.sh ../menu` (and the goldens) |
| An API call | kulmiss/menu `docs/mobile-api.md` (a test there fails when an app endpoint has no section) |
| A new icon | `tool/icons/names.txt`, then `tool/icons/gen.py` |
| Setup, release or Firebase steps | docs/laptop.md, docs/release.md, docs/firebase.md |
