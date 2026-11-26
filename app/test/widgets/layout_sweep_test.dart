// Every screen on a small phone (360 dp) and a 10" tablet, with long names and
// the largest text the app allows (130 %): nothing may overflow or throw.
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:menu_admin/app/router.dart';

import '../helpers/fake_api.dart';
import '../store/store_screenshots_test.dart' show demoPlatform, demoRestaurant;
import 'flows.dart';
import 'help_test.dart' show library;

const _long = 'Ethiopian traditional breakfast combination platter with honey';
const _email = 'selamawit.tesfaye-gebremariam@restaurant-example.com';

FakeApi stressServer() {
  final api = demoRestaurant();
  Map<String, String> en(String s) => {'en': s};
  api
    ..json('GET restaurant/7/branches', {
      'data': [
        {
          'id': 1,
          'name': _long,
          'address': _long,
          'phone': '+251 911 234 567',
          'is_default': true,
          'is_active': true,
          'tables_count': 24,
          'url': 'https://menu.test/demo',
        },
        {'id': 2, 'name': 'Bole', 'address': 'Bole road', 'is_default': false, 'is_active': false, 'tables_count': 0, 'url': 'https://menu.test/demo/bole'},
      ],
    })
    ..json('GET restaurant/7/members', {
      'data': [
        {
          'id': 1,
          'role': 'owner',
          'can_edit_prices': true,
          'user': {'id': 1, 'name': _long, 'email': _email},
        },
        {
          'id': 2,
          'role': 'staff',
          'can_edit_prices': false,
          'user': {'id': 2, 'name': 'Abel', 'email': _email},
        },
      ],
    })
    ..json('GET restaurant/7/invitations', {
      'data': [
        {'id': 1, 'email': _email, 'role': 'manager', 'expires_at': '2026-11-30T00:00:00Z'},
      ],
    })
    ..json('GET restaurant/7/billing', {
      'data': {
        'state': 'active',
        'paid_until': '2026-12-31',
        'plan': {'name': 'Business yearly with extra branches', 'price_minor': 2500000, 'interval': 'year', 'currency': 'ETB'},
        'invoices': [
          {'id': 1, 'number': 'INV-2026-000123', 'total_minor': 2500000, 'currency': 'ETB', 'status': 'paid', 'issued_at': '2026-11-01'},
        ],
      },
    })
    ..json('GET restaurant/7/qr-codes', {
      'data': [
        {'id': 1, 'label': _long, 'code': 'abc123', 'branch': _long, 'kind': 'menu'},
      ],
    })
    ..json('GET restaurant/7/print-materials', {
      'data': [
        {'kind': 'tent', 'name': _long, 'description': _long, 'headline': _long},
      ],
    })
    ..json('GET restaurant/7/insights?days=30', {
      'data': {
        'totals': {'views': 123456, 'scans': 98765, 'item_views': 456789},
        'days': [
          for (var i = 1; i <= 30; i++) {'date': '2026-10-${i.toString().padLeft(2, '0')}', 'views': i * 100, 'scans': i * 40},
        ],
        'top_items': [
          {'id': 41, 'name': _long, 'count': 9999},
        ],
        'scans': [
          {'label': _long, 'code': 'abc123', 'count': 4567},
        ],
      },
    })
    ..json('GET restaurant/7/insights/settings', {
      'data': {'monthly_email': true},
    })
    ..json('GET restaurant/7/hours', {
      'data': [
        for (var d = 0; d <= 6; d++) {'weekday': d, 'opens_at': '08:00', 'closes_at': '22:30'},
      ],
    })
    ..json('GET restaurant/7/profile', {
      'data': {
        'name': _long,
        'description': en(_long),
        'address': en(_long),
        'socials': {'instagram': 'https://instagram.com/$_long'},
        'show_wifi': true,
      },
    })
    ..json('GET restaurant/7/closures', {
      'data': [
        {'id': 1, 'starts_on': '2026-12-24', 'ends_on': '2027-01-02', 'note': en(_long)},
      ],
    })
    ..json('GET restaurant/7/promotions', {
      'data': [
        {'id': 1, 'type': 'special', 'title': en(_long), 'body': en(_long), 'is_active': true, 'starts_at': '2026-11-01', 'ends_at': '2026-12-31'},
      ],
    })
    ..json('GET restaurant/7/price-periods', {
      'data': [
        {
          'id': 1,
          'name': en(_long),
          'weekdays': [0, 1, 2, 3, 4, 5, 6],
          'starts_at': '16:00',
          'ends_at': '18:30',
          'items': [{}],
          'is_active': true,
        },
      ],
    })
    ..json('GET restaurant/7/activity', {
      'data': [
        {
          'id': 1,
          'action': 'item.price_changed',
          'created_at': '2026-11-16T10:00:00Z',
          'user': {'name': _long},
          'subject': _long,
        },
      ],
      'meta': {'next_cursor': null},
    })
    ..json('GET restaurant/7/items?sort=category&per=50&page=1', {
      'data': [
        {
          'id': 41,
          'name': en(_long),
          'category': {'name': en(_long)},
          'price_minor': 1234500,
          'variants': [
            {'id': 1, 'name': en('Small'), 'price_minor': 99900},
            {'id': 2, 'name': en('Family size'), 'price_minor': 1234500},
          ],
          'is_sold_out': true,
          'is_visible': false,
        },
      ],
      'meta': {'page': 1, 'pages': 1},
    })
    ..json('GET restaurant/7/items?sort=name&per=100&page=1', {
      'data': [
        {
          'id': 41,
          'name': en(_long),
          'category': {'name': en(_long)},
          'is_sold_out': false,
          'is_visible': true,
        },
      ],
      'meta': {'page': 1, 'pages': 1},
    })
    ..json('GET restaurant/7/help', library())
    ..json('GET mobile/notifications', {
      'data': [
        {'id': 1, 'type': 'plan_expiring', 'title': _long, 'body': _long, 'link': '/r/7/billing', 'read': false, 'created_at': '2026-11-16T10:00:00Z'},
      ],
      'meta': {'next_cursor': null},
    })
    ..json('GET mobile/devices', {
      'data': [
        {'id': 1, 'name': _long, 'platform': 'android', 'app_version': '1.0.0', 'last_used_at': '2026-11-16T10:00:00Z', 'current': true},
        {'id': 2, 'name': 'iPhone', 'platform': 'ios', 'app_version': '1.0.0', 'last_used_at': '2026-11-10T10:00:00Z', 'current': false},
      ],
    })
    ..json('GET mobile/notification-settings', {
      'data': [
        {'type': 'plan_expiring', 'group': 'restaurant', 'enabled': true, 'label': _long},
        {'type': 'monthly_report', 'group': 'restaurant', 'enabled': false, 'label': _long},
      ],
    })
    ..json('GET mobile/account/two-factor', {'data': {}});
  return api;
}

