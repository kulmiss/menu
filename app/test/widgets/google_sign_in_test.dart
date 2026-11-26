// "Continue with Google" (backend 2026-11-17): the button from mobile/config,
// Google's ID token to the server, the restaurant-name step for a new e-mail,
// two-factor, and signing out of Google too.
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:menu_admin/app/app.dart';
import 'package:menu_admin/core/app_config.dart';
import 'package:menu_admin/core/auth/google_auth.dart';
import 'package:menu_admin/core/auth/token_store.dart';
import 'package:menu_admin/core/providers.dart';

import '../helpers/fake_api.dart';
import 'flows.dart';

class FakeGoogle implements GoogleIdTokens {
  FakeGoogle({this.token = 'google-id-token', this.fail = false});
  String? token;
  bool fail;
  final List<String> askedFor = [];
  int signOuts = 0;

  @override
  Future<String?> idToken(String serverClientId) async {
    askedFor.add(serverClientId);
    if (fail) throw GoogleIdTokenFailure('clientConfigurationError');
    return token;
  }

  @override
  Future<void> signOut() async => signOuts++;
}

const webClient = 'web-client.apps.googleusercontent.com';

FakeApi googleServer({bool enabled = true}) => restaurantServer()
  ..json('GET mobile/config', {
    'data': {
      'min_version': '1.0.0',
      'links': {},
      'google': {'enabled': enabled, 'client_id': enabled ? webClient : null},
    },
  });

Future<ProviderContainer> start(WidgetTester tester, FakeApi api, FakeGoogle google, {String? token}) async {
  final store = MemoryTokenStore();
  if (token != null) store.values[TokenKeys.token] = token;
  final c = ProviderContainer(
    overrides: [
      ...testOverrides(api, store: store),
      googleIdTokensProvider.overrideWithValue(google),
    ],
    retry: noRetry,
  );
  addTearDown(c.dispose);
  await tester.runAsync(() => c.read(sessionProvider.notifier).restore());
  await tester.pumpWidget(UncontrolledProviderScope(container: c, child: const MenuAdminApp()));
  await tester.pumpAndSettle();
  return c;
}

