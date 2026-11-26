// Leaving a screen (or closing a sheet) while its save is still on its way
// must not throw "Using ref when a widget is about to or has been unmounted"
// (the Riverpod error the owner saw), and the change must still reach the
// lists that show it.
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:menu_admin/app/router.dart';

import '../store/store_screenshots_test.dart' show demoPlatform;
import 'flows.dart';
import 'help_test.dart' show library;

void main() {
  testWidgets('Inbox: "Mark all read", leave before the answer', (tester) async {
    final api = restaurantServer()
      ..json('GET mobile/notifications', {
        'data': [
          {'id': 1, 'type': 'plan_expiring', 'title': 'Plan', 'body': 'Ends soon', 'link': null, 'read': false, 'created_at': '2026-11-16T10:00:00Z'},
        ],
        'meta': {'next_cursor': null},
      })
      ..on('POST mobile/notifications/read', (_) => (204, null));
    final slow = api.hold('POST mobile/notifications/read');
    final c = await startApp(tester, api, token: 'tok');
    c.read(routerProvider).push('/inbox');
    await tester.pumpAndSettle();
    await tester.tap(find.text('Mark all as read'));
    await tester.pump();
    c.read(routerProvider).pop();
    await tester.pumpAndSettle();
    slow.complete();
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
  });

  testWidgets('QR codes: add one, leave before the answer', (tester) async {
    final api = restaurantServer()
      ..json('GET restaurant/7/qr-codes', {'data': []})
      ..json('POST restaurant/7/qr-codes', {
        'data': {'id': 3, 'label': 'Door'},
      });
    final slow = api.hold('POST restaurant/7/qr-codes');
    final c = await startApp(tester, api, token: 'tok');
    c.read(routerProvider).push('/r/7/qr');
    await tester.pumpAndSettle();
    await tester.tap(find.byType(FloatingActionButton));
    await tester.pumpAndSettle();
    await tester.enterText(find.byType(TextField).last, 'Door');
    await tester.tap(find.text('Add'));
    await tester.pump();
    c.read(routerProvider).pop();
    await tester.pumpAndSettle();
    slow.complete();
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
    expect(api.called('POST', 'restaurant/7/qr-codes'), hasLength(1));
  });

  testWidgets('Dish editor: save, leave before the answer — the menu still reloads', (tester) async {
    final api = restaurantServer();
    final slow = api.hold('PATCH restaurant/7/items/41');
    final c = await startApp(tester, api, token: 'tok');
    await tester.tap(find.text('Menu').first);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Spris'));
    await tester.pumpAndSettle();
    await tester.enterText(find.byKey(const Key('dishName')), 'Spris special');
    await tester.tap(find.byKey(const Key('saveDish')));
    await tester.pump();
    c.read(routerProvider).go('/r/7/menu');
    await tester.pumpAndSettle();
    final before = api.called('GET', 'restaurant/7/menus/2/tree').length;
    slow.complete();
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
    expect(api.called('GET', 'restaurant/7/menus/2/tree').length, greaterThan(before), reason: 'the open menu shows the saved dish');
  });

  testWidgets('Price sheet: close it before the answer', (tester) async {
    final api = restaurantServer()..json('PATCH restaurant/7/items/41/price', {'data': dish()});
    final slow = api.hold('PATCH restaurant/7/items/41/price');
    await startApp(tester, api, token: 'tok');
    await tester.tap(find.text('Menu').first);
    await tester.pumpAndSettle();
    await tester.tap(find.bySemanticsLabel(RegExp('Change a price')));
    await tester.pumpAndSettle();
    await tester.enterText(find.byType(TextFormField), '95');
    await tester.tap(find.text('Save'));
    await tester.pump();
    await tester.tapAt(const Offset(10, 10)); // the scrim closes the sheet
    await tester.pumpAndSettle();
    slow.complete();
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
  });

  testWidgets('Team: invite someone, leave before the answer', (tester) async {
    final api = restaurantServer()
      ..json('GET restaurant/7/members', {'data': []})
      ..json('GET restaurant/7/invitations', {'data': []})
      ..json('POST restaurant/7/invitations', {
        'data': {'id': 9},
      });
    final slow = api.hold('POST restaurant/7/invitations');
    final c = await startApp(tester, api, token: 'tok');
    c.read(routerProvider).push('/r/7/team');
    await tester.pumpAndSettle();
    await tester.tap(find.byType(FloatingActionButton));
    await tester.pumpAndSettle();
    await tester.enterText(find.byType(TextField).first, 'new@example.com');
    await tester.tap(find.widgetWithText(FilledButton, 'Invite'));
    await tester.pump();
    c.read(routerProvider).pop();
    await tester.pumpAndSettle();
    slow.complete();
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
  });

  testWidgets('Super Admin: a second tap while a switch is saving sends nothing', (tester) async {
    final api = demoPlatform()
      ..on(
        'PUT super/settings',
        (c) => (
          200,
          {
            'data': {'signup_open': c.body['signup_open']},
          },
        ),
      );
    final slow = api.hold('PUT super/settings');
    await startApp(tester, api, token: 'admin-tok');
    await tester.tap(find.byKey(const Key('sa-signup_open')));
    await tester.pump();
    await tester.tap(find.byKey(const Key('sa-signup_open')));
    await tester.pump();
    slow.complete();
    await tester.pumpAndSettle();
    expect(api.called('PUT', 'super/settings'), hasLength(1));
    expect(tester.takeException(), isNull);
  });

  testWidgets('Help: "Was this helpful?", leave the article before the answer', (tester) async {
    final api = restaurantServer()
      ..json('GET restaurant/7/help', library())
      ..on('POST help/feedback', (_) => (204, null));
    final slow = api.hold('POST help/feedback');
    final c = await startApp(tester, api, token: 'tok');
    c.read(routerProvider).push('/help/add-a-dish');
    await tester.pumpAndSettle();
    await tester.tap(find.text('Yes'));
    await tester.pump();
    c.read(routerProvider).pop();
    await tester.pumpAndSettle();
    slow.complete();
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
  });

  testWidgets('Appearance: save, leave before the answer', (tester) async {
    final api = restaurantServer()
      ..json('GET restaurant/7/appearance', {
        'data': {'style': 'editorial', 'primary_color': '#0F766E', 'font': 'system', 'logo': null, 'cover': null},
      })
      ..on('PUT restaurant/7/appearance', (c) => (200, {'data': c.body}));
    final slow = api.hold('PUT restaurant/7/appearance');
    final c = await startApp(tester, api, token: 'tok');
    c.read(routerProvider).push('/r/7/appearance');
    await tester.pumpAndSettle();
    // With 17 styles the font choice is below the first screen: scroll to it.
    final list = find.byWidgetPredicate((w) => w is Scrollable && w.axisDirection == AxisDirection.down).first;
    await tester.scrollUntilVisible(find.text('Classic (serif)'), 250, scrollable: list);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Classic (serif)'));
    await tester.pump();
    await tester.tap(find.text('Save'));
    await tester.pump();
    c.read(routerProvider).go('/r/7');
    await tester.pumpAndSettle();
    slow.complete();
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
    expect(api.called('PUT', 'restaurant/7/appearance'), hasLength(1));
  });
}
