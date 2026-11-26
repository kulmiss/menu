import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/cache/swr.dart';
import '../../core/providers.dart';
import '../../core/util/json.dart';
import '../../core/widgets/adaptive_shell.dart';
import '../../core/widgets/kit.dart';
import '../../core/widgets/net_image.dart';
import 'dish_editor_screen.dart';
import 'menu_data.dart';
import 'menu_settings.dart';
import 'quick_edit.dart';
import 'restaurant_scaffold.dart';

/// Menu editor: menus → categories → dishes, quick sold-out switches,
/// drag-to-reorder; on tablets the dish opens beside the list.
class MenuScreen extends ConsumerStatefulWidget {
  const MenuScreen({super.key, this.menuId});
  final int? menuId;

  @override
  ConsumerState<MenuScreen> createState() => _MenuScreenState();
}

class _MenuScreenState extends ConsumerState<MenuScreen> {
  int? _menuId;
  int? _selectedDish;
  bool _reorder = false;

  @override
  void initState() {
    super.initState();
    _menuId = widget.menuId;
  }

  @override
  Widget build(BuildContext context) {
    final tenant = ref.watch(tenantProvider)!;
    final can = ref.watch(canProvider);
    final menusPath = tenantPath(tenant.id, 'menus');
    final menus = ref.watch(cachedProvider(menusPath));
    final list = menus.value == null ? <J>[] : J.dataList(menus.value!.data);
    final menuId = _menuId ?? (list.isNotEmpty ? list.first.i('id') : null);
    final menu = list.where((m) => m.i('id') == menuId).firstOrNull;
    final wide = TwoPane.isWide(context);

    return RestaurantScaffold.largeBody(
      title: context.l.navMenu,
      actions: [
        if (wide && can.manageMenu && menu != null && !_reorder)
          TextButton.icon(
            onPressed: () => _openDish(context, tenant.id, null, menuId: menuId),
            icon: const Icon(AppIcons.plus),
            label: Text(context.l.dish),
          ),
        IconButton(tooltip: context.l.allDishes, icon: const Icon(AppIcons.rows3), onPressed: () => context.push('/r/${tenant.id}/all-dishes')),
        if (can.manageMenu && menu != null)
          IconButton(
            tooltip: _reorder ? context.l.reorderDone : context.l.reorder,
            icon: Icon(_reorder ? AppIcons.check : AppIcons.arrowUpDown),
            onPressed: () => setState(() => _reorder = !_reorder),
          ),
        if (can.manageMenu) MenuActions(menu: menu, onChanged: (id) => setState(() => _menuId = id)),
      ],
      fab: can.manageMenu && menu != null && !_reorder && !wide
          ? FloatingActionButton.extended(
              onPressed: () => _openDish(context, tenant.id, null, menuId: menuId),
              icon: const Icon(AppIcons.plus),
              label: Text(context.l.dish),
            )
          : null,
      body: menus.when(
        skipLoadingOnRefresh: true,
        loading: () => const Skeleton(),
        error: (e, _) => ErrorView(error: e, onRetry: () => ref.invalidate(cachedProvider(menusPath))),
        data: (_) {
          if (list.isEmpty) {
            return EmptyState(
              icon: AppIcons.utensils,
              title: context.l.noMenus,
              body: context.l.noMenusBody,
              action: can.manageMenu
                  ? FilledButton(onPressed: () => createMenu(context, ref).then((id) => setState(() => _menuId = id)), child: Text(context.l.newMenu))
                  : null,
            );
          }
          final tree = Column(
            children: [
              if (list.length > 1 || can.manageMenu)
                SizedBox(
                  height: 56,
                  child: ListView(
                    scrollDirection: Axis.horizontal,
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                    children: [
                      for (final m in list)
                        Padding(
                          padding: const EdgeInsets.only(right: 8),
                          child: ChoiceChip(
                            label: Text([m.t('name'), if (!m.b('is_visible', true)) '·  ${context.l.hide}'].join(' ')),
                            selected: m.i('id') == menuId,
                            onSelected: (_) => setState(() {
                              _menuId = m.i('id');
                              _selectedDish = null;
                            }),
                          ),
                        ),
                    ],
                  ),
                ),
              Expanded(
                child: MenuTree(
                  menuId: menuId!,
                  reorder: _reorder,
                  selectedDish: _selectedDish,
                  onDish: (id) => wide ? setState(() => _selectedDish = id) : _openDish(context, tenant.id, id),
                ),
              ),
            ],
          );
          return TwoPane(
            listWidth: 420,
            list: tree,
            detail: _selectedDish == null
                ? null
                : DishEditor(key: ValueKey(_selectedDish), dishId: _selectedDish, embedded: true, onClosed: () => setState(() => _selectedDish = null)),
            emptyDetail: EmptyState(icon: AppIcons.pointer, title: context.l.edit),
          );
        },
      ),
    );
  }

