import 'dart:async';
import 'dart:convert';

import 'package:dio/dio.dart' show Options;
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../app/env.dart';
import 'api/api_client.dart';
import 'api/api_error.dart';
import 'auth/google_auth.dart';
import 'auth/models.dart';
import 'auth/permissions.dart';
import 'auth/token_store.dart';
import 'cache/cache_db.dart';

/// Overridden in main() and in tests.
final tokenStoreProvider = Provider<TokenStore>((ref) => SecureTokenStore());
final cacheDbProvider = Provider<CacheDb>((ref) => CacheDb());
final appVersionProvider = Provider<String>((ref) => '1.0.0+1');
final serverProvider = Provider<String>((ref) => Env.apiBaseUrl);

/// Mutable holder the HTTP client reads on every request (avoids a provider cycle).
class TokenHolder {
  String? token;
  void Function()? onUnauthorized;
  void Function(String code, String? message)? onGate;
}

final tokenHolderProvider = Provider<TokenHolder>((ref) => TokenHolder());

final apiProvider = Provider<ApiClient>((ref) {
  final holder = ref.watch(tokenHolderProvider);
  return ApiClient(
    baseUrl: ref.watch(serverProvider),
    token: () => holder.token,
    onUnauthorized: () => holder.onUnauthorized?.call(),
    onGate: (code, message) => holder.onGate?.call(code, message),
    appVersion: () => ref.read(appVersionProvider),
  );
});

enum SessionStatus { loading, signedOut, signedIn }

@immutable
class SessionState {
  const SessionState({this.status = SessionStatus.loading, this.token, this.adminToken, this.me, this.tenantId});

  final SessionStatus status;
  final String? token;

  /// The Super Admin's own token, kept while "logged in as" an owner.
  final String? adminToken;
  final Me? me;
  final int? tenantId;

  bool get impersonating => adminToken != null;
  TenantInfo? get tenant => me?.tenant(tenantId) ?? (me?.tenants.isNotEmpty == true ? me!.tenants.first : null);

  /// Platform mode: a Super Admin who is not "logged in as" and chose no restaurant.
  bool get platformMode => (me?.isSuperAdmin ?? false) && tenantId == null && !impersonating;

  /// Cache scope: what this user may see on this device.
  String get scope => '${me?.id ?? 0}:${impersonating ? 'as' : ''}';

  SessionState copyWith({SessionStatus? status, String? token, String? adminToken, bool clearAdmin = false, Me? me, int? tenantId, bool clearTenant = false}) =>
      SessionState(
        status: status ?? this.status,
        token: token ?? this.token,
        adminToken: clearAdmin ? null : (adminToken ?? this.adminToken),
        me: me ?? this.me,
        tenantId: clearTenant ? null : (tenantId ?? this.tenantId),
      );
}

class SessionController extends Notifier<SessionState> {
  TokenStore get _store => ref.read(tokenStoreProvider);
  ApiClient get _api => ref.read(apiProvider);

  @override
  SessionState build() {
    ref.read(tokenHolderProvider).onUnauthorized = _onUnauthorized;
    return const SessionState();
  }

  /// Start-up: token and the last `/me` from disk, so the first screen shows
  /// without waiting for the network; `/me` refreshes in the background.
  Future<void> restore() async {
    final token = await _store.read(TokenKeys.token);
    if (token == null) {
      state = const SessionState(status: SessionStatus.signedOut);
      return;
    }
    final adminToken = await _store.read(TokenKeys.adminToken);
    final tenantId = int.tryParse(await _store.read(TokenKeys.tenant) ?? '');
    final meJson = await _store.read(TokenKeys.me);
    ref.read(tokenHolderProvider).token = token;
    final me = meJson != null ? Me((jsonDecode(meJson) as Map).cast<String, dynamic>()) : null;
    state = SessionState(status: me != null ? SessionStatus.signedIn : SessionStatus.loading, token: token, adminToken: adminToken, me: me, tenantId: tenantId);
    if (me == null) {
      try {
        await refreshMe();
      } on ApiError catch (e) {
        if (e.kind == ApiErrorKind.unauthorized) return;
        state = state.copyWith(status: SessionStatus.signedOut);
      }
    } else {
      unawaited(refreshMe().then<void>((_) {}, onError: (Object _) {}));
    }
  }

  Future<void> signIn(String token) async {
    await _store.write(TokenKeys.token, token);
    ref.read(tokenHolderProvider).token = token;
    state = SessionState(status: SessionStatus.loading, token: token);
    try {
      await refreshMe();
    } catch (_) {
      // Without the profile the app can't start: back to the sign-in screen,
      // which shows the error (it used to stay on the logo for good).
      await _clear();
      rethrow;
    }
  }

