import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../core/cache/swr.dart';
import '../../core/providers.dart';
import '../../core/util/json.dart';
import '../../core/widgets/kit.dart';
import 'menu_data.dart';

/// Reloads the menus and their trees ([ref]: see [invalidatePrefixes]).
Future<void> _reloadMenus(Object ref, int tenantId) => invalidatePrefix(ref, tenantPath(tenantId, 'menus'));

Future<int?> createMenu(BuildContext context, WidgetRef ref) async {
  final name = await askText(context, title: context.l.newMenu, label: context.l.menuName);
  if (name == null || !context.mounted) return null;
  final t = ref.read(tenantProvider)!;
  final api = ref.read(apiProvider), container = context.container;
  final data = {'name': translated(ref, null, name), 'is_visible': true};
  final r = await runAction(context, () => api.post(tenantPath(t.id, 'menus'), data: data));
  await _reloadMenus(container, t.id);
  return r == null ? null : J.data(r).i('id');
}

Future<void> createCategory(BuildContext context, WidgetRef ref, int menuId) async {
  final name = await askText(context, title: context.l.newCategory, label: context.l.categoryName);
  if (name == null || !context.mounted) return;
  final t = ref.read(tenantProvider)!;
  final api = ref.read(apiProvider), container = context.container;
  final data = {'menu_id': menuId, 'name': translated(ref, null, name), 'is_visible': true};
  await runAction(context, () => api.post(tenantPath(t.id, 'categories'), data: data));
  await _reloadMenus(container, t.id);
}

/// The "⋮" menu of the menu editor.
class MenuActions extends ConsumerWidget {
  const MenuActions({super.key, required this.menu, required this.onChanged});
  final J? menu;
  final ValueChanged<int?> onChanged;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final t = ref.watch(tenantProvider)!;
    final api = ref.read(apiProvider);
    final m = menu;
    return PopupMenuButton<String>(
      tooltip: context.l.more,
      onSelected: (v) async {
        final container = context.container;
        switch (v) {
          case 'new':
            final id = await createMenu(context, ref);
            if (context.mounted) onChanged(id);
          case 'rename':
            final name = await askText(context, title: context.l.renameMenu, label: context.l.menuName, initial: m!.t('name'));
            if (name != null && context.mounted) {
              await runAction(context, () => api.patch(tenantPath(t.id, 'menus/${m.i('id')}'), data: {'name': translated(ref, m['name'], name)}));
              await _reloadMenus(container, t.id);
            }
          case 'visible':
            await runAction(context, () => api.patch(tenantPath(t.id, 'menus/${m!.i('id')}'), data: {'is_visible': !m.b('is_visible', true)}));
            await _reloadMenus(container, t.id);
          case 'schedule':
            await showModalBottomSheet<void>(
              context: context,
              isScrollControlled: true,
              showDragHandle: true,
              builder: (_) => ScheduleSheet(menu: m!),
            );
            await _reloadMenus(container, t.id);
          case 'branches':
            await showModalBottomSheet<void>(
              context: context,
              showDragHandle: true,
              builder: (_) => MenuBranchesSheet(menu: m!),
            );
            await _reloadMenus(container, t.id);
          case 'duplicate':
            final r = await runAction(context, () => api.post(tenantPath(t.id, 'menus/${m!.i('id')}/duplicate')));
            await _reloadMenus(container, t.id);
            if (r != null && context.mounted) onChanged(J.data(r).i('id'));
          case 'delete':
            if (await confirm(
                  context,
                  title: context.l.confirmDeleteTitle(m!.t('name')),
                  body: context.l.confirmDeleteBody,
                  action: context.l.delete,
                  danger: true,
                ) &&
                context.mounted) {
              await runAction(context, () => api.delete(tenantPath(t.id, 'menus/${m.i('id')}')));
              if (context.mounted) onChanged(null);
              await _reloadMenus(container, t.id);
            }
        }
      },
      itemBuilder: (c) => [
        PopupMenuItem(value: 'new', child: Text(c.l.newMenu)),
        if (m != null) ...[
          PopupMenuItem(value: 'rename', child: Text(c.l.renameMenu)),
          PopupMenuItem(value: 'visible', child: Text(m.b('is_visible', true) ? c.l.hide : c.l.showToGuests)),
          PopupMenuItem(value: 'schedule', child: Text(c.l.schedule)),
          if (ref.read(canProvider).feature('branches')) PopupMenuItem(value: 'branches', child: Text(c.l.branchesShown)),
          PopupMenuItem(value: 'duplicate', child: Text(c.l.duplicate)),
          PopupMenuItem(
            value: 'delete',
            child: Text(c.l.delete, style: TextStyle(color: c.colors.error)),
          ),
        ],
      ],
    );
  }
}

