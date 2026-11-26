// Play Store screenshots from the real screens with demo data.
// `flutter test --run-skipped -t store --update-goldens test/store` (Linux).
@Tags(['store'])
library;

import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:menu_admin/app/app.dart';
import 'package:menu_admin/app/router.dart';
import 'package:menu_admin/core/auth/token_store.dart';
import 'package:menu_admin/core/providers.dart';

import '../helpers/fake_api.dart';
import '../widgets/flows.dart';
import '../helpers/fonts.dart';

Map<String, dynamic> item(int id, String name, String cat, int price, {bool sold = false}) => {
  'id': id,
  'category_id': 5,
  'name': {'en': name},
  'price_minor': price,
  'variants': [],
  'is_sold_out': sold,
  'is_visible': true,
  'category': {
    'name': {'en': cat},
  },
  'menu': {
    'name': {'en': 'All day'},
  },
};

FakeApi demoRestaurant() {
  final coffee = [
    item(41, 'Macchiato', 'Coffee', 6000),
    item(42, 'Spris', 'Coffee', 9000),
    item(43, 'Iced latte', 'Coffee', 8500, sold: true),
    item(44, 'Buna', 'Coffee', 4000),
  ];
  final breakfast = [
    item(51, 'Firfir', 'Breakfast', 18000),
    item(52, 'Chechebsa', 'Breakfast', 16000),
    item(53, 'Ful medames', 'Breakfast', 15000),
    item(54, 'Shiro tegabino', 'Breakfast', 17000, sold: true),
  ];
  final features = {'insights': true, 'promotions': true, 'price_periods': true, 'branches': true, 'pdf_menu': true, 'order_list': true};
  return restaurantServer(features: features)
    // A demo owner who already turned on two-step sign-in: no reminder card in the store pictures.
    ..json('GET me', meJson(features: features, twoFactor: true))
    ..json('GET restaurant/7/widgets/orders', {
      'data': {
        'enabled': true,
        'currency': 'ETB',
        'periods': {
          for (final (d, shown) in [('7', 64), ('30', 241)])
            d: {
              'lists_shown': shown,
              'average_minor': 48500,
              'top': [
                {
                  'id': 41,
                  'name': {'en': 'Spris'},
                  'added': shown ~/ 2,
                },
                {
                  'id': 51,
                  'name': {'en': 'Firfir'},
                  'added': shown ~/ 3,
                },
                {
                  'id': 52,
                  'name': {'en': 'Chechebsa'},
                  'added': shown ~/ 4,
                },
              ],
            },
        },
      },
    })
    ..json('GET restaurant/7/dashboard', {
      'data': {
        'today': {'views': 214, 'scans': 107},
        'yesterday': {'views': 176, 'scans': 88},
        'sold_out': [
          {'id': 43},
          {'id': 54},
        ],
      },
    })
    ..json('GET restaurant/7/widgets/setup', {
      'data': {
        'percent': 71,
        'complete': false,
        'hidden': false,
        'steps': [
          for (final k in ['logo', 'banner', 'dishes', 'hours', 'info']) {'key': k, 'done': true},
          {'key': 'photos', 'done': false},
          {'key': 'qr', 'done': false},
        ],
      },
    })
    ..json('GET restaurant/7/widgets/trend', {
      'data': {
        'days': [
          for (final v in [120, 140, 132, 168, 190, 176, 214]) {'views': v, 'scans': v ~/ 2},
        ],
        'yesterday': {'views': 176, 'scans': 88},
      },
    })
    ..json('GET restaurant/7/widgets/plan', {
      'data': {
        'plan': {'en': 'Plus'},
        'trial': false,
        'days_left': 89,
        'usage': [
          {'key': 'items', 'used': 58, 'limit': null},
          {'key': 'photos', 'used': 212, 'limit': 500},
          {'key': 'branches', 'used': 1, 'limit': 1},
        ],
        'next': {
          'name': {'en': 'Pro'},
          'adds': ['3 branches', 'Happy hour'],
        },
      },
    })
    ..json('GET restaurant/7/menus/2/tree', {
      'data': {
        'categories': [
          {
            'id': 5,
            'name': {'en': 'Coffee'},
            'is_visible': true,
            'items': coffee,
          },
          {
            'id': 6,
            'name': {'en': 'Breakfast'},
            'is_visible': true,
            'items': breakfast,
          },
        ],
      },
    })
    ..json('GET restaurant/7/items?status=sold_out&sort=name&per=100&page=1', {
      'data': [coffee[2], breakfast[3]],
      'meta': {'page': 1, 'pages': 1},
    })
    ..json('GET restaurant/7/items/42', {
      'data': {
        ...dish(),
        'name': {'en': 'Spris'},
        'description': {'en': 'Layered juice of avocado, mango and papaya.'},
        'price_minor': null,
        'variants': [
          {
            'id': 1,
            'name': {'en': 'Small'},
            'price_minor': 9000,
          },
          {
            'id': 2,
            'name': {'en': 'Large'},
            'price_minor': 12000,
          },
        ],
        'tag_ids': [1],
        'allergens': ['milk'],
      },
    })
    ..json('GET restaurant/7/tags', {
      'data': [
        {'id': 1, 'key': 'vegetarian'},
        {'id': 2, 'key': 'popular'},
        {'id': 3, 'key': 'spicy'},
      ],
    });
}

