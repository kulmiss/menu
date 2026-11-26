import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../core/providers.dart';
import '../core/util/links.dart';
import '../core/widgets/adaptive_shell.dart';
import '../core/widgets/banners.dart';
import '../core/widgets/kit.dart';
import '../features/account/account_screens.dart';
import '../features/account/inbox_screen.dart';
import '../features/help/help_screens.dart';
import '../features/auth/gates.dart';
import '../features/auth/sign_in_screen.dart';
import '../features/platform/platform_routes.dart';
import '../features/restaurant/restaurant_routes.dart';
import 'analytics_observer.dart';

final _rootKey = GlobalKey<NavigatorState>(debugLabel: 'root');

/// Bridges session changes to go_router's redirect.
class _SessionListenable extends ChangeNotifier {
  _SessionListenable(Ref ref) {
    ref.listen(sessionProvider.select((s) => (s.status, s.platformMode, s.tenantId, s.impersonating)), (_, _) => notifyListeners());
  }
}

String homeFor(SessionState s) {
  if (s.status != SessionStatus.signedIn) return '/sign-in';
  if (s.platformMode) return '/p';
  final t = s.tenant;
  return t == null ? '/no-restaurant' : '/r/${t.id}';
}

final routerProvider = Provider<GoRouter>((ref) {
  final listenable = _SessionListenable(ref);
  ref.onDispose(listenable.dispose);
  return GoRouter(
    navigatorKey: _rootKey,
    initialLocation: '/splash',
    refreshListenable: listenable,
    observers: [AppRouteObserver(ref)],
    redirect: (context, state) {
      final s = ref.read(sessionProvider);
      final loc = state.matchedLocation;
      if (s.status == SessionStatus.loading) return loc == '/splash' ? null : '/splash';
      // Help is readable signed out too (sign-in problems).
      if (s.status == SessionStatus.signedOut) return loc == '/sign-in' || loc.startsWith('/help') ? null : '/sign-in';
      // Signed in.
      if (loc == '/splash' || loc == '/sign-in' || loc == '/') return homeFor(s);
      final me = s.me;
      // Two-factor is required for Owners and not on yet: only the account screens (M17).
      if ((me?.twoFactorRequired ?? false) && !s.impersonating && !loc.startsWith('/account')) return '/account/two-factor';
      if (loc.startsWith('/p') && !(me?.isSuperAdmin ?? false)) return homeFor(s);
      if (loc.startsWith('/p') && s.impersonating) return homeFor(s);
      final tid = tenantOfPath(loc);
      if (tid != null && me?.tenant(tid) == null) return homeFor(s);
      return null;
    },
    routes: [
      GoRoute(path: '/splash', builder: (_, _) => const SplashScreen()),
      GoRoute(path: '/sign-in', builder: (_, _) => const SignInScreen()),
      GoRoute(path: '/no-restaurant', builder: (_, _) => const NoRestaurantScreen()),
      ...restaurantRoutes(ref, _rootKey),
      ...platformRoutes(ref, _rootKey),
      ...accountRoutes(),
      GoRoute(path: '/inbox', parentNavigatorKey: _rootKey, builder: (_, _) => const InboxScreen()),
      GoRoute(
        path: '/help',
        parentNavigatorKey: _rootKey,
        builder: (_, _) => const HelpScreen(),
        routes: [
          GoRoute(
            path: ':slug',
            builder: (_, st) => HelpArticleScreen(slug: st.pathParameters['slug']!),
          ),
        ],
      ),
    ],
    errorBuilder: (context, state) => Scaffold(
      appBar: AppBar(),
      body: EmptyState(icon: AppIcons.link2Off, title: context.l.errorNotFound),
    ),
  );
});

/// Shell with the adaptive navigation and the banners on top.
class ModeShell extends ConsumerWidget {
  const ModeShell({super.key, required this.items, required this.child, required this.location});
  final List<NavItem> items;
  final Widget child;
  final String location;

  @override
  Widget build(BuildContext context, WidgetRef ref) =>
      AdaptiveShell(items: items, location: location, onSelect: (p) => GoRouter.of(context).go(p), header: const TopBanners(), child: child);
}
