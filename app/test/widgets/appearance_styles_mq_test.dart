// Appearance → Menu style lists M · Coffee House to Q · Fine Dining too (website
// QUESTIONS #232). Each has one option, "Show the style's pictures and icons",
// which is ON unless the restaurant turned it off, and is saved with the rest.
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
const _labels = ['M · Coffee House', 'N · Grill & BBQ', 'O · Garden Fresh', 'P · Coastal', 'Q · Fine Dining'];
const _deco = "Show the style's pictures and icons";

Future<Finder> _show(WidgetTester tester, Finder f, {bool up = false}) async {
  final list = find.byWidgetPredicate((w) => w is Scrollable && w.axisDirection == AxisDirection.down).first;
  await tester.scrollUntilVisible(f, up ? -250 : 250, scrollable: list);
  await tester.pumpAndSettle();
  return f;
}

void main() {
  testWidgets('shows M–Q with the pictures switch on by default, and saves it off', (tester) async {
    final api = restaurantServer()
      ..json('GET restaurant/7/appearance', {
        // An older server without show_decoration: it counts as on.
        'data': {'style': 'grid', 'styles': _all, 'primary_color': '#0F766E', 'font': 'system', 'logo': null, 'cover': null},
      })
      ..on('PUT restaurant/7/appearance', (c) => (200, {'data': c.body}));
    final c = await startApp(tester, api, token: 'tok');
    c.read(routerProvider).push('/r/7/appearance');
    await tester.pumpAndSettle();

    for (final label in _labels) {
      expect(find.widgetWithText(ChoiceChip, label), findsOneWidget, reason: label);
    }
    expect(find.text(_deco), findsNothing); // B has no such option

    await tester.tap(await _show(tester, find.widgetWithText(ChoiceChip, 'P · Coastal')));
    await tester.pumpAndSettle();
    expect(find.text('Menu direction'), findsOneWidget); // for Arabic menus, as in H
    final sw = await _show(tester, find.widgetWithText(SwitchListTile, _deco));
    expect(tester.widget<SwitchListTile>(sw).value, isTrue);
    await tester.tap(sw);
    await tester.pump();

    await tester.tap(find.text('Save'));
    await tester.pumpAndSettle();
    final body = api.called('PUT', 'restaurant/7/appearance').single.body;
    expect(body['style'], 'coastal');
    expect(body['show_decoration'], isFalse);
  });

  testWidgets('shows a lock on the M–Q styles the plan does not include, and does not pick them', (tester) async {
    final api = restaurantServer()
      ..json('GET restaurant/7/appearance', {
        'data': {
          'style': 'grid',
          'styles': ['grid', 'coffee'],
          'primary_color': '#0F766E',
          'font': 'system',
          'logo': null,
          'cover': null,
        },
      });
    final c = await startApp(tester, api, token: 'tok');
    c.read(routerProvider).push('/r/7/appearance');
    await tester.pumpAndSettle();
    expect(tester.widget<ChoiceChip>(find.widgetWithText(ChoiceChip, 'M · Coffee House')).avatar, isNull);
    final luxe = await _show(tester, find.widgetWithText(ChoiceChip, 'Q · Fine Dining'));
    expect(tester.widget<ChoiceChip>(luxe).avatar, isNotNull);
    await tester.tap(luxe);
    await tester.pumpAndSettle();
    expect(find.text(_deco), findsNothing); // not picked: still B
  });
}
