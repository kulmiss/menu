import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../core/api/api_error.dart';
import '../../core/cache/swr.dart';
import '../../core/providers.dart';
import '../../core/util/json.dart';
import '../../core/widgets/kit.dart';
import 'menu_data.dart';

const _socials = ['facebook', 'instagram', 'tiktok', 'x', 'youtube', 'website'];

/// Restaurant info, opening hours with breaks, holidays and closures.
class InfoScreen extends ConsumerStatefulWidget {
  const InfoScreen({super.key});
  @override
  ConsumerState<InfoScreen> createState() => _InfoScreenState();
}

class _InfoScreenState extends ConsumerState<InfoScreen> with SingleTickerProviderStateMixin {
  late final _tabs = TabController(length: 3, vsync: this);

  @override
  void dispose() {
    _tabs.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l;
    return Scaffold(
      appBar: AppBar(
        title: Text(l.infoHours),
        bottom: TabBar(
          controller: _tabs,
          tabs: [
            Tab(text: l.about),
            Tab(text: l.openingHours),
            Tab(text: l.holidays),
          ],
        ),
      ),
      body: TabBarView(controller: _tabs, children: const [_Profile(), _Hours(), _Closures()]),
    );
  }
}

class _Profile extends ConsumerStatefulWidget {
  const _Profile();
  @override
  ConsumerState<_Profile> createState() => _ProfileState();
}

class _ProfileState extends ConsumerState<_Profile> {
  final _c = <String, TextEditingController>{};
  J? _data;
  bool _wifi = false;
  bool _busy = false;
  Map<String, List<String>> _errors = {};

  TextEditingController c(String k) => _c.putIfAbsent(k, TextEditingController.new);

  void _fill(J d) {
    _data = d;
    c('description').text = d.t('description');
    c('address').text = d.t('address');
    for (final k in ['maps_url', 'phone', 'whatsapp', 'telegram', 'email', 'wifi_name', 'wifi_password']) {
      c(k).text = d.s(k);
    }
    for (final k in _socials) {
      c('s_$k').text = d.o('socials').s(k);
    }
    _wifi = d.b('show_wifi');
  }

  @override
  void dispose() {
    for (final x in _c.values) {
      x.dispose();
    }
    super.dispose();
  }

  Future<void> _save() async {
    final t = ref.read(tenantProvider)!;
    String? v(String k) => c(k).text.trim().isEmpty ? null : c(k).text.trim();
    setState(() {
      _busy = true;
      _errors = {};
    });
    try {
      final r = await ref
          .read(apiProvider)
          .put(
            tenantPath(t.id, 'profile'),
            data: {
              'description': translated(ref, _data?['description'], c('description').text),
              'address': translated(ref, _data?['address'], c('address').text),
              for (final k in ['maps_url', 'phone', 'whatsapp', 'telegram', 'email', 'wifi_name', 'wifi_password']) k: v(k),
              'socials': {for (final k in _socials) k: v('s_$k')},
              'show_wifi': _wifi,
            },
          );
      _fill(J.data(r));
      if (mounted) showSnack(context, context.l.saved);
    } catch (e) {
      if (!mounted) return;
      setState(() => _errors = ApiError.from(e).fieldErrors);
      showSnack(context, errorText(context, e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final t = ref.watch(tenantProvider)!;
    final l = context.l;
    final v = ref.watch(cachedProvider(tenantPath(t.id, 'profile')));
    if (_data == null && v.value != null) _fill(J.data(v.value!.data));
    if (_data == null) return v.hasError ? ErrorView(error: v.error!) : const Skeleton();
    Widget field(String k, String label, {TextInputType? type, int lines = 1}) => Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: TextField(
        controller: c(k),
        keyboardType: type,
        minLines: lines,
        maxLines: lines == 1 ? 1 : 5,
        decoration: InputDecoration(labelText: label, errorText: _errors[k]?.first ?? _errors[k.replaceFirst('s_', 'socials.')]?.first),
      ),
    );
    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 48),
      children: [
        field('description', l.description, lines: 3),
        field('address', l.address, lines: 2),
        field('maps_url', l.mapsLink, type: TextInputType.url),
        SectionHeader(l.contact),
        field('phone', l.phone, type: TextInputType.phone),
        field('whatsapp', l.whatsapp, type: TextInputType.phone),
        field('telegram', l.telegram),
        field('email', l.email, type: TextInputType.emailAddress),
        SectionHeader(l.socials),
        for (final s in _socials) field('s_$s', s[0].toUpperCase() + s.substring(1), type: TextInputType.url),
        SectionHeader(l.wifi),
        field('wifi_name', l.wifiName),
        field('wifi_password', l.wifiPassword),
        SwitchListTile(contentPadding: EdgeInsets.zero, title: Text(l.showWifi), value: _wifi, onChanged: (x) => setState(() => _wifi = x)),
        const SizedBox(height: 12),
        FilledButton(onPressed: _busy ? null : _save, child: Text(l.save)),
      ],
    );
  }
}

