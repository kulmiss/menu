import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:menu_admin/core/auth/token_store.dart';
import 'package:menu_admin/core/platform/app_lock.dart';
import 'package:menu_admin/core/providers.dart';

import '../helpers/fake_api.dart';

/// SECURITY_REPORT.md M15: wrong PINs wait, then sign the app out.
void main() {
  late ProviderContainer c;
  late MemoryTokenStore store;
  late FakeApi api;

  setUp(() async {
    api = FakeApi()..json('POST mobile/auth/logout', null, 204);
    store = MemoryTokenStore();
    await store.write(TokenKeys.token, 'tok');
    c = ProviderContainer(
      overrides: testOverrides(api, store: store),
      retry: noRetry,
    );
    await c.read(appLockProvider.notifier).setPin('2468');
  });
  tearDown(() => c.dispose());

  test('waits 30 s → 5 min after 5 wrong PINs, and survives a restart', () async {
    final lock = c.read(appLockProvider.notifier);
    var t = DateTime(2026, 11, 14, 10);
    for (var i = 1; i <= 4; i++) {
      final r = await lock.checkPin('0000', now: t);
      expect(r.kind, PinOutcome.wrong);
      expect(r.wait, Duration.zero);
      expect(r.triesLeft, AppLock.maxTries - i);
    }
    final fifth = await lock.checkPin('0000', now: t);
    expect(fifth.wait, const Duration(seconds: 30));
    // Even the right PIN waits.
    expect((await lock.checkPin('2468', now: t.add(const Duration(seconds: 10)))).kind, PinOutcome.wait);

    // A new app start reads the same storage.
    final again = ProviderContainer(
      overrides: testOverrides(api, store: store),
      retry: noRetry,
    );
    addTearDown(again.dispose);
    t = t.add(const Duration(seconds: 31));
    expect((await again.read(appLockProvider.notifier).checkPin('1111', now: t)).wait, const Duration(seconds: 60));
    expect(AppLock.waitAfter(9), const Duration(seconds: 300));

    // The right PIN after the wait resets everything.
    t = t.add(const Duration(seconds: 61));
    expect((await again.read(appLockProvider.notifier).checkPin('2468', now: t)).ok, isTrue);
    expect(await store.read('pin_fails'), isNull);
  });

  test('signs the app out after 10 wrong PINs', () async {
    final lock = c.read(appLockProvider.notifier);
    var t = DateTime(2026, 11, 14, 10);
    PinResult r = const PinResult.ok();
    for (var i = 1; i <= AppLock.maxTries; i++) {
      r = await lock.checkPin('9999', now: t);
      t = t.add(const Duration(minutes: 6));
    }
    expect(r.kind, PinOutcome.signedOut);
    expect(await store.read(TokenKeys.token), isNull);
    expect(c.read(sessionProvider).status, SessionStatus.signedOut);
  });
}
