import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../core/platform/push.dart';
import '../core/util/links.dart';
import '../features/auth/gates.dart';
import '../l10n/app_localizations.dart';
import 'app_identity.dart';
import 'router.dart';
import 'theme.dart';
import 'theme_mode.dart';

class MenuAdminApp extends ConsumerStatefulWidget {
  const MenuAdminApp({super.key});
  @override
  ConsumerState<MenuAdminApp> createState() => _MenuAdminAppState();
}

class _MenuAdminAppState extends ConsumerState<MenuAdminApp> {
  @override
  void initState() {
    super.initState();
    final push = ref.read(pushServiceProvider);
    push.pendingLink.addListener(() {
      final path = push.pendingLink.value;
      if (path == null) return;
      push.pendingLink.value = null;
      ref.read(routerProvider).go(path);
    });
    push.foreground.addListener(_showForegroundPush);
  }

  void _showForegroundPush() {
    final msg = ref.read(pushServiceProvider).foreground.value;
    final n = msg?.notification;
    if (n == null) return;
    // Only app screens, checked like a tapped push (an outside link opened "not found").
    final path = appPathFrom(msg!.data['link'] as String?);
    final messenger = _messengerKey.currentState;
    if (messenger == null) return;
    messenger.showSnackBar(
      SnackBar(
        content: Text([n.title, n.body].whereType<String>().join('\n')),
        action: path == null
            ? null
            : SnackBarAction(label: L.of(messenger.context).open, onPressed: () => ref.read(pushServiceProvider).pendingLink.value = path),
      ),
    );
  }

  final _messengerKey = GlobalKey<ScaffoldMessengerState>();

  @override
  Widget build(BuildContext context) {
    final GoRouter router = ref.watch(routerProvider);
    return MaterialApp.router(
      title: AppIdentity.name,
      debugShowCheckedModeBanner: false,
      scaffoldMessengerKey: _messengerKey,
      theme: buildTheme(Brightness.light),
      darkTheme: buildTheme(Brightness.dark),
      themeMode: ref.watch(themeModeProvider),
      themeAnimationDuration: MediaQuery.maybeDisableAnimationsOf(context) ?? false ? Duration.zero : const Duration(milliseconds: 200),
      localizationsDelegates: const [
        L.delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
      supportedLocales: L.supportedLocales,
      routerConfig: router,
      // Text grows with the phone setting, up to 130 % so layouts hold.
      builder: (context, child) => MediaQuery.withClampedTextScaling(maxScaleFactor: 1.3, child: AppGates(child: child ?? const SizedBox.shrink())),
    );
  }
}
