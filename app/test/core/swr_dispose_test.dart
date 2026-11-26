import 'dart:async';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:menu_admin/core/cache/swr.dart';
import 'package:menu_admin/core/providers.dart';

import '../helpers/fake_api.dart';

/// A server that answers only when told to, and notices a cancelled request.
class SlowApi extends FakeApi {
  final gate = Completer<void>();
  bool cancelled = false;
  int started = 0;

  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<Uint8List>? requestStream, Future<void>? cancelFuture) async {
    started++;
    unawaited(cancelFuture?.then((_) => cancelled = true));
    await Future.any([gate.future, ?cancelFuture]);
    return super.fetch(options, requestStream, cancelFuture);
  }
}

/// Leaving a screen while its list is loading (the Orders crash): no "Ref used
/// after dispose", the request is cancelled and nothing is written afterwards.
void main() {
  // Riverpod reports "Ref used after dispose" to the zone; collect anything that lands there.
  Future<List<Object>> errorsDuring(Future<void> Function() body) async {
    final errors = <Object>[];
    final done = Completer<void>();
    runZonedGuarded(() => body().then(done.complete, onError: done.completeError), (e, _) => errors.add(e));
    await done.future.timeout(const Duration(seconds: 10));
    return errors;
  }

  Future<void> until(bool Function() ok) async {
    for (var i = 0; i < 400 && !ok(); i++) {
      await Future<void>.delayed(const Duration(milliseconds: 5));
    }
  }

  test('disposed while the request is in flight', () async {
    final api = SlowApi()..json('GET widgets/orders', {'data': []});
    final errors = await errorsDuring(() async {
      final c = ProviderContainer(overrides: testOverrides(api), retry: (_, _) => null);
      final sub = c.listen(cachedProvider('widgets/orders'), (_, _) {});
      await until(() => api.started > 0);
      expect(api.started, 1);

      sub.close(); // the screen closes mid-load
      await until(() => api.cancelled);
      expect(api.cancelled, isTrue);

      api.gate.complete(); // a late answer changes nothing
      await Future<void>.delayed(const Duration(milliseconds: 50));
      final saved = await c.read(cacheDbProvider).read(c.read(sessionProvider).scope, 'widgets/orders');
      expect(saved, isNull);
      c.dispose();
    });
    expect(errors, isEmpty);
  });

  test('disposed before the work even started (closed at once)', () async {
    final api = SlowApi()..json('GET widgets/orders', {'data': []});
    final errors = await errorsDuring(() async {
      final c = ProviderContainer(overrides: testOverrides(api), retry: (_, _) => null);
      c.listen(cachedProvider('widgets/orders'), (_, _) {});
      c.dispose(); // gone in the same frame the screen asked for it (the Orders crash)
      await Future<void>.delayed(const Duration(milliseconds: 50));
      api.gate.complete();
      await Future<void>.delayed(const Duration(milliseconds: 50));
      expect(api.started, 0);
    });
    expect(errors, isEmpty);
  });

  test('still shows the saved answer, then the fresh one', () async {
    final api = FakeApi()
      ..json('GET widgets/orders', {
        'data': [1],
      });
    final c = ProviderContainer(overrides: testOverrides(api), retry: (_, _) => null);
    addTearDown(c.dispose);
    await c.read(cacheDbProvider).write(c.read(sessionProvider).scope, 'widgets/orders', '{"data":[0]}');
    final seen = <Object?>[];
    c.listen(cachedProvider('widgets/orders'), (_, next) => seen.add(next.value?.data), fireImmediately: true);
    await until(() => seen.whereType<Map>().length >= 2);
    expect(seen.whereType<Map>().map((m) => m['data']).toList(), [
      [0],
      [1],
    ]);
  });
}
