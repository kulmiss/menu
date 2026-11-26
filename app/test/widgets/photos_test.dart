// Adding a photo: camera/gallery cancelled, crop screen backed out, camera
// access refused, a photo that can't be read, leaving mid-upload, two quick taps.
import 'dart:io';

import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:menu_admin/app/app.dart';
import 'package:menu_admin/app/router.dart';
import 'package:menu_admin/core/auth/token_store.dart';
import 'package:menu_admin/core/providers.dart';
import 'package:menu_admin/core/util/photos.dart';

import '../helpers/fake_api.dart';
import 'flows.dart';

/// A real small file, so a fake "photo" exists on disk.
String _file() {
  final f = File('${Directory.systemTemp.createTempSync('photo').path}/p.jpg')..writeAsBytesSync(List.filled(64, 1));
  return f.path;
}

Future<(ProviderContainer, FakeApi)> _openNewDish(WidgetTester tester, PhotoSteps steps) async {
  final api = restaurantServer()
    ..json('POST restaurant/7/media', {
      'data': {
        'id': 77,
        'urls': {'320': 'https://menu.test/p.jpg'},
      },
    });
  final store = MemoryTokenStore()..values[TokenKeys.token] = 'tok';
  final c = ProviderContainer(
    overrides: [
      ...testOverrides(api, store: store),
      photoStepsProvider.overrideWithValue(steps),
    ],
    retry: noRetry,
  );
  addTearDown(c.dispose);
  await tester.runAsync(() => c.read(sessionProvider.notifier).restore());
  await tester.pumpWidget(UncontrolledProviderScope(container: c, child: const MenuAdminApp()));
  await tester.pumpAndSettle();
  c.read(routerProvider).push('/r/7/dishes/new');
  await tester.pumpAndSettle();
  return (c, api);
}

Future<void> _addFromCamera(WidgetTester tester) async {
  await tester.tap(find.text('Add photo').first);
  await tester.pumpAndSettle();
  await tester.tap(find.text('Take a photo'));
  await tester.pumpAndSettle();
}

void main() {
  testWidgets('camera access refused: a clear message, nothing uploaded, no crash', (tester) async {
    final (_, api) = await _openNewDish(tester, PhotoSteps(pick: (_) async => throw PlatformException(code: 'camera_access_denied')));
    await _addFromCamera(tester);
    expect(tester.takeException(), isNull);
    expect(find.textContaining('may not use the camera'), findsOneWidget);
    expect(api.called('POST', 'restaurant/7/media'), isEmpty);
  });

  testWidgets('a photo that can\'t be read: a clear message, nothing uploaded', (tester) async {
    final path = _file();
    final (_, api) = await _openNewDish(
      tester,
      PhotoSteps(pick: (_) async => path, crop: (_, p, _) async => p, shrink: (_) async => throw Exception('bad jpeg')),
    );
    await _addFromCamera(tester);
    expect(tester.takeException(), isNull);
    expect(find.textContaining("can't be opened"), findsOneWidget);
    expect(api.called('POST', 'restaurant/7/media'), isEmpty);
  });

  testWidgets('backing out of the crop screen cancels: nothing uploaded', (tester) async {
    final path = _file();
    final (_, api) = await _openNewDish(tester, PhotoSteps(pick: (_) async => path, crop: (_, _, _) async => null, shrink: (p) async => p));
    await _addFromCamera(tester);
    await tester.runAsync(() => Future<void>.delayed(const Duration(milliseconds: 100)));
    await tester.pumpAndSettle();
    expect(api.called('POST', 'restaurant/7/media'), isEmpty);
  });

  testWidgets('a good photo is uploaded once', (tester) async {
    final path = _file();
    final (_, api) = await _openNewDish(tester, PhotoSteps(pick: (_) async => path, crop: (_, p, _) async => p, shrink: (p) async => p));
    await _addFromCamera(tester);
    await tester.runAsync(() => Future<void>.delayed(const Duration(milliseconds: 100)));
    await tester.pumpAndSettle();
    expect(api.called('POST', 'restaurant/7/media'), hasLength(1));
  });

  testWidgets('leaving the dish while its photo uploads: no error', (tester) async {
    final path = _file();
    final (c, api) = await _openNewDish(tester, PhotoSteps(pick: (_) async => path, crop: (_, p, _) async => p, shrink: (p) async => p));
    // Made in real time: the upload runs there (real file reading).
    final slow = (await tester.runAsync(() async => api.hold('POST restaurant/7/media')))!;
    await _addFromCamera(tester);
    for (var i = 0; i < 100 && api.called('POST', 'restaurant/7/media').isEmpty; i++) {
      await tester.runAsync(() => Future<void>.delayed(const Duration(milliseconds: 20)));
      await tester.pump(const Duration(milliseconds: 50));
    }
    expect(api.called('POST', 'restaurant/7/media'), hasLength(1), reason: 'the upload is on its way');
    c.read(routerProvider).go('/r/7');
    await tester.pumpAndSettle();
    await tester.runAsync(() async {
      slow.complete();
      await Future<void>.delayed(const Duration(milliseconds: 200));
    });
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
  });

  testWidgets('two quick taps on "Add photo" open one choice, not two', (tester) async {
    await _openNewDish(tester, const PhotoSteps());
    await tester.tap(find.text('Add photo').first);
    await tester.tap(find.text('Add photo').first, warnIfMissed: false);
    await tester.pumpAndSettle();
    expect(find.text('Take a photo'), findsOneWidget);
  });
}
