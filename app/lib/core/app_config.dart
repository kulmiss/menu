import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'cache/swr.dart';

/// `GET mobile/config` (backend QUESTIONS #190, #193), cached like any answer:
/// the app starts from the saved copy and never waits for the network.
class AppConfig {
  AppConfig(this.json);
  final Map<String, dynamic> json;

  Map<String, dynamic> _o(String k) => (json[k] as Map?)?.cast<String, dynamic>() ?? const {};
  static String? _s(Object? v) => v is String && v.trim().isNotEmpty ? v.trim() : null;

  Map<String, dynamic> get _versions => _o('versions');
  String get minVersion => _s(_versions['min']) ?? json['min_version'] as String? ?? '0.0.0';
  String get latestVersion => _s(_versions['latest']) ?? json['latest_version'] as String? ?? '0.0.0';

  /// Below this the app asks (once per version) to update; empty = never.
  String? get recommendedVersion => _s(_versions['recommended']);
  String? get releaseNotes => _s(_versions['notes']);

  /// App sign-in switches (Super Admin → Mobile app → Access). Missing = allowed.
  bool signInAllowed({required bool superAdmin}) => _o('sign_in')[superAdmin ? 'super_admin' : 'restaurant'] != false;
  bool get signInClosedForEveryone => !signInAllowed(superAdmin: false) && !signInAllowed(superAdmin: true);
  String? get signInMessage => _s(_o('sign_in')['message']);

  /// "Continue with Google" (backend 2026-11-17): shown only when the server
  /// says it is on and gives the web client the app asks Google for a token for.
  String? get googleClientId => _s(_o('google')['client_id']);
  bool get googleEnabled => _o('google')['enabled'] == true && googleClientId != null;

  bool get maintenance => _o('maintenance')['on'] == true;
  String? get maintenanceMessage => _s(_o('maintenance')['message']);

  /// The banner the server says is showing now (it checks the dates too).
  AppBanner? bannerFor({required bool superAdmin}) {
    final b = _o('banner');
    final text = _s(b['text']);
    if (text == null) return null;
    final now = DateTime.now();
    final from = DateTime.tryParse(b['starts_at'] as String? ?? ''), to = DateTime.tryParse(b['ends_at'] as String? ?? '');
    if ((from != null && now.isBefore(from)) || (to != null && now.isAfter(to))) return null;
    final who = b['audience'] as String? ?? 'all';
    if (who == 'restaurant' && superAdmin || who == 'super_admin' && !superAdmin) return null;
    return AppBanner(text, _s(b['link']));
  }

  /// App-only switches (biometric lock, push, analytics, crash reports). Missing = on.
  bool appFeature(String name) => (json['app_features'] as Map?)?[name] != false && (json['flags'] as Map?)?[name] != false;
  bool flag(String name) => (json['flags'] as Map?)?[name] == true;
  Map<String, dynamic> get links => (json['links'] as Map?)?.cast<String, dynamic>() ?? const {};
  String? link(String name) => _s(links[name]);
}

class AppBanner {
  const AppBanner(this.text, this.link);
  final String text;

  /// An app screen (`/inbox`) or an https link.
  final String? link;
}

final appConfigProvider = Provider<AppConfig?>((ref) {
  final c = ref.watch(cachedProvider('mobile/config')).value;
  final data = (c?.data as Map?)?['data'];
  return data is Map ? AppConfig(data.cast<String, dynamic>()) : null;
});

/// What the server answered on a normal request (`mobile.update_required`,
/// `mobile.maintenance`, `mobile.app_off`), in case the saved config is older.
class ServerGate {
  const ServerGate(this.code, this.message);
  final String code;
  final String? message;
}

class ServerGateController extends Notifier<ServerGate?> {
  @override
  ServerGate? build() => null;
  void set(ServerGate? g) => state = g;
}

final serverGateProvider = NotifierProvider<ServerGateController, ServerGate?>(ServerGateController.new);

enum AppBlock { none, update, maintenance, appOff }

/// Which full-screen message replaces the app (tested in app_config_test.dart).
AppBlock appBlock({
  required AppConfig? config,
  required String version,
  required bool signedIn,
  required bool superAdmin,
  ServerGate? gate,
  bool maintenanceSignIn = false,
}) {
  if ((config != null && compareVersions(version, config.minVersion) < 0) || gate?.code == 'mobile.update_required') return AppBlock.update;
  // Super Admins keep working during maintenance; signed out, they can choose to sign in.
  final maintenance = (config?.maintenance ?? false) || gate?.code == 'mobile.maintenance';
  if (maintenance && !(signedIn && superAdmin) && !(!signedIn && maintenanceSignIn)) return AppBlock.maintenance;
  if (gate?.code == 'mobile.app_off') return AppBlock.appOff;
  if (config != null) {
    if (signedIn && !config.signInAllowed(superAdmin: superAdmin)) return AppBlock.appOff;
    if (!signedIn && config.signInClosedForEveryone) return AppBlock.appOff;
  }
  return AppBlock.none;
}

/// Compares x.y.z versions (build numbers ignored).
int compareVersions(String a, String b) {
  List<int> parts(String v) => v.split('+').first.split('.').map((p) => int.tryParse(p) ?? 0).toList()..addAll([0, 0, 0]);
  final pa = parts(a), pb = parts(b);
  for (var i = 0; i < 3; i++) {
    if (pa[i] != pb[i]) return pa[i].compareTo(pb[i]);
  }
  return 0;
}
