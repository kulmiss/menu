import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:menu_admin/features/restaurant/insights_screen.dart' show shortNumber;
import 'package:menu_admin/app/theme.dart';
import 'package:menu_admin/core/analytics_names.dart';
import 'package:menu_admin/core/app_config.dart';
import 'package:menu_admin/core/auth/models.dart';
import 'package:menu_admin/core/auth/permissions.dart';
import 'package:menu_admin/core/platform/app_lock.dart';
import 'package:menu_admin/core/util/json.dart';
import 'package:menu_admin/core/util/links.dart';
import 'package:menu_admin/features/restaurant/dish_editor_screen.dart';

import '../helpers/fake_api.dart';

TenantInfo tenant(String role, {bool prices = false, Map<String, bool> features = const {}}) => TenantInfo({
  'id': 1,
  'features': features,
  'membership': {'role': role, 'can_edit_prices': prices},
  'subscription': {'allows_writes': true},
});

void main() {
  group('permissions mirror the server gates', () {
    test('owner can do everything', () {
      final c = Can(tenant('owner', prices: true));
      expect([c.manageMenu, c.manageRestaurant, c.viewTeam, c.manageAccount, c.viewBilling, c.toggleSoldOut, c.editPrices], everyElement(isTrue));
      expect(c.manage(Role.owner), isTrue);
    });
    test('manager manages menu and staff, not billing or owners', () {
      final c = Can(tenant('manager', prices: true));
      expect(c.manageMenu, isTrue);
      expect(c.viewBilling, isFalse);
      expect(c.manageAccount, isFalse);
      expect(c.manage(Role.staff), isTrue);
      expect(c.manage(Role.manager), isFalse);
    });
    test('staff only toggles sold out (and prices when allowed)', () {
      final c = Can(tenant('staff'));
      expect(c.toggleSoldOut, isTrue);
      expect([c.manageMenu, c.manageRestaurant, c.viewTeam, c.viewInsights, c.editPrices], everyElement(isFalse));
      expect(Can(tenant('staff', prices: true)).editPrices, isTrue);
    });
    test('plan switches', () {
      expect(Can(tenant('owner', features: {'insights': false})).feature('insights'), isFalse);
      expect(Can(tenant('owner', features: {'insights': true})).feature('insights'), isTrue);
    });
  });

  test('/me model', () {
    final me = Me((meJson()['data'] as Map).cast<String, dynamic>());
    expect(me.tenants.single.name, 'Buna & Bread');
    expect(me.tenant(7)?.role, Role.owner);
    expect(me.isSuperAdmin, isFalse);
  });

  test('only http(s) leaves the app; only our paths open inside', () {
    expect(safeExternalUri('https://wa.me/251911'), isNotNull);
    expect(safeExternalUri('javascript:alert(1)'), isNull);
    expect(safeExternalUri('file:///etc/passwd'), isNull);
    expect(safeExternalUri('intent://x'), isNull);
    expect(appPathFrom('/r/12/billing'), '/r/12/billing');
    expect(appPathFrom('menuadmin://open/p/restaurants/5'), '/p/restaurants/5');
    expect(appPathFrom('menuadmin://evil/p'), isNull);
    expect(appPathFrom('https://evil.example/r/1'), isNull);
    expect(appPathFrom('/r/1/../../etc'), isNull);
    expect(appPathFrom('/admin'), isNull);
    expect(tenantOfPath('/r/12/billing'), 12);
  });

  test('version compare for forced updates', () {
    expect(compareVersions('1.0.0+5', '1.0.1'), lessThan(0));
    expect(compareVersions('1.2.0', '1.2'), 0);
    expect(compareVersions('2.0.0', '1.9.9'), greaterThan(0));
  });

  test('money and prices in minor units', () {
    expect(textToMinor('12.5'), 1250);
    expect(textToMinor('12,50'), 1250);
    expect(textToMinor('abc'), isNull);
    expect(minorToText(6000), '60');
    expect(minorToText(1250), '12.50');
  });

  test('translated fields and photos', () {
    expect(tr({'en': 'Coffee', 'am': 'ቡና'}), 'Coffee');
    expect(tr({'am': 'ቡና'}), 'ቡና');
    expect(
      photoUrl({
        'urls': {'320': 'a.webp', '640': 'b.webp'},
      }, width: 640),
      'b.webp',
    );
    final j = J({
      'n': '5',
      'b': 1,
      'o': {'x': 2},
    });
    expect(j.i('n'), 5);
    expect(j.b('b'), isTrue);
    expect(j.o('o').i('x'), 2);
  });

  test('accent colour keeps 4.5:1 contrast', () {
    expect(accentFor(const Color(0xFFFFEB3B)), AppColors.teal);
    expect(accentFor(const Color(0xFF7C3AED)), const Color(0xFF7C3AED));
    expect(contrastRatio(Colors.black, Colors.white), closeTo(21, .01));
  });

  test('PIN hashing is salted and stable', () {
    expect(hashPin('1234', 'salt', iterations: 10), hashPin('1234', 'salt', iterations: 10));
    expect(hashPin('1234', 'salt', iterations: 10), isNot(hashPin('1234', 'other', iterations: 10)));
  });

  test('analytics screen names carry no ids', () {
    expect(screenNameOf('/r/12/dishes/5?menu=3'), '/r/:id/dishes/:id');
  });

  test('short chart numbers', () {
    expect([0, 950, 1000, 3450, 12345, 1200000].map(shortNumber), ['0', '950', '1K', '3.5K', '12K', '1.2M']);
  });
}
