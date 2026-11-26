// Opening a platform announcement marks it read once — not on every redraw.
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:menu_admin/app/router.dart';
import 'package:menu_admin/app/theme_mode.dart';

import 'flows.dart';

void main() {
  testWidgets('one "read" request per opening', (tester) async {
    final api = restaurantServer()
      ..json('GET restaurant/7/announcements', {
        'data': [
          {
            'id': 5,
            'title': {'en': 'New feature'},
            'body': {'en': 'Read me'},
            'starts_at': '2026-11-16T00:00:00Z',
          },
        ],
      })
      ..on('POST restaurant/7/announcements/5/read', (_) => (204, null));
    final c = await startApp(tester, api, token: 'tok');
    c.read(routerProvider).push('/r/7/news/5');
    await tester.pumpAndSettle();
    expect(find.text('Read me'), findsOneWidget);
    // Redraws: the theme changes twice (also happens when the phone switches dark mode).
    await c.read(themeModeProvider.notifier).set(ThemeMode.dark);
    await tester.pumpAndSettle();
    await c.read(themeModeProvider.notifier).set(ThemeMode.light);
    await tester.pumpAndSettle();
    expect(api.called('POST', 'restaurant/7/announcements/5/read'), hasLength(1));
  });
}
