// Appearance → Menu style lists every style the server knows, A–L (website
// QUESTIONS #228): E · Mesob, F · Night Lounge and G · Street Board too, with
// the restaurant's current one selected, and saves the one picked.
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:menu_admin/app/router.dart';

import 'flows.dart';

const _all = [
  'editorial',
  'grid',
  'bistro',
  'minimal',
  'mesob',
  'lounge',
  'board',
  'arabic',
  'islamic',
  'somali',
  'mono',
  'colour',
  'coffee',
  'grill',
  'garden',
  'coastal',
  'luxe',
];
const _labels = ['A · Editorial', 'B · Grid', 'C · Bistro', 'D · Minimal', 'E · Mesob', 'F · Night Lounge', 'G · Street Board'];

void main() {
  testWidgets('shows A–G, selects F · Night Lounge, saves E · Mesob', (tester) async {
    final api = restaurantServer()
      ..json('GET restaurant/7/appearance', {
        'data': {'style': 'lounge', 'styles': _all, 'primary_color': '#C58BFF', 'font': 'system', 'logo': null, 'cover': null},
      })
      ..on('PUT restaurant/7/appearance', (c) => (200, {'data': c.body}));
    final c = await startApp(tester, api, token: 'tok');
    c.read(routerProvider).push('/r/7/appearance');
    await tester.pumpAndSettle();

    for (final label in _labels) {
      expect(find.widgetWithText(ChoiceChip, label), findsOneWidget, reason: label);
    }
    final lounge = tester.widget<ChoiceChip>(find.widgetWithText(ChoiceChip, 'F · Night Lounge'));
    expect(lounge.selected, isTrue);

    await tester.tap(find.widgetWithText(ChoiceChip, 'E · Mesob'));
    await tester.pump();
    await tester.tap(find.text('Save'));
    await tester.pumpAndSettle();
    expect(api.called('PUT', 'restaurant/7/appearance').single.body['style'], 'mesob');
  });

  testWidgets('a style the plan does not include has a lock and is not picked', (tester) async {
    final api = restaurantServer()
      ..json('GET restaurant/7/appearance', {
        'data': {
          'style': 'grid',
          'styles': ['grid', 'bistro'],
          'primary_color': '#0F766E',
          'font': 'system',
          'logo': null,
          'cover': null,
        },
      });
    final c = await startApp(tester, api, token: 'tok');
    c.read(routerProvider).push('/r/7/appearance');
    await tester.pumpAndSettle();
    final board = tester.widget<ChoiceChip>(find.widgetWithText(ChoiceChip, 'G · Street Board'));
    expect(board.avatar, isNotNull);
    expect(board.selected, isFalse);
  });
}
