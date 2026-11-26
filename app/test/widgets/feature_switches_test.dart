// "Sold out" and other feature switches from /me (backend QUESTIONS #191/#192):
// when the Super Admin turns a feature off (plan, restaurant or everyone),
// the app hides its controls.
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'flows.dart';

const _all = {'insights': true, 'promotions': true, 'price_periods': true, 'branches': true, 'pdf_menu': true};

void main() {
  testWidgets('sold out on: Home count, quick action and menu switches are there', (tester) async {
    await startApp(tester, restaurantServer(features: {..._all, 'sold_out': true}), token: 'tok');
    expect(find.text('Sold out now'), findsOneWidget);
    expect(find.text('Mark sold out'), findsOneWidget);
    await tester.tap(find.text('Menu').first);
    await tester.pumpAndSettle();
    expect(find.byType(Switch), findsOneWidget);
  });

  testWidgets('sold out off: no Home count, no quick action, no switches, marks ignored', (tester) async {
    final api = restaurantServer(features: {..._all, 'sold_out': false});
    await startApp(tester, api, token: 'tok');
    expect(find.text('Sold out now'), findsNothing);
    expect(find.text('Mark sold out'), findsNothing);
    await tester.tap(find.text('More').first);
    await tester.pumpAndSettle();
    expect(find.text('Sold out'), findsNothing);

    await tester.tap(find.text('Menu').first);
    await tester.pumpAndSettle();
    expect(find.text('Spris'), findsOneWidget);
    expect(find.byType(Switch), findsNothing);
  });

  testWidgets('an older server without the key keeps "Sold out" on', (tester) async {
    await startApp(tester, restaurantServer(features: _all), token: 'tok');
    expect(find.text('Mark sold out'), findsOneWidget);
  });

  testWidgets('print materials off for everyone: not in More', (tester) async {
    await startApp(tester, restaurantServer(features: {..._all, 'print_materials': false}), token: 'tok');
    await tester.tap(find.text('More').first);
    await tester.pumpAndSettle();
    expect(find.text('QR codes'), findsOneWidget);
    expect(find.text('Print materials'), findsNothing);
  });
}
