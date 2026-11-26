// Main user flows, shared by widget tests (fake server) and integration_test
// (same fake server on a real phone): login + 2FA, edit a dish, sold-out
// toggle, record a payment (PLAN.md §11).
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:menu_admin/app/app.dart';
import 'package:menu_admin/app/router.dart';
import 'package:menu_admin/core/auth/token_store.dart';
import 'package:menu_admin/core/providers.dart';

import '../helpers/fake_api.dart';
import '../store/store_screenshots_test.dart' show demoPlatform;

Map<String, dynamic> dish({bool soldOut = false}) => {
  'id': 41,
  'category_id': 5,
  'menu_id': 2,
  'name': {'en': 'Spris'},
  'summary': {},
  'description': {'en': 'Layered juice'},
  'price_minor': 9000,
  'variants': [],
  'addon_group_ids': [],
  'tag_ids': [],
  'allergens': [],
  'photos': [],
  'is_sold_out': soldOut,
  'is_visible': true,
  'show_photo': true,
};

/// A fake restaurant server with enough answers for the main screens.
FakeApi restaurantServer({String role = 'owner', Map<String, bool>? features}) {
  final api = FakeApi()
    ..json('GET mobile/config', {
      'data': {'min_version': '1.0.0', 'links': {}},
    })
    ..json('GET me', meJson(role: role, features: features))
    ..json('GET restaurant/7/dashboard', {
      'data': {'today': {}, 'yesterday': {}, 'sold_out': []},
    })
    ..json('GET restaurant/7/widgets/trend', {
      'data': {
        'days': [
          for (var i = 0; i < 7; i++) {'date': '2026-11-0$i', 'views': i * 10, 'scans': i},
        ],
        'today': {},
        'yesterday': {'views': 50, 'scans': 5},
      },
    })
    ..json('GET restaurant/7/announcements', {'data': []})
    ..json('GET restaurant/7/menus', {
      'data': [
        {
          'id': 2,
          'name': {'en': 'All day'},
          'is_visible': true,
          'schedules': [],
          'branch_ids': [],
        },
      ],
    })
    ..json('GET restaurant/7/menus/2/tree', {
      'data': {
        'menu': {'id': 2},
        'categories': [
          {
            'id': 5,
            'menu_id': 2,
            'name': {'en': 'Coffee'},
            'is_visible': true,
            'items': [dish()],
          },
        ],
      },
    })
    ..json('GET restaurant/7/items/41', {'data': dish()})
    ..json('GET restaurant/7/tags', {'data': []})
    ..json('GET restaurant/7/addon-groups', {'data': []})
    ..json('PATCH restaurant/7/items/41/sold-out', {'data': dish(soldOut: true)})
    ..on(
      'PATCH restaurant/7/items/41',
      (c) => (
        200,
        {
          'data': {...dish(), 'name': c.body['name']},
        },
      ),
    )
    ..json('GET restaurant/7/items?status=sold_out&sort=name&per=100&page=1', {
      'data': [],
      'meta': {'page': 1, 'pages': 1},
    })
    ..json('GET restaurant/7/items?sort=name&per=100&page=1', {
      'data': [
        {
          'id': 41,
          'name': {'en': 'Spris'},
          'category': {
            'name': {'en': 'Coffee'},
          },
          'is_sold_out': false,
          'is_visible': true,
        },
      ],
      'meta': {'page': 1, 'pages': 1},
    });
  return api;
}

/// Starts the real app with a fake server; returns the container.
Future<ProviderContainer> startApp(WidgetTester tester, FakeApi api, {String? token}) async {
  final store = MemoryTokenStore();
  if (token != null) store.values[TokenKeys.token] = token;
  final container = ProviderContainer(
    overrides: testOverrides(api, store: store),
    retry: noRetry,
  );
  addTearDown(container.dispose);
  await tester.runAsync(() => container.read(sessionProvider.notifier).restore());
  await tester.pumpWidget(UncontrolledProviderScope(container: container, child: const MenuAdminApp()));
  await tester.pumpAndSettle();
  return container;
}

