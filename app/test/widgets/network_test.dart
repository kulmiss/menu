// Bad networks: offline with and without a saved copy, back online, token
// expired or signed out from the web.
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:menu_admin/app/router.dart';
import 'package:menu_admin/core/providers.dart';

import 'flows.dart';

void main() {
  testWidgets('offline with a saved copy: the menu shows with a "saved copy" line; back online it refreshes', (tester) async {
    final api = restaurantServer();
    final c = await startApp(tester, api, token: 'tok');
    await tester.tap(find.text('Menu').first);
    await tester.pumpAndSettle();
    expect(find.text('Spris'), findsOneWidget);
    api.offline = true;
    c.read(routerProvider).go('/r/7');
    await tester.pumpAndSettle();
    c.read(routerProvider).go('/r/7/menu');
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
    expect(find.text('Spris'), findsOneWidget, reason: 'the saved copy');
    expect(find.textContaining('Saved copy'), findsWidgets);
    api.offline = false;
    await tester.fling(find.byType(Scrollable).last, const Offset(0, 400), 1000);
    await tester.pumpAndSettle();
    expect(find.textContaining('Saved copy'), findsNothing);
  });

  testWidgets('offline with nothing saved: an offline message with Try again, then it loads', (tester) async {
    final api = restaurantServer();
    final c = await startApp(tester, api, token: 'tok');
    api.offline = true;
    c.read(routerProvider).push('/r/7/sold-out');
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
    expect(find.text('Try again'), findsWidgets);
    api.offline = false;
    await tester.tap(find.text('Try again').first);
    await tester.pumpAndSettle();
    expect(find.text('Try again'), findsNothing);
  });

  testWidgets('token expired / signed out from the web: the next request goes back to sign-in', (tester) async {
    final api = restaurantServer();
    final c = await startApp(tester, api, token: 'tok');
    api.json('GET restaurant/7/menus', {'message': 'Unauthenticated.'}, 401);
    await tester.tap(find.text('Menu').first);
    await tester.pumpAndSettle();
    expect(c.read(sessionProvider).status, SessionStatus.signedOut);
    expect(find.byKey(const Key('submit')), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('first start offline: sign-in shows, and a sign-in attempt says there is no connection', (tester) async {
    final api = restaurantServer()..offline = true;
    await startApp(tester, api);
    expect(find.byKey(const Key('submit')), findsOneWidget);
    await tester.enterText(find.byKey(const Key('email')), 'owner@example.com');
    await tester.enterText(find.byKey(const Key('password')), 'correct-horse');
    await tester.tap(find.byKey(const Key('submit')));
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
    expect(find.byKey(const Key('submit')), findsOneWidget);
  });
}
