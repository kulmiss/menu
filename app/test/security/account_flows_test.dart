import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:menu_admin/core/providers.dart';

import '../helpers/fake_api.dart';
import '../widgets/flows.dart';

/// The app side of SECURITY_REPORT.md H3 (e-mail change), M3 (weak password) and M17 (Owner two-factor).
Map<String, dynamic> me({bool twoFactor = false, bool required = false, bool weak = false, String? pending}) {
  final m = meJson(twoFactor: twoFactor);
  (m['data'] as Map<String, dynamic>).addAll({'two_factor_required': required, 'password_weak': weak, 'pending_email': pending});
  return m;
}

void main() {
  testWidgets('a new e-mail asks for the password, sends it, and keeps the old address until the link', (tester) async {
    final api = restaurantServer()
      ..json('GET me', me(twoFactor: true, pending: 'new@example.com'))
      ..json('PUT mobile/account/profile', {'data': {}});
    final c = await startApp(tester, api, token: 'tok');
    GoRouter.of(tester.element(find.byType(Scaffold).first)).push('/account');
    await tester.pumpAndSettle();

    expect(find.textContaining('Waiting for confirmation: new@example.com'), findsOneWidget);
    await tester.enterText(find.widgetWithText(TextField, 'E-mail'), 'other@example.com');
    await tester.tap(find.widgetWithText(FilledButton, 'Save'));
    await tester.pumpAndSettle();
    // The password dialog.
    await tester.enterText(find.descendant(of: find.byType(AlertDialog), matching: find.byType(TextField)), 'correct-horse');
    await tester.tap(find.descendant(of: find.byType(AlertDialog), matching: find.byType(FilledButton)));
    await tester.pumpAndSettle();

    final put = api.called('PUT', 'mobile/account/profile').single;
    expect(put.body, containsPair('current_password', 'correct-horse'));
    expect(put.body, containsPair('email', 'other@example.com'));
    expect(find.textContaining('We sent a link to other@example.com'), findsOneWidget);
    expect(c.read(sessionProvider).me!.email, 'owner@example.com');
  });

  testWidgets('owners without two-factor see the dashboard card and the weak-password banner', (tester) async {
    final api = restaurantServer()..json('GET me', me(weak: true));
    await startApp(tester, api, token: 'tok');
    expect(find.text('Protect your restaurant with two-step sign-in'), findsOneWidget);
    expect(find.textContaining('common or leaked passwords'), findsOneWidget);
  });

  testWidgets('when the Super Admin requires it, an Owner without two-factor lands on its setup', (tester) async {
    final api = restaurantServer()
      ..json('GET me', me(required: true))
      ..json('POST mobile/account/two-factor', null, 204);
    await startApp(tester, api, token: 'tok');
    expect(find.textContaining('requires two-step sign-in for restaurant owners'), findsOneWidget);
    // The password prompt opens at once (one tap).
    expect(find.byType(AlertDialog), findsOneWidget);
  });
}