FakeApi demoPlatform() => FakeApi()
  ..json('GET mobile/config', {
    'data': {'min_version': '1.0.0', 'links': {}},
  })
  ..json('GET me', meJson(superAdmin: true))
  ..json('GET super/dashboard', {
    'data': {
      'restaurants': 128,
      'states': {'active': 74, 'trial': 31, 'grace': 4, 'read_only': 9, 'suspended': 10},
      'signups_30d': 11,
      'views_30d': 48210,
      'scans_30d': 20114,
    },
  })
  ..json('GET super/mobile-app', {
    'data': {
      'maintenance': false,
      'super_admin_sign_in': true,
      'restaurant_sign_in': true,
      'app_features': {'push': true, 'analytics': true, 'crash_reports': true, 'biometric_lock': true},
    },
  })
  ..json('GET super/settings', {
    'data': {'signup_open': true},
  });

const phone = Size(392.7, 785.5); // 1080 × 2160 at 2.75
const tablet7 = Size(600, 960); // 1200 × 1920 at 2
const tablet10 = Size(1280, 800); // 2560 × 1600 at 2 (landscape: two panes)

Future<void> shoot(
  WidgetTester tester,
  FakeApi api,
  String path,
  String name,
  Size size,
  double dpr, {
  Future<void> Function()? then,
  bool signedIn = true,
}) async {
  tester.view.physicalSize = size * dpr;
  tester.view.devicePixelRatio = dpr;
  addTearDown(tester.view.reset);
  final store = MemoryTokenStore();
  if (signedIn) store.values[TokenKeys.token] = 'tok';
  final c = ProviderContainer(
    overrides: testOverrides(api, store: store),
    retry: noRetry,
  );
  addTearDown(c.dispose);
  await tester.runAsync(() => c.read(sessionProvider.notifier).restore());
  await tester.pumpWidget(UncontrolledProviderScope(container: c, child: const MenuAdminApp()));
  await tester.pumpAndSettle();
  c.read(routerProvider).go(path);
  await tester.pumpAndSettle();
  if (then != null) await then();
  await expectLater(find.byType(MenuAdminApp), matchesGoldenFile('../../store/screenshots/$name.png'));
}

void main() {
  setUpAll(loadTestFonts);
  testWidgets('phone 1 dashboard', (t) => shoot(t, demoRestaurant(), '/r/7', 'phone_1_dashboard', phone, 2.75));
  testWidgets('phone 2 menu', (t) => shoot(t, demoRestaurant(), '/r/7/menu', 'phone_2_menu', phone, 2.75));
  testWidgets('phone 3 sold out', (t) => shoot(t, demoRestaurant(), '/r/7/sold-out', 'phone_3_sold_out', phone, 2.75));
  testWidgets('phone 4 dish', (t) => shoot(t, demoRestaurant(), '/r/7/dishes/42', 'phone_4_dish', phone, 2.75));
  testWidgets('phone 5 platform', (t) => shoot(t, demoPlatform(), '/p', 'phone_5_platform', phone, 2.75));
  testWidgets('tablet7 menu', (t) => shoot(t, demoRestaurant(), '/r/7/menu', 'tablet7_1_menu', tablet7, 2));
  testWidgets('tablet7 platform', (t) => shoot(t, demoPlatform(), '/p', 'tablet7_2_platform', tablet7, 2));
  testWidgets(
    'tablet10 menu',
    (t) => shoot(
      t,
      demoRestaurant(),
      '/r/7/menu',
      'tablet10_1_menu',
      tablet10,
      2,
      then: () async {
        await t.tap(find.text('Spris'));
        await t.pumpAndSettle();
      },
    ),
  );
}
