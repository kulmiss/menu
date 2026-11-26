import 'dart:async';

import 'package:flutter/services.dart';
import 'package:flutter/widgets.dart';

/// Screens that show secrets (two-factor key and QR, recovery codes):
/// no screenshots or screen recording on Android (FLAG_SECURE), and the
/// app-switcher preview is covered on both platforms, even with the app lock off
/// (SECURITY_REPORT.md M13).
class SecureScreens {
  SecureScreens._();
  static const _channel = MethodChannel('menuadmin/secure');

  /// How many secret screens are open; AppGates covers the app while > 0 and in the background.
  static final ValueNotifier<int> open = ValueNotifier(0);

  static Future<void> _apply() async {
    try {
      await _channel.invokeMethod<void>('setSecure', open.value > 0);
    } on MissingPluginException {
      // iOS, tests: the cover alone protects the app switcher.
    } on PlatformException {
      // Never block the screen because of this.
    }
  }

  static void enter() {
    open.value++;
    unawaited(_apply());
  }

  static void leave() {
    if (open.value > 0) open.value--;
    unawaited(_apply());
  }

  /// Copies a secret; Android 13+ hides it from the clipboard preview, and it is
  /// cleared after [clearAfter] if it is still the clipboard's content (M14).
  static Future<void> copySecret(String text, {Duration clearAfter = const Duration(seconds: 60)}) async {
    var done = false;
    try {
      done = await _channel.invokeMethod<bool>('copySensitive', text) ?? false;
    } on MissingPluginException {
      done = false;
    } on PlatformException {
      done = false;
    }
    if (!done) await Clipboard.setData(ClipboardData(text: text));
    clearTimer?.cancel();
    clearTimer = Timer(clearAfter, () async {
      final now = await Clipboard.getData(Clipboard.kTextPlain);
      if (now?.text == text) await Clipboard.setData(const ClipboardData(text: ''));
    });
  }

  @visibleForTesting
  static Timer? clearTimer;
}

/// Wrap a secret screen's body in this.
class SecureScreen extends StatefulWidget {
  const SecureScreen({super.key, required this.child, this.active = true});
  final Widget child;
  final bool active;
  @override
  State<SecureScreen> createState() => _SecureScreenState();
}

class _SecureScreenState extends State<SecureScreen> {
  bool _on = false;

  void _sync() {
    if (widget.active && !_on) {
      _on = true;
      SecureScreens.enter();
    } else if (!widget.active && _on) {
      _on = false;
      SecureScreens.leave();
    }
  }

  @override
  void initState() {
    super.initState();
    _sync();
  }

  @override
  void didUpdateWidget(covariant SecureScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    _sync();
  }

  @override
  void dispose() {
    if (_on) SecureScreens.leave();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => widget.child;
}