class _Hours extends ConsumerStatefulWidget {
  const _Hours();
  @override
  ConsumerState<_Hours> createState() => _HoursState();
}

class _HoursState extends ConsumerState<_Hours> {
  int? _branch;
  List<Map<String, dynamic>>? _rows;
  bool _dirty = false;

  String get _path {
    final t = ref.read(tenantProvider)!;
    return tenantPath(t.id, 'hours${_branch == null ? '' : '?branch_id=$_branch'}');
  }

  Future<String?> _time(String initial) async {
    final p = initial.split(':');
    final t = await showTimePicker(
      context: context,
      initialTime: TimeOfDay(hour: int.parse(p[0]), minute: int.parse(p[1])),
      builder: (c, w) => MediaQuery(data: MediaQuery.of(c).copyWith(alwaysUse24HourFormat: true), child: w!),
    );
    return t == null ? null : '${t.hour.toString().padLeft(2, '0')}:${t.minute.toString().padLeft(2, '0')}';
  }

  @override
  Widget build(BuildContext context) {
    final t = ref.watch(tenantProvider)!;
    final l = context.l;
    final v = ref.watch(cachedProvider(_path));
    final branches = ref.watch(cachedProvider(tenantPath(t.id, 'branches'))).value;
    final bl = branches == null ? <J>[] : J.dataList(branches.data);
    if (_rows == null && v.value != null) {
      _rows = [
        for (final r in J.dataList(v.value!.data)) {...r.m},
      ];
    }
    if (_rows == null) return v.hasError ? ErrorView(error: v.error!) : const Skeleton();
    final days = DateFormat.EEEE().dateSymbols.STANDALONEWEEKDAYS;
    final order = [1, 2, 3, 4, 5, 6, 0];
    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 48),
      children: [
        if (bl.length > 1)
          DropdownButtonFormField<int?>(
            isExpanded: true,
            initialValue: _branch,
            decoration: InputDecoration(labelText: l.forBranch),
            items: [
              DropdownMenuItem(value: null, child: Text(l.allBranches)),
              for (final b in bl) DropdownMenuItem(value: b.i('id'), child: Text(b.s('name'))),
            ],
            onChanged: (x) => setState(() {
              _branch = x;
              _rows = null;
              _dirty = false;
            }),
          ),
        for (final d in order) ...[
          Padding(
            padding: const EdgeInsets.only(top: 12),
            child: Text(days[d], style: const TextStyle(fontWeight: FontWeight.w600)),
          ),
          if (!_rows!.any((r) => r['weekday'] == d)) Text(l.closed, style: TextStyle(color: context.colors.onSurfaceVariant)),
          for (final r in _rows!.where((r) => r['weekday'] == d))
            Row(
              children: [
                TextButton(
                  onPressed: () async {
                    final x = await _time(r['opens_at'] as String);
                    if (x != null && mounted) setState(() => [r['opens_at'] = x, _dirty = true]);
                  },
                  child: Text(r['opens_at'] as String),
                ),
                const Text('–'),
                TextButton(
                  onPressed: () async {
                    final x = await _time(r['closes_at'] as String);
                    if (x != null && mounted) setState(() => [r['closes_at'] = x, _dirty = true]);
                  },
                  child: Text(r['closes_at'] as String),
                ),
                if ((r['closes_at'] as String).compareTo(r['opens_at'] as String) < 0)
                  Text('(${l.nextDay})', style: TextStyle(fontSize: 12, color: context.colors.onSurfaceVariant)),
                const Spacer(),
                IconButton(tooltip: l.delete, onPressed: () => setState(() => [_rows!.remove(r), _dirty = true]), icon: const Icon(AppIcons.x)),
              ],
            ),
          Align(
            alignment: Alignment.centerLeft,
            child: TextButton.icon(
              onPressed: () => setState(
                () => [
                  _rows!.add({'weekday': d, 'opens_at': '08:00', 'closes_at': '22:00'}),
                  _dirty = true,
                ],
              ),
              icon: const Icon(AppIcons.plus, size: 18),
              label: Text(l.addBreak),
            ),
          ),
        ],
        OutlinedButton(
          onPressed: () => setState(() {
            final monday = _rows!.where((r) => r['weekday'] == 1).toList();
            _rows!.removeWhere((r) => [2, 3, 4, 5].contains(r['weekday']));
            for (final d in [2, 3, 4, 5]) {
              _rows!.addAll(monday.map((m) => {...m, 'weekday': d}));
            }
            _dirty = true;
          }),
          child: Text(l.copyMonday),
        ),
        const SizedBox(height: 12),
        FilledButton(
          onPressed: !_dirty
              ? null
              : () async {
                  final api = ref.read(apiProvider), container = context.container, path = _path;
                  if (await runOk(
                    context,
                    () => api.put(tenantPath(t.id, 'hours'), data: {if (_branch != null) 'branch_id': _branch, 'hours': _rows}),
                    success: l.saved,
                  )) {
                    container.invalidate(cachedProvider(path));
                    if (mounted) setState(() => _dirty = false);
                  }
                },
          child: Text(l.save),
        ),
      ],
    );
  }
}

