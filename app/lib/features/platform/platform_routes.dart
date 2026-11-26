import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../app/router.dart';
import 'super_admin_screen.dart';

/// Older Super Admin screens (removed 2026-11, now on the web): links from
/// pushes and bookmarks land on the overview instead of "Not found".
const _retired = [
  'restaurants',
  'restaurants/:id',
  'restaurants/:id/payments',
  'approvals',
  'search',
  'more',
  'dashboard',
  'plans',
  'payments',
  'invoices',
  'messages',
  'messages/new',
  'backups',
  'announcements',
  'settings',
  'activity',
];

List<RouteBase> platformRoutes(Ref ref, GlobalKey<NavigatorState> rootKey) => [
  ShellRoute(
    builder: (context, state, child) => ModeShell(location: state.uri.path, items: const [], child: child),
    routes: [GoRoute(path: '/p', builder: (_, _) => const SuperAdminScreen())],
  ),
  for (final p in _retired) GoRoute(path: '/p/$p', redirect: (_, _) => '/p'),
];
