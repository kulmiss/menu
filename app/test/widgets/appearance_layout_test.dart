// Small phone (360 dp, like the SM A165F from the report) with bigger text:
// no screen may overflow. Covers Appearance with a logo and a cover set
// (the reported overflow) and the other main screens.
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:menu_admin/app/router.dart';

import '../store/store_screenshots_test.dart' show demoPlatform, demoRestaurant;
import 'flows.dart';

Map<String, dynamic> _media(int id) => {
  'id': id,
  'urls': {'320': 'https://example.test/$id.jpg', '640': 'https://example.test/$id-640.jpg'},
};

/// Prints where an overflow comes from, so a failure names the widget.
void _explainOverflows(WidgetTester tester) {
  final prev = FlutterError.onError;
  FlutterError.onError = (d) {
    for (final x in d.informationCollector?.call() ?? const <DiagnosticsNode>[]) {
      if (x.name == 'debugCreator') debugPrint('OVERFLOW at ${((x as dynamic).value as dynamic).element.debugGetCreatorChain(12)}');
    }
    prev?.call(d);
  };
  addTearDown(() => FlutterError.onError = prev);
}

void main() {
  const screens = {
    'appearance': '/r/7/appearance',
    'home': '/r/7',
    'menu': '/r/7/menu',
    'orders': '/r/7/orders',
    'more': '/r/7/more',
    'dish': '/r/7/dishes/42',
    'settings': '/account/app',
    'super admin': '/p',
  };
  for (final scale in [1.0, 1.3]) {
    for (final s in screens.entries) {
      testWidgets('${s.key} fits at 360 dp, text ×$scale', (tester) async {
        tester.view.physicalSize = const Size(360, 780) * 3;
        tester.view.devicePixelRatio = 3;
        tester.platformDispatcher.textScaleFactorTestValue = scale;
        addTearDown(tester.view.reset);
        addTearDown(tester.platformDispatcher.clearTextScaleFactorTestValue);
        _explainOverflows(tester);
        final api = s.key == 'super admin'
            ? demoPlatform()
            : (demoRestaurant()..json('GET restaurant/7/appearance', {
                'data': {'style': 'editorial', 'primary_color': '#0F766E', 'font': 'system', 'logo': _media(1), 'cover': _media(2)},
              }));
        final c = await startApp(tester, api, token: s.key == 'super admin' ? 'admin-tok' : 'tok');
        expect(tester.takeException(), isNull);
        c.read(routerProvider).go(s.value);
        await tester.pumpAndSettle();
        expect(tester.takeException(), isNull);
        if (s.key == 'appearance') {
          // The image rows may sit below the first screen (seven style chips above them): scroll down to them.
          for (var i = 0; i < 20 && find.text('Remove').evaluate().isEmpty; i++) {
            await tester.drag(find.byType(Scrollable).first, const Offset(0, -200));
            await tester.pumpAndSettle();
          }
          expect(tester.takeException(), isNull);
          expect(find.text('Remove'), findsWidgets);
        }
        // Scroll to the end so rows further down are laid out too.
        final scrollable = find.byType(Scrollable);
        if (scrollable.evaluate().isNotEmpty) {
          await tester.drag(scrollable.first, const Offset(0, -3000));
          await tester.pumpAndSettle();
          expect(tester.takeException(), isNull);
        }
      });
    }
  }
}
