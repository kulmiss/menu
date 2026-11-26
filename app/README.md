# Menu Admin

The Flutter app for restaurant teams (Owner, Manager, Staff) of the Menu platform ([kulmiss/menu](https://github.com/kulmiss/menu)), plus a small Super Admin screen (overview, emergency switches, link to the web console). Android first; iOS builds from the same code.

## Start here

| You want to… | Read |
|---|---|
| Run it on your laptop | [docs/laptop.md](docs/laptop.md) |
| Release it / Play Store | [docs/release.md](docs/release.md) · store texts: [store/listing.md](store/listing.md) |
| Push, crash reports, analytics | [docs/firebase.md](docs/firebase.md) |
| Know the API it talks to | [mobile-api.md](https://github.com/kulmiss/menu/blob/develop/docs/mobile-api.md) · [API reference](https://github.com/kulmiss/menu/blob/develop/docs/api-reference.md) |
| Help for users | in the app: More → Help (same articles as the web, `backend/resources/help/` in kulmiss/menu) |
| Super Admin guide | [kulmiss/menu docs/admin-guide](https://github.com/kulmiss/menu/blob/develop/docs/admin-guide/README.md) |
| Why something is the way it is | [QUESTIONS.md](QUESTIONS.md) · [PLAN.md](PLAN.md) · [docs/redesign.md](docs/redesign.md) |
| Where the project stands | [handover.md](handover.md) |
| Change something | [CONTRIBUTING.md](CONTRIBUTING.md) |

- Design (tokens, components, top tasks, before/after): [docs/redesign.md](docs/redesign.md)
- Run it on your laptop: [docs/laptop.md](docs/laptop.md) · Firebase: [docs/firebase.md](docs/firebase.md) · Release and Play Store: [docs/release.md](docs/release.md)

```powershell
flutter pub get
flutter run --dart-define=API_BASE_URL=http://<your PC's IP>:8000
flutter test --exclude-tags golden
```

Stack: Riverpod 3, go_router, dio (Bearer token, Idempotency-Key, retries, error mapping), drift (offline-first cache), flutter_secure_storage, Firebase (optional: push, Crashlytics, Analytics), the web admin's shadcn tokens + Inter + Lucide icons, light/dark (phone setting or Settings › Theme), phones and tablets.
