// Before/after screenshots for comparing with the web admin (redesign, 2026-11-14).
// `COMPARE_DIR=after flutter test --run-skipped -t compare --update-goldens test/compare` (Linux).
@Tags(['compare'])
library;

import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:menu_admin/app/app.dart';

import '../helpers/fonts.dart';
import '../helpers/screens.dart';
import '../store/store_screenshots_test.dart' show demoRestaurant;

final dir = Platform.environment['COMPARE_DIR'] ?? 'before';

Future<void> shot(WidgetTester tester, String path, String name, Brightness b, {bool signedIn = true}) async {
  await openScreen(tester, demoRestaurant(), path, b, signedIn: signedIn);
  await expectLater(find.byType(MenuAdminApp), matchesGoldenFile('../../docs/screens/$dir/${name}_${b.name}.png'));
}

void main() {
  setUpAll(loadTestFonts);
  for (final b in Brightness.values) {
    testWidgets('sign-in ${b.name}', (t) => shot(t, '/sign-in', 'sign_in', b, signedIn: false));
    testWidgets('home ${b.name}', (t) => shot(t, '/r/7', 'home', b));
    testWidgets('menu ${b.name}', (t) => shot(t, '/r/7/menu', 'menu', b));
    testWidgets('dish ${b.name}', (t) => shot(t, '/r/7/dishes/42', 'dish_edit', b));
    testWidgets('settings ${b.name}', (t) => shot(t, '/account/app', 'settings', b));
  }
}
