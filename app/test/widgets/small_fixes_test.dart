// Small bugs found in the bug hunt (2026-11-17).
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:menu_admin/app/router.dart';
import 'package:menu_admin/core/platform/push.dart';
import 'package:plugin_platform_interface/plugin_platform_interface.dart';
import 'package:url_launcher_platform_interface/link.dart';
import 'package:url_launcher_platform_interface/url_launcher_platform_interface.dart';

import 'flows.dart';

class _Launcher extends UrlLauncherPlatform with MockPlatformInterfaceMixin {
  final opened = <String>[];
  @override
  LinkDelegate? get linkDelegate => null;
  @override
  Future<bool> canLaunch(String url) async => true;
  @override
  Future<bool> launchUrl(String url, LaunchOptions options) async {
    opened.add(url);
    return true;
  }
}

void main() {
  late _Launcher launcher;
  setUp(() => UrlLauncherPlatform.instance = launcher = _Launcher());

  testWidgets('Settings › Delete my account opens the "Your rights" section of the privacy page', (tester) async {
    final c = await startApp(tester, restaurantServer(), token: 'tok');
    c.read(routerProvider).push('/account/app');
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.text('Delete my account'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Delete my account'));
    await tester.pumpAndSettle();
    // The website's section ids drop the number: "10. Your rights" → #your-rights.
    expect(launcher.opened.single, 'https://menu.test/privacy#your-rights');
  });

  testWidgets('a push shown in the app: "Open" goes only to app screens, never to an unknown page', (tester) async {
    final c = await startApp(tester, restaurantServer(), token: 'tok');
    final push = c.read(pushServiceProvider);
    push.foreground.value = const RemoteMessage(
      notification: RemoteNotification(title: 'News', body: 'Read more'),
      data: {'link': 'https://example.com/elsewhere'},
    );
    await tester.pumpAndSettle();
    expect(find.text('Open'), findsNothing, reason: 'not an app screen: no Open button');
    await tester.pump(const Duration(seconds: 5)); // the first message goes away
    await tester.pumpAndSettle();
    push.foreground.value = const RemoteMessage(
      notification: RemoteNotification(title: 'Team', body: 'Selam joined'),
      data: {'link': '/r/7/sold-out'},
    );
    await tester.pumpAndSettle();
    await tester.tap(find.text('Open'));
    await tester.pumpAndSettle();
    expect(find.text('This was not found. It may have been deleted.'), findsNothing);
    expect(c.read(routerProvider).state.uri.path, '/r/7/sold-out');
  });

  testWidgets('a dish that fails to load offers "Try again"', (tester) async {
    final api = restaurantServer()..json('GET restaurant/7/items/41', {'message': 'Server error'}, 500);
    final c = await startApp(tester, api, token: 'tok');
    c.read(routerProvider).push('/r/7/dishes/41');
    await tester.pumpAndSettle();
    expect(find.text('Try again'), findsOneWidget);
    api.json('GET restaurant/7/items/41', {'data': dish()});
    await tester.tap(find.text('Try again'));
    await tester.pumpAndSettle();
    expect(find.text('Edit dish'), findsOneWidget);
  });

  testWidgets('dish editor: Ctrl+S twice while saving saves once', (tester) async {
    final api = restaurantServer();
    final slow = api.hold('PATCH restaurant/7/items/41');
    final c = await startApp(tester, api, token: 'tok');
    c.read(routerProvider).push('/r/7/dishes/41');
    await tester.pumpAndSettle();
    await tester.enterText(find.byKey(const Key('dishName')), 'Spris special');
    for (var i = 0; i < 2; i++) {
      await tester.sendKeyDownEvent(LogicalKeyboardKey.controlLeft);
      await tester.sendKeyEvent(LogicalKeyboardKey.keyS);
      await tester.sendKeyUpEvent(LogicalKeyboardKey.controlLeft);
      await tester.pump();
    }
    slow.complete();
    await tester.pumpAndSettle();
    expect(api.called('PATCH', 'restaurant/7/items/41'), hasLength(1));
  });
}
