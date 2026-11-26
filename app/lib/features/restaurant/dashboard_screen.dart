import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../core/auth/permissions.dart';
import '../../core/cache/swr.dart';
import '../../core/providers.dart';
import '../../core/util/files.dart';
import '../../core/util/json.dart';
import '../../core/widgets/auth_image.dart';
import '../../core/widgets/kit.dart';
import '../help/tour.dart';
import 'quick_edit.dart';
import 'restaurant_scaffold.dart';

/// Restaurant Home (redesign 2026-11): today's numbers, the quick actions a
/// restaurant admin uses most, then the web admin's widgets (setup, 7-day
/// trend, missing photos, plan, platform news) and shortcuts.
class DashboardScreen extends ConsumerWidget {
  const DashboardScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final tenant = ref.watch(tenantProvider)!;
    final can = ref.watch(canProvider);
    final l = context.l;
    final t = tenantPath(tenant.id);
    maybeShowAppTour(context, ref);
    final paths = [
      '${t}dashboard',
      '${t}widgets/trend',
      if (can.manageRestaurant) '${t}widgets/setup',
      if (can.manageMenu) '${t}widgets/missing-photos',
      if (can.viewBilling) '${t}widgets/plan',
      '${t}announcements',
    ];
    final date = DateFormat.MMMEd(Localizations.localeOf(context).toString()).format(ref.watch(todayProvider));
    return RestaurantScaffold.large(
      title: tenant.name,
      subtitle: l.homeTodaySubtitle(date),
      actions: [IconButton(tooltip: l.actionShareMenu, icon: const Icon(AppIcons.share2), onPressed: () => showMyMenuSheet(context, ref))],
      onRefresh: () => ref.refreshPaths(paths),
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: AppTokens.pad),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              // Owners and Managers without two-step sign-in: strongly encouraged (owner, 2026-11-14; M17).
              if (can.manageRestaurant) const TwoFactorCard(),
              _TodayCard(path: '${t}dashboard', tenantId: tenant.id),
              const SizedBox(height: 12),
              const QuickActions(),
              const SizedBox(height: 12),
              if (can.manageRestaurant) _SetupCard(path: '${t}widgets/setup', tenantId: tenant.id),
              _TrendCard(path: '${t}widgets/trend'),
              if (can.manageMenu) _MissingPhotos(path: '${t}widgets/missing-photos', tenantId: tenant.id),
              if (can.viewBilling) _PlanCard(path: '${t}widgets/plan', tenantId: tenant.id),
              _News(path: '${t}announcements', tenantId: tenant.id),
            ],
          ),
        ),
        const _ManageList(),
      ],
    );
  }
}

/// "Today" on Home (a provider so goldens can fix the date).
final todayProvider = Provider<DateTime>((ref) => DateTime.now());

/// Today's views, QR scans and sold-out count, with yesterday for comparison.
class _TodayCard extends ConsumerWidget {
  const _TodayCard({required this.path, required this.tenantId});
  final String path;
  final int tenantId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final can = ref.watch(canProvider);
    final v = ref.watch(cachedProvider(path));
    final d = v.value == null ? null : J.data(v.value!.data);
    final t = context.tokens;
    final l = context.l;
    Widget stat(String label, int? value, {String? hint, VoidCallback? onTap, Color? color}) => Expanded(
      child: InkWell(
        borderRadius: BorderRadius.circular(AppTokens.radiusMd),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 4, horizontal: 4),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(label, style: TextStyle(fontSize: 13, color: t.mutedForeground), maxLines: 2),
              const SizedBox(height: 4),
              value == null
                  ? const SkeletonBox(width: 48, height: 28)
                  : Text(
                      '$value',
                      style: TextStyle(fontSize: 26, fontWeight: FontWeight.w700, letterSpacing: -0.5, color: color ?? t.foreground),
                    ),
              if (hint != null) Text(hint, style: TextStyle(fontSize: 12, color: t.mutedForeground)),
            ],
          ),
        ),
      ),
    );
    final insights = can.viewInsights ? () => context.push('/r/$tenantId/insights') : null;
    final soldOut = d?.list('sold_out').length;
    return AppCard(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          stat(l.viewsToday, d?.o('today').i('views'), hint: d == null ? null : l.vsYesterday(d.o('yesterday').i('views')), onTap: insights),
          stat(l.scansToday, d?.o('today').i('scans'), hint: d == null ? null : l.vsYesterday(d.o('yesterday').i('scans')), onTap: insights),
          if (can.soldOut) stat(l.soldOutNow, soldOut, color: (soldOut ?? 0) > 0 ? t.destructive : null, onTap: () => context.push('/r/$tenantId/sold-out')),
        ],
      ),
    );
  }
}

