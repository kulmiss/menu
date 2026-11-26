import 'package:flutter/services.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:menu_admin/core/platform/secure_screen.dart';

/// SECURITY_REPORT.md M13 (no screenshots of secrets) and M14 (clipboard cleared).
void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  final calls = <MethodCall>[];
  String? clipboard;

  setUp(() {
    calls.clear();
    clipboard = null;
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger.setMockMethodCallHandler(const MethodChannel('menuadmin/secure'), (call) async {
      calls.add(call);
      if (call.method == 'copySensitive') clipboard = call.arguments as String;
      return call.method == 'copySensitive' ? true : null;
    });
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger.setMockMethodCallHandler(SystemChannels.platform, (call) async {
      if (call.method == 'Clipboard.setData') clipboard = (call.arguments as Map)['text'] as String?;
      if (call.method == 'Clipboard.getData') return {'text': clipboard};
      return null;
    });
  });

  testWidgets('turns FLAG_SECURE on while a secret screen is open, and off after', (tester) async {
    await tester.pumpWidget(
      const Directionality(
        textDirection: TextDirection.ltr,
        child: SecureScreen(child: Text('codes')),
      ),
    );
    expect(SecureScreens.open.value, 1);
    expect(calls.last, isMethodCall('setSecure', arguments: true));

    await tester.pumpWidget(
      const Directionality(
        textDirection: TextDirection.ltr,
        child: SecureScreen(active: false, child: Text('done')),
      ),
    );
    expect(SecureScreens.open.value, 0);
    expect(calls.last, isMethodCall('setSecure', arguments: false));

    await tester.pumpWidget(
      const Directionality(
        textDirection: TextDirection.ltr,
        child: SecureScreen(child: Text('again')),
      ),
    );
    await tester.pumpWidget(const SizedBox());
    expect(SecureScreens.open.value, 0);
  });

  testWidgets('copies a secret as sensitive and clears it after a minute if unchanged', (tester) async {
    await SecureScreens.copySecret('aaaa-bbbb\ncccc-dddd', clearAfter: const Duration(seconds: 60));
    expect(calls.single.method, 'copySensitive');
    expect(clipboard, 'aaaa-bbbb\ncccc-dddd');
    await tester.pump(const Duration(seconds: 61));
    await tester.pump();
    expect(clipboard, '');

    // Something else copied meanwhile is left alone.
    await SecureScreens.copySecret('secret', clearAfter: const Duration(seconds: 60));
    clipboard = 'my own note';
    await tester.pump(const Duration(seconds: 61));
    await tester.pump();
    expect(clipboard, 'my own note');
  });
}
