import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../core/cache/swr.dart';
import '../../core/providers.dart';
import '../../core/util/json.dart';
import '../../core/util/photos.dart';
import '../../core/widgets/kit.dart';
import '../../core/widgets/net_image.dart';
import 'menu_data.dart';

/// Specials and announcements at the top of the guest menu.
class PromotionsScreen extends ConsumerWidget {
  const PromotionsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final t = ref.watch(tenantProvider)!;
    final can = ref.watch(canProvider);
    final path = tenantPath(t.id, 'promotions');
    final l = context.l;
    if (!can.feature('promotions')) {
      return Scaffold(
        appBar: AppBar(title: Text(l.promotions)),
        body: ListView(
          padding: const EdgeInsets.all(16),
          children: [UpgradeCard(upgrade: t.upgrade)],
        ),
      );
    }
    return Scaffold(
      appBar: AppBar(title: Text(l.promotions)),
      floatingActionButton: FloatingActionButton.extended(
        icon: const Icon(AppIcons.plus),
        label: Text(l.add),
        onPressed: () async {
          final type = await showModalBottomSheet<String>(
            context: context,
            showDragHandle: true,
            builder: (c) => SafeArea(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  ListTile(leading: const Icon(AppIcons.star), title: Text(c.l.newSpecial), onTap: () => Navigator.pop(c, 'special')),
                  ListTile(leading: const Icon(AppIcons.megaphone), title: Text(c.l.newAnnouncement), onTap: () => Navigator.pop(c, 'announcement')),
                ],
              ),
            ),
          );
          if (type != null && context.mounted) await _edit(context, ref, null, type);
        },
      ),
      body: CachedView(
        path: path,
        builder: (context, data, _) {
          final rows = J.dataList(data);
          if (rows.isEmpty) {
            return ListView(
              children: [EmptyState(icon: AppIcons.megaphone, title: l.promotions)],
            );
          }
          return ListView(
            padding: const EdgeInsets.only(bottom: 96),
            children: [
              for (final p in rows)
                ListTile(
                  leading: p.has('media') ? NetImage(p.photo('media'), size: 48) : Icon(p.s('type') == 'special' ? AppIcons.star : AppIcons.megaphone),
                  title: Text(p.t('title')),
                  subtitle: Text(
                    [
                      p.s('type') == 'special' ? l.special : l.announcement,
                      if (p.sn('starts_at') != null || p.sn('ends_at') != null) '${p.s('starts_at', '…')} – ${p.s('ends_at', l.noEnd)}',
                    ].join(' · '),
                  ),
                  trailing: Switch(
                    value: p.b('is_active', true),
                    onChanged: (v) async {
                      final api = ref.read(apiProvider), container = context.container;
                      await runOk(context, () => api.patch('$path/${p.i('id')}', data: {'is_active': v}));
                      container.invalidate(cachedProvider(path));
                    },
                  ),
                  onTap: () => _edit(context, ref, p, p.s('type')),
                ),
            ],
          );
        },
      ),
    );
  }

  Future<void> _edit(BuildContext context, WidgetRef ref, J? p, String type) => showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    showDragHandle: true,
    builder: (_) => _PromotionSheet(promotion: p, type: type),
  );
}

class _PromotionSheet extends ConsumerStatefulWidget {
  const _PromotionSheet({required this.promotion, required this.type});
  final J? promotion;
  final String type;
  @override
  ConsumerState<_PromotionSheet> createState() => _PromotionSheetState();
}

class _PromotionSheetState extends ConsumerState<_PromotionSheet> {
  late final _title = TextEditingController(text: widget.promotion?.t('title'));
  late final _body = TextEditingController(text: widget.promotion?.t('body'));
  late int? _media = widget.promotion?.o('media').iN('id');
  late String? _mediaUrl = widget.promotion?.photo('media');
  late String? _starts = widget.promotion?.sn('starts_at');
  late String? _ends = widget.promotion?.sn('ends_at');
  bool _busy = false;

  Future<String?> _pick(String? current) async {
    final now = DateTime.now();
    final d = await showDatePicker(
      context: context,
      firstDate: now.subtract(const Duration(days: 1)),
      lastDate: now.add(const Duration(days: 730)),
      initialDate: DateTime.tryParse(current?.replaceFirst(' ', 'T') ?? '') ?? now,
    );
    if (d == null || !mounted) return current;
    final t = await showTimePicker(context: context, initialTime: const TimeOfDay(hour: 12, minute: 0));
    final dt = DateTime(d.year, d.month, d.day, t?.hour ?? 0, t?.minute ?? 0);
    return DateFormat('yyyy-MM-dd HH:mm').format(dt);
  }