class _Closures extends ConsumerWidget {
  const _Closures();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final t = ref.watch(tenantProvider)!;
    final path = tenantPath(t.id, 'closures');
    final l = context.l;
    return CachedView(
      path: path,
      builder: (context, data, _) => ListView(
        padding: const EdgeInsets.only(bottom: 48),
        children: [
          Padding(
            padding: const EdgeInsets.all(16),
            child: OutlinedButton.icon(
              icon: const Icon(AppIcons.plus),
              label: Text(l.addClosure),
              onPressed: () async {
                final range = await showDateRangePicker(
                  context: context,
                  firstDate: DateTime.now().subtract(const Duration(days: 1)),
                  lastDate: DateTime.now().add(const Duration(days: 700)),
                );
                if (range == null || !context.mounted) return;
                final note = await askText(context, title: l.note, label: '${l.note} (${l.optional})', max: 120);
                if (!context.mounted) return;
                final f = DateFormat('yyyy-MM-dd');
                final api = ref.read(apiProvider), container = context.container;
                final data = {'starts_on': f.format(range.start), 'ends_on': f.format(range.end), if (note != null) 'note': translated(ref, null, note)};
                await runOk(context, () => api.post(path, data: data));
                container.invalidate(cachedProvider(path));
              },
            ),
          ),
          for (final c in J.dataList(data))
            ListTile(
              leading: const Icon(AppIcons.calendarX),
              title: Text('${formatDate(context, c.s('starts_on'))} – ${formatDate(context, c.s('ends_on'))}'),
              subtitle: c.t('note').isEmpty ? null : Text(c.t('note')),
              trailing: IconButton(
                tooltip: l.delete,
                icon: const Icon(AppIcons.trash2),
                onPressed: () async {
                  final api = ref.read(apiProvider), container = context.container;
                  await runOk(context, () => api.delete('$path/${c.i('id')}'));
                  container.invalidate(cachedProvider(path));
                },
              ),
            ),
        ],
      ),
    );
  }
}
