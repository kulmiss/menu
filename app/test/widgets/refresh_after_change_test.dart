// A change made in a sheet or dialog shows at once on the screen below it
// (bug: only the saved copy on the device was dropped, the open list kept the
// old price / visibility / sold-out count until it was reopened).
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:menu_admin/app/router.dart';

import '../helpers/fake_api.dart';
import 'flows.dart';

/// The menu tree answers with the current price and visibility.
FakeApi _server() {
  var price = 9000;
  var visible = true;
  Map<String, dynamic> item() => {...dish(), 'price_minor': price, 'is_visible': visible};
  return restaurantServer()
    ..on(
      'GET restaurant/7/menus/2/tree',
      (_) => (
        200,
        {
          'data': {
            'menu': {'id': 2},
            'categories': [
              {
                'id': 5,
                'menu_id': 2,
                'name': {'en': 'Coffee'},
                'is_visible': true,
                'items': [item()],
              },
            ],
          },
        },
      ),
    )
    ..on('GET restaurant/7/items/41', (_) => (200, {'data': item()}))
    ..on('PATCH restaurant/7/items/41/price', (c) {
      price = c.body['price_minor'] as int;
      return (200, {'data': item()});
    })
    ..on('POST restaurant/7/items/bulk', (c) {
      visible = c.body['action'] == 'show';
      return (204, null);
    });
}

Future<void> _openMenu(WidgetTester tester, FakeApi api) async {
  await startApp(tester, api, token: 'tok');
  await tester.tap(find.text('Menu').first);
  await tester.pumpAndSettle();
  expect(find.text('ETB 90'), findsOneWidget);
}

void main() {
  testWidgets('Menu: a price changed in the sheet shows in the list at once', (tester) async {
    final api = _server();
    await _openMenu(tester, api);
    await tester.tap(find.bySemanticsLabel(RegExp('Change a price')));
    await tester.pumpAndSettle();
    await tester.enterText(find.byType(TextFormField), '95');
    await tester.tap(find.text('Save'));
    await tester.pumpAndSettle();
    expect(find.text('ETB 95'), findsOneWidget);
    expect(find.text('ETB 90'), findsNothing);
  });

  testWidgets('Menu: hiding a dish from the long-press menu shows "Hidden" at once', (tester) async {
    final api = _server();
    await _openMenu(tester, api);
    await tester.longPress(find.text('Spris'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Hide'));
    await tester.pumpAndSettle();
    expect(find.text('Hidden from guests'), findsOneWidget);
  });

  testWidgets('Home: the sold-out count follows a dish marked sold out', (tester) async {
    var soldOut = false;
    final api = restaurantServer()
      ..on(
        'GET restaurant/7/dashboard',
        (_) => (
          200,
          {
            'data': {
              'today': {},
              'yesterday': {},
              'sold_out': [
                if (soldOut)
                  {
                    'id': 41,
                    'name': {'en': 'Spris'},
                  },
              ],
            },
          },
        ),
      )
      ..on('PATCH restaurant/7/items/41/sold-out', (c) {
        soldOut = c.body['is_sold_out'] as bool;
        return (200, {'data': dish(soldOut: soldOut)});
      });
    await startApp(tester, api, token: 'tok');
    await tester.tap(find.text('Mark sold out'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('All dishes'));
    await tester.pumpAndSettle();
    await tester.tap(find.widgetWithText(OutlinedButton, 'Sold out'));
    await tester.pumpAndSettle();
    await tester.pageBack();
    await tester.pumpAndSettle();
    expect(find.text('Sold out now'), findsOneWidget);
    final tile = find.ancestor(of: find.text('Sold out now'), matching: find.byType(Column)).first;
    expect(find.descendant(of: tile, matching: find.text('1')), findsOneWidget);
  });

  testWidgets('All dishes (2 pages): a dish edited on page 2 shows its new name on return', (tester) async {
    tester.view.physicalSize = const Size(390, 844) * 3;
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);
    final names = {for (var i = 1; i <= 60; i++) i: 'Dish ${i.toString().padLeft(2, '0')}'};
    Map<String, dynamic> row(int i) => {
      'id': i,
      'name': {'en': names[i]},
      'category': {
        'name': {'en': 'Food'},
      },
      'price_minor': 1000,
      'variants': [],
      'is_sold_out': false,
      'is_visible': true,
    };
    final api = restaurantServer();
    for (final page in [1, 2]) {
      api.on(
        'GET restaurant/7/items?sort=category&per=50&page=$page',
        (_) => (
          200,
          {
            'data': [for (var i = page * 50 - 49; i <= (page * 50).clamp(0, 60); i++) row(i)],
            'meta': {'page': page, 'pages': 2},
          },
        ),
      );
    }
    api
      ..on(
        'GET restaurant/7/items/55',
        (_) => (
          200,
          {
            'data': {...dish(), ...row(55), 'category_id': 5, 'menu_id': 2},
          },
        ),
      )
      ..on('PATCH restaurant/7/items/55', (c) {
        names[55] = (c.body['name'] as Map)['en'] as String;
        return (200, {'data': row(55)});
      });
    final c = await startApp(tester, api, token: 'tok');
    c.read(routerProvider).go('/r/7/all-dishes');
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(find.text('Dish 55'), 300, scrollable: find.byType(Scrollable).last);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Dish 55'));
    await tester.pumpAndSettle();
    await tester.enterText(find.byKey(const Key('dishName')), 'Dish 55 renamed');
    await tester.tap(find.byKey(const Key('saveDish')));
    await tester.pumpAndSettle();
    await tester.pageBack();
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(find.text('Dish 55 renamed'), 300, scrollable: find.byType(Scrollable).last);
    expect(find.text('Dish 55 renamed'), findsOneWidget);
    expect(find.text('Dish 55'), findsNothing);
  });
}