void main() {
  test('reads the Google switch and the web client from mobile/config', () {
    expect(AppConfig({}).googleEnabled, isFalse);
    expect(
      AppConfig({
        'google': {'enabled': true, 'client_id': null},
      }).googleEnabled,
      isFalse,
    );
    final on = AppConfig({
      'google': {'enabled': true, 'client_id': webClient},
    });
    expect(on.googleEnabled, isTrue);
    expect(on.googleClientId, webClient);
  });

  testWidgets('shows "Continue with Google" only when the server says it is on', (tester) async {
    await start(tester, googleServer(enabled: false), FakeGoogle());
    expect(find.text('Sign in to Menu Admin'), findsOneWidget);
    expect(find.byKey(const Key('google')), findsNothing);

    await start(tester, googleServer(), FakeGoogle());
    expect(find.byKey(const Key('google')), findsOneWidget);
    expect(find.text('Continue with Google'), findsOneWidget);
  });

  testWidgets('signs in with Google\'s ID token and says when Google was linked', (tester) async {
    final api = googleServer()
      ..json('POST mobile/auth/google', {
        'token': 'tok-g',
        'user': {'id': 1},
        'google_linked': true,
      });
    final google = FakeGoogle();
    final c = await start(tester, api, google);

    await tester.tap(find.byKey(const Key('google')));
    await tester.pumpAndSettle();

    expect(google.askedFor, [webClient]);
    final call = api.called('POST', 'mobile/auth/google').single;
    expect(call.body['id_token'], 'google-id-token');
    expect(call.body['device_name'], isNotEmpty);
    expect(call.headers['Idempotency-Key'], isNull, reason: 'sign-in is not stored for replay');
    expect(c.read(sessionProvider).status, SessionStatus.signedIn);
    expect(api.called('GET', 'me').last.headers['Authorization'], 'Bearer tok-g');
    expect(find.text('Google is now linked. Next time, tap “Continue with Google”.'), findsOneWidget);
  });

  testWidgets('does nothing when the person closes Google\'s sheet, and explains a phone without Google', (tester) async {
    final api = googleServer();
    final google = FakeGoogle(token: null);
    await start(tester, api, google);
    await tester.tap(find.byKey(const Key('google')));
    await tester.pumpAndSettle();
    expect(api.called('POST', 'mobile/auth/google'), isEmpty);

    google.fail = true;
    await tester.tap(find.byKey(const Key('google')));
    await tester.pumpAndSettle();
    expect(find.text('Google sign-in is not available on this phone. Use your e-mail and password.'), findsOneWidget);
  });

  testWidgets('asks the two-factor code after Google', (tester) async {
    final api = googleServer()
      ..json('POST mobile/auth/google', {'two_factor': true, 'challenge': 'c' * 48})
      ..json('POST mobile/auth/two-factor', {
        'token': 'tok-2',
        'user': {'id': 1},
      });
    final c = await start(tester, api, FakeGoogle());
    await tester.tap(find.byKey(const Key('google')));
    await tester.pumpAndSettle();

    expect(find.text('Two-step sign-in'), findsOneWidget);
    await tester.enterText(find.byKey(const Key('code')), '123456');
    await tester.pumpAndSettle();
    expect(api.called('POST', 'mobile/auth/two-factor').single.body['challenge'], 'c' * 48);
    expect(c.read(sessionProvider).status, SessionStatus.signedIn);
  });

  testWidgets('a new e-mail gives the restaurant name and accepts the Terms, then is signed in', (tester) async {
    final api = googleServer()
      ..json('POST mobile/auth/google', {'google_signup': true, 'ticket': 't' * 48, 'email': 'selam@gmail.com', 'name': 'Selam'})
      ..json('POST mobile/auth/google/finish', {
        'token': 'tok-new',
        'user': {'id': 1},
      });
    final c = await start(tester, api, FakeGoogle());
    await tester.tap(find.byKey(const Key('google')));
    await tester.pumpAndSettle();

    expect(find.text('Almost done'), findsOneWidget);
    expect(find.textContaining('selam@gmail.com'), findsOneWidget);
    await tester.tap(find.byKey(const Key('createMenu')));
    await tester.pumpAndSettle();
    expect(find.text('Please accept the terms to continue.'), findsOneWidget);
    expect(api.called('POST', 'mobile/auth/google/finish'), isEmpty);

    await tester.enterText(find.byKey(const Key('restaurantName')), 'Buna & Bread');
    await tester.tap(find.byKey(const Key('terms')));
    await tester.tap(find.byKey(const Key('createMenu')));
    await tester.pumpAndSettle();

    final finish = api.called('POST', 'mobile/auth/google/finish').single;
    expect(finish.body, {'ticket': 't' * 48, 'restaurant_name': 'Buna & Bread', 'terms': true, 'locale': 'en'});
    expect(c.read(sessionProvider).status, SessionStatus.signedIn);
    expect(api.called('GET', 'me').last.headers['Authorization'], 'Bearer tok-new');
  });

  testWidgets('shows the server\'s reason, e.g. a Super Admin account', (tester) async {
    final api = googleServer()
      ..json('POST mobile/auth/google', {
        'message': 'Super Admin accounts sign in with their password and two-factor code only.',
        'code': 'google.super_admin',
      }, 403);
    await start(tester, api, FakeGoogle());
    await tester.tap(find.byKey(const Key('google')));
    await tester.pumpAndSettle();
    expect(find.text('Super Admin accounts sign in with their password and two-factor code only.'), findsOneWidget);
  });

  testWidgets('signing out also signs out of Google on the phone', (tester) async {
    final api = googleServer()..json('POST mobile/auth/logout', null, 204);
    final google = FakeGoogle();
    final c = await start(tester, api, google, token: 'tok');
    await tester.runAsync(() => c.read(sessionProvider.notifier).signOut());
    await tester.pumpAndSettle();
    expect(google.signOuts, 1);
    expect(c.read(sessionProvider).status, SessionStatus.signedOut);
  });
}