class CategoryActions extends ConsumerWidget {
  const CategoryActions({super.key, required this.category, required this.menuId});
  final J category;
  final int menuId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final t = ref.watch(tenantProvider)!;
    final api = ref.read(apiProvider);
    final id = category.i('id');
    return PopupMenuButton<String>(
      tooltip: context.l.more,
      onSelected: (v) async {
        switch (v) {
          case 'rename':
            final name = await askText(context, title: context.l.renameCategory, label: context.l.categoryName, initial: category.t('name'));
            if (name != null && context.mounted) {
              await runAction(context, () => api.patch(tenantPath(t.id, 'categories/$id'), data: {'name': translated(ref, category['name'], name)}));
            }
          case 'visible':
            await runAction(context, () => api.patch(tenantPath(t.id, 'categories/$id'), data: {'is_visible': !category.b('is_visible', true)}));
          case 'duplicate':
            await runAction(context, () => api.post(tenantPath(t.id, 'categories/$id/duplicate')));
          case 'delete':
            if (await confirm(
                  context,
                  title: context.l.confirmDeleteTitle(category.t('name')),
                  body: context.l.confirmDeleteBody,
                  action: context.l.delete,
                  danger: true,
                ) &&
                context.mounted) {
              await runAction(context, () => api.delete(tenantPath(t.id, 'categories/$id')));
            }
        }
        _reloadMenus(ref, menuId);
      },
      itemBuilder: (c) => [
        PopupMenuItem(value: 'rename', child: Text(c.l.renameCategory)),
        PopupMenuItem(value: 'visible', child: Text(category.b('is_visible', true) ? c.l.hide : c.l.showToGuests)),
        PopupMenuItem(value: 'duplicate', child: Text(c.l.duplicate)),
        PopupMenuItem(
          value: 'delete',
          child: Text(c.l.delete, style: TextStyle(color: c.colors.error)),
        ),
      ],
    );
  }
}

/// "All day" or set times per weekday (Breakfast 7–11).
class ScheduleSheet extends ConsumerStatefulWidget {
  const ScheduleSheet({super.key, required this.menu});
  final J menu;
  @override
  ConsumerState<ScheduleSheet> createState() => _ScheduleSheetState();
}

class _ScheduleSheetState extends ConsumerState<ScheduleSheet> {
  late bool _scheduled = widget.menu.s('schedule_mode') == 'scheduled';
  late final List<Map<String, dynamic>> _rows = [
    for (final s in widget.menu.list('schedules')) {...s.m},
  ];
  bool _busy = false;

  Future<void> _pickTime(int i, String key) async {
    final parts = (_rows[i][key] as String? ?? '08:00').split(':');
    final t = await showTimePicker(
      context: context,
      initialTime: TimeOfDay(hour: int.parse(parts[0]), minute: int.parse(parts[1])),
    );
    if (t != null && mounted) setState(() => _rows[i][key] = '${t.hour.toString().padLeft(2, '0')}:${t.minute.toString().padLeft(2, '0')}');
  }

