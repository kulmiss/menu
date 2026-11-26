import 'dart:async';
import 'dart:math';

import 'package:dio/dio.dart';

import 'api_error.dart';

const _mutating = {'POST', 'PUT', 'PATCH', 'DELETE'};

/// Answers from the server's MobileAppGate (backend QUESTIONS #193).
const gateCodes = {'mobile.update_required', 'mobile.maintenance', 'mobile.app_off'};

/// A random URL-safe key of 32 characters (server accepts 8–100 of [A-Za-z0-9_-]).
String newIdempotencyKey([Random? random]) {
  final r = random ?? Random.secure();
  const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
  return List.generate(32, (_) => chars[r.nextInt(chars.length)]).join();
}

/// Bearer token, JSON and the app version on every request; 401 → signed out.
class AuthInterceptor extends Interceptor {
  AuthInterceptor({required this.token, required this.onUnauthorized, required this.appVersion, this.onGate});

  final String? Function() token;
  final void Function() onUnauthorized;
  final String Function() appVersion;

  /// Super Admin → Mobile app said no (forced update, maintenance, app off).
  final void Function(String code, String? message)? onGate;

  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) {
    final t = options.extra['token'] as String? ?? token();
    if (t != null && options.extra['noAuth'] != true) options.headers['Authorization'] = 'Bearer $t';
    options.headers['Accept'] = 'application/json';
    options.headers['X-App-Version'] = appVersion();
    handler.next(options);
  }

  @override
  void onError(DioException err, ErrorInterceptorHandler handler) {
    if (err.response?.statusCode == 401 && err.requestOptions.extra['noAuth'] != true) onUnauthorized();
    final data = err.response?.data;
    final code = data is Map ? data['code'] : null;
    if (code is String && gateCodes.contains(code)) onGate?.call(code, data['message'] as String?);
    handler.next(err);
  }
}

/// Every create/change request carries an Idempotency-Key, kept across retries
/// so a repeat never saves twice (server: EnsureIdempotency).
class IdempotencyInterceptor extends Interceptor {
  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) {
    if (_mutating.contains(options.method.toUpperCase()) && options.extra['noIdempotency'] != true) {
      options.headers['Idempotency-Key'] ??= options.extra['idempotencyKey'] ??= newIdempotencyKey();
    }
    handler.next(options);
  }
}

/// Retries flaky networks: GETs and mutations (same key) on timeouts, dropped
/// connections, 502/503/504, 429 (Retry-After) and 409 "still in progress".
class RetryInterceptor extends Interceptor {
  RetryInterceptor(this.dio, {this.delays = const [Duration(milliseconds: 400), Duration(milliseconds: 1200), Duration(seconds: 3)], this.sleep = _sleep});

  final Dio dio;
  final List<Duration> delays;
  final Future<void> Function(Duration) sleep;

  static Future<void> _sleep(Duration d) => Future<void>.delayed(d);

  bool _retryable(DioException e) {
    final status = e.response?.statusCode;
    final code = (e.response?.data is Map) ? (e.response!.data as Map)['code'] : null;
    return switch (e.type) {
      DioExceptionType.connectionTimeout || DioExceptionType.receiveTimeout || DioExceptionType.sendTimeout || DioExceptionType.connectionError => true,
      DioExceptionType.badResponse =>
        status == 502 ||
            (status == 503 && code != 'mobile.maintenance') ||
            status == 504 ||
            status == 429 ||
            (status == 409 && code == 'idempotency.in_progress'),
      _ => false,
    };
  }

  @override
  Future<void> onError(DioException err, ErrorInterceptorHandler handler) async {
    final options = err.requestOptions;
    final attempt = (options.extra['attempt'] as int?) ?? 0;
    if (options.extra['noRetry'] == true || attempt >= delays.length || !_retryable(err) || options.data is FormData) {
      return handler.next(err);
    }
    var wait = delays[attempt];
    final retryAfter = int.tryParse(err.response?.headers.value('retry-after') ?? '');
    if (retryAfter != null) wait = Duration(seconds: min(retryAfter, 30));
    await sleep(wait);
    options.extra['attempt'] = attempt + 1;
    try {
      handler.resolve(await dio.fetch<dynamic>(options));
    } on DioException catch (e) {
      handler.next(e);
    }
  }
}

/// Turns every failure into an [ApiError] (kept in DioException.error).
class ErrorInterceptor extends Interceptor {
  @override
  void onError(DioException err, ErrorInterceptorHandler handler) {
    final mapped = err.response != null ? ApiError.fromResponse(err.response!) : ApiError.from(err);
    handler.next(err.copyWith(error: mapped));
  }
}
