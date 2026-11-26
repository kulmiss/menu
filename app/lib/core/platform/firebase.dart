import 'dart:async';

import 'package:dio/dio.dart';
import 'package:firebase_analytics/firebase_analytics.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_crashlytics/firebase_crashlytics.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../analytics_names.dart';
import '../api/api_error.dart';
import '../auth/token_store.dart';
import '../providers.dart';

/// Firebase is optional (QUESTIONS M16): without google-services.json the app
/// runs normally and push/crash/analytics report "not set up".
class FirebaseState {
  FirebaseState._();
  static bool ready = false;

  /// Called after the first frame so it never slows the cold start. Collection is
  /// off in the manifest / Info.plist until this applies the user's two switches,
  /// so nothing is sent before the choice is known (SECURITY_REPORT.md M16).
  static Future<bool> init({bool analytics = true, bool crashes = true}) async {
    if (ready) return true;
    try {
      await Firebase.initializeApp();
      ready = true;
      await FirebaseAnalytics.instance.setAnalyticsCollectionEnabled(analytics);
      await setCrashes(crashes);
    } catch (_) {
      ready = false;
    }
    return ready;
  }

  static Future<void> setCrashes(bool on) async {
    if (!ready) return;
    final send = on && !kDebugMode;
    await FirebaseCrashlytics.instance.setCrashlyticsCollectionEnabled(send);
    if (send) {
      FlutterError.onError = (d) => FirebaseCrashlytics.instance.recordFlutterFatalError(
        FlutterErrorDetails(exception: scrubError(d.exception), stack: d.stack, library: d.library, context: d.context),
      );
      PlatformDispatcher.instance.onError = (e, s) {
        FirebaseCrashlytics.instance.recordError(scrubError(e), s, fatal: true);
        return true;
      };
    } else {
      FlutterError.onError = FlutterError.presentError;
      PlatformDispatcher.instance.onError = null;
    }
  }
}

/// A crash report never carries a server answer, a URL with ids or a query, or
/// request headers: only status, method and the route shape (M16).
class ApiFailure implements Exception {
  ApiFailure(this.status, this.method, this.route);
  final int? status;
  final String method;
  final String route;
  @override
  String toString() => 'ApiFailure($status $method $route)';
}

Object scrubError(Object error) {
  if (error is DioException) {
    return ApiFailure(
      error.response?.statusCode,
      error.requestOptions.method,
      screenNameOf(error.requestOptions.uri.path.replaceFirst(RegExp(r'^/api/v1'), '')),
    );
  }
  return error;
}

/// Anonymous screen and feature events only (QUESTIONS M15), and crash
/// reports, each with its own off switch (SECURITY_REPORT.md M16).
class Analytics {
  Analytics(this._store);
  final TokenStore _store;
  bool enabled = true;
  bool crashes = true;

  static const _key = 'analytics_enabled';
  static const _crashKey = 'crash_reports_enabled';

  Future<void> load() async {
    enabled = (await _store.read(_key)) != '0';
    crashes = (await _store.read(_crashKey)) != '0';
  }

  Future<void> setEnabled(bool v) async {
    enabled = v;
    await _store.write(_key, v ? '1' : '0');
    if (FirebaseState.ready) await FirebaseAnalytics.instance.setAnalyticsCollectionEnabled(v);
  }

  Future<void> setCrashes(bool v) async {
    crashes = v;
    await _store.write(_crashKey, v ? '1' : '0');
    await FirebaseState.setCrashes(v);
  }

  /// Route paths without ids: /r/12/dishes/5 → /r/:id/dishes/:id.
  static String screenName(String path) => screenNameOf(path);

  void screen(String path) {
    if (!enabled || !FirebaseState.ready) return;
    unawaited(FirebaseAnalytics.instance.logScreenView(screenName: screenName(path)).catchError((_) {}));
  }

  void event(String name, [Map<String, Object>? params]) {
    if (!enabled || !FirebaseState.ready) return;
    unawaited(FirebaseAnalytics.instance.logEvent(name: name, parameters: params).catchError((_) {}));
  }
}

final analyticsProvider = Provider<Analytics>((ref) => Analytics(ref.watch(tokenStoreProvider)));

/// Server errors (5xx) as non-fatal reports, no personal data.
void reportNonFatal(Object error, StackTrace stack) {
  if (!FirebaseState.ready || kDebugMode) return;
  final e = ApiError.from(error);
  if (e.kind != ApiErrorKind.server && e.kind != ApiErrorKind.unknown) return;
  unawaited(FirebaseCrashlytics.instance.recordError(scrubError(error), stack, reason: 'api ${e.status}', fatal: false));
}
