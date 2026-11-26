// Google Play in-app review (PLAN.md §7): after 5 sessions on 3 days and 3
// saves, asked once, right after a save that worked — never after an error.
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:menu_admin/core/auth/token_store.dart';
import 'package:menu_admin/core/providers.dart';

import 'flows.dart';

Future<MemoryTokenStore> _editAndSave(WidgetTester tester, {required int savesBefore, bool fail = false}) async {
  final api = restaurantServer();
  if (fail) api.json('PATCH restaurant/7/items/41', {'message': 'Server error'}, 500);
  final c = await startApp(tester, api, token: 'tok');
  final store = c.read(tokenStoreProvider) as MemoryTokenStore;
  store.values.addAll({'review_sessions': '6', 'review_days': '2026-11-10,2026-11-12,2026-11-15', 'review_saves': '$savesBefore'});
  await tester.tap(find.text('Menu').first);
  await tester.pumpAndSettle();
  await tester.tap(find.text('Spris'));
  await tester.pumpAndSettle();
  await tester.enterText(find.byKey(const Key('dishName')), 'Spris special');
  await tester.tap(find.byKey(const Key('saveDish')));
  await tester.pumpAndSettle();
  return store;
}

void main() {
  testWidgets('the third save of a regular user asks for a review (once)', (tester) async {
    final store = await _editAndSave(tester, savesBefore: 2);
    expect(store.values['review_saves'], '3');
    expect(store.values['review_asked'], isNotNull);
  });

  testWidgets('a save that fails is not counted and never asks', (tester) async {
    final store = await _editAndSave(tester, savesBefore: 2, fail: true);
    expect(store.values['review_saves'], '2');
    expect(store.values['review_asked'], isNull);
  });
}
