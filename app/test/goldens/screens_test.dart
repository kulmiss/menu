// Main screens of the redesign, light + dark, whole app at phone size.
// Linux only; update with `flutter test --update-goldens test/goldens`.
@Tags(['golden'])
library;

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:menu_admin/app/app.dart';

import '../helpers/fonts.dart';
import '../helpers/screens.dart';
import '../store/store_screenshots_test.dart' show demoPlatform, demoRestaurant;

const screens = {'home': '/r/7', 'menu': '/r/7/menu', 'orders': '/r/7/orders', 'more': '/r/7/more', 'settings': '/account/app', 'super_admin': '/p'};

void main() {
  setUpAll(loadTestFonts);
  for (final s in screens.entries) {
    for (final b in Brightness.values) {
      testWidgets('${s.key} ${b.name}', (tester) async {
        await openScreen(tester, s.key == 'super_admin' ? demoPlatform() : demoRestaurant(), s.value, b);
        await expectLater(find.byType(MenuAdminApp), matchesGoldenFile('images/screen_${s.key}_${b.name}.png'));
      });
    }
  }
}
