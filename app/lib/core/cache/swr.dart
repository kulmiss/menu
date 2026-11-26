import 'dart:async';
import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter/widgets.dart' show BuildContext;
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_riverpod/misc.dart' show ProviderListenable, ProviderOrFamily;

import '../api/api_error.dart';
import '../providers.dart';

/// An API answer with where it came from (PLAN.md §6).
class Cached<T> {
  const Cached(this.data, {required this.fetchedAt, this.fromCache = false, this.error});

  final T data;
  final DateTime fetchedAt;

  /// Shown from the device while the network answer is on its way.
  final bool fromCache;

  /// The refresh failed (e.g. offline); [data] is the last good answer.
  final ApiError? error;

  Cached<T> copyWith({T? data, bool? fromCache, ApiError? error}) =>
      Cached(data ?? this.data, fetchedAt: fetchedAt, fromCache: fromCache ?? this.fromCache, error: error);
}

/// GET paths that have a live [cachedProvider] (open screens and the ones kept
/// alive), so [invalidatePrefix] can reload them too.
final livePathsProvider = Provider<Set<String>>((ref) => <String>{});

/// How long an answer stays in memory after its screen closes (0 in tests).
final cacheKeepAliveProvider = Provider<Duration>((ref) => const Duration(minutes: 3));

/// Stale-while-revalidate read of a GET path: the saved answer at once (if
/// any), then the network answer. Errors keep the saved answer on screen.
///
/// Not an `async*` body: that starts a microtask later, so a screen closed at
/// once made it call `ref.watch` on a disposed Ref. All dependencies are read
/// here, synchronously; after each `await` the work stops if the provider was
/// disposed, and disposing cancels the request.
final cachedProvider = StreamProvider.autoDispose.family<Cached<dynamic>, String>((ref, path) {
  final db = ref.watch(cacheDbProvider);
  final scope = ref.watch(sessionProvider.select((s) => s.scope));
  final api = ref.watch(apiProvider);
  final live = ref.read(livePathsProvider)..add(path);
  // Keep a list alive a little while after leaving it, so going back is instant.
  final link = ref.keepAlive();
  final keep = ref.read(cacheKeepAliveProvider);
  Timer? timer;
  ref.onCancel(() => keep == Duration.zero ? link.close() : timer = Timer(keep, link.close));
  ref.onResume(() => timer?.cancel());

  final cancel = CancelToken();
  final out = StreamController<Cached<dynamic>>();
  var disposed = false;
  ref.onDispose(() {
    live.remove(path);
    disposed = true;
    timer?.cancel();
    cancel.cancel('provider disposed');
    unawaited(out.close());
  });
  bool gone() => disposed || !ref.mounted;

  Future<void> load() async {
    Cached<dynamic>? last;
    try {
      final saved = await db.read(scope, path);
      if (gone()) return;
      if (saved != null) {
        last = Cached<dynamic>(jsonDecode(saved.json), fetchedAt: saved.fetchedAt, fromCache: true);
        out.add(last);
      }
      final data = await api.get(path, cancelToken: cancel);
      if (gone()) return;
      unawaited(db.write(scope, path, jsonEncode(data)));
      out.add(Cached<dynamic>(data, fetchedAt: DateTime.now()));
    } on ApiError catch (e, st) {
      if (gone()) return;
      if (last == null) {
        out.addError(e, st);
      } else {
        out.add(last.copyWith(error: e));
      }
    } catch (e, st) {
      if (!gone()) out.addError(e, st);
    } finally {
      if (!gone()) unawaited(out.close());
    }
  }

  unawaited(load());
  return out.stream;
});

/// Helpers for screens.
extension CacheRefresh on WidgetRef {
  /// Pull-to-refresh: fetch again and wait for the NETWORK answer (or its
  /// error). Waiting only for the first value returned the saved copy at once,
  /// so the spinner vanished before anything was fetched.
  Future<void> refreshPath(String path, {Duration timeout = const Duration(seconds: 20)}) async {
    final done = Completer<void>();
    final sub = listenManual<AsyncValue<Cached<dynamic>>>(cachedProvider(path), (_, next) {
      if (done.isCompleted || next.isLoading) return;
      if (next.hasError || (next.value != null && (!next.value!.fromCache || next.value!.error != null))) done.complete();
    });
    try {
      invalidate(cachedProvider(path));
      await done.future.timeout(timeout, onTimeout: () {});
    } finally {
      sub.close();
    }
  }

  /// Several paths at once (the dashboards).
  Future<void> refreshPaths(Iterable<String> paths) => Future.wait(paths.map(refreshPath));
}

/// Drops saved answers whose path starts with [prefix] and reloads the screens
/// that show them (open, or kept alive for going back).
Future<void> invalidatePrefix(Object ref, String prefix) => invalidatePrefixes(ref, [prefix]);

/// [invalidatePrefix] for several prefixes. [ref] is a [Ref], a [WidgetRef] or a
/// [ProviderContainer]; everything is read before the first `await`, so a
/// screen that closes meanwhile doesn't matter.
Future<void> invalidatePrefixes(Object ref, Iterable<String> prefixes) async {
  final (T Function<T>(ProviderListenable<T>) read, void Function(ProviderOrFamily) invalidate) = switch (ref) {
    final Ref r => (r.read, r.invalidate),
    final WidgetRef r => (r.read, r.invalidate),
    final ProviderContainer c => (c.read, c.invalidate),
    _ => throw ArgumentError('ref'),
  };
  final scope = read(sessionProvider).scope;
  final db = read(cacheDbProvider);
  final live = [...read(livePathsProvider)];
  for (final prefix in prefixes) {
    for (final path in live.where((p) => p.startsWith(prefix))) {
      invalidate(cachedProvider(path));
    }
  }
  for (final prefix in prefixes) {
    await db.removePrefix(scope, prefix);
  }
}

/// The container of [context], for work that goes on after the screen closes
/// (a `WidgetRef` can't be used once its widget is gone).
extension ContainerOfContext on BuildContext {
  ProviderContainer get container => ProviderScope.containerOf(this, listen: false);
}

/// Writes a changed answer into the cache (optimistic updates).
Future<void> writeCache(WidgetRef ref, String path, Object? data) async {
  final scope = ref.read(sessionProvider).scope;
  await ref.read(cacheDbProvider).write(scope, path, jsonEncode(data));
}
