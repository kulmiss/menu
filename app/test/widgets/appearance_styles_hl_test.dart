// Appearance → Menu style lists H · Arabic to L · Colourful too (website
// QUESTIONS #229), and shows each style's options only while that style is
// picked: menu direction (H), Halal badge and Ramadan mode (I), black-and-white
// photos and the accent (K). They are saved with the rest.
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:menu_admin/app/router.dart';

import 'flows.dart';

const _all = ['editorial', 'grid', 'bistro', 'minimal', 'mesob', 'lounge', 'board', 'arabic', 'islamic', 'somali', 'mono', 'colour'];
const _labels = ['H · Arabic', 'I · Islamic', 'J · Somali', 'K · Black & White', 'L · Colourful'];

/// Scrolls the (lazily built) Appearance list until [f] is on screen: up for the style chips, down for the options.
Future<Finder> _show(WidgetTester tester, Finder f, {bool up = false}) async {
  final list = find.byWidgetPredicate((w) => w is Scrollable && w.axisDirection == AxisDirection.down).first;
  await tester.scrollUntilVisible(f, up ? -250 : 250, scrollable: list);
  await tester.pumpAndSettle();
  return f;
}

void main() {
  testWidgets('shows H–L and only the options of the picked style, and saves them', (tester) async {
    final api = restaurantServer()
      ..json('GET restaurant/7/appearance', {
        'data': {'style': 'grid', 'styles': _all, 'primary_color': '#0F766E', 'font': 'system', 'logo': null, 'cover': null, 'menu_direction': 'auto'},
      })
      ..on('PUT restaurant/7/appearance', (c) => (200, {'data': c.body}));
    final c = await startApp(tester, api, token: 'tok');
    c.read(routerProvider).push('/r/7/appearance');
    await tester.pumpAndSettle();

    for (final label in _labels) {
      expect(find.widgetWithText(ChoiceChip, label), findsOneWidget, reason: label);
    }
    // Style B has no options.
    expect(find.text('Menu direction'), findsNothing);
    expect(find.text('Ramadan mode'), findsNothing);

    await tester.tap(await _show(tester, find.widgetWithText(ChoiceChip, 'H · Arabic')));
    await tester.pumpAndSettle();
    await tester.tap(await _show(tester, find.text("Automatic (follows your menu's language)")));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Right to left').last);
    await tester.pumpAndSettle();

    await tester.tap(await _show(tester, find.widgetWithText(ChoiceChip, 'I · Islamic'), up: true));
    await tester.pumpAndSettle();
    expect(find.text('Menu direction'), findsNothing);
    await tester.tap(await _show(tester, find.text('Show a "Halal" badge')));
    await tester.pump();
    await tester.tap(await _show(tester, find.text('Ramadan mode')));
    await tester.pump();

    await tester.tap(await _show(tester, find.widgetWithText(ChoiceChip, 'K · Black & White'), up: true));
    await tester.pumpAndSettle();
    expect(find.text('Ramadan mode'), findsNothing);
    await tester.tap(await _show(tester, find.text('Black-and-white photos')));
    await tester.pump();

    await tester.tap(find.text('Save'));
    await tester.pumpAndSettle();
    final body = api.called('PUT', 'restaurant/7/appearance').single.body;
    expect(body['style'], 'mono');
    expect(body['menu_direction'], 'rtl');
    expect(body['show_halal'], isTrue);
    expect(body['ramadan_mode'], isTrue);
    expect(body['photos_greyscale'], isTrue);
    expect(body['mono_accent'], isFalse);
  });

  testWidgets('an older server without the options: defaults, nothing breaks', (tester) async {
    final api = restaurantServer()
      ..json('GET restaurant/7/appearance', {
        'data': {'style': 'islamic', 'styles': _all, 'primary_color': '#0F766E', 'font': 'system', 'logo': null, 'cover': null},
      });
    final c = await startApp(tester, api, token: 'tok');
    c.read(routerProvider).push('/r/7/appearance');
    await tester.pumpAndSettle();
    final halal = tester.widget<SwitchListTile>(find.widgetWithText(SwitchListTile, 'Show a "Halal" badge'));
    expect(halal.value, isFalse);
  });
}
