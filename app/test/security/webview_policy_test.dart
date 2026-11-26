import 'package:flutter_test/flutter_test.dart';
import 'package:menu_admin/features/restaurant/appearance_screen.dart';

/// SECURITY_REPORT.md L8: the live preview can't navigate anywhere.
void main() {
  test('allows only loading the preview itself', () {
    const site = 'https://menu.example.com/';
    expect(allowPreviewNavigation('about:blank', site), isTrue);
    expect(allowPreviewNavigation(site, site), isTrue);
    for (final url in [
      'https://menu.example.com/buna',
      'https://evil.example/',
      'data:text/html,<script>alert(1)</script>',
      'javascript:alert(1)',
      'file:///data/data/com.kulmis.menu_admin/shared_prefs/x.xml',
      'intent://scan/#Intent;scheme=zxing;end',
      'http://menu.example.com/',
    ]) {
      expect(allowPreviewNavigation(url, site), isFalse, reason: url);
    }
  });
}
