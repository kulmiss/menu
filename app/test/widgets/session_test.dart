// Sign-in and sign-out edge cases on a bad network.
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:menu_admin/core/auth/token_store.dart';
import 'package:menu_admin/core/providers.dart';

import 'flows.dart';

void main() {
  testWidgets('sign-in: the password is right but /me fails — back on the sign-in screen with the error, not stuck on the logo', (tester) async {
    final api = restaurantServer()
      ..json('POST mobile/auth/login', {'token': 'tok-1'})
      ..json('GET me', {'message': 'Server error'}, 500);
    final c = await startApp(tester, api);
    await tester.enterText(find.byKey(const Key('email')), 'owner@example.com');
    await tester.enterText(find.byKey(const Key('password')), 'correct-horse');
    await tester.tap(find.byKey(const Key('submit')));
    await tester.pumpAndSettle();
    expect(c.read(sessionProvider).status, SessionStatus.signedOut);
    expect(find.byKey(const Key('submit')), findsOneWidget, reason: 'the sign-in form, so they can try again');
    expect(await c.read(tokenStoreProvider).read(TokenKeys.token), isNull);
  });

  testWidgets('sign out: a server that never answers still signs out within seconds', (tester) async {
    final api = restaurantServer()..on('POST mobile/auth/logout', (_) => (204, null));
    api.hold('POST mobile/auth/logout');
    final c = await startApp(tester, api, token: 'tok');
    var done = false;
    c.read(sessionProvider.notifier).signOut().then((_) => done = true).ignore();
    for (var i = 0; i < 20 && !done; i++) {
      await tester.pump(const Duration(milliseconds: 500));
    }
    expect(done, isTrue, reason: 'signed out within 10 seconds');
    expect(c.read(sessionProvider).status, SessionStatus.signedOut);
  });

  testWidgets('a slow /me answer that arrives after signing out does not sign back in', (tester) async {
    final api = restaurantServer()..on('POST mobile/auth/logout', (_) => (204, null));
    final c = await startApp(tester, api, token: 'tok');
    await tester.runAsync(() async {
      // Made here: a completer from the fake-time zone would only answer when the test pumps.
      final slow = api.hold('GET me');
      final late = c.read(sessionProvider.notifier).refreshMe().then<void>((_) {}, onError: (Object _) {});
      await Future<void>.delayed(const Duration(milliseconds: 50));
      await c.read(sessionProvider.notifier).signOut();
      slow.complete();
      await late;
    });
    await tester.pumpAndSettle();
    expect(c.read(sessionProvider).status, SessionStatus.signedOut);
    expect(c.read(sessionProvider).me, isNull);
    expect(await c.read(tokenStoreProvider).read(TokenKeys.me), isNull, reason: 'the old profile is not written back');
  });
}
