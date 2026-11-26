// Golden images of key screens: light/dark × phone/tablet (PLAN.md §11).
// Linux only (font rendering differs per OS); update with
// `flutter test --update-goldens test/goldens`.
@Tags(['golden'])
library;

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:menu_admin/core/auth/token_store.dart';
import 'package:menu_admin/core/providers.dart';
import 'package:menu_admin/features/auth/sign_in_screen.dart';
import 'package:menu_admin/features/restaurant/sold_out_screen.dart';

import '../helpers/fake_api.dart';
import '../widgets/flows.dart';
import '../helpers/fonts.dart';

const sizes = {'phone': Size(390, 844), 'tablet': Size(1024, 1366)};

void main() {
  setUpAll(loadTestFonts);

  for (final size in sizes.entries) {
    for (final b in Brightness.values) {
      testWidgets('sign-in ${size.key} ${b.name}', (tester) async {
        tester.view.physicalSize = size.value * 2;
        tester.view.devicePixelRatio = 2;
        addTearDown(tester.view.reset);
        await tester.pumpWidget(
          ProviderScope(
            overrides: testOverrides(FakeApi()),
            retry: noRetry,
            child: testApp(const SignInScreen(), brightness: b),
          ),
        );
        await tester.pumpAndSettle();
        await expectLater(find.byType(SignInScreen), matchesGoldenFile('images/sign_in_${size.key}_${b.name}.png'));
      });

      testWidgets('sign-in with Google ${size.key} ${b.name}', (tester) async {
        tester.view.physicalSize = size.value * 2;
        tester.view.devicePixelRatio = 2;
        addTearDown(tester.view.reset);
        final api = FakeApi()
          ..json('GET mobile/config', {
            'data': {
              'links': {},
              'google': {'enabled': true, 'client_id': 'web.apps.googleusercontent.com'},
            },
          });
        await tester.pumpWidget(
          ProviderScope(
            overrides: testOverrides(api),
            retry: noRetry,
            child: testApp(const SignInScreen(), brightness: b),
          ),
        );
        await tester.pumpAndSettle();
        // Asset images decode outside the fake clock: wait for Google's "G".
        await tester.runAsync(() async {
          for (final e in find.byType(Image).evaluate()) {
            await precacheImage((e.widget as Image).image, e);
          }
        });
        await tester.pumpAndSettle();
        expect(find.text('Continue with Google'), findsOneWidget);
        await expectLater(find.byType(SignInScreen), matchesGoldenFile('images/sign_in_google_${size.key}_${b.name}.png'));
      });

      testWidgets('sold out ${size.key} ${b.name}', (tester) async {
        tester.view.physicalSize = size.value * 2;
        tester.view.devicePixelRatio = 2;
        addTearDown(tester.view.reset);
        final api = restaurantServer(role: 'staff')
          ..json('GET restaurant/7/items?status=sold_out&sort=name&per=100&page=1', {
            'data': [
              {
                'id': 41,
                'name': {'en': 'Iced latte'},
                'category': {
                  'name': {'en': 'Coffee'},
                },
                'is_sold_out': true,
                'is_visible': true,
              },
              {
                'id': 42,
                'name': {'en': 'Shiro tegabino'},
                'category': {
                  'name': {'en': 'Mains'},
                },
                'is_sold_out': true,
                'is_visible': true,
              },
            ],
            'meta': {'page': 1, 'pages': 1},
          });
        final store = MemoryTokenStore()..values[TokenKeys.token] = 'tok';
        final container = ProviderContainer(
          overrides: testOverrides(api, store: store),
          retry: noRetry,
        );
        addTearDown(container.dispose);
        await tester.runAsync(() => container.read(sessionProvider.notifier).restore());
        await tester.pumpWidget(
          UncontrolledProviderScope(
            container: container,
            child: testApp(const SoldOutScreen(), brightness: b),
          ),
        );
        await tester.pumpAndSettle();
        await expectLater(find.byType(SoldOutScreen), matchesGoldenFile('images/sold_out_${size.key}_${b.name}.png'));
      });
    }
  }
}
