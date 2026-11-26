import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/auth/models.dart';
import '../../core/cache/swr.dart';
import '../../core/providers.dart';
import '../../core/util/json.dart';
import '../../core/widgets/kit.dart';
import '../restaurant/more_screen.dart' show showRestaurantSwitcher;
import '../restaurant/restaurant_scaffold.dart';

/// Super Admin in the app, kept small on purpose (owner, 2026-11-14): an
/// overview, the emergency switches, and the way to the full web console.
class SuperAdminScreen extends ConsumerWidget {
  const SuperAdminScreen({super.key});

  static const paths = ['super/dashboard', 'super/mobile-app', 'super/settings'];

  /// "Open Super Admin on the web": the area's real address (it can be secret), not the old /platform.
  static String webUrl(String siteUrl, Me? me) => '$siteUrl${(me?.superAdminPath ?? '/super').replaceFirst(RegExp('^/+'), '')}';

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l;
    final s = ref.watch(sessionProvider);
    final web = webUrl(ref.read(apiProvider).siteUrl, s.me);
    return RestaurantScaffold.large(
      title: l.saTitle,
      subtitle: s.me?.name,
      actions: [IconButton(tooltip: l.saOpenWeb, icon: const Icon(AppIcons.externalLink), onPressed: () => openExternal(context, web))],
      onRefresh: () => ref.refreshPaths(paths),
      children: [
        const Padding(
          padding: EdgeInsets.symmetric(horizontal: AppTokens.pad),
          child: _Overview(),
        ),
        SectionHeader(l.saEmergency),
        const _Switches(),
        Padding(
          padding: const EdgeInsets.fromLTRB(AppTokens.pad, 6, AppTokens.pad, 0),
          child: Text(l.saEmergencyHint, style: TextStyle(fontSize: 13, color: context.tokens.mutedForeground)),
        ),
        const SizedBox(height: 20),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: AppTokens.pad),
          child: AppCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(l.saOpenWebHint, style: TextStyle(color: context.tokens.mutedForeground)),
                const SizedBox(height: 12),
                AppButton(label: l.saOpenWeb, icon: AppIcons.externalLink, expand: true, onPressed: () => openExternal(context, web)),
              ],
            ),
          ),
        ),
        SectionHeader(l.account),
        Group(
          children: [
            if ((s.me?.tenants.length ?? 0) > 0)
              ListTile(
                leading: const Icon(AppIcons.store),
                title: Text(l.switchRestaurant),
                trailing: const Icon(AppIcons.chevronRight, size: 18),
                onTap: () => showRestaurantSwitcher(context, ref),
              ),
            ListTile(
              leading: const Icon(AppIcons.bell),
              title: Text(l.notifications),
              trailing: const Icon(AppIcons.chevronRight, size: 18),
              onTap: () => context.push('/inbox'),
            ),
            ListTile(
              leading: const Icon(AppIcons.user),
              title: Text(l.profile),
              trailing: const Icon(AppIcons.chevronRight, size: 18),
              onTap: () => context.push('/account'),
            ),
            ListTile(
              leading: const Icon(AppIcons.settings),
              title: Text(l.settings),
              trailing: const Icon(AppIcons.chevronRight, size: 18),
              onTap: () => context.push('/account/app'),
            ),
          ],
        ),
      ],
    );
  }
}

class _Overview extends ConsumerWidget {
  const _Overview();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final v = ref.watch(cachedProvider('super/dashboard'));
    final d = v.value == null ? null : J.data(v.value!.data);
    if (d == null && v.hasError) return ErrorView(error: v.error!, onRetry: () => ref.refreshPath('super/dashboard'));
    final l = context.l;
    final states = d?.o('states');
    String n(int? x) => x == null ? '—' : '$x';
    final tiles = [
      (l.saRestaurants, n(d?.iN('restaurants'))),
      (l.saActive, n(states?.iN('active'))),
      (l.saTrial, n(states?.iN('trial'))),
      (l.saSignups30, n(d?.iN('signups_30d'))),
    ];
    return Column(
      children: [
        for (var i = 0; i < tiles.length; i += 2) ...[
          if (i > 0) const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: StatTile(label: tiles[i].$1, value: tiles[i].$2),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: StatTile(label: tiles[i + 1].$1, value: tiles[i + 1].$2),
              ),
            ],
          ),
        ],
        const SizedBox(height: 12),
        SizedBox(
          width: double.infinity,
          child: StatTile(label: l.saViews30, value: n(d?.iN('views_30d'))),
        ),
      ],
    );
  }
}

