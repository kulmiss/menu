import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:menu_admin/core/platform/device_integrity.dart';
import 'package:menu_admin/features/auth/gates.dart';

import '../helpers/fake_api.dart';

/// SECURITY_REPORT.md L12 (owner: warning only, no Play Integrity).
void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  test('a normal test machine is not reported as rooted (no false alarms)', () async {
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger.setMockMethodCallHandler(
      const MethodChannel('menuadmin/secure'),
      (call) async => call.method == 'looksRooted' ? false : null,
    );
    expect(await DeviceIntegrity.looksCompromised(), isFalse);
  });

  testWidgets('shows the warning with a close button, and never blocks the app', (tester) async {
    var closed = false;
    await tester.pumpWidget(testApp(RootWarning(onClose: () => closed = true)));
    expect(find.textContaining('rooted or jailbroken'), findsOneWidget);
    await tester.tap(find.bySemanticsLabel('Close'));
    expect(closed, isTrue);
  });
}
