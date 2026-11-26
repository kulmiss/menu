import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../app/router.dart';
import '../../core/providers.dart';
import '../../core/widgets/adaptive_shell.dart';
import '../../core/widgets/kit.dart';
import 'all_dishes_screen.dart';
import 'appearance_screen.dart';
import 'billing_screen.dart';
import 'branches_screen.dart';
import 'dashboard_screen.dart';
import 'dish_editor_screen.dart';
import 'info_screen.dart';
import 'insights_screen.dart';
import 'menu_screen.dart';
import 'more_screen.dart';
import 'orders_screen.dart';
import 'promotions_screen.dart';
import 'qr_screen.dart';
import 'sold_out_screen.dart';
import 'team_screen.dart';

/// Makes sure the restaurant in the address is the one being edited
/// (deep links from pushes can point at another restaurant).
class _TenantGuard extends ConsumerWidget {
  const _TenantGuard({required this.tenantId, required this.child});
  final int tenantId;
  final Widget child;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final s = ref.watch(sessionProvider);
    if (s.tenantId != tenantId || s.platformMode) {
      WidgetsBinding.instance.addPostFrameCallback((_) => ref.read(sessionProvider.notifier).selectTenant(tenantId));
      return const Scaffold(body: Skeleton());
    }
    return child;
  }
}

int _tid(GoRouterState s) => int.parse(s.pathParameters['tid']!);

List<RouteBase> restaurantRoutes(Ref ref, GlobalKey<NavigatorState> rootKey) => [
  ShellRoute(
    builder: (context, state, child) {
      final t = _tid(state);
      final can = ref.read(canProvider);
      final l = context.l;
      return _TenantGuard(
        tenantId: t,
        child: ModeShell(
          location: state.uri.path,
          items: [
            NavItem(l.navHome, AppIcons.house, AppIcons.house, '/r/$t'),
            NavItem(l.navMenu, AppIcons.utensils, AppIcons.utensils, '/r/$t/menu'),
            if (ordersTabOn(can)) NavItem(l.navOrders, AppIcons.clipboardList, AppIcons.clipboardList, '/r/$t/orders'),
            NavItem(l.navMore, AppIcons.ellipsis, AppIcons.ellipsis, '/r/$t/more'),
          ],
          child: child,
        ),
      );
    },
    routes: [
      // Tabs are siblings, so switching tabs never shows a back arrow.
      GoRoute(path: '/r/:tid', builder: (_, _) => const DashboardScreen()),
      GoRoute(
        path: '/r/:tid/menu',
        builder: (_, s) => MenuScreen(menuId: int.tryParse(s.uri.queryParameters['menu'] ?? '')),
      ),
      GoRoute(path: '/r/:tid/orders', builder: (_, _) => const OrdersScreen()),
      GoRoute(path: '/r/:tid/more', builder: (_, _) => const MoreScreen()),
    ],
  ),
  // Full-screen pages above the navigation.
  GoRoute(
    path: '/r/:tid/dishes/new',
    parentNavigatorKey: rootKey,
    builder: (_, s) => _TenantGuard(
      tenantId: _tid(s),
      child: DishEditorScreen(
        menuId: int.tryParse(s.uri.queryParameters['menu'] ?? ''),
        categoryId: int.tryParse(s.uri.queryParameters['category'] ?? ''),
        autoPhoto: s.uri.queryParameters['photo'] == '1',
      ),
    ),
  ),
  GoRoute(
    path: '/r/:tid/dishes/:id',
    parentNavigatorKey: rootKey,
    builder: (_, s) => _TenantGuard(
      tenantId: _tid(s),
      child: DishEditorScreen(dishId: int.parse(s.pathParameters['id']!)),
    ),
  ),
  for (final (path, builder) in <(String, Widget Function(GoRouterState))>[
    ('all-dishes', (_) => const AllDishesScreen()),
    ('sold-out', (_) => const SoldOutScreen()),
    ('insights', (_) => const InsightsScreen()),
    ('branches', (_) => const BranchesScreen()),
    ('branches/:bid', (s) => BranchDetailScreen(branchId: int.parse(s.pathParameters['bid']!))),
    ('qr', (_) => const QrScreen()),
    ('print', (_) => const PrintScreen()),
    ('appearance', (_) => const AppearanceScreen()),
    ('info', (_) => const InfoScreen()),
    ('promotions', (_) => const PromotionsScreen()),
    ('happy-hour', (_) => const HappyHourScreen()),
    ('team', (_) => const TeamScreen()),
    ('billing', (_) => const BillingScreen()),
    ('activity', (_) => const ActivityScreen()),
    ('news/:id', (s) => NewsScreen(id: int.parse(s.pathParameters['id']!))),
  ])
    GoRoute(
      path: '/r/:tid/$path',
      parentNavigatorKey: rootKey,
      builder: (_, s) => _TenantGuard(tenantId: _tid(s), child: builder(s)),
    ),
];