  @override
  Widget build(BuildContext context) {
    final t = ref.watch(tenantProvider)!;
    final l = context.l;
    final path = tenantPath(t.id, 'promotions');
    final p = widget.promotion;
    return Padding(
      padding: EdgeInsets.fromLTRB(16, 0, 16, 16 + MediaQuery.viewInsetsOf(context).bottom),
      child: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(widget.type == 'special' ? l.special : l.announcement, style: context.text.titleMedium?.copyWith(fontWeight: FontWeight.w600)),
            const SizedBox(height: 12),
            TextField(
              controller: _title,
              maxLength: 80,
              decoration: InputDecoration(labelText: l.title),
            ),
            TextField(
              controller: _body,
              maxLength: 500,
              minLines: 2,
              maxLines: 5,
              decoration: InputDecoration(labelText: l.text),
            ),
            if (widget.type == 'special')
              Row(
                children: [
                  NetImage(_mediaUrl, size: 64),
                  const SizedBox(width: 12),
                  OutlinedButton(
                    onPressed: () async {
                      final api = ref.read(apiProvider);
                      final f = await pickPhoto(context, ref);
                      if (f == null || !context.mounted) return;
                      final r = await runAction(context, () => api.upload(tenantPath(t.id, 'media'), filePath: f.path, fileName: 'special.jpg'));
                      if (r == null || !mounted) return;
                      setState(() {
                        _media = J.data(r).i('id');
                        _mediaUrl = photoUrl(J.data(r).m);
                      });
                    },
                    child: Text(l.addPhoto),
                  ),
                ],
              ),
            ListTile(
              contentPadding: EdgeInsets.zero,
              title: Text(l.startsAt),
              subtitle: Text(_starts ?? '—'),
              trailing: const Icon(AppIcons.calendar),
              onTap: () async {
                final v = await _pick(_starts);
                if (mounted) setState(() => _starts = v);
              },
            ),
            ListTile(
              contentPadding: EdgeInsets.zero,
              title: Text(l.endsAt),
              subtitle: Text(_ends ?? l.noEnd),
              trailing: const Icon(AppIcons.calendar),
              onTap: () async {
                final v = await _pick(_ends);
                if (mounted) setState(() => _ends = v);
              },
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                if (p != null)
                  TextButton(
                    onPressed: () async {
                      final api = ref.read(apiProvider), container = context.container;
                      if (await runOk(context, () => api.delete('$path/${p.i('id')}'))) {
                        container.invalidate(cachedProvider(path));
                        if (context.mounted) Navigator.pop(context);
                      }
                    },
                    child: Text(l.delete, style: TextStyle(color: context.colors.error)),
                  ),
                const Spacer(),
                FilledButton(
                  onPressed: _busy
                      ? null
                      : () async {
                          setState(() => _busy = true);
                          final data = {
                            'type': widget.type,
                            'title': translated(ref, p?['title'], _title.text),
                            'body': translated(ref, p?['body'], _body.text),
                            'media_id': _media,
                            'starts_at': _starts,
                            'ends_at': _ends,
                          };
                          final api = ref.read(apiProvider), container = context.container;
                          final ok = await runOk(context, () => p == null ? api.post(path, data: data) : api.patch('$path/${p.i('id')}', data: data));
                          if (ok) container.invalidate(cachedProvider(path));
                          if (!context.mounted) return;
                          setState(() => _busy = false);
                          if (ok) Navigator.pop(context);
                        },
                  child: Text(l.save),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

/// Time-based prices: happy hour, lunch (plan feature `price_periods`).
class HappyHourScreen extends ConsumerWidget {
  const HappyHourScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final t = ref.watch(tenantProvider)!;
    final can = ref.watch(canProvider);
    final l = context.l;
    final path = tenantPath(t.id, 'price-periods');
    if (!can.feature('price_periods') || !can.editPrices) {
      return Scaffold(
        appBar: AppBar(title: Text(l.happyHour)),
        body: ListView(
          padding: const EdgeInsets.all(16),
          children: [can.editPrices ? UpgradeCard(upgrade: t.upgrade) : Text(l.pricesLocked)],
        ),
      );
    }
    final days = DateFormat.E().dateSymbols.STANDALONESHORTWEEKDAYS;
    return Scaffold(
      appBar: AppBar(title: Text(l.pricePeriods)),
      floatingActionButton: FloatingActionButton.extended(
        icon: const Icon(AppIcons.plus),
        label: Text(l.newPricePeriod),
        onPressed: () => Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => const PricePeriodEditor())),
      ),
      body: CachedView(
        path: path,
        builder: (context, data, _) {
          final rows = J.dataList(data);
          if (rows.isEmpty) {
            return ListView(
              children: [EmptyState(icon: AppIcons.wine, title: l.pricePeriods)],
            );
          }
          return ListView(
            padding: const EdgeInsets.only(bottom: 96),
            children: [
              for (final p in rows)
                ListTile(
                  title: Text(p.t('name')),
                  subtitle: Text(
                    '${p.ints('weekdays').map((d) => days[d]).join(', ')} · ${p.s('starts_at')}–${p.s('ends_at')} · ${p.list('items').length} ${l.dishes.toLowerCase()}',
                  ),
                  trailing: Switch(
                    value: p.b('is_active', true),
                    onChanged: (v) async {
                      final api = ref.read(apiProvider), container = context.container;
                      await runOk(context, () => api.put('$path/${p.i('id')}', data: {..._periodPayload(p), 'is_active': v}));
                      container.invalidate(cachedProvider(path));
                    },
                  ),
                  onTap: () => Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => PricePeriodEditor(period: p))),
                ),
            ],
          );
        },
      ),
    );
  }
}