  Future<Me> refreshMe() async {
    final token = state.token;
    final json = (await _api.get('me') as Map)['data'] as Map;
    final me = Me(json.cast<String, dynamic>());
    // Signed out (or someone else signed in) while this was on its way: drop it.
    if (state.token != token || state.status == SessionStatus.signedOut) return me;
    await _store.write(TokenKeys.me, jsonEncode(json));
    var tenantId = state.tenantId;
    // Restaurant users start in their (first) restaurant; Super Admins in the platform.
    if (tenantId != null && me.tenant(tenantId) == null) tenantId = null;
    if (tenantId == null && (!me.isSuperAdmin || state.impersonating) && me.tenants.isNotEmpty) tenantId = me.tenants.first.id;
    if (state.impersonating && me.impersonation != null) tenantId = me.impersonation!.tenantId;
    state = SessionState(status: SessionStatus.signedIn, token: state.token, adminToken: state.adminToken, me: me, tenantId: tenantId);
    await _store.write(TokenKeys.tenant, tenantId?.toString());
    return me;
  }

  Future<void> selectTenant(int? tenantId) async {
    state = state.copyWith(tenantId: tenantId, clearTenant: tenantId == null);
    await _store.write(TokenKeys.tenant, tenantId?.toString());
  }

  /// "Log in as" the owner (QUESTIONS #188): keep our own token for later.
  Future<void> startImpersonation(String ownerToken, int tenantId) async {
    final admin = state.token!;
    await _store.write(TokenKeys.adminToken, admin);
    await _store.write(TokenKeys.token, ownerToken);
    await _store.write(TokenKeys.tenant, '$tenantId');
    ref.read(tokenHolderProvider).token = ownerToken;
    state = state.copyWith(token: ownerToken, adminToken: admin, tenantId: tenantId);
    await refreshMe();
  }

  Future<void> stopImpersonation() async {
    final admin = state.adminToken;
    if (admin == null) return;
    try {
      await _api.post('impersonation/stop');
    } catch (_) {
      // The token may have run out already; nothing else to do.
    }
    await _store.write(TokenKeys.adminToken, null);
    await _store.write(TokenKeys.token, admin);
    await _store.write(TokenKeys.tenant, null);
    ref.read(tokenHolderProvider).token = admin;
    state = SessionState(status: SessionStatus.signedIn, token: admin, me: state.me);
    await refreshMe();
  }

  /// How long signing out waits for the server before signing out on the phone anyway.
  static const signOutWait = Duration(seconds: 5);

  Future<void> signOut({bool everywhere = false}) async {
    // One quick try, no retries: offline or a slow server must not keep the
    // user waiting (it took up to minutes with the normal retries).
    final once = Options(extra: {'noRetry': true});
    try {
      if (state.impersonating) await _api.post('impersonation/stop', options: once).timeout(signOutWait);
      if (state.token != null) {
        await _api.post(everywhere ? 'mobile/auth/logout-all' : 'mobile/auth/logout', options: once).timeout(signOutWait);
      }
    } catch (_) {
      // Signing out on the phone always works, even offline.
    }
    // Also out of Google on this phone (after "Continue with Google"), so the next sign-in asks for the account.
    try {
      await ref.read(googleIdTokensProvider).signOut();
    } catch (_) {}
    await _clear();
  }

  void _onUnauthorized() {
    // An expired "log in as" token: back to the Super Admin's own.
    if (state.impersonating) {
      unawaited(stopImpersonation().catchError((_) => _clear()));
      return;
    }
    if (state.status == SessionStatus.signedIn || state.status == SessionStatus.loading) unawaited(_clear());
  }

  Future<void> _clear() async {
    for (final k in [TokenKeys.token, TokenKeys.adminToken, TokenKeys.tenant, TokenKeys.me]) {
      await _store.write(k, null);
    }
    ref.read(tokenHolderProvider).token = null;
    await ref.read(cacheDbProvider).clearAll();
    state = const SessionState(status: SessionStatus.signedOut);
  }
}

final sessionProvider = NotifierProvider<SessionController, SessionState>(SessionController.new);

/// The restaurant being edited (null in platform mode).
final tenantProvider = Provider<TenantInfo?>((ref) => ref.watch(sessionProvider.select((s) => s.platformMode ? null : s.tenant)));

final canProvider = Provider<Can>((ref) => Can(ref.watch(tenantProvider)));

/// Path prefix of the current restaurant: `t/12/`.
String tenantPath(int tenantId, [String rest = '']) => 'restaurant/$tenantId/$rest';
