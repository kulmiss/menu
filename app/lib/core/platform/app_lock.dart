import 'dart:convert';
import 'dart:math';

import 'package:crypto/crypto.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:local_auth/local_auth.dart';

import '../auth/token_store.dart';
import '../providers.dart';

/// PBKDF2-HMAC-SHA256 for the app PIN (QUESTIONS M14).
String hashPin(String pin, String salt, {int iterations = 20000}) {
  final hmac = Hmac(sha256, utf8.encode(pin));
  var u = hmac.convert([...utf8.encode(salt), 0, 0, 0, 1]).bytes;
  final out = List<int>.from(u);
  for (var i = 1; i < iterations; i++) {
    u = hmac.convert(u).bytes;
    for (var j = 0; j < out.length; j++) {
      out[j] ^= u[j];
    }
  }
  return base64Encode(out);
}

/// What a PIN attempt did.
@immutable
class PinResult {
  const PinResult.ok() : kind = PinOutcome.ok, triesLeft = 0, wait = Duration.zero;
  const PinResult.wrong(this.triesLeft, {this.wait = Duration.zero}) : kind = PinOutcome.wrong;
  const PinResult.wait(this.wait) : kind = PinOutcome.wait, triesLeft = 0;
  const PinResult.signedOut() : kind = PinOutcome.signedOut, triesLeft = 0, wait = Duration.zero;
  final PinOutcome kind;
  final int triesLeft;
  final Duration wait;
  bool get ok => kind == PinOutcome.ok;
}

enum PinOutcome { ok, wrong, wait, signedOut }

@immutable
class LockState {
  const LockState({this.enabled = false, this.locked = false, this.minutes = 5, this.biometrics = true, this.hasPin = false});
  final bool enabled;
  final bool locked;
  final int minutes;
  final bool biometrics;
  final bool hasPin;

  LockState copyWith({bool? enabled, bool? locked, int? minutes, bool? biometrics, bool? hasPin}) => LockState(
    enabled: enabled ?? this.enabled,
    locked: locked ?? this.locked,
    minutes: minutes ?? this.minutes,
    biometrics: biometrics ?? this.biometrics,
    hasPin: hasPin ?? this.hasPin,
  );
}

/// Optional biometric/PIN lock with auto-lock after time in the background.
class AppLock extends Notifier<LockState> {
  TokenStore get _s => ref.read(tokenStoreProvider);
  DateTime? _backgroundAt;
  final _auth = LocalAuthentication();

  @override
  LockState build() => const LockState();

  Future<void> load() async {
    final enabled = await _s.read('lock_enabled') == '1';
    state = LockState(
      enabled: enabled,
      locked: enabled,
      minutes: int.tryParse(await _s.read('lock_minutes') ?? '') ?? 5,
      biometrics: await _s.read('lock_bio') != '0',
      hasPin: await _s.read(TokenKeys.pinHash) != null,
    );
  }

  Future<bool> canUseBiometrics() async {
    try {
      return await _auth.canCheckBiometrics || await _auth.isDeviceSupported();
    } catch (_) {
      return false;
    }
  }

  Future<void> setPin(String pin) async {
    final salt = base64Encode(List<int>.generate(16, (_) => Random.secure().nextInt(256)));
    await _s.write(TokenKeys.pinHash, '$salt:${hashPin(pin, salt)}');
    state = state.copyWith(hasPin: true);
  }

  /// Wrong PINs: after 5 a wait (30 s, doubling, at most 5 min), after 10 the
  /// app signs out and the server password is needed again (SECURITY_REPORT.md M15).
  /// Counted in secure storage, so restarting the app doesn't reset it.
  static const maxTries = 10;
  static const freeTries = 5;

  static Duration waitAfter(int fails) {
    if (fails < freeTries) return Duration.zero;
    final s = 30 * (1 << (fails - freeTries).clamp(0, 4));
    return Duration(seconds: s > 300 ? 300 : s);
  }

  Future<PinResult> checkPin(String pin, {DateTime? now}) async {
    final at = now ?? DateTime.now();
    final until = DateTime.tryParse(await _s.read(_waitKey) ?? '');
    if (until != null && at.isBefore(until)) return PinResult.wait(until.difference(at));

    final stored = await _s.read(TokenKeys.pinHash);
    if (stored == null) return const PinResult.wrong(0);
    final [salt, hash] = stored.split(':');
    if (hashPin(pin, salt) == hash) {
      await _s.write(_failKey, null);
      await _s.write(_waitKey, null);
      state = state.copyWith(locked: false);
      return const PinResult.ok();
    }

    final fails = (int.tryParse(await _s.read(_failKey) ?? '') ?? 0) + 1;
    if (fails >= maxTries) {
      await _s.write(_failKey, null);
      await _s.write(_waitKey, null);
      await ref.read(sessionProvider.notifier).signOut();
      state = state.copyWith(locked: false);
      return const PinResult.signedOut();
    }
    await _s.write(_failKey, '$fails');
    final wait = waitAfter(fails);
    if (wait > Duration.zero) await _s.write(_waitKey, at.add(wait).toIso8601String());
    return PinResult.wrong(maxTries - fails, wait: wait);
  }

  static const _failKey = 'pin_fails';
  static const _waitKey = 'pin_wait_until';

  Future<bool> biometric(String reason) async {
    try {
      final ok = await _auth.authenticate(localizedReason: reason, persistAcrossBackgrounding: true);
      if (ok) state = state.copyWith(locked: false);
      return ok;
    } catch (_) {
      return false;
    }
  }

  Future<void> setEnabled(bool v) async {
    await _s.write('lock_enabled', v ? '1' : '0');
    state = state.copyWith(enabled: v, locked: false);
  }

  Future<void> setMinutes(int m) async {
    await _s.write('lock_minutes', '$m');
    state = state.copyWith(minutes: m);
  }

  Future<void> setBiometrics(bool v) async {
    await _s.write('lock_bio', v ? '1' : '0');
    state = state.copyWith(biometrics: v);
  }

  void onLifecycle(AppLifecycleState s) {
    if (!state.enabled) return;
    if (s == AppLifecycleState.paused || s == AppLifecycleState.hidden) {
      _backgroundAt ??= DateTime.now();
    } else if (s == AppLifecycleState.resumed) {
      final at = _backgroundAt;
      _backgroundAt = null;
      if (at != null && DateTime.now().difference(at) >= Duration(minutes: state.minutes)) state = state.copyWith(locked: true);
    }
  }
}

final appLockProvider = NotifierProvider<AppLock, LockState>(AppLock.new);
