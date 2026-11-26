import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_sign_in/google_sign_in.dart';

/// Gets a Google ID token on the phone for "Continue with Google" (backend
/// 2026-11-17). On Android google_sign_in uses Credential Manager. The token
/// is for the server's **web** client ([serverClientId] from mobile/config);
/// the server checks it and answers with the normal app token.
abstract class GoogleIdTokens {
  /// The ID token, or null when the person closed Google's sheet.
  Future<String?> idToken(String serverClientId);

  /// Also sign out of Google on this phone, so the next time asks which account.
  Future<void> signOut();
}

/// Google could not give a token (not set up for this build, no Google
/// Play services, network…). [code] is google_sign_in's code name.
class GoogleIdTokenFailure implements Exception {
  GoogleIdTokenFailure(this.code);
  final String code;
  @override
  String toString() => 'GoogleIdTokenFailure($code)';
}

class PluginGoogleIdTokens implements GoogleIdTokens {
  String? _initializedFor;

  @override
  Future<String?> idToken(String serverClientId) async {
    final google = GoogleSignIn.instance;
    if (_initializedFor != serverClientId) {
      await google.initialize(serverClientId: serverClientId);
      _initializedFor = serverClientId;
    }
    try {
      final account = await google.authenticate(scopeHint: const ['email']);
      return account.authentication.idToken;
    } on GoogleSignInException catch (e) {
      if (e.code == GoogleSignInExceptionCode.canceled || e.code == GoogleSignInExceptionCode.interrupted) return null;
      throw GoogleIdTokenFailure(e.code.name);
    }
  }

  @override
  Future<void> signOut() async {
    if (_initializedFor == null) return;
    await GoogleSignIn.instance.signOut();
  }
}

final googleIdTokensProvider = Provider<GoogleIdTokens>((ref) => PluginGoogleIdTokens());