const _screens = {
  'home': '/r/7',
  'menu': '/r/7/menu',
  'orders': '/r/7/orders',
  'more': '/r/7/more',
  'dish': '/r/7/dishes/42',
  'new dish': '/r/7/dishes/new',
  'all dishes': '/r/7/all-dishes',
  'sold out': '/r/7/sold-out',
  'insights': '/r/7/insights',
  'branches': '/r/7/branches',
  'qr': '/r/7/qr',
  'print': '/r/7/print',
  'appearance': '/r/7/appearance',
  'info': '/r/7/info',
  'promotions': '/r/7/promotions',
  'happy hour': '/r/7/happy-hour',
  'team': '/r/7/team',
  'billing': '/r/7/billing',
  'activity': '/r/7/activity',
  'news': '/r/7/news/1',
  'inbox': '/inbox',
  'help': '/help',
  'help article': '/help/add-a-dish',
  'profile': '/account',
  'password': '/account/password',
  'two-factor': '/account/two-factor',
  'devices': '/account/devices',
  'notification settings': '/account/notifications',
  'app settings': '/account/app',
  'super admin': '/p',
};

/// Prints where an overflow comes from, so a failure names the widget.
void _explainOverflows() {
  final prev = FlutterError.onError;
  FlutterError.onError = (d) {
    for (final x in d.informationCollector?.call() ?? const <DiagnosticsNode>[]) {
      if (x.name == 'debugCreator') debugPrint('OVERFLOW at ${((x as dynamic).value as dynamic).element.debugGetCreatorChain(10)}');
    }
    prev?.call(d);
  };
  addTearDown(() => FlutterError.onError = prev);
}

