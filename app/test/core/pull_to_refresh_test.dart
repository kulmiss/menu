import 'dart:async';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:menu_admin/core/widgets/kit.dart';

import '../helpers/fake_api.dart';

/// Pull-to-refresh fetches again and shows the new answer, even on a short list.
void main() {
  testWidgets('pulling a short list fetches again and shows the fresh answer', (tester) async {
    var n = 0;
    final api = FakeApi()..on('GET widgets/orders', (_) => (200, {'label': 'answer ${++n}'}));
    await tester.pumpWidget(
      ProviderScope(
        overrides: testOverrides(api),
        retry: (_, _) => null,
        child: testApp(
          Scaffold(
            body: CachedView(
              path: 'widgets/orders',
              builder: (context, data, _) => ListView(children: [Text((data as Map)['label'] as String)]),
            ),
          ),
        ),
      ),
    );
    await tester.runAsync(() => Future<void>.delayed(const Duration(milliseconds: 50)));
    await tester.pumpAndSettle();
    expect(find.text('answer 1'), findsOneWidget);

    // One short row: the list doesn't scroll by itself, the pull must still work.
    await tester.fling(find.text('answer 1'), const Offset(0, 400), 1500);
    await tester.pump();
    await tester.pump(const Duration(seconds: 1));
    await tester.runAsync(() => Future<void>.delayed(const Duration(milliseconds: 50)));
    await tester.pumpAndSettle();

    expect(api.called('GET', 'widgets/orders'), hasLength(2));
    expect(find.text('answer 2'), findsOneWidget);
    expect(find.byType(RefreshProgressIndicator), findsNothing);
  });

  testWidgets('the spinner stays until the network answers (not just the saved copy)', (tester) async {
    var n = 0;
    final api = _GatedApi()..on('GET widgets/orders', (_) => (200, {'label': 'answer ${++n}'}));
    await tester.pumpWidget(
      ProviderScope(
        overrides: testOverrides(api),
        retry: (_, _) => null,
        child: testApp(
          Scaffold(
            body: CachedView(
              path: 'widgets/orders',
              builder: (context, data, _) => ListView(children: [Text((data as Map)['label'] as String)]),
            ),
          ),
        ),
      ),
    );
    await tester.runAsync(() => Future<void>.delayed(const Duration(milliseconds: 50)));
    await tester.pumpAndSettle();
    expect(find.text('answer 1'), findsOneWidget);

    api.gate = Completer<void>(); // the next answer is slow
    await tester.fling(find.text('answer 1'), const Offset(0, 400), 1500);
    for (var i = 0; i < 10; i++) {
      await tester.pump(const Duration(milliseconds: 100));
      await tester.runAsync(() => Future<void>.delayed(const Duration(milliseconds: 10)));
    }
    // The saved copy is back on screen, but the refresh is still going.
    expect(find.byType(RefreshProgressIndicator), findsOneWidget);

    api.gate!.complete();
    await tester.runAsync(() => Future<void>.delayed(const Duration(milliseconds: 50)));
    await tester.pumpAndSettle();
    expect(find.text('answer 2'), findsOneWidget);
    expect(find.byType(RefreshProgressIndicator), findsNothing);
  });
}

/// The network answer comes later than the saved copy: the spinner must wait for it.
class _GatedApi extends FakeApi {
  Completer<void>? gate;
  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<Uint8List>? requestStream, Future<void>? cancelFuture) async {
    final g = gate;
    if (g != null) await g.future;
    return super.fetch(options, requestStream, cancelFuture);
  }
}
