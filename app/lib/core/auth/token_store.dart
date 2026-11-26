import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Where secrets live: Android Keystore / iOS Keychain (QUESTIONS M4).
abstract class TokenStore {
  Future<String?> read(String key);
  Future<void> write(String key, String? value);
}

class SecureTokenStore implements TokenStore {
  SecureTokenStore([FlutterSecureStorage? storage])
    : _s = storage ?? const FlutterSecureStorage(iOptions: IOSOptions(accessibility: KeychainAccessibility.first_unlock_this_device));
  final FlutterSecureStorage _s;

  @override
  Future<String?> read(String key) async {
    try {
      return await _s.read(key: key);
    } catch (_) {
      return null; // A restored backup without its keys: start signed out.
    }
  }

  @override
  Future<void> write(String key, String? value) => value == null ? _s.delete(key: key) : _s.write(key: key, value: value);
}

class MemoryTokenStore implements TokenStore {
  final Map<String, String> values = {};
  @override
  Future<String?> read(String key) async => values[key];
  @override
  Future<void> write(String key, String? value) async => value == null ? values.remove(key) : values[key] = value;
}

abstract final class TokenKeys {
  static const token = 'token';
  static const adminToken = 'admin_token';
  static const tenant = 'tenant_id';
  static const me = 'me_json';
  static const server = 'server';
  static const pinHash = 'pin_hash';
}