/// The four switches the owner wants on the phone for emergencies.
class _Switches extends ConsumerStatefulWidget {
  const _Switches();
  @override
  ConsumerState<_Switches> createState() => _SwitchesState();
}

class _SwitchesState extends ConsumerState<_Switches> {
  /// Optimistic values while a change is on its way.
  final Map<String, bool> _pending = {};

  /// Switches with a change on its way: locked, so a second tap can't send the
  /// opposite value and leave the server and the screen disagreeing.
  final Set<String> _saving = {};

  Future<void> _set(String key, bool value, {required String path, required Map<String, dynamic> body, String? confirmText}) async {
    if (_saving.contains(key)) return;
    final l = context.l;
    _saving.add(key);
    try {
      if (confirmText != null && !await confirm(context, title: confirmText, action: value ? l.turnOn : l.turnOff, danger: true)) return;
      if (!mounted) return;
      final api = ref.read(apiProvider), container = context.container;
      setState(() => _pending[key] = value);
      final ok = await runOk(context, () => api.put(path, data: body), success: l.saved);
      container
        ..invalidate(cachedProvider(path))
        ..invalidate(cachedProvider('mobile/config'));
      if (!ok && mounted) setState(() => _pending.remove(key));
    } finally {
      _saving.remove(key);
      if (mounted) setState(() {});
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l;
    final mobile = ref.watch(cachedProvider('super/mobile-app'));
    final settings = ref.watch(cachedProvider('super/settings'));
    final m = mobile.value == null ? null : J.data(mobile.value!.data);
    final st = settings.value == null ? null : J.data(settings.value!.data);
    bool? value(String key, bool? server) => _pending[key] ?? server;

    Widget row(String key, String title, String hint, bool? v, ValueChanged<bool> onChanged) => SwitchListTile(
      key: Key('sa-$key'),
      title: Text(title),
      subtitle: Text(hint),
      value: v ?? false,
      onChanged: v == null || _saving.contains(key) ? null : onChanged,
    );

    final maintenance = value('maintenance', m?.bN('maintenance'));
    final signup = value('signup_open', st?.bN('signup_open'));
    final push = value('push', m?.o('app_features').bN('push'));
    final saSignIn = value('super_admin_sign_in', m?.bN('super_admin_sign_in'));
    return Group(
      children: [
        row(
          'maintenance',
          l.saMaintenance,
          l.saMaintenanceHint,
          maintenance,
          (v) => _set('maintenance', v, path: 'super/mobile-app', body: {'maintenance': v}, confirmText: v ? l.saMaintenanceConfirm : null),
        ),
        row('signup_open', l.saSignup, l.saSignupHint, signup, (v) => _set('signup_open', v, path: 'super/settings', body: {'signup_open': v})),
        row(
          'push',
          l.saPush,
          l.saPushHint,
          push,
          (v) => _set(
            'push',
            v,
            path: 'super/mobile-app',
            body: {
              'app_features': {'push': v},
            },
          ),
        ),
        row(
          'super_admin_sign_in',
          l.saSaSignIn,
          l.saSaSignInHint,
          saSignIn,
          (v) => _set('super_admin_sign_in', v, path: 'super/mobile-app', body: {'super_admin_sign_in': v}, confirmText: v ? null : l.saSaSignInConfirm),
        ),
      ],
    );
  }
}
