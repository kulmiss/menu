// Super Admin → Mobile app (backend QUESTIONS #193): forced update,
// maintenance, app sign-in off, update prompt and banner, read from
// `mobile/config` and from the server's answers.
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import '../helpers/fake_api.dart';
import 'flows.dart';

import 'package:menu_admin/app/icons.dart';

void config(FakeApi api, Map<String, dynamic> extra) => api.json('GET mobile/config', {
  'data': {
    'versions': {'min': '1.0.0', 'latest': '1.0.0'},
    'links': {'web_admin': 'https://menu.test/admin'},
    ...extra,
  },
});

void main() {
  testWidgets('below the minimum version: update required, nothing else', (tester) async {
    final api = restaurantServer();
    config(api, {
      'versions': {'min': '2.0.0', 'latest': '2.1.0', 'notes': 'Faster menus'},
    });
    await startApp(tester, api, token: 'tok');
    expect(find.byKey(const Key('update-required')), findsOneWidget);
    expect(find.textContaining('Faster menus'), findsOneWidget);
    expect(find.text('Home'), findsNothing);
  });

  testWidgets('maintenance: the message instead of the app, "Try again" reads the config again', (tester) async {
    final api = restaurantServer();
    config(api, {
      'maintenance': {'on': true, 'message': 'Back at 10:00'},
    });
    await startApp(tester, api, token: 'tok');
    expect(find.byKey(const Key('maintenance')), findsOneWidget);
    expect(find.text('Back at 10:00'), findsOneWidget);

    config(api, {
      'maintenance': {'on': false},
    });
    final before = api.called('GET', 'mobile/config').length;
    await tester.tap(find.text('Try again'));
    await tester.pumpAndSettle();
    expect(api.called('GET', 'mobile/config').length, greaterThan(before));
    expect(find.byKey(const Key('maintenance')), findsNothing);
    expect(find.text('Home'), findsWidgets);
  });

  testWidgets('maintenance is picked up when the app comes back to the screen', (tester) async {
    final api = restaurantServer();
    config(api, {});
    await startApp(tester, api, token: 'tok');
    expect(find.text('Home'), findsWidgets);

    config(api, {
      'maintenance': {'on': true},
    });
    tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.paused);
    tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.resumed);
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('maintenance')), findsOneWidget);
  });

  testWidgets('app sign-in off for restaurant teams: message and the website link', (tester) async {
    final api = restaurantServer();
    config(api, {
      'sign_in': {'restaurant': false, 'super_admin': true, 'message': 'Please use the website for now.'},
    });
    await startApp(tester, api, token: 'tok');
    expect(find.byKey(const Key('app-off')), findsOneWidget);
    expect(find.text('Please use the website for now.'), findsOneWidget);
    expect(find.text('Open the website'), findsOneWidget);
    expect(find.text('Sign out'), findsOneWidget);
  });

  testWidgets('the server says the app is off (older saved config): the same screen', (tester) async {
    final api = restaurantServer();
    config(api, {});
    api.json('GET restaurant/7/announcements', {'message': 'Not for the app.', 'code': 'mobile.app_off'}, 403);
    await startApp(tester, api, token: 'tok');
    expect(find.byKey(const Key('app-off')), findsOneWidget);
    expect(find.text('Not for the app.'), findsOneWidget);
  });

  testWidgets('server unreachable for the config: the app opens normally', (tester) async {
    final api = restaurantServer();
    api.routes.remove('GET mobile/config');
    await startApp(tester, api, token: 'tok');
    expect(find.text('Home'), findsWidgets);
  });

  testWidgets('recommended update and banner show above the app and can be closed', (tester) async {
    final api = restaurantServer();
    config(api, {
      'versions': {'min': '1.0.0', 'latest': '1.4.0', 'recommended': '1.3.0'},
      'banner': {'text': 'New menu styles are here', 'link': null, 'audience': 'restaurant'},
    });
    await startApp(tester, api, token: 'tok');
    expect(find.byKey(const Key('update-prompt')), findsOneWidget);
    expect(find.text('New menu styles are here'), findsOneWidget);
    expect(find.text('Home'), findsWidgets);

    await tester.tap(find.descendant(of: find.byKey(const Key('update-prompt')), matching: find.byIcon(AppIcons.x)));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('update-prompt')), findsNothing);
    await tester.tap(find.descendant(of: find.byKey(const Key('app-banner')), matching: find.byIcon(AppIcons.x)));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('app-banner')), findsNothing);
  });

  testWidgets('banner for Super Admins only is not shown to a restaurant team', (tester) async {
    final api = restaurantServer();
    config(api, {
      'banner': {'text': 'Admins: new reports', 'link': null, 'audience': 'super_admin'},
    });
    await startApp(tester, api, token: 'tok');
    expect(find.text('Admins: new reports'), findsNothing);
  });
}