/// The top jobs, one tap each (redesign 2026-11): change a price, hide/show a
/// dish, add a dish with a photo, share the menu, sold out, promotions.
class QuickActions extends ConsumerWidget {
  const QuickActions({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final tenant = ref.watch(tenantProvider)!;
    final can = ref.watch(canProvider);
    final l = context.l;
    final id = tenant.id;
    final actions = <(IconData, String, VoidCallback)>[
      if (can.editPrices) (AppIcons.tag, l.actionChangePrice, () => changePriceFlow(context, ref)),
      if (can.manageMenu) (AppIcons.eyeOff, l.actionHideDish, () => showVisibilitySheet(context)),
      if (can.manageMenu && can.writable) (AppIcons.imagePlus, l.actionAddDish, () => context.push('/r/$id/dishes/new?photo=1')),
      (AppIcons.qrCode, l.actionShareMenu, () => showMyMenuSheet(context, ref)),
      if (can.toggleSoldOut) (AppIcons.circleMinus, l.actionSoldOut, () => context.push('/r/$id/sold-out')),
      if (can.manageRestaurant && can.featureOn('promotions')) (AppIcons.megaphone, l.promotions, () => context.push('/r/$id/promotions')),
    ];
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Padding(
          padding: const EdgeInsets.only(bottom: 8, top: 4),
          child: Semantics(
            header: true,
            child: Text(l.quickActions, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 15)),
          ),
        ),
        LayoutBuilder(
          builder: (context, c) {
            final cols = c.maxWidth >= 600 ? 3 : 2;
            final w = (c.maxWidth - 8 * (cols - 1)) / cols;
            return Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                for (final (icon, label, onTap) in actions)
                  SizedBox(
                    width: w,
                    child: AppCard(
                      padding: const EdgeInsets.all(12),
                      onTap: onTap,
                      child: ConstrainedBox(
                        constraints: const BoxConstraints(minHeight: 48),
                        child: Row(
                          children: [
                            Container(
                              width: 36,
                              height: 36,
                              decoration: BoxDecoration(color: context.tokens.muted, borderRadius: BorderRadius.circular(AppTokens.radiusMd)),
                              child: Icon(icon, size: 18),
                            ),
                            const SizedBox(width: 10),
                            Expanded(
                              child: Text(label, style: const TextStyle(fontWeight: FontWeight.w500, fontSize: 14)),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
              ],
            );
          },
        ),
      ],
    );
  }
}

/// Shortcuts to the rest: menu, My order, branches, QR codes, settings.
class _ManageList extends ConsumerWidget {
  const _ManageList();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final tenant = ref.watch(tenantProvider)!;
    final can = ref.watch(canProvider);
    final l = context.l;
    final id = tenant.id;
    ListTile tile(IconData icon, String title, VoidCallback onTap) =>
        ListTile(leading: Icon(icon), title: Text(title), trailing: const Icon(AppIcons.chevronRight, size: 18), onTap: onTap);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        SectionHeader(l.manageSection),
        Group(
          children: [
            tile(AppIcons.utensils, l.navMenu, () => context.go('/r/$id/menu')),
            if (ordersTabOn(can)) tile(AppIcons.clipboardList, l.myOrderTitle, () => context.go('/r/$id/orders')),
            if (can.manageRestaurant) tile(AppIcons.store, l.branches, () => context.push('/r/$id/branches')),
            if (can.manageRestaurant) tile(AppIcons.qrCode, l.qrCodes, () => context.push('/r/$id/qr')),
            tile(AppIcons.settings, l.settings, () => context.push('/account/app')),
          ],
        ),
      ],
    );
  }
}

