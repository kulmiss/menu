import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../api/api_client.dart';
import '../providers.dart';

/// Result of the first sign-in step.
sealed class LoginResult {}

class LoggedIn extends LoginResult {
  LoggedIn(this.token, {this.googleLinked = false});
  final String token;

  /// "Continue with Google" just linked Google to an existing account.
  final bool googleLinked;
}

class NeedsTwoFactor extends LoginResult {
  NeedsTwoFactor(this.challenge);
  final String challenge;
}

/// "Continue with Google" with a new e-mail: the restaurant name and the Terms
/// are still needed ([googleFinish] within 15 minutes).
class NeedsRestaurant extends LoginResult {
  NeedsRestaurant(this.ticket, this.email);
  final String ticket;
  final String email;
}

/// Token sign-in (backend QUESTIONS #187).
class AuthRepository {
  AuthRepository(this._api, {required this.deviceName, required this.platform, required this.appVersion});

  final ApiClient _api;
  final String deviceName;
  final String platform;
  final String appVersion;

  /// Sign-in requests carry no token and no Idempotency-Key (the server keeps no answer with a token).
  static Options get _signIn => Options(extra: {'noIdempotency': true, 'noAuth': true});

  Map<String, dynamic> get _device => {'device_name': deviceName, 'platform': platform, 'app_version': appVersion.split('+').first};

  Future<LoginResult> login(String email, String password) async {
    final r = (await _api.post('mobile/auth/login', data: {'email': email.trim(), 'password': password, ..._device}, options: _signIn)) as Map;
    if (r['two_factor'] == true) return NeedsTwoFactor(r['challenge'] as String);
    return LoggedIn(r['token'] as String);
  }

  /// "Continue with Google": Google's ID token in, the app token (or the next step) out.
  Future<LoginResult> google(String idToken) async {
    final r = (await _api.post('mobile/auth/google', data: {'id_token': idToken, ..._device}, options: _signIn)) as Map;
    if (r['two_factor'] == true) return NeedsTwoFactor(r['challenge'] as String);
    if (r['google_signup'] == true) return NeedsRestaurant(r['ticket'] as String, r['email'] as String? ?? '');
    return LoggedIn(r['token'] as String, googleLinked: r['google_linked'] == true);
  }

  /// The new owner's restaurant name and the Terms → the app token.
  Future<String> googleFinish(String ticket, String restaurantName, {required String locale}) async {
    final r = (await _api.post(
      'mobile/auth/google/finish',
      data: {'ticket': ticket, 'restaurant_name': restaurantName.trim(), 'terms': true, 'locale': locale},
      options: _signIn,
    )) as Map;
    return r['token'] as String;
  }

  Future<String> twoFactor(String challenge, {String? code, String? recoveryCode}) async {
    final r = (await _api.post(
      'mobile/auth/two-factor',
      data: {'challenge': challenge, if (code != null) 'code': code.replaceAll(' ', ''), if (recoveryCode != null) 'recovery_code': recoveryCode.trim()},
    )) as Map;
    return r['token'] as String;
  }
}

/// Filled in main() with the real device name (package_info / platform).
final deviceNameProvider = Provider<String>((ref) => 'Android phone');
final platformNameProvider = Provider<String>((ref) => 'android');

final authRepositoryProvider = Provider<AuthRepository>(
  (ref) => AuthRepository(
    ref.watch(apiProvider),
    deviceName: ref.watch(deviceNameProvider),
    platform: ref.watch(platformNameProvider),
    appVersion: ref.watch(appVersionProvider),
  ),
);
