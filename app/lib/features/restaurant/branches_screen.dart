import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/cache/swr.dart';
import '../../core/providers.dart';
import '../../core/util/files.dart';
import '../../core/util/json.dart';
import '../../core/widgets/kit.dart';
import 'dish_editor_screen.dart' show minorToText, textToMinor;

/// Branches, their tables, and branch prices / hidden / sold out per branch.
class BranchesScreen extends ConsumerWidget {
  const BranchesScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final t = ref.watch(tenantProvider)!;
    final can = ref.watch(canProvider);
    final path = tenantPath(t.id, 'branches');
    return Scaffold(
      appBar: AppBar(title: Text(context.l.branches)),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => can.feature('branches') ? editBranch(context, ref, null) : showUpgradeSheet(context),
        icon: const Icon(AppIcons.plus),
        label: Text(context.l.newBranch),
      ),
      body: CachedView(
        path: path,
        builder: (context, data, _) => ListView(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 96),
          children: [
            for (final b in J.dataList(data))
              Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: Card(
                  child: ListTile(
                    contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
                    title: Text(b.s('name'), style: const TextStyle(fontWeight: FontWeight.w600)),
                    subtitle: Text(
                      [
                        if (b.b('is_default')) context.l.mainBranch,
                        if (!b.b('is_active', true)) context.l.inactive,
                        context.l.tablesCount(b.i('tables_count')),
                        if (b.s('address').isNotEmpty) b.s('address'),
                      ].join(' · '),
                    ),
                    trailing: const Icon(AppIcons.chevronRight),
                    onTap: () => context.push('/r/${t.id}/branches/${b.i('id')}'),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

Future<void> editBranch(BuildContext context, WidgetRef ref, J? branch) async {
  final l = context.l;
  final name = TextEditingController(text: branch?.s('name'));
  final address = TextEditingController(text: branch?.s('address'));
  final phone = TextEditingController(text: branch?.s('phone'));
  final maps = TextEditingController(text: branch?.s('maps_url'));
  var active = branch?.b('is_active', true) ?? true;
  final ok = await showModalBottomSheet<bool>(
    context: context,
    isScrollControlled: true,
    showDragHandle: true,
    builder: (c) => StatefulBuilder(
      builder: (c, set) => Padding(
        padding: EdgeInsets.fromLTRB(16, 0, 16, 16 + MediaQuery.viewInsetsOf(c).bottom),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(branch == null ? l.newBranch : l.edit, style: c.text.titleMedium?.copyWith(fontWeight: FontWeight.w600)),
            const SizedBox(height: 12),
            TextField(
              controller: name,
              autofocus: branch == null,
              decoration: InputDecoration(labelText: l.branchName),
            ),
            const SizedBox(height: 10),
            TextField(
              controller: address,
              decoration: InputDecoration(labelText: l.address),
            ),
            const SizedBox(height: 10),
            TextField(
              controller: phone,
              keyboardType: TextInputType.phone,
              decoration: InputDecoration(labelText: l.phone),
            ),
            const SizedBox(height: 10),
            TextField(
              controller: maps,
              keyboardType: TextInputType.url,
              decoration: InputDecoration(labelText: l.mapsLink),
            ),
            if (branch != null && !branch.b('is_default'))
              SwitchListTile(contentPadding: EdgeInsets.zero, title: Text(l.active), value: active, onChanged: (v) => set(() => active = v)),
            const SizedBox(height: 12),
            FilledButton(onPressed: () => Navigator.pop(c, true), child: Text(l.save)),
          ],
        ),
      ),
    ),
  );
  if (ok != true || !context.mounted) return;
  final t = ref.read(tenantProvider)!;
  final data = {
    'name': name.text.trim(),
    'address': address.text.trim().isEmpty ? null : address.text.trim(),
    'phone': phone.text.trim().isEmpty ? null : phone.text.trim(),
    'maps_url': maps.text.trim().isEmpty ? null : maps.text.trim(),
    if (branch != null && !branch.b('is_default')) 'is_active': active,
  };
  final api = ref.read(apiProvider), container = context.container;
  await runOk(
    context,
    () => branch == null ? api.post(tenantPath(t.id, 'branches'), data: data) : api.patch(tenantPath(t.id, 'branches/${branch.i('id')}'), data: data),
    success: l.saved,
  );
  container.invalidate(cachedProvider(tenantPath(t.id, 'branches')));
}

class BranchDetailScreen extends ConsumerStatefulWidget {
  const BranchDetailScreen({super.key, required this.branchId});
  final int branchId;
  @override
  ConsumerState<BranchDetailScreen> createState() => _BranchDetailScreenState();
}

class _BranchDetailScreenState extends ConsumerState<BranchDetailScreen> with SingleTickerProviderStateMixin {
  late final _tabs = TabController(length: 2, vsync: this);

  @override
  void dispose() {
    _tabs.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = ref.watch(tenantProvider)!;
    final l = context.l;
    final list = ref.watch(cachedProvider(tenantPath(t.id, 'branches'))).value;
    final b = list == null ? null : J.dataList(list.data).where((x) => x.i('id') == widget.branchId).firstOrNull;
    return Scaffold(
      appBar: AppBar(
        title: Text(b?.s('name') ?? l.branches),
        actions: [
          if (b != null) ...[
            IconButton(tooltip: l.edit, icon: const Icon(AppIcons.pencil), onPressed: () => editBranch(context, ref, b)),
            PopupMenuButton<String>(
              onSelected: (v) async {
                if (v == 'open') await openExternal(context, b.s('url'));
                if (v == 'delete' &&
                    await confirm(context, title: l.confirmDeleteTitle(b.s('name')), body: l.confirmDeleteBody, action: l.delete, danger: true) &&
                    context.mounted) {
                  final api = ref.read(apiProvider), container = context.container;
                  if (await runOk(context, () => api.delete(tenantPath(t.id, 'branches/${b.i('id')}')))) {
                    container.invalidate(cachedProvider(tenantPath(t.id, 'branches')));
                    if (context.mounted) context.pop();
                  }
                }
              },
              itemBuilder: (c) => [
                PopupMenuItem(value: 'open', child: Text(l.openBranchPage)),
                if (!b.b('is_default'))
                  PopupMenuItem(
                    value: 'delete',
                    child: Text(l.delete, style: TextStyle(color: c.colors.error)),
                  ),
              ],
            ),
          ],
        ],
        bottom: TabBar(
          controller: _tabs,
          tabs: [
            Tab(text: l.pricesAvailability),
            Tab(text: l.tables),
          ],
        ),
      ),
      body: TabBarView(
        controller: _tabs,
        children: [
          _BranchPrices(branchId: widget.branchId),
          _Tables(branchId: widget.branchId),
        ],
      ),
    );
  }
}

/// Price per branch (also per size), hidden here, sold out here.
class _BranchPrices extends ConsumerStatefulWidget {
  const _BranchPrices({required this.branchId});
  final int branchId;
  @override
  ConsumerState<_BranchPrices> createState() => _BranchPricesState();
}

class _BranchPricesState extends ConsumerState<_BranchPrices> {
  Map<int, Map<String, dynamic>>? _rows;
  bool _dirty = false;
  bool _busy = false;
  String _q = '';

  Map<String, dynamic> _row(int itemId) =>
      _rows!.putIfAbsent(itemId, () => {'item_id': itemId, 'price_minor': null, 'is_sold_out': null, 'is_hidden': null, 'sizes': <String, int>{}});

  Future<void> _save() async {
    final t = ref.read(tenantProvider)!;
    setState(() => _busy = true);
    final rows = _rows!.values
        .where((r) => r['price_minor'] != null || r['is_sold_out'] == true || r['is_hidden'] == true || (r['sizes'] as Map).isNotEmpty)
        .toList();
    final api = ref.read(apiProvider);
    final ok = await runOk(
      context,
      () => api.put(tenantPath(t.id, 'branches/${widget.branchId}/overrides'), data: {'overrides': rows}),
      success: context.l.saved,
    );
    if (mounted) {
      setState(() {
        _busy = false;
        if (ok) _dirty = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final t = ref.watch(tenantProvider)!;
    final can = ref.watch(canProvider);
    final l = context.l;
    final ov = ref.watch(cachedProvider(tenantPath(t.id, 'branches/${widget.branchId}/overrides')));
    final items = ref.watch(cachedProvider(tenantPath(t.id, 'items?per=100&sort=category${_q.isEmpty ? '' : '&q=${Uri.encodeQueryComponent(_q)}'}')));
    if (ov.value != null && _rows == null) {
      _rows = {
        for (final r in J.dataList(ov.value!.data))
          r.i('item_id'): {
            ...r.m,
            'sizes': {...((r['sizes'] as Map?) ?? {}).map((k, v) => MapEntry('$k', v as int))},
          },
      };
    }
    if (_rows == null || items.value == null) return items.hasError ? ErrorView(error: items.error!) : const Skeleton();
    final dishes = J.dataList(items.value!.data);
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
          child: Row(
            children: [
              Expanded(
                child: TextField(
                  decoration: InputDecoration(prefixIcon: const Icon(AppIcons.search), hintText: l.search, isDense: true),
                  onSubmitted: (v) => setState(() => _q = v.trim()),
                ),
              ),
              const SizedBox(width: 8),
              FilledButton(onPressed: _dirty && !_busy ? _save : null, child: Text(l.save)),
            ],
          ),
        ),
        Expanded(
          child: ListView.separated(
            padding: const EdgeInsets.only(bottom: 40),
            itemCount: dishes.length,
            separatorBuilder: (_, _) => const Divider(height: 1),
            itemBuilder: (context, i) {
              final d = dishes[i];
              final r = _rows![d.i('id')];
              final hidden = r?['is_hidden'] == true, sold = can.soldOut && r?['is_sold_out'] == true;
              final price = r?['price_minor'] as int?;
              return ExpansionTile(
                title: Text(d.t('name')),
                subtitle: Text(
                  [
                    if (price != null) '${l.branchPrice}: ${formatMoney(price, t.currency)}',
                    if ((r?['sizes'] as Map?)?.isNotEmpty ?? false) l.sizes,
                    if (sold) l.soldOutHere,
                    if (hidden) l.hiddenHere,
                  ].join(' · '),
                ),
                childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
                children: [
                  if (can.soldOut)
                    SwitchListTile(
                      contentPadding: EdgeInsets.zero,
                      title: Text(l.soldOutHere),
                      value: sold,
                      onChanged: (v) => setState(() {
                        _row(d.i('id'))['is_sold_out'] = v ? true : null;
                        _dirty = true;
                      }),
                    ),
                  if (can.manageMenu)
                    SwitchListTile(
                      contentPadding: EdgeInsets.zero,
                      title: Text(l.hiddenHere),
                      value: hidden,
                      onChanged: (v) => setState(() {
                        _row(d.i('id'))['is_hidden'] = v ? true : null;
                        _dirty = true;
                      }),
                    ),
                  if (can.editPrices && d.i('sizes') == 0)
                    _PriceField(
                      label: l.branchPrice,
                      hint: '${l.samePrice} (${formatMoney(d.iN('price_minor'), '')})',
                      value: price,
                      onChanged: (v) => setState(() {
                        _row(d.i('id'))['price_minor'] = v;
                        _dirty = true;
                      }),
                    ),
                  if (can.editPrices && d.i('sizes') > 0)
                    _SizePrices(itemId: d.i('id'), sizes: (_row(d.i('id'))['sizes'] as Map<String, int>), onChanged: () => setState(() => _dirty = true)),
                ],
              );
            },
          ),
        ),
      ],
    );
  }
}

class _PriceField extends StatelessWidget {
  const _PriceField({required this.label, required this.hint, required this.value, required this.onChanged});
  final String label;
  final String hint;
  final int? value;
  final ValueChanged<int?> onChanged;

  @override
  Widget build(BuildContext context) => TextFormField(
    initialValue: minorToText(value),
    keyboardType: const TextInputType.numberWithOptions(decimal: true),
    inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[0-9.,]'))],
    decoration: InputDecoration(labelText: label, hintText: hint),
    onChanged: (v) => onChanged(v.trim().isEmpty ? null : textToMinor(v)),
  );
}

class _SizePrices extends ConsumerWidget {
  const _SizePrices({required this.itemId, required this.sizes, required this.onChanged});
  final int itemId;
  final Map<String, int> sizes;
  final VoidCallback onChanged;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final t = ref.watch(tenantProvider)!;
    final item = ref.watch(cachedProvider(tenantPath(t.id, 'items/$itemId'))).value;
    if (item == null) return const LinearProgressIndicator();
    return Column(
      children: [
        for (final v in J.data(item.data).list('variants'))
          Padding(
            padding: const EdgeInsets.only(top: 8),
            child: _PriceField(
              label: v.t('name'),
              hint: '${context.l.samePrice} (${formatMoney(v.i('price_minor'), '')})',
              value: sizes['${v.i('id')}'],
              onChanged: (p) {
                p == null ? sizes.remove('${v.i('id')}') : sizes['${v.i('id')}'] = p;
                onChanged();
              },
            ),
          ),
      ],
    );
  }
}

class _Tables extends ConsumerWidget {
  const _Tables({required this.branchId});
  final int branchId;

  Future<void> _add(BuildContext context, WidgetRef ref) async {
    final l = context.l;
    final from = TextEditingController(text: '1'), to = TextEditingController(text: '10'), prefix = TextEditingController(text: 'Table');
    final ok = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        title: Text(l.addTables),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: prefix,
              decoration: InputDecoration(labelText: l.tablesPrefix),
            ),
            Row(
              children: [
                Expanded(
                  child: TextField(
                    controller: from,
                    keyboardType: TextInputType.number,
                    decoration: InputDecoration(labelText: l.tablesFrom),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: TextField(
                    controller: to,
                    keyboardType: TextInputType.number,
                    decoration: InputDecoration(labelText: l.tablesTo),
                  ),
                ),
              ],
            ),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(c, false), child: Text(l.cancel)),
          FilledButton(onPressed: () => Navigator.pop(c, true), child: Text(l.add)),
        ],
      ),
    );
    if (ok != true || !context.mounted) return;
    final t = ref.read(tenantProvider)!;
    final api = ref.read(apiProvider), container = context.container;
    await runOk(
      context,
      () => api.post(
        tenantPath(t.id, 'branches/$branchId/tables'),
        data: {'prefix': prefix.text.trim(), 'from': int.tryParse(from.text), 'to': int.tryParse(to.text)},
      ),
    );
    container
      ..invalidate(cachedProvider(tenantPath(t.id, 'branches/$branchId/tables')))
      ..invalidate(cachedProvider(tenantPath(t.id, 'branches')));
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final t = ref.watch(tenantProvider)!;
    final path = tenantPath(t.id, 'branches/$branchId/tables');
    final l = context.l;
    return CachedView(
      path: path,
      builder: (context, data, _) {
        final tables = J.dataList(data);
        return ListView(
          padding: const EdgeInsets.only(bottom: 40),
          children: [
            Padding(
              padding: const EdgeInsets.all(16),
              child: Row(
                children: [
                  Expanded(
                    child: OutlinedButton.icon(onPressed: () => _add(context, ref), icon: const Icon(AppIcons.plus), label: Text(l.addTables)),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: tables.isEmpty
                          ? null
                          : () => downloadAndOpen(context, ref, tenantPath(t.id, 'branches/$branchId/table-cards.pdf'), 'table-cards.pdf'),
                      icon: const Icon(AppIcons.fileText),
                      label: Text(l.tableCardsPdf, maxLines: 1, overflow: TextOverflow.ellipsis),
                    ),
                  ),
                ],
              ),
            ),
            for (final tb in tables)
              ListTile(
                leading: const Icon(AppIcons.armchair),
                title: Text(tb.s('label')),
                subtitle: Text(tb.o('qr').s('code', tb.s('code'))),
                trailing: IconButton(
                  tooltip: l.delete,
                  icon: const Icon(AppIcons.trash2),
                  onPressed: () async {
                    if (await confirm(context, title: l.confirmDeleteTitle(tb.s('label')), action: l.delete, danger: true) && context.mounted) {
                      final api = ref.read(apiProvider), container = context.container;
                      await runOk(context, () => api.delete(tenantPath(t.id, 'tables/${tb.i('id')}')));
                      container.invalidate(cachedProvider(path));
                    }
                  },
                ),
              ),
          ],
        );
      },
    );
  }
}
