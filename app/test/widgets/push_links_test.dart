// Every link the server puts in a push (backend Push::send calls, 2026-11-17)
// opens an app screen — never "not found".
import 'package:flutter_test/flutter_test.dart';
import 'package:menu_admin/app/router.dart';
import 'package:menu_admin/core/platform/push.dart';
import 'package:menu_admin/core/util/links.dart';

import '../store/store_screenshots_test.dart' show demoPlatform;
import 'flows.dart';

const _restaurant = ['/r/7/team', '/r/7/billing', '/r/7/insights', '/r/7/news/5', '/inbox'];
const _superAdmin = ['/p/restaurants/9', '/p/restaurants/9/payments', '/p/approvals', '/p/backups', '/p/dashboard', '/p/restaurants?follow_up=due', '/inbox'];

void main() {
  for (final link in _restaurant) {
    testWidgets('restaurant push $link', (tester) async {
      final c = await startApp(
        tester,
        restaurantServer()
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
          ..on('POST restaurant/7/announcements/5/read', (_) => (204, null)),
        token: 'tok',
      );
      expect(appPathFrom(link), isNotNull);
      c.read(pushServiceProvider).pendingLink.value = appPathFrom(link);
      await tester.pumpAndSettle();
      expect(find.text('This was not found. It may have been deleted.'), findsNothing);
      expect(c.read(routerProvider).state.uri.path, Uri.parse(link).path);
    });
  }
  for (final link in _superAdmin) {
    testWidgets('Super Admin push $link', (tester) async {
      final c = await startApp(tester, demoPlatform()..json('GET mobile/notifications', {'data': [], 'meta': {}}), token: 'admin-tok');
      expect(appPathFrom(link), isNotNull);
      c.read(pushServiceProvider).pendingLink.value = appPathFrom(link);
      await tester.pumpAndSettle();
      expect(find.text('This was not found. It may have been deleted.'), findsNothing);
      expect(c.read(routerProvider).state.uri.path, link == '/inbox' ? '/inbox' : '/p', reason: 'old Super Admin screens open the overview');
    });
  }
}
