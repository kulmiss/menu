import 'dart:async';
import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:drift/drift.dart' show driftRuntimeOptions;
import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/misc.dart' show Override;
import 'package:menu_admin/app/theme.dart';
import 'package:menu_admin/core/api/api_client.dart';
import 'package:menu_admin/core/auth/token_store.dart';
import 'package:menu_admin/core/cache/cache_db.dart';
import 'package:menu_admin/core/cache/swr.dart';
import 'package:menu_admin/core/providers.dart';
import 'package:menu_admin/l10n/app_localizations.dart';
import 'package:menu_admin/features/restaurant/dashboard_screen.dart' show todayProvider;

/// One recorded request.
class Call {
  Call(this.method, this.path, this.headers, this.body);
  final String method;
  final String path;
  final Map<String, dynamic> headers;
  final dynamic body;
}

typedef Handler = (int, Object?) Function(Call call);

/// A fake server for dio: routes "METHOD path" (path without /api/v1/, query
/// ignored unless the key has a '?') to a (status, json) answer.
class FakeApi implements HttpClientAdapter {
  final Map<String, Handler> routes = {};
  final List<Call> calls = [];

  /// "METHOD path" answers that wait until the test completes them (slow server).
  final Map<String, Completer<void>> holds = {};

  /// No network: every request fails like a phone without a connection.
  bool offline = false;

  /// Makes "METHOD path" wait; complete the returned completer to answer.
  Completer<void> hold(String key) => holds[key] = Completer<void>();

  void on(String key, Handler h) => routes[key] = h;
  void json(String key, Object? body, [int status = 200]) => routes[key] = (_) => (status, body);

  Iterable<Call> called(String method, String path) => calls.where((c) => c.method == method && c.path == path);

  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<Uint8List>? requestStream, Future<void>? cancelFuture) async {
    final full = options.uri;
    final path = full.path.replaceFirst(RegExp(r'^/api/v1/'), '');
    final body = options.data is String ? jsonDecode(options.data as String) : options.data;
    final call = Call(options.method, path, Map.of(options.headers), body);
    calls.add(call);
    if (offline) throw DioException.connectionError(requestOptions: options, reason: 'offline');
    final held = holds['${options.method} $path'];
    if (held != null && !held.isCompleted) {
      await Future.any([held.future, ?cancelFuture]);
      if (!held.isCompleted) throw DioException.requestCancelled(requestOptions: options, reason: 'cancelled');
    }
    final withQuery = full.hasQuery ? '$path?${_sorted(full.query)}' : path;
    final h =
        routes.entries.where((e) => _normal(e.key) == '${options.method} $withQuery').map((e) => e.value).firstOrNull ?? routes['${options.method} $path'];
    if (h == null) return ResponseBody.fromString(jsonEncode({'message': 'No fake for ${options.method} $withQuery'}), 404, headers: _json);
    final (status, data) = h(call);
    if (status == 204) return ResponseBody.fromString('', 204);
    return ResponseBody.fromString(jsonEncode(data), status, headers: _json);
  }

  static String _sorted(String q) => (q.split('&')..sort()).join('&');
  static String _normal(String key) {
    final i = key.indexOf('?');
    return i < 0 ? key : '${key.substring(0, i)}?${_sorted(key.substring(i + 1))}';
  }

  static const _json = {
    Headers.contentTypeHeader: [Headers.jsonContentType],
  };

  @override
  void close({bool force = false}) {}
}

/// Riverpod's automatic retry is off (dio retries the network itself).
Duration? noRetry(int count, Object error) => null;

/// Provider overrides for a test app with a fake server and memory storage.
List<Override> testOverrides(FakeApi api, {MemoryTokenStore? store}) {
  driftRuntimeOptions.dontWarnAboutMultipleDatabases = true;
  final s = store ?? MemoryTokenStore();
  return [
    tokenStoreProvider.overrideWithValue(s),
    cacheKeepAliveProvider.overrideWithValue(Duration.zero),
    cacheDbProvider.overrideWithValue(CacheDb(NativeDatabase.memory())),
    apiProvider.overrideWith((ref) {
      final holder = ref.watch(tokenHolderProvider);
      return ApiClient(
        baseUrl: 'https://menu.test',
        token: () => holder.token,
        onUnauthorized: () => holder.onUnauthorized?.call(),
        onGate: (code, message) => holder.onGate?.call(code, message),
        appVersion: () => '1.0.0+1',
        adapter: api,
        retryDelays: const [Duration.zero],
      );
    }),
    todayProvider.overrideWithValue(DateTime(2026, 11, 14, 9)),
  ];
}

/// Wraps a screen with theme, localisations and a fixed size.
Widget testApp(Widget child, {Brightness brightness = Brightness.light}) => MaterialApp(
  theme: buildTheme(Brightness.light),
  darkTheme: buildTheme(Brightness.dark),
  themeMode: brightness == Brightness.dark ? ThemeMode.dark : ThemeMode.light,
  localizationsDelegates: const [L.delegate, GlobalMaterialLocalizations.delegate, GlobalWidgetsLocalizations.delegate, GlobalCupertinoLocalizations.delegate],
  supportedLocales: L.supportedLocales,
  home: child,
);

Map<String, dynamic> meJson({bool superAdmin = false, String role = 'owner', bool twoFactor = false, Map<String, bool>? features}) => {
  'data': {
    'id': 1,
    'name': superAdmin ? 'Admin' : 'Selam',
    'email': superAdmin ? 'admin@example.com' : 'owner@example.com',
    'email_verified': true,
    'two_factor_enabled': twoFactor || superAdmin,
    'is_super_admin': superAdmin,
    'impersonation': null,
    'tenants': superAdmin
        ? []
        : [
            {
              'id': 7,
              'slug': 'demo',
              'name': 'Buna & Bread',
              'currency_code': 'ETB',
              'default_locale': 'en',
              'subscription': {'state': 'active', 'allows_writes': true},
              'features': features ?? {'insights': true, 'promotions': true, 'price_periods': true, 'branches': true, 'pdf_menu': true},
              'upgrade': {'whatsapp': null, 'email': null},
              'membership': {'id': 3, 'role': role, 'can_edit_prices': role != 'staff'},
            },
          ],
  },
};