Map<String, dynamic> _periodPayload(J p) => {
  'name': p['name'],
  'branch_id': p['branch_id'],
  'weekdays': p.ints('weekdays'),
  'starts_at': p.s('starts_at'),
  'ends_at': p.s('ends_at'),
  'is_active': p.b('is_active', true),
  'items': [for (final i in p.list('items')) i.m],
};

class PricePeriodEditor extends ConsumerStatefulWidget {
  const PricePeriodEditor({super.key, this.period});
  final J? period;
  @override
  ConsumerState<PricePeriodEditor> createState() => _PricePeriodEditorState();
}

class _PricePeriodEditorState extends ConsumerState<PricePeriodEditor> {
  late final _name = TextEditingController(text: widget.period?.t('name') ?? 'Happy hour');
  late final Set<int> _days = (widget.period?.ints('weekdays') ?? [1, 2, 3, 4, 5]).toSet();
  late String _start = widget.period?.s('starts_at') ?? '16:00';
  late String _end = widget.period?.s('ends_at') ?? '18:00';
  late final List<Map<String, dynamic>> _items = [
    for (final i in widget.period?.list('items') ?? <J>[]) {...i.m},
  ];
  bool _busy = false;

  Future<void> _time(bool start) async {
    final p = (start ? _start : _end).split(':');
    final t = await showTimePicker(
      context: context,
      initialTime: TimeOfDay(hour: int.parse(p[0]), minute: int.parse(p[1])),
    );
    if (t != null && mounted) setState(() => start ? _start = _fmt(t) : _end = _fmt(t));
  }

  String _fmt(TimeOfDay t) => '${t.hour.toString().padLeft(2, '0')}:${t.minute.toString().padLeft(2, '0')}';