  void _openDish(BuildContext context, int tenantId, int? id, {int? menuId}) {
    context.push(id == null ? '/r/$tenantId/dishes/new${menuId != null ? '?menu=$menuId' : ''}' : '/r/$tenantId/dishes/$id');
  }
}

/// Categories and dishes of one menu.
class MenuTree extends ConsumerWidget {
  const MenuTree({super.key, required this.menuId, required this.onDish, this.reorder = false, this.selectedDish});
  final int menuId;
  final bool reorder;
  final int? selectedDish;
  final ValueChanged<int> onDish;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final tenant = ref.watch(tenantProvider)!;
    final path = tenantPath(tenant.id, 'menus/$menuId/tree');
    final can = ref.watch(canProvider);
    final overrides = ref.watch(soldOutProvider);
    return CachedView(
      path: path,
      builder: (context, data, _) {
        final cats = J.data(data).list('categories');
        if (cats.isEmpty) {
          return ListView(
            children: [
              EmptyState(
                icon: AppIcons.shapes,
                title: context.l.noCategories,
                body: context.l.noCategoriesBody,
                action: can.manageMenu ? FilledButton(onPressed: () => createCategory(context, ref, menuId), child: Text(context.l.newCategory)) : null,
              ),
            ],
          );
        }
        if (reorder) return _ReorderCategories(menuId: menuId, categories: cats, treePath: path);
        return ListView.builder(
          padding: const EdgeInsets.only(bottom: 96),
          itemCount: cats.length + (can.manageMenu ? 1 : 0),
          itemBuilder: (context, i) {
            if (i == cats.length) {
              return Padding(
                padding: const EdgeInsets.all(16),
                child: OutlinedButton.icon(
                  onPressed: () => createCategory(context, ref, menuId),
                  icon: const Icon(AppIcons.plus),
                  label: Text(context.l.newCategory),
                ),
              );
            }
            final c = cats[i];
            final items = c.list('items');
            return Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                SectionHeader(
                  [c.t('name'), if (!c.b('is_visible', true)) '(${context.l.hiddenFromGuests})'].join(' '),
                  trailing: can.manageMenu ? CategoryActions(category: c, menuId: menuId) : null,
                ),
                if (items.isEmpty)
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    child: Text(context.l.noDishes, style: TextStyle(color: context.colors.onSurfaceVariant)),
                  ),
                for (final it in items)
                  DishRow(
                    item: it,
                    currency: tenant.currency,
                    selected: it.i('id') == selectedDish,
                    soldOut: can.soldOut && (overrides[it.i('id')] ?? it.b('is_sold_out')),
                    onTap: () => onDish(it.i('id')),
                    onSoldOut: can.toggleSoldOut ? (v) => ref.read(soldOutProvider.notifier).set(context, it.i('id'), v) : null,
                    onPrice: can.editPrices ? () => showPriceSheet(context, ref, it.i('id'), name: it.t('name')) : null,
                    onLongPress: () => showDishActions(context, ref, it, onEdit: () => onDish(it.i('id'))),
                  ),
              ],
            );
          },
        );
      },
    );
  }
}

class DishRow extends StatelessWidget {
  const DishRow({
    super.key,
    required this.item,
    required this.currency,
    required this.soldOut,
    required this.onTap,
    this.onSoldOut,
    this.onPrice,
    this.onLongPress,
    this.selected = false,
  });
  final J item;
  final String currency;
  final bool soldOut;
  final bool selected;
  final VoidCallback onTap;
  final ValueChanged<bool>? onSoldOut;

