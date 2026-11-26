import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/providers.dart';
import '../../core/util/json.dart';
import '../../core/widgets/kit.dart';
import '../../core/widgets/paged_list.dart';
import 'menu_data.dart';
import 'restaurant_scaffold.dart';

/// Big, one-tap sold-out list for busy service (every role can use it).
class SoldOutScreen extends ConsumerStatefulWidget {
  const SoldOutScreen({super.key});
  @override
  ConsumerState<SoldOutScreen> createState() => _SoldOutScreenState();
}

class _SoldOutScreenState extends ConsumerState<SoldOutScreen> {
  bool _all = false;
  String _q = '';

  @override
  Widget build(BuildContext context) {
    final t = ref.watch(tenantProvider)!;
    final overrides = ref.watch(soldOutProvider);
    final l = context.l;
    // Switched off for this restaurant, its plan or everyone (backend #191/#192).
    if (!ref.watch(canProvider).soldOut) {
      return RestaurantScaffold(
        title: l.navSoldOut,
        body: EmptyState(icon: AppIcons.circleOff, title: l.soldOutOffTitle, body: l.soldOutOffBody),
      );
    }
    return RestaurantScaffold(
      title: l.navSoldOut,
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
            child: SegmentedButton<bool>(
              showSelectedIcon: false,
              segments: [
                ButtonSegment(value: false, label: Text(l.soldOutTab)),
                ButtonSegment(value: true, label: Text(l.allDishesTab)),
              ],
              selected: {_all},
              onSelectionChanged: (s) => setState(() => _all = s.first),
              style: const ButtonStyle(minimumSize: WidgetStatePropertyAll(Size(0, 48))),
            ),
          ),
          if (_all)
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
              child: SearchField(onChanged: (v) => setState(() => _q = v)),
            ),
          Expanded(
            child: PagedList(
              key: ValueKey('$_all|$_q'),
              path: tenantPath(t.id, 'items'),
              perPage: 100,
              separated: false,
              query: {if (!_all) 'status': 'sold_out', if (_q.isNotEmpty) 'q': _q, 'sort': 'name'},
              header: _all
                  ? null
                  : Padding(
                      padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
                      child: Text(l.soldOutBranchNote, style: TextStyle(fontSize: 13, color: context.colors.onSurfaceVariant)),
                    ),
              empty: EmptyState(icon: AppIcons.circleCheck, title: _all ? l.noMatches : l.soldOutEmpty, body: _all ? null : l.soldOutEmptyBody),
              itemBuilder: (context, row, _) {
                final id = row.i('id');
                final sold = overrides[id] ?? row.b('is_sold_out');
                return _BigRow(row: row, soldOut: sold, onToggle: () => ref.read(soldOutProvider.notifier).set(context, id, !sold));
              },
            ),
          ),
        ],
      ),
    );
  }
}

class _BigRow extends StatelessWidget {
  const _BigRow({required this.row, required this.soldOut, required this.onToggle});
  final J row;
  final bool soldOut;
  final VoidCallback onToggle;

  @override
  Widget build(BuildContext context) {
    final l = context.l;
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 5),
      child: Card(
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(14),
          side: BorderSide(color: soldOut ? context.colors.error.withValues(alpha: .4) : context.colors.outlineVariant),
        ),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
          child: Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(row.t('name'), style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w600)),
                    const SizedBox(height: 2),
                    Text(
                      soldOut ? l.soldOut : '${row.o('category').t('name')} · ${l.available}',
                      style: TextStyle(fontSize: 13, color: soldOut ? context.colors.error : context.colors.onSurfaceVariant),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 12),
              SizedBox(
                width: 132,
                height: 52,
                child: soldOut
                    ? FilledButton(
                        onPressed: onToggle,
                        child: Text(l.availableAgain, textAlign: TextAlign.center),
                      )
                    : OutlinedButton(onPressed: onToggle, child: Text(l.markSoldOut)),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
