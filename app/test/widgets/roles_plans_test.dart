// What each role and each plan sees (scenario matrix, 2026-11-17).
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:menu_admin/app/router.dart';
import 'package:menu_admin/core/widgets/kit.dart';

import 'flows.dart';

const _free = {
  'insights': false,
  'promotions': false,
  'price_periods': false,
  'branches': false,
  'pdf_menu': false,
  'order_list': false,
  'import_export': false,
};

Future<List<String>> _moreItems(WidgetTester tester, String role, {Map<String, bool>? features}) async {
  final c = await startApp(
    tester,
    restaurantServer(role: role, features: features),
    token: 'tok',
  );
  c.read(routerProvider).go('/r/7/more');
  await tester.pumpAndSettle();
  return [for (final w in find.descendant(of: find.byType(ListTile), matching: find.byType(Text)).evaluate().map((e) => e.widget as Text)) w.data ?? ''];
}

void main() {
  testWidgets('Owner: every restaurant screen, billing included', (tester) async {
    final items = await _moreItems(tester, 'owner');
    for (final t in ['All dishes', 'Insights', 'Branches', 'QR codes', 'Appearance', 'Promotions', 'Happy hour', 'Team', 'Activity', 'Billing']) {
      expect(items, contains(t), reason: t);
    }
  });

  testWidgets('Manager: everything but billing', (tester) async {
    final items = await _moreItems(tester, 'manager');
    expect(items, containsAll(['Insights', 'Branches', 'Team', 'Activity']));
    expect(items, isNot(contains('Billing')));
  });

  testWidgets('Staff: dishes and sold out only; Home has no manager actions, no Orders tab', (tester) async {
    final items = await _moreItems(tester, 'staff');
    expect(items, containsAll(['All dishes', 'Sold out']));
    for (final t in ['Insights', 'Branches', 'QR codes', 'Appearance', 'Promotions', 'Team', 'Activity', 'Billing']) {
      expect(items, isNot(contains(t)), reason: t);
    }
    await tester.tap(find.text('Home').first);
    await tester.pumpAndSettle();
    expect(find.text('Hide or show a dish'), findsNothing);
    expect(find.text('Add dish with photo'), findsNothing);
    expect(find.text('Mark sold out'), findsOneWidget);
    expect(find.text('Orders'), findsNothing);
  });

  testWidgets('Staff opening a manager screen from a link: a message, no crash', (tester) async {
    final api = restaurantServer(role: 'staff')..json('GET restaurant/7/members', {'message': 'This action is unauthorized.'}, 403);
    final c = await startApp(tester, api, token: 'tok');
    c.read(routerProvider).push('/r/7/team');
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
    expect(find.byType(ErrorView), findsOneWidget);
  });

  testWidgets('Free plan: no Orders tab; Insights and Promotions show the upgrade card', (tester) async {
    final c = await startApp(tester, restaurantServer(features: _free), token: 'tok');
    expect(find.text('Orders'), findsNothing);
    for (final path in ['/r/7/insights', '/r/7/promotions']) {
      c.read(routerProvider).push(path);
      await tester.pumpAndSettle();
      expect(find.text('Upgrade to use this'), findsOneWidget, reason: path);
      c.read(routerProvider).pop();
      await tester.pumpAndSettle();
    }
  });

  testWidgets('Plan with "My order": Orders tab for owners', (tester) async {
    await startApp(tester, restaurantServer(features: {..._free, 'order_list': true}), token: 'tok');
    expect(find.text('Orders'), findsWidgets);
  });

  testWidgets('Read-only (unpaid) restaurant: a save shows the upgrade sheet, nothing breaks', (tester) async {
    final api = restaurantServer()
      ..json('PATCH restaurant/7/items/41/price', {'message': 'Your plan is read-only. Renew to make changes.', 'code': 'subscription.read_only'}, 402);
    await startApp(tester, api, token: 'tok');
    await tester.tap(find.text('Menu').first);
    await tester.pumpAndSettle();
    await tester.tap(find.bySemanticsLabel(RegExp('Change a price')));
    await tester.pumpAndSettle();
    await tester.enterText(find.byType(TextFormField), '95');
    await tester.tap(find.text('Save'));
    // The Save spinner keeps turning under the sheet, so no pumpAndSettle.
    for (var i = 0; i < 10; i++) {
      await tester.pump(const Duration(milliseconds: 100));
    }
    expect(tester.takeException(), isNull);
    expect(find.text('Upgrade to use this'), findsOneWidget);
  });
}