  Future<void> _addDishes() async {
    final t = ref.read(tenantProvider)!;
    final rows = J.dataList(await ref.read(apiProvider).get(tenantPath(t.id, 'items'), query: {'per': '100', 'sort': 'category'}));
    if (!mounted) return;
    final picked = await showModalBottomSheet<List<int>>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (c) {
        final sel = <int>{};
        return StatefulBuilder(
          builder: (c, set) => SizedBox(
            height: MediaQuery.sizeOf(c).height * .8,
            child: Column(
              children: [
                Expanded(
                  child: ListView(
                    children: [
                      for (final r in rows.where((r) => !_items.any((i) => i['item_id'] == r.i('id'))))
                        CheckboxListTile(
                          value: sel.contains(r.i('id')),
                          title: Text(r.t('name')),
                          onChanged: (v) => set(() => v == true ? sel.add(r.i('id')) : sel.remove(r.i('id'))),
                        ),
                    ],
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.all(16),
                  child: FilledButton(onPressed: () => Navigator.pop(c, sel.toList()), child: Text(c.l.add)),
                ),
              ],
            ),
          ),
        );
      },
    );
    if (picked != null) {
      setState(
        () => _items.addAll([
          for (final id in picked)
            {'item_id': id, 'item_variant_id': null, 'mode': 'percent', 'value': 15, '_name': rows.firstWhere((r) => r.i('id') == id).t('name')},
        ]),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final t = ref.watch(tenantProvider)!;
    final l = context.l;
    final days = DateFormat.E().dateSymbols.STANDALONESHORTWEEKDAYS;
    final path = tenantPath(t.id, 'price-periods');
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.period == null ? l.newPricePeriod : l.pricePeriods),
        actions: [
          if (widget.period != null)
            IconButton(
              tooltip: l.delete,
              icon: const Icon(AppIcons.trash2),
              onPressed: () async {
                final api = ref.read(apiProvider), container = context.container;
                if (await runOk(context, () => api.delete('$path/${widget.period!.i('id')}'))) {
                  container.invalidate(cachedProvider(path));
                  if (context.mounted) Navigator.pop(context);
                }
              },
            ),
          Padding(
            padding: const EdgeInsets.only(right: 8),
            child: FilledButton(
              onPressed: _busy || _items.isEmpty || _days.isEmpty
                  ? null
                  : () async {
                      setState(() => _busy = true);
                      final data = {
                        'name': translated(ref, widget.period?['name'], _name.text),
                        'weekdays': _days.toList()..sort(),
                        'starts_at': _start,
                        'ends_at': _end,
                        'branch_id': widget.period?['branch_id'],
                        'is_active': widget.period?.b('is_active', true) ?? true,
                        'items': [
                          for (final i in _items)
                            {
                              for (final e in i.entries)
                                if (!e.key.startsWith('_')) e.key: e.value,
                            },
                        ],
                      };
                      final api = ref.read(apiProvider), container = context.container;
                      final ok = await runOk(
                        context,
                        () => widget.period == null ? api.post(path, data: data) : api.put('$path/${widget.period!.i('id')}', data: data),
                        success: l.saved,
                      );
                      if (ok) container.invalidate(cachedProvider(path));
                      if (!context.mounted) return;
                      setState(() => _busy = false);
                      if (ok) Navigator.pop(context);
                    },
              child: Text(l.save),
            ),
          ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          TextField(
            controller: _name,
            maxLength: 60,
            decoration: InputDecoration(labelText: l.periodName),
          ),
          Wrap(
            spacing: 6,
            children: [
              for (final d in [1, 2, 3, 4, 5, 6, 0])
                FilterChip(label: Text(days[d]), selected: _days.contains(d), onSelected: (v) => setState(() => v ? _days.add(d) : _days.remove(d))),
            ],
          ),
          Row(
            children: [
              TextButton(onPressed: () => _time(true), child: Text('${l.from} $_start')),
              TextButton(onPressed: () => _time(false), child: Text('${l.to} $_end')),
            ],
          ),
          SectionHeader(
            l.dishes,
            trailing: TextButton.icon(onPressed: _addDishes, icon: const Icon(AppIcons.plus), label: Text(l.addDishes)),
          ),
          for (final i in _items)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: Row(
                children: [
                  Expanded(child: Text(i['_name'] as String? ?? '#${i['item_id']}')),
                  DropdownButton<String>(
                    value: i['mode'] as String,
                    items: [
                      DropdownMenuItem(value: 'percent', child: Text(l.percentOff)),
                      DropdownMenuItem(value: 'fixed', child: Text(l.fixedPrice)),
                    ],
                    onChanged: (v) => setState(() => i['mode'] = v),
                  ),
                  const SizedBox(width: 8),
                  SizedBox(
                    width: 80,
                    child: TextFormField(
                      initialValue: i['mode'] == 'fixed' ? '${(i['value'] as int) / 100}' : '${i['value']}',
                      keyboardType: TextInputType.number,
                      inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[0-9.]'))],
                      decoration: InputDecoration(isDense: true, labelText: l.value),
                      onChanged: (v) => i['value'] = i['mode'] == 'fixed' ? ((double.tryParse(v) ?? 0) * 100).round() : int.tryParse(v) ?? 0,
                    ),
                  ),
                  IconButton(tooltip: l.remove, onPressed: () => setState(() => _items.remove(i)), icon: const Icon(AppIcons.x)),
                ],
              ),
            ),
        ],
      ),
    );
  }
}
