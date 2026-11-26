import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/cache/swr.dart';
import '../../core/providers.dart';
import '../../core/util/json.dart';
import '../../core/widgets/kit.dart';
import 'restaurant_scaffold.dart';

/// "Orders" tab: what guests put on their "My order" list (the list they show
/// the waiter) — lists shown, average value and most-added dishes.
class OrdersScreen extends ConsumerStatefulWidget {
  const OrdersScreen({super.key});
  @override
  ConsumerState<OrdersScreen> createState() => _OrdersScreenState();
}

class _OrdersScreenState extends ConsumerState<OrdersScreen> {
  var _days = '7';

  @override
  Widget build(BuildContext context) {
    final tenant = ref.watch(tenantProvider)!;
    final path = tenantPath(tenant.id, 'widgets/orders');
    final v = ref.watch(cachedProvider(path));
    final l = context.l;
    final t = context.tokens;
    final d = v.value == null ? null : J.data(v.value!.data);
    final List<Widget> body;
    if (v.hasError && d == null) {
      body = [ErrorView(error: v.error!, onRetry: () => ref.refreshPath(path))];
    } else if (d == null) {
      body = [const SizedBox(height: 400, child: Skeleton(lines: 4))];
    } else if (!d.b('enabled')) {
      body = [
        EmptyState(
          icon: AppIcons.clipboardList,
          title: l.ordersOff,
          action: AppButton(label: l.ordersTurnOn, variant: ButtonVariant.outline, onPressed: () => context.push('/r/${tenant.id}/appearance')),
        ),
      ];
    } else {
      final p = d.o('periods').o(_days);
      final currency = d.s('currency', tenant.currency);
      body = [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: AppTokens.pad),
          child: SegmentedButton<String>(
            showSelectedIcon: false,
            segments: [
              ButtonSegment(value: '7', label: Text(l.days7)),
              ButtonSegment(value: '30', label: Text(l.days30)),
            ],
            selected: {_days},
            onSelectionChanged: (s) => setState(() => _days = s.single),
          ),
        ),
        const SizedBox(height: 12),
        if (p.i('lists_shown') == 0 && p.list('top').isEmpty)
          EmptyState(icon: AppIcons.clipboardList, title: l.ordersEmpty)
        else ...[
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: AppTokens.pad),
            child: Row(
              children: [
                Expanded(
                  child: StatTile(label: l.listsShown, value: '${p.i('lists_shown')}'),
                ),
                if (p.iN('average_minor') != null) ...[
                  const SizedBox(width: 12),
                  Expanded(
                    child: StatTile(label: l.averageList, value: formatMoney(p.i('average_minor'), currency)),
                  ),
                ],
              ],
            ),
          ),
          if (p.list('top').isNotEmpty) ...[
            SectionHeader(l.myOrderTop),
            Group(
              children: [
                for (final x in p.list('top'))
                  ListTile(
                    title: Text(x.t('name')),
                    trailing: Text(
                      '${x.i('added')}',
                      style: TextStyle(fontWeight: FontWeight.w600, color: t.foreground),
                    ),
                    onTap: () => context.push('/r/${tenant.id}/dishes/${x.i('id')}'),
                  ),
              ],
            ),
          ],
        ],
      ];
    }
    return RestaurantScaffold.large(title: l.navOrders, onRefresh: () => ref.refreshPath(path), children: body);
  }
}
