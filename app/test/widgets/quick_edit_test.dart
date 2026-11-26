// Quick edits from Home and the Menu list (redesign 2026-11).
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import '../helpers/fake_api.dart';
import 'flows.dart';

FakeApi _server({String role = 'owner'}) => restaurantServer(role: role)
  ..json('GET restaurant/7/items?sort=name&per=30&page=1', {
    'data': [
      {
        'id': 41,
        'name': {'en': 'Spris'},
        'category': {
          'name': {'en': 'Coffee'},
        },
        'price_minor': 9000,
        'variants': [],
        'is_sold_out': false,
        'is_visible': true,
      },
    ],
    'meta': {'page': 1, 'pages': 1},
  })
  ..json('PATCH restaurant/7/items/41/price', {'data': dish()})
  ..on('POST restaurant/7/items/bulk', (_) => (204, null));

void main() {
  testWidgets('Home › Change a price: pick, type, save — one PATCH to /price', (tester) async {
    final api = _server();
    await startApp(tester, api, token: 'tok');
    await tester.tap(find.text('Change a price'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Spris'));
    await tester.pumpAndSettle();
    expect(find.widgetWithText(TextFormField, '90'), findsOneWidget);
    await tester.enterText(find.byType(TextFormField), '95.50');
    await tester.tap(find.text('Save'));
    await tester.pumpAndSettle();
    final call = api.called('PATCH', 'restaurant/7/items/41/price').single;
    expect(call.body, {'price_minor': 9550});
    expect(find.text('Saved'), findsOneWidget);
  });

  testWidgets('Home › Hide or show: one switch hides the dish at once', (tester) async {
    final api = _server();
    await startApp(tester, api, token: 'tok');
    await tester.tap(find.text('Hide or show a dish'));
    await tester.pumpAndSettle();
    await tester.tap(find.byType(Switch).last);
    await tester.pumpAndSettle();
    final call = api.called('POST', 'restaurant/7/items/bulk').single;
    expect(call.body, {
      'ids': [41],
      'action': 'hide',
    });
  });

  testWidgets('Menu list: tapping the price opens the price sheet', (tester) async {
    final api = _server();
    await startApp(tester, api, token: 'tok');
    await tester.tap(find.text('Menu').first);
    await tester.pumpAndSettle();
    await tester.tap(find.bySemanticsLabel(RegExp('Change a price')));
    await tester.pumpAndSettle();
    expect(find.widgetWithText(TextFormField, '90'), findsOneWidget);
  });

  testWidgets('staff without price rights: no price action, price is plain text', (tester) async {
    await startApp(tester, _server(role: 'staff'), token: 'tok');
    expect(find.text('Change a price'), findsNothing);
    expect(find.text('Hide or show a dish'), findsNothing);
    await tester.tap(find.text('Menu').first);
    await tester.pumpAndSettle();
    expect(find.bySemanticsLabel(RegExp('Change a price')), findsNothing);
  });
}
