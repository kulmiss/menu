import 'package:flutter_test/flutter_test.dart';
import 'package:menu_admin/core/api/api_client.dart';
import 'package:menu_admin/core/api/api_error.dart';
import 'package:menu_admin/core/api/interceptors.dart';

import '../helpers/fake_api.dart';

ApiClient client(FakeApi fake, {String? token = 't0k', void Function()? on401}) => ApiClient(
  baseUrl: 'https://menu.test/',
  token: () => token,
  onUnauthorized: on401 ?? () {},
  appVersion: () => '1.2.3+4',
  adapter: fake,
  retryDelays: const [Duration.zero, Duration.zero],
);

Future<ApiError> errorOf(Future<dynamic> f) async {
  try {
    await f;
  } on ApiError catch (e) {
    return e;
  }
  throw StateError('no error');
}

void main() {
  test('sends the bearer token, JSON accept and the app version', () async {
    final fake = FakeApi()..json('GET me', {'data': {}});
    await client(fake).get('me');
    final h = fake.calls.single.headers;
    expect(h['Authorization'], 'Bearer t0k');
    expect(h['Accept'], 'application/json');
    expect(h['X-App-Version'], '1.2.3+4');
  });

  test('adds an Idempotency-Key to every change, never to reads', () async {
    final fake = FakeApi()
      ..json('GET menus', {'data': []})
      ..json('POST menus', {'data': {}})
      ..json('PATCH menus/1', {'data': {}})
      ..json('DELETE menus/1', null, 204);
    final api = client(fake);
    await api.get('menus');
    await api.post('menus');
    await api.patch('menus/1');
    await api.delete('menus/1');
    expect(fake.calls[0].headers['Idempotency-Key'], isNull);
    for (final c in fake.calls.skip(1)) {
      expect(c.headers['Idempotency-Key'], matches(RegExp(r'^[A-Za-z0-9]{32}$')));
    }
    expect(fake.calls[1].headers['Idempotency-Key'], isNot(fake.calls[2].headers['Idempotency-Key']));
  });

  test('a retried change keeps the same Idempotency-Key (no double save)', () async {
    var n = 0;
    final fake = FakeApi()
      ..on(
        'POST items',
        (_) => ++n < 2
            ? (503, {'message': 'busy'})
            : (
                201,
                {
                  'data': {'id': 5},
                },
              ),
      );
    final r = await client(fake).post('items', data: {'x': 1});
    expect(r['data']['id'], 5);
    expect(fake.calls, hasLength(2));
    expect(fake.calls[0].headers['Idempotency-Key'], fake.calls[1].headers['Idempotency-Key']);
  });

  test('waits and retries when the same key is still in progress (409)', () async {
    var n = 0;
    final fake = FakeApi()..on('POST items', (_) => ++n < 2 ? (409, {'code': 'idempotency.in_progress'}) : (201, {'data': {}}));
    await client(fake).post('items');
    expect(fake.calls, hasLength(2));
  });

  test('maps Laravel validation errors to fields', () async {
    final fake = FakeApi()
      ..json('POST items', {
        'message': 'The name field is required.',
        'errors': {
          'name': ['The name field is required.'],
        },
      }, 422);
    final e = await errorOf(client(fake).post('items'));
    expect(e.kind, ApiErrorKind.validation);
    expect(e.field('name'), 'The name field is required.');
  });

  test('plan limits (402) and plan switches (feature.*) ask for an upgrade', () async {
    final fake = FakeApi()
      ..json('POST branches', {'message': 'Upgrade to add branches.', 'code': 'plan.limit'}, 402)
      ..json('GET insights', {'message': 'Not in your plan', 'code': 'feature.insights'}, 403);
    final a = await errorOf(client(fake).post('branches'));
    final b = await errorOf(client(fake).get('insights'));
    expect(a.needsUpgrade, isTrue);
    expect(a.message, 'Upgrade to add branches.');
    expect(b.needsUpgrade, isTrue);
  });

  test('401 signs the app out', () async {
    var out = false;
    final fake = FakeApi()..json('GET me', {'message': 'Unauthenticated.'}, 401);
    await expectLater(client(fake, on401: () => out = true).get('me'), throwsA(isA<ApiError>()));
    expect(out, isTrue);
  });

  test('keys are random and URL-safe', () {
    final keys = {for (var i = 0; i < 100; i++) newIdempotencyKey()};
    expect(keys, hasLength(100));
  });
}
