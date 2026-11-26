import 'dart:async';

import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../cache/swr.dart';
import '../providers.dart';
import '../util/links.dart';
import 'firebase.dart';

/// FCM registration and taps on notifications (backend QUESTIONS #189).
class PushService {
  PushService(this._ref);
  final Ref _ref;
  String? _token;
  StreamSubscription<RemoteMessage>? _open, _foreground;
  StreamSubscription<String>? _refresh;

  /// Where a tapped notification wants to go; the router listens.
  final ValueNotifier<String?> pendingLink = ValueNotifier(null);

  /// Shown as an in-app banner while the app is open.
  final ValueNotifier<RemoteMessage?> foreground = ValueNotifier(null);

  bool get available => FirebaseState.ready;

  Future<void> start() async {
    if (!FirebaseState.ready) return;
    final m = FirebaseMessaging.instance;
    try {
      final settings = await m.requestPermission();
      if (settings.authorizationStatus == AuthorizationStatus.denied) return;
      _refresh ??= m.onRegistered.listen(_register);
      await m.register();
      _open ??= FirebaseMessaging.onMessageOpenedApp.listen(_handleTap);
      _foreground ??= FirebaseMessaging.onMessage.listen((msg) {
        foreground.value = msg;
        _ref.invalidate(cachedProvider('mobile/notifications'));
      });
      final initial = await m.getInitialMessage();
      if (initial != null) _handleTap(initial);
    } catch (_) {
      // Push stays off; the inbox still works.
    }
  }

  void _handleTap(RemoteMessage msg) {
    final path = appPathFrom(msg.data['link'] as String?);
    if (path != null) pendingLink.value = path;
    final id = int.tryParse('${msg.data['notification_id'] ?? ''}');
    if (id != null) {
      _ref
          .read(apiProvider)
          .post(
            'mobile/notifications/read',
            data: {
              'ids': [id],
            },
          )
          .ignore();
    }
  }

  Future<void> _register(String token) async {
    _token = token;
    if (_ref.read(sessionProvider).status != SessionStatus.signedIn) return;
    try {
      await _ref
          .read(apiProvider)
          .post('mobile/push-tokens', data: {'token': token, 'platform': defaultTargetPlatform == TargetPlatform.iOS ? 'ios' : 'android'});
    } catch (_) {}
  }

  /// Before signing out: this phone stops getting this user's pushes.
  Future<void> unregister() async {
    final t = _token;
    if (t == null) return;
    try {
      // Part of signing out: one quick try (the server also drops it with the sign-in token).
      await _ref.read(apiProvider).delete('mobile/push-tokens', data: {'token': t}, noRetry: true).timeout(SessionController.signOutWait);
    } catch (_) {}
  }
}

final pushServiceProvider = Provider<PushService>((ref) => PushService(ref));