void main() {
  for (final (device, size) in [('phone 360', const Size(360, 740)), ('phone landscape', const Size(740, 360)), ('tablet', const Size(1280, 800))]) {
    for (final s in _screens.entries) {
      testWidgets('${s.key} · $device · text 130 %', (tester) async {
        tester.view.physicalSize = size * 3;
        tester.view.devicePixelRatio = 3;
        tester.platformDispatcher.textScaleFactorTestValue = 1.3;
        addTearDown(tester.view.reset);
        addTearDown(tester.platformDispatcher.clearTextScaleFactorTestValue);
        _explainOverflows();
        final sa = s.key == 'super admin';
        final c = await startApp(tester, sa ? demoPlatform() : stressServer(), token: sa ? 'admin-tok' : 'tok');
        c.read(routerProvider).go(s.value);
        await tester.pumpAndSettle();
        expect(tester.takeException(), isNull);
        // Scroll every list to its end, so rows further down are laid out too.
        final count = find.byType(Scrollable).evaluate().length;
        for (var i = 0; i < count; i++) {
          final f = find.byType(Scrollable);
          if (i >= f.evaluate().length) break;
          await tester.drag(f.at(i), const Offset(0, -4000), warnIfMissed: false);
          await tester.pumpAndSettle();
          expect(tester.takeException(), isNull);
        }
      });
    }
  }

  for (final (device, size) in [('phone 360', const Size(360, 740)), ('phone landscape', const Size(740, 360))]) {
    testWidgets('sign-in and two-step code · $device · text 130 %', (tester) async {
      tester.view.physicalSize = size * 3;
      tester.view.devicePixelRatio = 3;
      tester.platformDispatcher.textScaleFactorTestValue = 1.3;
      addTearDown(tester.view.reset);
      addTearDown(tester.platformDispatcher.clearTextScaleFactorTestValue);
      _explainOverflows();
      final api = restaurantServer()..json('POST mobile/auth/login', {'two_factor': true, 'challenge': 'ch-1'});
      await startApp(tester, api);
      expect(tester.takeException(), isNull);
      await tester.enterText(find.byKey(const Key('email')), _email);
      await tester.enterText(find.byKey(const Key('password')), 'correct-horse');
      await tester.ensureVisible(find.byKey(const Key('submit')));
      await tester.tap(find.byKey(const Key('submit')));
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
      expect(find.byKey(const Key('code')), findsOneWidget);
    });
  }

  testWidgets('Insights at 360 dp and 130 %: "7 days", big numbers and chart labels stay on one line', (tester) async {
    tester.view.physicalSize = const Size(360, 740) * 3;
    tester.view.devicePixelRatio = 3;
    tester.platformDispatcher.textScaleFactorTestValue = 1.3;
    addTearDown(tester.view.reset);
    addTearDown(tester.platformDispatcher.clearTextScaleFactorTestValue);
    final c = await startApp(tester, stressServer(), token: 'tok');
    c.read(routerProvider).go('/r/7/insights');
    await tester.pumpAndSettle();
    tester.takeException();
    double lines(Finder f) {
      final p = tester.renderObject<RenderParagraph>(f);
      return p.size.height / p.preferredLineHeight;
    }

    expect(lines(find.text('7 days')), lessThan(1.5));
    expect(lines(find.text('123456')), lessThan(1.5));
    expect(lines(find.text('3K')), lessThan(1.5), reason: 'chart labels are short (3K, not 3000)');
  });
}
