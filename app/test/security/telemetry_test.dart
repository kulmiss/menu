import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:menu_admin/core/auth/token_store.dart';
import 'package:menu_admin/core/platform/firebase.dart';

/// SECURITY_REPORT.md M16: separate off switches, and crash reports without server data.
void main() {
  test('keeps both switches, on by default, off when chosen (survives a restart)', () async {
    final store = MemoryTokenStore();
    final a = Analytics(store);
    await a.load();
    expect((a.enabled, a.crashes), (true, true));

    await a.setEnabled(false);
    await a.setCrashes(false);
    final again = Analytics(store);
    await again.load();
    expect((again.enabled, again.crashes), (false, false));
  });

  test('turns an API error into status, method and route shape only', () {
    final e = DioException(
      requestOptions: RequestOptions(
        path: 'https://menu.example.com/api/v1/t/12/items/345?search=secret',
        method: 'PATCH',
        headers: {'Authorization': 'Bearer 1|abc'},
      ),
      response: Response(requestOptions: RequestOptions(), statusCode: 500, data: {'message': 'SQLSTATE… owner@example.com'}),
    );
    final scrubbed = scrubError(e);
    expect(scrubbed, isA<ApiFailure>());
    final text = scrubbed.toString();
    expect(text, 'ApiFailure(500 PATCH /t/:id/items/:id)');
    for (final leak in ['secret', 'Bearer', 'abc', 'owner@example.com', 'SQLSTATE', '12', '345']) {
      expect(text.contains(leak), isFalse, reason: leak);
    }
    final other = StateError('x');
    expect(scrubError(other), same(other));
  });
}
