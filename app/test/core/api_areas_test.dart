import 'package:flutter_test/flutter_test.dart';
import 'package:menu_admin/core/auth/models.dart';
import 'package:menu_admin/core/providers.dart';
import 'package:menu_admin/features/auth/sign_in_screen.dart';
import 'package:menu_admin/features/platform/super_admin_screen.dart';

// The server's two areas (website QUESTIONS #215–#220): restaurant data under
// restaurant/{id}/…, the Super Admin under super/…; the old t/ and platform/
// addresses no longer exist.
void main() {
  test('restaurant calls go to restaurant/{id}/…', () {
    expect(tenantPath(7, 'items'), 'restaurant/7/items');
    expect(tenantPath(7), 'restaurant/7/');
  });

  test('Super Admin calls go to super/…', () {
    expect(SuperAdminScreen.paths, everyElement(startsWith('super/')));
  });

  // "Open Super Admin on the web" opened the old /platform, a 404 (QA, 2026-11-17).
  test('opens the Super Admin web area at its real (secret) address', () {
    const site = 'https://menu.example/';
    expect(SuperAdminScreen.webUrl(site, Me({'is_super_admin': true, 'super_admin_url': '/office-7k2q'})), 'https://menu.example/office-7k2q');
    expect(SuperAdminScreen.webUrl(site, Me({'is_super_admin': true})), 'https://menu.example/super');
    expect(SuperAdminScreen.webUrl(site, null), 'https://menu.example/super');
  });

  test('falls back to the website\'s /forgot-password page', () {
    expect(SignInScreen.forgotPasswordPath, 'forgot-password');
  });
}
