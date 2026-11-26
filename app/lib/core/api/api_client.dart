import 'dart:io';

import 'package:dio/dio.dart';
import 'package:path_provider/path_provider.dart';

import 'api_error.dart';
import 'interceptors.dart';

/// The one HTTP client of the app. All paths are relative to `<server>/api/v1/`.
class ApiClient {
  ApiClient({
    required String baseUrl,
    required String? Function() token,
    required void Function() onUnauthorized,
    required String Function() appVersion,
    void Function(String code, String? message)? onGate,
    HttpClientAdapter? adapter,
    List<Duration>? retryDelays,
  }) : dio = Dio(
         BaseOptions(
           baseUrl: _apiBase(baseUrl),
           connectTimeout: const Duration(seconds: 10),
           receiveTimeout: const Duration(seconds: 30),
           contentType: Headers.jsonContentType,
           responseType: ResponseType.json,
         ),
       ) {
    if (adapter != null) dio.httpClientAdapter = adapter;
    dio.interceptors.addAll([
      AuthInterceptor(token: token, onUnauthorized: onUnauthorized, appVersion: appVersion, onGate: onGate),
      IdempotencyInterceptor(),
      RetryInterceptor(dio, delays: retryDelays ?? const [Duration(milliseconds: 400), Duration(milliseconds: 1200), Duration(seconds: 3)]),
      ErrorInterceptor(),
    ]);
  }

  final Dio dio;

  static String _apiBase(String server) => '${server.replaceAll(RegExp(r'/+$'), '')}/api/v1/';

  /// The site root (for images and the live preview), without /api/v1.
  String get siteUrl => dio.options.baseUrl.replaceFirst(RegExp(r'api/v1/$'), '');

  void setServer(String server) => dio.options.baseUrl = _apiBase(server);

  Future<T> _run<T>(Future<Response<dynamic>> Function() call) async {
    try {
      return (await call()).data as T;
    } catch (e) {
      throw ApiError.from(e);
    }
  }

  Future<dynamic> get(String path, {Map<String, dynamic>? query, Options? options, CancelToken? cancelToken}) =>
      _run(() => dio.get<dynamic>(path, queryParameters: query, options: options, cancelToken: cancelToken));

  Future<dynamic> post(String path, {Object? data, String? idempotencyKey, Options? options}) =>
      _run(() => dio.post<dynamic>(path, data: data, options: _withKey(options, idempotencyKey)));

  Future<dynamic> put(String path, {Object? data, String? idempotencyKey}) =>
      _run(() => dio.put<dynamic>(path, data: data, options: _withKey(null, idempotencyKey)));

  Future<dynamic> patch(String path, {Object? data, String? idempotencyKey}) =>
      _run(() => dio.patch<dynamic>(path, data: data, options: _withKey(null, idempotencyKey)));

  Future<dynamic> delete(String path, {Object? data, String? idempotencyKey, bool noRetry = false}) => _run(
    () => dio.delete<dynamic>(
      path,
      data: data,
      options: _withKey(noRetry ? Options(extra: {'noRetry': true}) : null, idempotencyKey),
    ),
  );

  Options _withKey(Options? o, String? key) {
    final options = o ?? Options();
    if (key != null) options.extra = {...?options.extra, 'idempotencyKey': key};
    return options;
  }

  /// Downloads a file (PDF, CSV, PNG…) with the token into the app's cache folder.
  Future<File> download(String path, String fileName, {Map<String, dynamic>? query}) async {
    final dir = await getTemporaryDirectory();
    final file = File('${dir.path}/${fileName.replaceAll(RegExp(r'[^A-Za-z0-9._-]'), '_')}');
    try {
      await dio.download(
        path,
        file.path,
        queryParameters: query,
        options: Options(headers: {'Accept': '*/*'}),
      );
    } catch (e) {
      throw ApiError.from(e);
    }
    return file;
  }

  /// Multipart upload (photos) with progress.
  Future<dynamic> upload(
    String path, {
    required String filePath,
    required String fileName,
    String field = 'file',
    Map<String, dynamic> fields = const {},
    void Function(double)? onProgress,
  }) => _run(
    () async => dio.post<dynamic>(
      path,
      data: FormData.fromMap({...fields, field: await MultipartFile.fromFile(filePath, filename: fileName)}),
      onSendProgress: (sent, total) => total > 0 ? onProgress?.call(sent / total) : null,
    ),
  );
}