  @override
  Widget build(BuildContext context) {
    final days = DateFormat.EEEE().dateSymbols.STANDALONEWEEKDAYS; // Sunday = 0, like the API
    return SafeArea(
      child: Padding(
        padding: EdgeInsets.fromLTRB(16, 0, 16, 16 + MediaQuery.viewInsetsOf(context).bottom),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(context.l.schedule, style: context.text.titleMedium?.copyWith(fontWeight: FontWeight.w600)),
            RadioGroup<bool>(
              groupValue: _scheduled,
              onChanged: (v) => setState(() => _scheduled = v ?? false),
              child: Column(
                children: [
                  RadioListTile<bool>(value: false, title: Text(context.l.scheduleAlways)),
                  RadioListTile<bool>(value: true, title: Text(context.l.scheduleTimes)),
                ],
              ),
            ),
            if (_scheduled) ...[
              ConstrainedBox(
                constraints: const BoxConstraints(maxHeight: 320),
                child: ListView(
                  shrinkWrap: true,
                  children: [
                    for (var i = 0; i < _rows.length; i++)
                      Row(
                        children: [
                          Expanded(
                            child: DropdownButton<int>(
                              isExpanded: true,
                              value: _rows[i]['weekday'] as int,
                              items: [for (var d = 0; d < 7; d++) DropdownMenuItem(value: d, child: Text(days[d]))],
                              onChanged: (v) => setState(() => _rows[i]['weekday'] = v),
                            ),
                          ),
                          TextButton(onPressed: () => _pickTime(i, 'starts_at'), child: Text(_rows[i]['starts_at'] as String)),
                          const Text('–'),
                          TextButton(onPressed: () => _pickTime(i, 'ends_at'), child: Text(_rows[i]['ends_at'] as String)),
                          IconButton(tooltip: context.l.delete, onPressed: () => setState(() => _rows.removeAt(i)), icon: const Icon(AppIcons.x)),
                        ],
                      ),
                  ],
                ),
              ),
              TextButton.icon(
                onPressed: () => setState(() => _rows.add({'weekday': 1, 'starts_at': '08:00', 'ends_at': '11:00'})),
                icon: const Icon(AppIcons.plus),
                label: Text(context.l.addTime),
              ),
            ],
            const SizedBox(height: 12),
            FilledButton(
              onPressed: _busy
                  ? null
                  : () async {
                      setState(() => _busy = true);
                      final t = ref.read(tenantProvider)!;
                      final ok = await runOk(
                        context,
                        () => ref
                            .read(apiProvider)
                            .put(
                              tenantPath(t.id, 'menus/${widget.menu.i('id')}/schedules'),
                              data: {'schedule_mode': _scheduled ? 'scheduled' : 'always', 'schedules': _scheduled ? _rows : <Object>[]},
                            ),
                      );
                      if (!mounted) return;
                      setState(() => _busy = false);
                      if (ok) Navigator.pop(context);
                    },
              child: Text(context.l.save),
            ),
          ],
        ),
      ),
    );
  }
}

class MenuBranchesSheet extends ConsumerStatefulWidget {
  const MenuBranchesSheet({super.key, required this.menu});
  final J menu;
  @override
  ConsumerState<MenuBranchesSheet> createState() => _MenuBranchesSheetState();
}

class _MenuBranchesSheetState extends ConsumerState<MenuBranchesSheet> {
  Set<int>? _ids;

  @override
  Widget build(BuildContext context) {
    final t = ref.watch(tenantProvider)!;
    final branches = ref.watch(cachedProvider(tenantPath(t.id, 'branches'))).value;
    final list = branches == null ? <J>[] : J.dataList(branches.data);
    _ids ??= widget.menu.ints('branch_ids').toSet();
    final all = _ids!.isEmpty;
    return SafeArea(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          CheckboxListTile(
            value: all,
            title: Text(context.l.allBranches),
            onChanged: (v) => setState(() => _ids = v == true ? {} : {for (final b in list) b.i('id')}),
          ),
          for (final b in list)
            CheckboxListTile(
              value: all || _ids!.contains(b.i('id')),
              title: Text(b.s('name')),
              onChanged: (v) => setState(() {
                final next = all ? {for (final x in list) x.i('id')} : {..._ids!};
                v == true ? next.add(b.i('id')) : next.remove(b.i('id'));
                _ids = next.length == list.length ? {} : next;
              }),
            ),
          Padding(
            padding: const EdgeInsets.all(16),
            child: FilledButton(
              onPressed: () async {
                final ids = _ids!.isEmpty ? [for (final b in list) b.i('id')] : _ids!.toList();
                final api = ref.read(apiProvider);
                final ok = await runOk(context, () => api.put(tenantPath(t.id, 'menus/${widget.menu.i('id')}/branches'), data: {'branch_ids': ids}));
                if (ok && context.mounted) Navigator.pop(context);
              },
              child: Text(context.l.save),
            ),
          ),
        ],
      ),
    );
  }
}
