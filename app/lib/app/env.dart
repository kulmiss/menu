import 'package:flutter/foundation.dart';

/// Build-time settings (QUESTIONS M9).
///
/// `flutter run --dart-define=API_BASE_URL=http://192.168.1.20:8000`
abstract final class Env {
  static const apiBaseUrl = String.fromEnvironment('API_BASE_URL', defaultValue: 'https://menu.example.com');

  /// Debug builds can change the server on the sign-in screen (tap the logo 5×).
  static bool get canChangeServer => kDebugMode;
}