Future<void> loginWithTwoFactor(WidgetTester tester) async {
  final api = restaurantServer()
    ..json('POST mobile/auth/login', {'two_factor': true, 'challenge': 'c' * 48})
    ..on(
      'POST mobile/auth/two-factor',
      (c) => c.body['code'] == '123456'
          ? (
              200,
              {
                'token': 'tok-1',
                'user': {'id': 1},
              },
            )
          : (
              422,
              {
                'message': 'Wrong',
                'errors': {
                  'code': ['That code is not right.'],
                },
              },
            ),
    );
  final container = await startApp(tester, api);

  expect(find.text('Sign in to Menu Admin'), findsOneWidget);
  await tester.enterText(find.byKey(const Key('email')), 'owner@example.com');
  await tester.enterText(find.byKey(const Key('password')), 'correct-horse');
  await tester.tap(find.byKey(const Key('submit')));
  await tester.pumpAndSettle();

  expect(find.text('Two-step sign-in'), findsOneWidget);
  final login = api.called('POST', 'mobile/auth/login').single;
  expect(login.body['device_name'], isNotEmpty);
  expect(login.headers['Idempotency-Key'], isNull, reason: 'sign-in is not stored for replay');

  await tester.enterText(find.byKey(const Key('code')), '123456');
  await tester.pumpAndSettle();

  expect(container.read(sessionProvider).status, SessionStatus.signedIn);
  expect(container.read(sessionProvider).tenantId, 7);
  expect(api.called('GET', 'me').last.headers['Authorization'], 'Bearer tok-1');
  expect(find.text('Home'), findsWidgets);
}

Future<void> toggleSoldOut(WidgetTester tester) async {
  final api = restaurantServer(role: 'staff');
  await startApp(tester, api, token: 'tok');
  await tester.tap(find.text('Mark sold out'));
  await tester.pumpAndSettle();
  await tester.tap(find.text('All dishes'));
  await tester.pumpAndSettle();
  expect(find.text('Spris'), findsOneWidget);

  await tester.tap(find.widgetWithText(OutlinedButton, 'Sold out'));
  await tester.pump();
  // Optimistic: the button flips before the server answers.
  expect(find.text('Available again'), findsOneWidget);
  await tester.pumpAndSettle();
  final call = api.called('PATCH', 'restaurant/7/items/41/sold-out').single;
  expect(call.body, {'is_sold_out': true});
  expect(call.headers['Idempotency-Key'], isNotNull);
}

Future<void> editDish(WidgetTester tester) async {
  final api = restaurantServer();
  await startApp(tester, api, token: 'tok');
  await tester.tap(find.text('Menu').first);
  await tester.pumpAndSettle();
  await tester.tap(find.text('Spris'));
  await tester.pumpAndSettle();
  expect(find.text('Edit dish'), findsOneWidget);

  await tester.enterText(find.byKey(const Key('dishName')), 'Spris special');
  await tester.enterText(find.byKey(const Key('dishPrice')), '95.50');
  await tester.tap(find.byKey(const Key('saveDish')));
  await tester.pumpAndSettle();

  final call = api.called('PATCH', 'restaurant/7/items/41').single;
  expect(call.body['name'], {'en': 'Spris special'});
  expect(call.body['price_minor'], 9550);
  expect(find.text('Dish saved'), findsOneWidget);
}

/// Super Admin: overview, then an emergency switch (maintenance, with a confirm).
Future<void> superAdminSwitchFlow(WidgetTester tester) async {
  final api = demoPlatform()
    ..on(
      'PUT super/mobile-app',
      (c) => (
        200,
        {
          'data': {'maintenance': true},
        },
      ),
    );
  final container = await startApp(tester, api, token: 'admin-tok');
  expect(container.read(sessionProvider).platformMode, isTrue);
  expect(find.text('Super Admin'), findsOneWidget);
  expect(find.text('128'), findsOneWidget);
  expect(find.text('Open Super Admin on the web'), findsNothing, reason: 'below the fold until scrolled');

  await tester.tap(find.byKey(const Key('sa-maintenance')));
  await tester.pumpAndSettle();
  await tester.tap(find.text('Turn on'));
  await tester.pumpAndSettle();
  expect(api.called('PUT', 'super/mobile-app').single.body, {'maintenance': true});

  // Old links (pushes, bookmarks) land on the overview.
  container.read(routerProvider).go('/p/restaurants/9/payments');
  await tester.pumpAndSettle();
  expect(find.text('Super Admin'), findsOneWidget);
}

Future<ProviderContainer> startAppNoSettle(WidgetTester tester, FakeApi api) async {
  final store = MemoryTokenStore()..values[TokenKeys.token] = 'tok';
  final container = ProviderContainer(
    overrides: testOverrides(api, store: store),
    retry: noRetry,
  );
  addTearDown(container.dispose);
  await tester.runAsync(() => container.read(sessionProvider.notifier).restore());
  await tester.pumpWidget(UncontrolledProviderScope(container: container, child: const MenuAdminApp()));
  return container;
}