/// "Orders" tab: only when the plan has "My order" lists (QUESTIONS: order_list).
bool ordersTabOn(Can can) => can.manageRestaurant && can.feature('order_list');

/// A widget card that loads its own answer and hides itself on errors
/// (one failing widget never blanks the dashboard).
class _WidgetCard extends ConsumerWidget {
  const _WidgetCard({required this.path, required this.builder});
  final String path;
  final Widget? Function(BuildContext, J) builder;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final v = ref.watch(cachedProvider(path));
    return v.when(
      skipLoadingOnRefresh: true,
      loading: () => Padding(
        padding: const EdgeInsets.only(bottom: 12),
        child: Container(
          height: 96,
          decoration: BoxDecoration(color: context.colors.surfaceContainerHigh, borderRadius: BorderRadius.circular(12)),
        ),
      ),
      error: (_, _) => const SizedBox.shrink(),
      data: (c) {
        final w = builder(context, J.data(c.data));
        return w == null ? const SizedBox.shrink() : Padding(padding: const EdgeInsets.only(bottom: 12), child: w);
      },
    );
  }
}

class _SetupCard extends ConsumerWidget {
  const _SetupCard({required this.path, required this.tenantId});
  final String path;
  final int tenantId;

  static String _route(String key, int t) => switch (key) {
    'logo' || 'banner' => '/r/$t/appearance',
    'dishes' || 'photos' => '/r/$t/menu',
    'hours' || 'info' => '/r/$t/info',
    'qr' => '/r/$t/qr',
    _ => '/r/$t',
  };

  String _label(BuildContext context, String key) => switch (key) {
    'logo' => context.l.setup_logo,
    'banner' => context.l.setup_banner,
    'dishes' => context.l.setup_dishes,
    'photos' => context.l.setup_photos,
    'hours' => context.l.setup_hours,
    'info' => context.l.setup_info,
    'qr' => context.l.setup_qr,
    _ => key,
  };

  @override
  Widget build(BuildContext context, WidgetRef ref) => _WidgetCard(
    path: path,
    builder: (context, d) {
      if (d.b('complete') || d.b('hidden')) return null;
      final steps = d.list('steps');
      final done = steps.where((s) => s.b('done')).length;
      return Card(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(context.l.setupTitle, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 15)),
                  ),
                  Text(context.l.setupProgress(done, steps.length), style: TextStyle(color: context.colors.onSurfaceVariant, fontSize: 13)),
                ],
              ),
              const SizedBox(height: 10),
              ClipRRect(
                borderRadius: BorderRadius.circular(3),
                child: LinearProgressIndicator(value: d.i('percent') / 100, minHeight: 6, backgroundColor: context.colors.outlineVariant),
              ),
              const SizedBox(height: 6),
              for (final s in steps.where((s) => !s.b('done')).take(3))
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: Icon(AppIcons.circle, color: context.colors.outline),
                  title: Text(_label(context, s.s('key'))),
                  trailing: const Icon(AppIcons.chevronRight),
                  onTap: () => context.go(_route(s.s('key'), tenantId)),
                ),
            ],
          ),
        ),
      );
    },
  );
}

class _TrendCard extends ConsumerWidget {
  const _TrendCard({required this.path});
  final String path;

