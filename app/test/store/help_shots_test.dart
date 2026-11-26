// App screenshots for the Help centre (backend resources/help, "appshot:").
// `flutter test --run-skipped -t store --update-goldens test/store/help_shots_test.dart`,
// then `tool/help_shots.sh ../menu` converts them to WebP in the web repo.
@Tags(['store'])
library;

import 'dart:convert';
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

import '../helpers/fake_api.dart';
import '../helpers/fonts.dart';
import 'store_screenshots_test.dart' show demoRestaurant, phone;
import 'store_screenshots_test.dart' as store;

Future<void> help(WidgetTester t, FakeApi api, String path, String name, {Future<void> Function()? then, bool signedIn = true}) =>
    store.shoot(t, api, path, '../help/$name', phone, 2.75, then: then, signedIn: signedIn);

/// Real articles, as the server renders them for the demo café (resources/help).
/// Pictures come from the server in the app; tests have no network, so they are left out here.
FakeApi withHelp() {
  final lib = jsonDecode(File('test/store/help_library.json').readAsStringSync()) as Map<String, dynamic>;
  for (final a in (lib['data']['articles'] as List).cast<Map<String, dynamic>>()) {
    a['body'] = (a['body'] as String).replaceAll(RegExp(r'!\[[^\]]*\]\([^)]*\)\s*'), '');
  }
  return demoRestaurant()..json('GET restaurant/7/help', lib);
}

void main() {
  setUpAll(loadTestFonts);
  testWidgets('app home', (t) => help(t, demoRestaurant(), '/r/7', 'app-home'));
  testWidgets('app sold out', (t) => help(t, demoRestaurant(), '/r/7/sold-out', 'app-sold-out'));
  testWidgets('app more', (t) => help(t, demoRestaurant(), '/r/7/more', 'app-more'));
  testWidgets(
    'app sign in',
    (t) => help(
      t,
      FakeApi()..json('GET mobile/config', {
        'data': {'min_version': '1.0.0', 'links': {}},
      }),
      '/sign-in',
      'app-sign-in',
      signedIn: false,
    ),
  );
  testWidgets('app help', (t) => help(t, withHelp(), '/help', 'app-help'));
  testWidgets('app help article', (t) => help(t, withHelp(), '/help/add-a-dish', 'app-help-article'));
  testWidgets(
    'app help sheet',
    (t) => help(
      t,
      withHelp(),
      '/r/7/menu',
      'app-help-sheet',
      then: () async {
        await t.tap(find.byTooltip('Help for this screen'));
        await t.pumpAndSettle();
      },
    ),
  );
  testWidgets('app tour', (t) {
    final me = meJson();
    (me['data'] as Map)['onboarding'] = {'web_tour': true, 'app_tour': false, 'setup_needed': false};
    return help(t, demoRestaurant()..json('GET me', me), '/r/7', 'app-tour');
  });
}
