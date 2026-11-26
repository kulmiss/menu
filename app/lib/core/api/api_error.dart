import 'package:dio/dio.dart';

enum ApiErrorKind { offline, timeout, validation, unauthorized, paymentRequired, forbidden, notFound, conflict, rateLimited, server, unknown }

/// One error type for the whole app, mapped from the API's formats
/// (`422 {message, errors}`, `402/403/409 {message, code}`).
class ApiError implements Exception {
  ApiError(this.kind, {this.status, this.message, this.code, this.fieldErrors = const {}});

  final ApiErrorKind kind;
  final int? status;
  final String? message;
  final String? code;
  final Map<String, List<String>> fieldErrors;

  String? field(String name) => fieldErrors[name]?.first;

  bool get isOffline => kind == ApiErrorKind.offline || kind == ApiErrorKind.timeout;

  /// Plan limit or read-only subscription: show the "Upgrade" card.
  bool get needsUpgrade => kind == ApiErrorKind.paymentRequired || (code?.startsWith('feature.') ?? false);

  static ApiError from(Object error) {
    if (error is ApiError) return error;
    if (error is DioException) {
      if (error.error is ApiError) return error.error! as ApiError;
      switch (error.type) {
        case DioExceptionType.connectionError:
          return ApiError(ApiErrorKind.offline);
        case DioExceptionType.connectionTimeout:
        case DioExceptionType.sendTimeout:
        case DioExceptionType.receiveTimeout:
          return ApiError(ApiErrorKind.timeout);
        case DioExceptionType.badResponse:
          return fromResponse(error.response!);
        default:
          return ApiError(error.error is ApiError ? (error.error! as ApiError).kind : ApiErrorKind.unknown, message: error.message);
      }
    }
    return ApiError(ApiErrorKind.unknown, message: error.toString());
  }

  static ApiError fromResponse(Response<dynamic> r) {
    final status = r.statusCode ?? 0;
    final data = r.data;
    String? message;
    String? code;
    var fields = <String, List<String>>{};
    if (data is Map) {
      message = data['message'] as String?;
      code = data['code'] as String?;
      final errors = data['errors'];
      if (errors is Map) {
        fields = {
          for (final e in errors.entries)
            e.key.toString(): [
              for (final m in (e.value is List ? e.value as List : [e.value])) m.toString(),
            ],
        };
      }
    }
    final kind = switch (status) {
      401 => ApiErrorKind.unauthorized,
      402 => ApiErrorKind.paymentRequired,
      403 => ApiErrorKind.forbidden,
      404 || 410 => ApiErrorKind.notFound,
      409 => ApiErrorKind.conflict,
      422 => ApiErrorKind.validation,
      429 => ApiErrorKind.rateLimited,
      >= 500 => ApiErrorKind.server,
      _ => ApiErrorKind.unknown,
    };
    return ApiError(kind, status: status, message: message, code: code, fieldErrors: fields);
  }

  @override
  String toString() => 'ApiError($kind, $status, $code, $message)';
}
