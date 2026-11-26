import 'package:flutter_test/flutter_test.dart';
import 'package:menu_admin/core/app_config.dart';

AppConfig cfg(Map<String, dynamic> json) => AppConfig({
  'versions': {'min': '1.0.0', 'latest': '1.2.0'},
  ...json,
});

AppBlock block(AppConfig? c, {String version = '1.1.0', bool signedIn = true, bool superAdmin = false, ServerGate? gate, bool maintenanceSignIn = false}) =>
    appBlock(config: c, version: version, signedIn: signedIn, superAdmin: superAdmin, gate: gate, maintenanceSignIn: maintenanceSignIn);

void main() {
  test('no config (first start, server unreachable): the app just opens', () {
    expect(block(null), AppBlock.none);
  });

  test('forced update below the minimum version, also from the old fields and a server answer', () {
    expect(block(cfg({}), version: '0.9.9+12'), AppBlock.update);
    expect(block(AppConfig({'min_version': '2.0.0'})), AppBlock.update);
    expect(block(cfg({}), version: '1.0.0+3'), AppBlock.none);
    expect(block(cfg({}), gate: const ServerGate('mobile.update_required', null)), AppBlock.update);
  });

  test('maintenance: everyone except Super Admins; signed out, a Super Admin may choose to sign in', () {
    final m = cfg({
      'maintenance': {'on': true, 'message': 'Back at 10'},
    });
    expect(block(m), AppBlock.maintenance);
    expect(block(m, superAdmin: true), AppBlock.none);
    expect(block(m, signedIn: false), AppBlock.maintenance);
    expect(block(m, signedIn: false, maintenanceSignIn: true), AppBlock.none);
    expect(m.maintenanceMessage, 'Back at 10');
    expect(block(cfg({}), gate: const ServerGate('mobile.maintenance', 'x')), AppBlock.maintenance);
  });

  test('app sign-in off per kind of user', () {
    final off = cfg({
      'sign_in': {'restaurant': false, 'super_admin': true, 'message': 'Use the website'},
    });
    expect(block(off), AppBlock.appOff);
    expect(block(off, superAdmin: true), AppBlock.none);
    expect(block(off, signedIn: false), AppBlock.none, reason: 'a Super Admin can still sign in; the server answers a restaurant user');
    expect(
      block(
        cfg({
          'sign_in': {'restaurant': false, 'super_admin': false},
        }),
        signedIn: false,
      ),
      AppBlock.appOff,
    );
    expect(off.signInMessage, 'Use the website');
    expect(block(cfg({}), gate: const ServerGate('mobile.app_off', null)), AppBlock.appOff);
  });

  test('recommended version, release notes and app features', () {
    final c = cfg({
      'versions': {'min': '1.0.0', 'latest': '1.3.0', 'recommended': '1.2.0', 'notes': 'Faster menus'},
      'app_features': {'push': false},
    });
    expect(c.recommendedVersion, '1.2.0');
    expect(c.releaseNotes, 'Faster menus');
    expect(c.appFeature('push'), isFalse);
    expect(c.appFeature('analytics'), isTrue, reason: 'missing = on');
    expect(cfg({}).recommendedVersion, isNull);
  });

  test('banner: audience and dates', () {
    AppConfig b(String who, {String? from, String? to}) => cfg({
      'banner': {'text': 'New styles!', 'link': '/inbox', 'audience': who, 'starts_at': from, 'ends_at': to},
    });
    expect(b('all').bannerFor(superAdmin: false)?.link, '/inbox');
    expect(b('restaurant').bannerFor(superAdmin: true), isNull);
    expect(b('super_admin').bannerFor(superAdmin: false), isNull);
    expect(b('all', to: DateTime.now().subtract(const Duration(hours: 1)).toIso8601String()).bannerFor(superAdmin: false), isNull);
    expect(b('all', from: DateTime.now().add(const Duration(hours: 1)).toIso8601String()).bannerFor(superAdmin: false), isNull);
    expect(cfg({'banner': null}).bannerFor(superAdmin: false), isNull);
  });
}
