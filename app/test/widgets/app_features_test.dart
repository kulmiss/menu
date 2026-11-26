// Super Admin → Mobile app → App features: each switch off hides its part of
// the app (and the lock never locks anyone out when it's switched off).
import 'package:flutter_test/flutter_test.dart';
import 'package:menu_admin/app/router.dart';
import 'package:menu_admin/core/auth/token_store.dart';
import 'package:menu_admin/core/providers.dart';
import 'package:menu_admin/core/platform/app_lock.dart';
import 'package:menu_admin/features/auth/gates.dart' show LockScreen;

import 'app_control_test.dart' show config;
import 'flows.dart';

void main() {
  testWidgets('all on (or an older server without the keys): lock, usage stats and crash reports are in Settings', (tester) async {
    final c = await startApp(tester, restaurantServer(), token: 'tok');
    c.read(routerProvider).push('/account/app');
    await tester.pumpAndSettle();
    expect(find.text('App lock'), findsWidgets);
    expect(find.text('Anonymous usage statistics'), findsOneWidget);
    expect(find.text('Send crash reports'), findsOneWidget);
  });

  testWidgets('each one off: gone from Settings', (tester) async {
    final api = restaurantServer();
    config(api, {
      'app_features': {'biometric_lock': false, 'analytics': false, 'crash_reports': false, 'push': false},
    });
    final c = await startApp(tester, api, token: 'tok');
    c.read(routerProvider).push('/account/app');
    await tester.pumpAndSettle();
    expect(find.text('App lock'), findsNothing);
    expect(find.text('Anonymous usage statistics'), findsNothing);
    expect(find.text('Send crash reports'), findsNothing);
  });

  testWidgets('lock switched off for everyone while this phone had it on: the app opens, no lock screen', (tester) async {
    final api = restaurantServer();
    config(api, {
      'app_features': {'biometric_lock': false},
    });
    final c = await startApp(tester, api, token: 'tok');
    final store = c.read(tokenStoreProvider) as MemoryTokenStore;
    store.values['lock_enabled'] = '1';
    await tester.runAsync(() => c.read(appLockProvider.notifier).load());
    await tester.pumpAndSettle();
    expect(c.read(appLockProvider).locked, isTrue);
    expect(find.byType(LockScreen), findsNothing);
    expect(find.text('Home'), findsWidgets);
  });

  testWidgets('lock on and allowed: the lock screen covers the app', (tester) async {
    final c = await startApp(tester, restaurantServer(), token: 'tok');
    final store = c.read(tokenStoreProvider) as MemoryTokenStore;
    store.values['lock_enabled'] = '1';
    await tester.runAsync(() => c.read(appLockProvider.notifier).load());
    await tester.pump();
    expect(find.byType(LockScreen), findsOneWidget);
  });
}
