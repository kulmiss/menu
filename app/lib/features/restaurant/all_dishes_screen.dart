import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/cache/swr.dart';
import '../../core/providers.dart';
import '../../core/util/files.dart';
import '../../core/util/json.dart';
import '../../core/widgets/kit.dart';
import '../../core/widgets/net_image.dart';
import '../../core/widgets/paged_list.dart';
import 'menu_data.dart';

/// Every dish of every menu: search, filters, sort, multi-select bulk actions.
class AllDishesScreen extends ConsumerStatefulWidget {
  const AllDishesScreen({super.key});
  @override
  ConsumerState<AllDishesScreen> createState() => _AllDishesScreenState();
}

class _AllDishesScreenState extends ConsumerState<AllDishesScreen> {
  String _q = '';
  String? _status;
  String _sort = 'category';
  final Set<int> _selected = {};
  final _list = GlobalKey<PagedListState>();

  Map<String, String> get _query => {if (_q.isNotEmpty) 'q': _q, 'status': ?_status, 'sort': _sort};

  Future<void> _bulk(String action, {Map<String, dynamic> extra = const {}}) async {
    final t = ref.read(tenantProvider)!;
    final api = ref.read(apiProvider);
    final container = context.container;
    final ok = await runOk(
      context,
      () => api.post(
        tenantPath(t.id, action == 'price' ? 'items/bulk-price' : 'items/bulk'),
        data: {'ids': _selected.toList(), if (action != 'price') 'action': action, ...extra},
      ),
      success: context.l.saved,
    );
    if (!ok) return;
    container.read(soldOutProvider.notifier).clear();
    // Reloads this list (all its pages) and every other place that shows dishes.
    await dishesChanged(container, t.id);
    if (mounted) setState(_selected.clear);
  }

