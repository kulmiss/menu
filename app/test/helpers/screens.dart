import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:menu_admin/app/app.dart';
import 'package:menu_admin/app/router.dart';
import 'package:menu_admin/core/auth/token_store.dart';
import 'package:menu_admin/core/providers.dart';

import 'fake_api.dart';

/// Starts the whole app on [path] at a phone size in [b] and returns once settled.
Future<void> openScreen(WidgetTester tester, FakeApi api, String path, Brightness b, {bool signedIn = true, Size size = const Size(390, 844)}) async {
  tester.view.physicalSize = size * 2.75;
  tester.view.devicePixelRatio = 2.75;
  tester.platformDispatcher.platformBrightnessTestValue = b;
  addTearDown(tester.view.reset);
  addTearDown(tester.platformDispatcher.clearPlatformBrightnessTestValue);
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
  if (signedIn) {
    c.read(routerProvider).go(path);
    await tester.pumpAndSettle();
  }
}
