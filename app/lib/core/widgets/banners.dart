import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../providers.dart';
import 'kit.dart';

final onlineProvider = StreamProvider<bool>((ref) async* {
  final c = Connectivity();
  bool online(List<ConnectivityResult> r) => r.any((x) => x != ConnectivityResult.none);
  yield online(await c.checkConnectivity());
  yield* c.onConnectivityChanged.map(online);
});

/// Offline, "viewing as" and read-only notices above every screen.
class TopBanners extends ConsumerWidget {
  const TopBanners({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final online = ref.watch(onlineProvider).value ?? true;
    final session = ref.watch(sessionProvider);
    final tenant = ref.watch(tenantProvider);
    final children = <Widget>[
      if (session.impersonating)
        _Bar(
          color: const Color(0xFFB91C1C),
          icon: AppIcons.eye,
          text: context.l.viewingAs(session.me?.impersonation?.tenantName ?? tenant?.name ?? ''),
          action: TextButton(
            style: TextButton.styleFrom(foregroundColor: Colors.white),
            onPressed: () => ref.read(sessionProvider.notifier).stopImpersonation(),
            child: Text(context.l.backToPlatform),
          ),
        ),
      // Password on the common/leaked list: a nudge, never forced (owner, 2026-11-14; M3).
      if (session.me?.passwordWeak == true && !session.impersonating)
        _Bar(
          color: const Color(0xFFFDE68A),
          icon: AppIcons.keyRound,
          text: context.l.passwordWeakBanner,
          action: TextButton(onPressed: () => GoRouter.of(context).push('/account/password'), child: Text(context.l.passwordWeakAction)),
        ),
      if (!online) _Bar(color: context.colors.secondary, icon: AppIcons.wifiOff, text: context.l.offlineBanner),
      if (tenant != null && !tenant.allowsWrites) _Bar(color: const Color(0xFFB45309), icon: AppIcons.lockKeyhole, text: context.l.readOnlyBanner),
    ];
    if (children.isEmpty) return const SizedBox.shrink();
    return Column(mainAxisSize: MainAxisSize.min, children: children);
  }
}

class _Bar extends StatelessWidget {
  const _Bar({required this.color, required this.icon, required this.text, this.action});
  final Color color;
  final IconData icon;
  final String text;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    final fg = ThemeData.estimateBrightnessForColor(color) == Brightness.dark ? Colors.white : Colors.black;
    return Material(
      color: color,
      child: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
          child: Row(
            children: [
              Icon(icon, size: 18, color: fg),
              const SizedBox(width: 10),
              Expanded(
                child: Semantics(
                  liveRegion: true,
                  child: Text(text, style: TextStyle(color: fg, fontSize: 13)),
                ),
              ),
              ?action,
            ],
          ),
        ),
      ),
    );
  }
}
