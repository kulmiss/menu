// Screens fixed in the bug hunt (2026-11-17), 360 dp phone, 130 % text.
// `COMPARE_DIR=after flutter test --run-skipped -t compare --update-goldens test/compare/bug_hunt_screens_test.dart`
@Tags(['compare'])
library;

import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:menu_admin/app/app.dart';
import 'package:menu_admin/app/router.dart';

import '../helpers/fonts.dart';
import '../widgets/flows.dart';
import '../widgets/layout_sweep_test.dart' show stressServer;

final dir = Platform.environment['COMPARE_DIR'] ?? 'after';

void main() {
  setUpAll(loadTestFonts);
  for (final (name, path) in [('insights', '/r/7/insights'), ('qr_codes', '/r/7/qr')]) {
    testWidgets(name, (tester) async {
      tester.view.physicalSize = const Size(360, 740) * 3;
      tester.view.devicePixelRatio = 3;
      tester.platformDispatcher.textScaleFactorTestValue = 1.3;
      addTearDown(tester.view.reset);
      addTearDown(tester.platformDispatcher.clearTextScaleFactorTestValue);
      final c = await startApp(tester, stressServer(), token: 'tok');
      c.read(routerProvider).go(path);
      await tester.pumpAndSettle();
      tester.takeException();
      await expectLater(find.byType(MenuAdminApp), matchesGoldenFile('../../docs/screens/bug-hunt/${name}_$dir.png'));
    });
  }
}