  @override
  Widget build(BuildContext context, WidgetRef ref) => _WidgetCard(
    path: path,
    builder: (context, d) {
      final days = d.list('days');
      final week = days.fold<int>(0, (a, x) => a + x.i('views'));
      final max = days.fold<int>(1, (a, x) => x.i('views') > a ? x.i('views') : a);
      return Column(
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Wrap(
                    alignment: WrapAlignment.spaceBetween,
                    spacing: 8,
                    children: [
                      Text(context.l.viewsChart, style: const TextStyle(fontWeight: FontWeight.w600)),
                      Text(context.l.weekViews(week), style: TextStyle(fontSize: 13, color: context.colors.onSurfaceVariant)),
                    ],
                  ),
                  const SizedBox(height: 12),
                  SizedBox(
                    height: 110,
                    child: Semantics(
                      label: '${context.l.viewsChart}: ${days.map((x) => x.i('views')).join(', ')}',
                      child: BarChart(
                        BarChartData(
                          maxY: max * 1.1,
                          gridData: const FlGridData(show: false),
                          borderData: FlBorderData(show: false),
                          titlesData: const FlTitlesData(show: false),
                          barTouchData: BarTouchData(enabled: true),
                          barGroups: [
                            for (var i = 0; i < days.length; i++)
                              BarChartGroupData(
                                x: i,
                                barRods: [
                                  BarChartRodData(
                                    toY: days[i].i('views').toDouble(),
                                    width: 18,
                                    color: i == days.length - 1 ? context.colors.primary : context.colors.outline,
                                    borderRadius: const BorderRadius.vertical(top: Radius.circular(4)),
                                  ),
                                ],
                              ),
                          ],
                        ),
                        duration: context.reduceMotion ? Duration.zero : const Duration(milliseconds: 250),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      );
    },
  );
}

class _MissingPhotos extends ConsumerWidget {
  const _MissingPhotos({required this.path, required this.tenantId});
  final String path;
  final int tenantId;

  @override
  Widget build(BuildContext context, WidgetRef ref) => _WidgetCard(
    path: path,
    builder: (context, d) {
      if (!d.b('enabled') || d.i('total') == 0) return null;
      return Card(
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 8),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 4),
                child: Text(context.l.missingPhotosTitle, style: const TextStyle(fontWeight: FontWeight.w600)),
              ),
              for (final i in d.list('items').take(4))
                ListTile(
                  leading: const Icon(AppIcons.imagePlus),
                  title: Text(i.t('name')),
                  trailing: Text('${i.i('views')}'),
                  onTap: () => context.push('/r/$tenantId/dishes/${i.i('id')}'),
                ),
              if (d.i('total') > 4)
                Padding(
                  padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
                  child: Text(context.l.missingPhotosMore(d.i('total')), style: TextStyle(color: context.colors.onSurfaceVariant, fontSize: 13)),
                ),
            ],
          ),
        ),
      );
    },
  );
}

class _PlanCard extends ConsumerWidget {
  const _PlanCard({required this.path, required this.tenantId});
  final String path;
  final int tenantId;

  String _usage(BuildContext context, String key) => switch (key) {
    'items' => context.l.usage_items,
    'photos' => context.l.usage_photos,
    'branches' => context.l.usage_branches,
    _ => key,
  };

  @override
  Widget build(BuildContext context, WidgetRef ref) => _WidgetCard(
    path: path,
    builder: (context, d) {
      final days = d.iN('days_left');
      final next = d.o('next');
      return Card(
        child: InkWell(
          borderRadius: BorderRadius.circular(12),
          onTap: () => context.push('/r/$tenantId/billing'),
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        [tr(d['plan']), if (d.b('trial')) context.l.planTrial].where((s) => s.isNotEmpty).join(' · '),
                        style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 15),
                      ),
                    ),
                    if (days != null)
                      Text(
                        days == 0 ? context.l.planEnded : context.l.planEndsIn(days),
                        style: TextStyle(fontSize: 13, color: days <= 7 ? context.colors.error : context.colors.onSurfaceVariant),
                      ),
                  ],
                ),
                const SizedBox(height: 10),
                for (final u in d.list('usage'))
                  Padding(
                    padding: const EdgeInsets.only(bottom: 8),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Expanded(child: Text(_usage(context, u.s('key')), style: const TextStyle(fontSize: 13))),
                            Text(
                              u.iN('limit') == null ? '${u.i('used')} · ${context.l.unlimited}' : '${u.i('used')} / ${u.i('limit')}',
                              style: const TextStyle(fontSize: 13),
                            ),
                          ],
                        ),
                        if (u.iN('limit') != null) ...[
                          const SizedBox(height: 4),
                          LinearProgressIndicator(value: (u.i('used') / (u.i('limit') == 0 ? 1 : u.i('limit'))).clamp(0, 1).toDouble(), minHeight: 4),
                        ],
                      ],
                    ),
                  ),
                if (next.has('name')) ...[
                  const SizedBox(height: 4),
                  OutlinedButton.icon(
                    onPressed: () => showUpgradeSheet(context, next.list('adds').isEmpty ? null : (next['adds'] as List).join(' · ')),
                    icon: const Icon(AppIcons.arrowUp, size: 18),
                    label: Text(context.l.upgradeTo(next.t('name'))),
                  ),
                ],
              ],
            ),
          ),
        ),
      );
    },
  );
}