  /// Tap on the price: the quick price sheet (when the role may edit prices).
  final VoidCallback? onPrice;

  /// Long press: the dish's quick actions.
  final VoidCallback? onLongPress;

  @override
  Widget build(BuildContext context) {
    final t = context.tokens;
    final l = context.l;
    final hidden = !item.b('is_visible', true);
    final price = priceLine(item, currency);
    return Material(
      color: selected ? t.accent : Colors.transparent,
      child: InkWell(
        onTap: onTap,
        onLongPress: onLongPress,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: AppTokens.pad, vertical: 8),
          child: Row(
            children: [
              Opacity(
                opacity: soldOut || hidden ? .55 : 1,
                child: NetImage(item.photo('media'), size: 52, semanticLabel: item.t('name')),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      item.t('name'),
                      style: TextStyle(fontWeight: FontWeight.w600, fontSize: 15, color: soldOut || hidden ? t.mutedForeground : t.foreground),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                    const SizedBox(height: 4),
                    Wrap(
                      spacing: 6,
                      runSpacing: 4,
                      crossAxisAlignment: WrapCrossAlignment.center,
                      children: [
                        if (price.isNotEmpty)
                          onPrice == null
                              ? Text(price, style: TextStyle(fontSize: 13, color: t.mutedForeground))
                              : Semantics(
                                  button: true,
                                  label: '${l.actionChangePrice}: $price',
                                  child: InkWell(
                                    onTap: onPrice,
                                    borderRadius: BorderRadius.circular(AppTokens.radiusSm),
                                    child: Padding(
                                      padding: const EdgeInsets.only(right: 6, top: 4, bottom: 4),
                                      child: Row(
                                        mainAxisSize: MainAxisSize.min,
                                        children: [
                                          Flexible(
                                            child: Text(
                                              price,
                                              style: TextStyle(fontSize: 13, fontWeight: FontWeight.w500, color: t.foreground),
                                              overflow: TextOverflow.ellipsis,
                                            ),
                                          ),
                                          const SizedBox(width: 4),
                                          Icon(AppIcons.pencil, size: 12, color: t.mutedForeground),
                                        ],
                                      ),
                                    ),
                                  ),
                                ),
                        if (soldOut) AppBadge(l.soldOut, variant: BadgeVariant.destructive),
                        if (hidden) AppBadge(l.hiddenFromGuests, variant: BadgeVariant.outline, icon: AppIcons.eyeOff),
                      ],
                    ),
                  ],
                ),
              ),
              if (onSoldOut != null)
                Semantics(
                  label: '${item.t('name')}: ${l.available}',
                  child: Switch(value: !soldOut, onChanged: (v) => onSoldOut!(!v)),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Long press on a dish: show/hide, price, sold out, edit everything.
Future<void> showDishActions(BuildContext context, WidgetRef ref, J item, {required VoidCallback onEdit}) {
  final can = ref.read(canProvider);
  final l = context.l;
  final hidden = !item.b('is_visible', true);
  final soldOut = ref.read(soldOutProvider)[item.i('id')] ?? item.b('is_sold_out');
  return showAppSheet<void>(
    context,
    title: item.t('name'),
    builder: (c) => Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        if (can.editPrices)
          ListTile(
            leading: const Icon(AppIcons.tag),
            title: Text(l.actionChangePrice),
            onTap: () {
              Navigator.pop(c);
              showPriceSheet(context, ref, item.i('id'), name: item.t('name'));
            },
          ),
        if (can.manageMenu)
          ListTile(
            leading: Icon(hidden ? AppIcons.eye : AppIcons.eyeOff),
            title: Text(hidden ? l.show : l.hide),
            onTap: () async {
              Navigator.pop(c);
              final t = ref.read(tenantProvider)!;
              final api = ref.read(apiProvider);
              final container = context.container;
              final ok = await runOk(
                context,
                () => api.post(
                  tenantPath(t.id, 'items/bulk'),
                  data: {
                    'ids': [item.i('id')],
                    'action': hidden ? 'show' : 'hide',
                  },
                ),
                success: l.saved,
              );
              if (ok) await dishesChanged(container, t.id);
            },
          ),
        if (can.toggleSoldOut)
          ListTile(
            leading: const Icon(AppIcons.circleMinus),
            title: Text(soldOut ? l.available : l.soldOut),
            onTap: () {
              Navigator.pop(c);
              ref.read(soldOutProvider.notifier).set(context, item.i('id'), !soldOut);
            },
          ),
        ListTile(
          leading: const Icon(AppIcons.pencil),
          title: Text(l.edit),
          onTap: () {
            Navigator.pop(c);
            onEdit();
          },
        ),
      ],
    ),
  );
}

class _ReorderCategories extends ConsumerStatefulWidget {
  const _ReorderCategories({required this.menuId, required this.categories, required this.treePath});
  final int menuId;
  final List<J> categories;
  final String treePath;

  @override
  ConsumerState<_ReorderCategories> createState() => _ReorderCategoriesState();
}

class _ReorderCategoriesState extends ConsumerState<_ReorderCategories> {
  late List<J> _cats = [...widget.categories];

  @override
  void didUpdateWidget(covariant _ReorderCategories old) {
    super.didUpdateWidget(old);
    _cats = [...widget.categories];
  }

  Future<void> _save(List<J> order) async {
    final t = ref.read(tenantProvider)!;
    final api = ref.read(apiProvider), container = context.container;
    setState(() => _cats = order);
    await runAction(context, () async {
      await api.post(
        tenantPath(t.id, 'categories/reorder'),
        data: {
          'menu_id': widget.menuId,
          'ids': [for (final c in order) c.i('id')],
        },
      );
      container.invalidate(cachedProvider(widget.treePath));
    });
  }

  @override
  Widget build(BuildContext context) => ReorderableListView.builder(
    padding: const EdgeInsets.only(bottom: 40),
    header: Padding(
      padding: const EdgeInsets.all(16),
      child: Text(context.l.dragToReorder, style: TextStyle(color: context.colors.onSurfaceVariant)),
    ),
    itemCount: _cats.length,
    onReorderItem: (from, to) {
      final next = [..._cats];
      final c = next.removeAt(from);
      next.insert(to, c);
      _save(next);
    },
    itemBuilder: (context, i) => ListTile(
      key: ValueKey(_cats[i].i('id')),
      leading: const Icon(AppIcons.gripVertical),
      title: Text(_cats[i].t('name')),
      subtitle: Text('${_cats[i].list('items').length} ${context.l.dishes.toLowerCase()}'),
      trailing: TextButton(
        onPressed: () => Navigator.of(context).push(
          MaterialPageRoute<void>(
            builder: (_) => _ReorderDishes(category: _cats[i], treePath: widget.treePath),
          ),
        ),
        child: Text(context.l.dishes),
      ),
    ),
  );
}

class _ReorderDishes extends ConsumerStatefulWidget {
  const _ReorderDishes({required this.category, required this.treePath});
  final J category;
  final String treePath;

  @override
  ConsumerState<_ReorderDishes> createState() => _ReorderDishesState();
}

class _ReorderDishesState extends ConsumerState<_ReorderDishes> {
  late List<J> _items = widget.category.list('items');

  Future<void> _save(List<J> order) async {
    final t = ref.read(tenantProvider)!;
    final api = ref.read(apiProvider), container = context.container;
    setState(() => _items = order);
    await runAction(context, () async {
      await api.post(
        tenantPath(t.id, 'items/reorder'),
        data: {
          'category_id': widget.category.i('id'),
          'ids': [for (final c in order) c.i('id')],
        },
      );
      container.invalidate(cachedProvider(widget.treePath));
    });
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Text(widget.category.t('name'))),
    body: ReorderableListView.builder(
      itemCount: _items.length,
      onReorderItem: (from, to) {
        final next = [..._items];
        final c = next.removeAt(from);
        next.insert(to, c);
        _save(next);
      },
      itemBuilder: (context, i) => ListTile(
        key: ValueKey(_items[i].i('id')),
        leading: NetImage(_items[i].photo('media'), size: 40),
        title: Text(_items[i].t('name')),
        trailing: const Icon(AppIcons.gripVertical),
      ),
    ),
  );
}