  Future<void> _priceDialog() async {
    final pct = TextEditingController();
    var round = 0;
    final l = context.l;
    final go = await showDialog<bool>(
      context: context,
      builder: (c) => StatefulBuilder(
        builder: (c, set) => AlertDialog(
          title: Text(l.bulkPriceTitle),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(
                controller: pct,
                autofocus: true,
                keyboardType: const TextInputType.numberWithOptions(signed: true, decimal: true),
                inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[-0-9.]'))],
                decoration: InputDecoration(labelText: l.bulkPricePercent, helperText: l.bulkPriceHint, suffixText: '%'),
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<int>(
                isExpanded: true,
                initialValue: round,
                decoration: InputDecoration(labelText: l.bulkPriceRound),
                items: [
                  DropdownMenuItem(value: 0, child: Text(l.noRounding)),
                  for (final v in [100, 500, 1000]) DropdownMenuItem(value: v, child: Text(formatMoney(v, ''))),
                ],
                onChanged: (v) => set(() => round = v ?? 0),
              ),
            ],
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(c, false), child: Text(l.cancel)),
            FilledButton(onPressed: () => Navigator.pop(c, true), child: Text(l.save)),
          ],
        ),
      ),
    );
    final v = double.tryParse(pct.text);
    if (go == true && v != null && v != 0) await _bulk('price', extra: {'percent': v, if (round > 0) 'round_to': round});
  }

  Future<void> _move() async {
    final t = ref.read(tenantProvider)!;
    final api = ref.read(apiProvider);
    final menus = J.dataList(await api.get(tenantPath(t.id, 'menus')));
    final cats = <(String, int)>[];
    for (final m in menus) {
      final tree = J.data(await api.get(tenantPath(t.id, 'menus/${m.i('id')}/tree')));
      for (final c in tree.list('categories')) {
        cats.add(('${m.t('name')} › ${c.t('name')}', c.i('id')));
      }
    }
    if (!mounted) return;
    final id = await showModalBottomSheet<int>(
      context: context,
      showDragHandle: true,
      builder: (c) => ListView(
        children: [for (final (name, id) in cats) ListTile(title: Text(name), onTap: () => Navigator.pop(c, id))],
      ),
    );
    if (id != null) await _bulk('move', extra: {'category_id': id});
  }

  @override
  Widget build(BuildContext context) {
    final t = ref.watch(tenantProvider)!;
    final can = ref.watch(canProvider);
    final l = context.l;
    final selecting = _selected.isNotEmpty;
    final filters = {
      null: l.filterAll,
      if (can.soldOut) 'sold_out': l.filterSoldOut,
      'hidden': l.filterHidden,
      'no_photo': l.filterNoPhoto,
      'new': l.filterNew,
      'popular': l.filterPopular,
    };
    return Scaffold(
      appBar: AppBar(
        leading: selecting ? IconButton(tooltip: l.close, icon: const Icon(AppIcons.x), onPressed: () => setState(_selected.clear)) : null,
        title: Text(selecting ? l.selectedCount(_selected.length) : l.allDishes),
        actions: [
          if (!selecting) ...[
            PopupMenuButton<String>(
              tooltip: l.sortBy,
              icon: const Icon(AppIcons.arrowDownWideNarrow),
              initialValue: _sort,
              onSelected: (v) => setState(() => _sort = v),
              itemBuilder: (c) => [
                PopupMenuItem(value: 'category', child: Text(l.sortCategory)),
                PopupMenuItem(value: 'name', child: Text(l.sortName)),
                PopupMenuItem(value: 'price', child: Text(l.sortPrice)),
                PopupMenuItem(value: '-updated_at', child: Text(l.sortUpdated)),
              ],
            ),
            if (can.exportDishes)
              IconButton(
                tooltip: l.exportCsv,
                icon: const Icon(AppIcons.download),
                onPressed: () =>
                    downloadAndOpen(context, ref, tenantPath(t.id, 'items'), '${t.slug}-dishes.csv', share: true, query: {..._query, 'format': 'csv'}),
              ),
          ] else
            PopupMenuButton<String>(
              tooltip: l.more,
              onSelected: (v) async {
                switch (v) {
                  case 'price':
                    await _priceDialog();
                  case 'move':
                    await _move();
                  case 'delete':
                    if (await confirm(context, title: l.bulkDeleteTitle(_selected.length), body: l.confirmDeleteBody, action: l.delete, danger: true)) {
                      await _bulk('delete');
                    }
                  default:
                    await _bulk(v);
                }
              },
              itemBuilder: (c) => [
                if (can.toggleSoldOut) ...[
                  PopupMenuItem(value: 'sold_out', child: Text(l.bulkSoldOut)),
                  PopupMenuItem(value: 'available', child: Text(l.bulkAvailable)),
                ],
                if (can.manageMenu) ...[
                  PopupMenuItem(value: 'hide', child: Text(l.bulkHide)),
                  PopupMenuItem(value: 'show', child: Text(l.bulkShow)),
                  PopupMenuItem(value: 'move', child: Text(l.bulkMove)),
                  if (can.editPrices) PopupMenuItem(value: 'price', child: Text(l.bulkPrice)),
                  PopupMenuItem(
                    value: 'delete',
                    child: Text(l.bulkDelete, style: TextStyle(color: c.colors.error)),
                  ),
                ],
              ],
            ),
        ],
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
            child: SearchField(onChanged: (v) => setState(() => _q = v)),
          ),
          SizedBox(
            height: 52,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
              children: [
                for (final e in filters.entries)
                  Padding(
                    padding: const EdgeInsets.only(right: 8),
                    child: ChoiceChip(label: Text(e.value), selected: _status == e.key, onSelected: (_) => setState(() => _status = e.key)),
                  ),
              ],
            ),
          ),
          Expanded(
            child: PagedList(
              key: _list,
              path: tenantPath(t.id, 'items'),
              query: _query,
              empty: EmptyState(icon: AppIcons.searchX, title: l.noMatches),
              itemBuilder: (context, row, _) {
                final id = row.i('id');
                final sel = _selected.contains(id);
                final price = row.iN('price_minor') != null
                    ? formatMoney(row.i('price_minor'), t.currency)
                    : (row.iN('from_minor') != null ? l.priceFrom(formatMoney(row.i('from_minor'), t.currency)) : '');
                final sold = can.soldOut && (ref.watch(soldOutProvider)[id] ?? row.b('is_sold_out'));
                return ListTile(
                  selected: sel,
                  leading: selecting
                      ? Checkbox(value: sel, onChanged: (_) => setState(() => sel ? _selected.remove(id) : _selected.add(id)))
                      : NetImage(row.sn('photo'), size: 44),
                  title: Text(row.t('name')),
                  subtitle: Text(
                    [row.o('menu').t('name'), row.o('category').t('name'), if (sold) l.soldOut, if (!row.b('is_visible', true)) l.hiddenFromGuests].join(' · '),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  trailing: Text(price),
                  onLongPress: () => setState(() => _selected.add(id)),
                  onTap: () => selecting ? setState(() => sel ? _selected.remove(id) : _selected.add(id)) : context.push('/r/${t.id}/dishes/$id'),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}