class _News extends ConsumerWidget {
  const _News({required this.path, required this.tenantId});
  final String path;
  final int tenantId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final v = ref.watch(cachedProvider(path)).value;
    final rows = v == null ? <J>[] : J.dataList(v.data);
    if (rows.isEmpty) return const SizedBox.shrink();
    return Card(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 4),
            child: Text(context.l.newsTitle, style: const TextStyle(fontWeight: FontWeight.w600)),
          ),
          for (final a in rows.take(3))
            ListTile(
              leading: Icon(a.s('level') == 'warning' ? AppIcons.triangleAlert : AppIcons.megaphone),
              title: Text(a.t('title')),
              subtitle: a.t('body').isEmpty ? null : Text(a.t('body'), maxLines: 2, overflow: TextOverflow.ellipsis),
              onTap: () => context.push('/r/$tenantId/news/${a.i('id')}'),
            ),
        ],
      ),
    );
  }
}

/// "Open my menu": the public link and its QR code, to show or share.
Future<void> showMyMenuSheet(BuildContext context, WidgetRef ref) {
  final tenant = ref.read(tenantProvider)!;
  final url = '${ref.read(apiProvider).siteUrl}${tenant.slug}';
  return showModalBottomSheet<void>(
    context: context,
    showDragHandle: true,
    isScrollControlled: true,
    builder: (c) => SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(20, 0, 20, 20),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(c.l.menuLink, style: c.text.titleMedium?.copyWith(fontWeight: FontWeight.w600)),
            const SizedBox(height: 12),
            Consumer(
              builder: (c, ref, _) {
                final codes = ref.watch(cachedProvider(tenantPath(tenant.id, 'qr-codes'))).value;
                final restaurant = codes == null ? null : J.dataList(codes.data).where((q) => q.s('kind') == 'restaurant').firstOrNull;
                return restaurant == null
                    ? const SizedBox(height: 8)
                    : Container(
                        color: Colors.white,
                        padding: const EdgeInsets.all(8),
                        child: AuthImage(tenantPath(tenant.id, 'qr-codes/${restaurant.i('id')}.png'), size: 200, semanticLabel: c.l.showQr),
                      );
              },
            ),
            const SizedBox(height: 12),
            SelectableText(url, textAlign: TextAlign.center),
            const SizedBox(height: 16),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: () {
                      Clipboard.setData(ClipboardData(text: url));
                      showSnack(c, c.l.copied);
                    },
                    icon: const Icon(AppIcons.copy),
                    label: Text(c.l.copyLink),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: FilledButton.icon(
                    onPressed: () => shareText(url, subject: tenant.name),
                    icon: const Icon(AppIcons.share2),
                    label: Text(c.l.share),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            TextButton(onPressed: () => openExternal(c, url), child: Text(c.l.openMyMenu)),
          ],
        ),
      ),
    ),
  );
}

/// "Protect your restaurant": until two-step sign-in is on. One tap starts the setup.
class TwoFactorCard extends ConsumerWidget {
  const TwoFactorCard({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final s = ref.watch(sessionProvider);
    if (s.impersonating || (s.me?.twoFactorEnabled ?? true)) return const SizedBox.shrink();
    final l = context.l;
    final t = context.tokens;
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Card(
        color: t.warningSurface,
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Icon(AppIcons.shieldCheck, color: t.warning),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      l.twoFactorCardTitle,
                      style: context.text.titleSmall?.copyWith(fontWeight: FontWeight.w700, color: t.foreground),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 6),
              Text(l.twoFactorCardText, style: TextStyle(color: t.foreground)),
              const SizedBox(height: 10),
              FilledButton(onPressed: () => context.push('/account/two-factor?setup=1'), child: Text(l.twoFactorCardAction)),
            ],
          ),
        ),
      ),
    );
  }
}
