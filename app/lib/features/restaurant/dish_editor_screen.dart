import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_error.dart';
import '../../core/cache/swr.dart';
import '../../core/platform/updates.dart';
import '../../core/providers.dart';
import '../../core/util/json.dart';
import '../../core/util/photos.dart';
import '../../core/widgets/kit.dart';
import '../../core/widgets/net_image.dart';
import 'menu_data.dart';

const allergenKeys = [
  'gluten',
  'crustaceans',
  'eggs',
  'fish',
  'peanuts',
  'soy',
  'milk',
  'nuts',
  'celery',
  'mustard',
  'sesame',
  'sulphites',
  'lupin',
  'molluscs',
];
const maxPhotos = 5;

String allergenLabel(BuildContext c, String k) => switch (k) {
  'gluten' => c.l.allergen_gluten,
  'crustaceans' => c.l.allergen_crustaceans,
  'eggs' => c.l.allergen_eggs,
  'fish' => c.l.allergen_fish,
  'peanuts' => c.l.allergen_peanuts,
  'soy' => c.l.allergen_soy,
  'milk' => c.l.allergen_milk,
  'nuts' => c.l.allergen_nuts,
  'celery' => c.l.allergen_celery,
  'mustard' => c.l.allergen_mustard,
  'sesame' => c.l.allergen_sesame,
  'sulphites' => c.l.allergen_sulphites,
  'lupin' => c.l.allergen_lupin,
  'molluscs' => c.l.allergen_molluscs,
  _ => k,
};

String tagLabel(BuildContext c, J tag) => switch (tag.s('key')) {
  'vegan' => c.l.tag_vegan,
  'vegetarian' => c.l.tag_vegetarian,
  'spicy' => c.l.tag_spicy,
  'halal' => c.l.tag_halal,
  'gluten_free' => c.l.tag_gluten_free,
  'new' => c.l.tag_new,
  'popular' => c.l.tag_popular,
  _ => tag.t('name'),
};

/// Minor units ⇄ text ("60", "12.50").
String minorToText(int? minor) {
  if (minor == null) return '';
  return minor % 100 == 0 ? '${minor ~/ 100}' : (minor / 100).toStringAsFixed(2);
}

int? textToMinor(String text) {
  final v = double.tryParse(text.replaceAll(',', '.').trim());
  return v == null ? null : (v * 100).round();
}

class DishEditorScreen extends StatelessWidget {
  const DishEditorScreen({super.key, this.dishId, this.menuId, this.categoryId, this.autoPhoto = false});
  final int? dishId;
  final int? menuId;
  final int? categoryId;

  /// Home › "Add dish with photo": open the camera/gallery choice right away.
  final bool autoPhoto;

  @override
  Widget build(BuildContext context) => DishEditor(dishId: dishId, menuId: menuId, categoryId: categoryId, autoPhoto: autoPhoto);
}

/// Every field of a dish, photos from camera or gallery (crop, reorder, main).
class DishEditor extends ConsumerStatefulWidget {
  const DishEditor({super.key, this.dishId, this.menuId, this.categoryId, this.embedded = false, this.onClosed, this.autoPhoto = false});
  final bool autoPhoto;
  final int? dishId;
  final int? menuId;
  final int? categoryId;
  final bool embedded;
  final VoidCallback? onClosed;

  @override
  ConsumerState<DishEditor> createState() => _DishEditorState();
}

class _SizeRow {
  _SizeRow({this.id, String name = '', String price = '', this.old}) : name = TextEditingController(text: name), price = TextEditingController(text: price);
  final int? id;
  final Object? old;
  final TextEditingController name;
  final TextEditingController price;
}

class _DishEditorState extends ConsumerState<DishEditor> {
  final _form = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _summary = TextEditingController();
  final _description = TextEditingController();
  final _price = TextEditingController();
  final _calories = TextEditingController();
  final _prep = TextEditingController();
  J? _item;
  bool _loaded = false;
  bool _dirty = false;
  bool _busy = false;
  int? _categoryId;
  int? _menuId;
  bool _useSizes = false;
  final List<_SizeRow> _sizes = [];
  Set<int> _addons = {};
  Set<int> _tags = {};
  Set<String> _allergens = {};
  List<Map<String, dynamic>> _photos = [];
  bool _showPhoto = true, _visible = true, _soldOut = false, _isNew = false, _popular = false;
  double? _upload;
  Map<String, List<String>> _errors = {};

  bool get _isNewDish => widget.dishId == null;

  @override
  void initState() {
    super.initState();
    _menuId = widget.menuId;
    _categoryId = widget.categoryId;
    for (final c in [_name, _summary, _description, _price, _calories, _prep]) {
      c.addListener(_markDirty);
    }
    if (_isNewDish) _loaded = true;
    if (_isNewDish && widget.autoPhoto) WidgetsBinding.instance.addPostFrameCallback((_) => mounted ? _addPhoto() : null);
  }

  void _markDirty() {
    if (_loaded && !_dirty) setState(() => _dirty = true);
  }

  void _fill(J item) {
    _item = item;
    _name.text = item.t('name');
    _summary.text = item.t('summary');
    _description.text = item.t('description');
    _price.text = minorToText(item.iN('price_minor'));
    _calories.text = item.iN('calories')?.toString() ?? '';
    _prep.text = item.iN('prep_minutes')?.toString() ?? '';
    _categoryId = item.i('category_id');
    _menuId = item.i('menu_id');
    final variants = item.list('variants');
    _useSizes = variants.isNotEmpty;
    _sizes
      ..clear()
      ..addAll([for (final v in variants) _SizeRow(id: v.i('id'), name: v.t('name'), price: minorToText(v.i('price_minor')), old: v['name'])]);
    _addons = item.ints('addon_group_ids').toSet();
    _tags = item.ints('tag_ids').toSet();
    _allergens = {for (final a in (item['allergens'] as List? ?? const [])) '$a'};
    _photos = [for (final p in item.list('photos')) p.m];
    _showPhoto = item.b('show_photo', true);
    _visible = item.b('is_visible', true);
    _soldOut = item.b('is_sold_out');
    _isNew = item.b('is_new');
    _popular = item.b('is_popular');
    _loaded = true;
    _dirty = false;
  }

  Map<String, dynamic> _payload() {
    int? n(String s) => int.tryParse(s.trim());
    return {
      'category_id': _categoryId,
      'name': translated(ref, _item?['name'], _name.text),
      'summary': translated(ref, _item?['summary'], _summary.text),
      'description': translated(ref, _item?['description'], _description.text),
      if (ref.read(canProvider).editPrices) ...{
        'price_minor': _useSizes ? null : textToMinor(_price.text),
        'variants': _useSizes
            ? [
                for (final s in _sizes)
                  {if (s.id != null) 'id': s.id, 'name': translated(ref, s.old, s.name.text), 'price_minor': textToMinor(s.price.text) ?? 0},
              ]
            : <Object>[],
      },
      'calories': n(_calories.text),
      'prep_minutes': n(_prep.text),
      'addon_group_ids': _addons.toList(),
      'tag_ids': _tags.toList(),
      'allergens': _allergens.toList(),
      'photo_ids': [for (final p in _photos) p['id']],
      'show_photo': _showPhoto,
      'is_visible': _visible,
      if (ref.read(canProvider).soldOut) 'is_sold_out': _soldOut,
      'is_new': _isNew,
      'is_popular': _popular,
    };
  }

  Future<void> _save() async {
    // Ctrl+S reaches here even while the Save button is disabled.
    if (_busy || _upload != null || !(_form.currentState?.validate() ?? false)) return;
    setState(() {
      _busy = true;
      _errors = {};
    });
    final t = ref.read(tenantProvider)!;
    final api = ref.read(apiProvider);
    final container = context.container;
    final payload = _payload();
    try {
      await (_isNewDish ? api.post(tenantPath(t.id, 'items'), data: payload) : api.patch(tenantPath(t.id, 'items/${widget.dishId}'), data: payload));
      await dishesChanged(container, t.id);
      // Saves are what the Play review prompt counts (PLAN.md §7); it was never called.
      unawaited(container.read(reviewPromptProvider).afterSave());
      if (!mounted) return;
      showSnack(context, context.l.dishSaved);
      setState(() => _dirty = false);
      if (_isNewDish) {
        widget.embedded ? widget.onClosed?.call() : context.pop();
      }
    } catch (e) {
      if (!mounted) return;
      final err = ApiError.from(e);
      if (err.needsUpgrade) {
        await showUpgradeSheet(context, err.message);
      } else {
        setState(() => _errors = err.fieldErrors);
        showSnack(context, errorText(context, e));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _addPhoto() async {
    if (_photos.length >= maxPhotos) return;
    final file = await pickPhoto(context, ref);
    if (file == null || !mounted) return;
    final t = ref.read(tenantProvider)!;
    setState(() => _upload = 0);
    try {
      final r = await ref
          .read(apiProvider)
          .upload(tenantPath(t.id, 'media'), filePath: file.path, fileName: 'dish.jpg', onProgress: (p) => mounted ? setState(() => _upload = p) : null);
      if (!mounted) return;
      setState(() {
        _photos.add(J.data(r).m);
        _dirty = true;
      });
    } catch (e) {
      if (mounted) showSnack(context, errorText(context, e));
    } finally {
      if (mounted) setState(() => _upload = null);
    }
  }

  Future<bool> _confirmLeave() async {
    if (!_dirty) return true;
    return confirm(context, title: context.l.unsavedTitle, body: context.l.unsavedBody, action: context.l.discard, danger: true);
  }

  @override
  void dispose() {
    for (final c in [_name, _summary, _description, _price, _calories, _prep]) {
      c.dispose();
    }
    for (final s in _sizes) {
      s.name.dispose();
      s.price.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = ref.watch(tenantProvider)!;
    final can = ref.watch(canProvider);
    if (!_isNewDish && !_loaded) {
      final v = ref.watch(cachedProvider(tenantPath(t.id, 'items/${widget.dishId}')));
      return v.when(
        loading: () => Scaffold(appBar: AppBar(), body: const Skeleton()),
        error: (e, _) => Scaffold(
          appBar: AppBar(),
          body: ErrorView(error: e, onRetry: () => ref.invalidate(cachedProvider(tenantPath(t.id, 'items/${widget.dishId}')))),
        ),
        data: (c) {
          WidgetsBinding.instance.addPostFrameCallback((_) {
            if (mounted && !_loaded) setState(() => _fill(J.data(c.data)));
          });
          return Scaffold(appBar: AppBar(), body: const Skeleton());
        },
      );
    }
    final readOnly = !can.manageMenu;
    final l = context.l;
    final scaffold = Scaffold(
      appBar: AppBar(
        automaticallyImplyLeading: !widget.embedded,
        leading: widget.embedded
            ? IconButton(tooltip: l.close, icon: const Icon(AppIcons.x), onPressed: () async => await _confirmLeave() ? widget.onClosed?.call() : null)
            : null,
        title: Text(_isNewDish ? l.newDish : l.editDish),
        actions: [
          if (!readOnly && !_isNewDish)
            PopupMenuButton<String>(
              onSelected: (v) async {
                final api = ref.read(apiProvider);
                final container = context.container;
                if (v == 'duplicate') {
                  final r = await runAction(context, () => api.post(tenantPath(t.id, 'items/${widget.dishId}/duplicate')));
                  if (r == null) return;
                  await dishesChanged(container, t.id);
                  if (mounted) context.pushReplacement('/r/${t.id}/dishes/${J.data(r).i('id')}');
                } else if (v == 'delete' &&
                    await confirm(context, title: l.confirmDeleteTitle(_name.text), body: l.confirmDeleteBody, action: l.delete, danger: true)) {
                  if (!mounted) return;
                  final ok = await runAction(context, () async {
                    await api.delete(tenantPath(t.id, 'items/${widget.dishId}'));
                    return true;
                  });
                  if (ok != true) return;
                  await dishesChanged(container, t.id);
                  if (mounted) {
                    setState(() => _dirty = false);
                    widget.embedded ? widget.onClosed?.call() : context.pop();
                  }
                }
              },
              itemBuilder: (c) => [
                PopupMenuItem(value: 'duplicate', child: Text(c.l.duplicate)),
                PopupMenuItem(
                  value: 'delete',
                  child: Text(c.l.deleteDish, style: TextStyle(color: c.colors.error)),
                ),
              ],
            ),
          if (!readOnly)
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 8),
              child: FilledButton(
                key: const Key('saveDish'),
                onPressed: _busy || _upload != null ? null : _save,
                style: FilledButton.styleFrom(minimumSize: const Size(72, 40)),
                child: Text(l.save),
              ),
            ),
        ],
      ),
      body: CallbackShortcuts(
        bindings: {const SingleActivator(LogicalKeyboardKey.keyS, control: true): _save},
        child: Form(
          key: _form,
          child: ListView(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 48),
            children: [
              _PhotoStrip(
                photos: _photos,
                upload: _upload,
                readOnly: readOnly,
                onAdd: _addPhoto,
                onChanged: (p) => setState(() {
                  _photos = p;
                  _dirty = true;
                }),
              ),
              const SizedBox(height: 16),
              TextFormField(
                key: const Key('dishName'),
                controller: _name,
                readOnly: readOnly,
                maxLength: 120,
                textCapitalization: TextCapitalization.sentences,
                decoration: InputDecoration(labelText: l.name, errorText: _errors['name']?.first),
                validator: (v) => (v == null || v.trim().isEmpty) ? l.requiredField : null,
              ),
              const SizedBox(height: 8),
              _CategoryPicker(
                menuId: _menuId,
                value: _categoryId,
                readOnly: readOnly,
                onChanged: (v) => setState(() {
                  _categoryId = v;
                  _dirty = true;
                }),
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _summary,
                readOnly: readOnly,
                maxLength: 160,
                decoration: InputDecoration(labelText: l.shortDescription),
              ),
              const SizedBox(height: 8),
              TextFormField(
                controller: _description,
                readOnly: readOnly,
                maxLength: 2000,
                minLines: 2,
                maxLines: 6,
                decoration: InputDecoration(labelText: l.description),
              ),
              const SizedBox(height: 8),
              _priceSection(context, readOnly || !can.editPrices, t.currency),
              const SizedBox(height: 16),
              SectionHeader(l.tags),
              _TagChips(
                selected: _tags,
                readOnly: readOnly,
                onChanged: (v) => setState(() {
                  _tags = v;
                  _dirty = true;
                }),
              ),
              SectionHeader(l.allergens),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  for (final a in allergenKeys)
                    FilterChip(
                      label: Text(allergenLabel(context, a)),
                      selected: _allergens.contains(a),
                      onSelected: readOnly
                          ? null
                          : (v) => setState(() {
                              v ? _allergens.add(a) : _allergens.remove(a);
                              _dirty = true;
                            }),
                    ),
                ],
              ),
              SectionHeader(l.addons),
              _AddonPicker(
                selected: _addons,
                readOnly: readOnly,
                onChanged: (v) => setState(() {
                  _addons = v;
                  _dirty = true;
                }),
              ),
              const SizedBox(height: 16),
              Row(
                children: [
                  Expanded(
                    child: TextFormField(
                      controller: _calories,
                      readOnly: readOnly,
                      keyboardType: TextInputType.number,
                      inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                      decoration: InputDecoration(labelText: l.calories),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: TextFormField(
                      controller: _prep,
                      readOnly: readOnly,
                      keyboardType: TextInputType.number,
                      inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                      decoration: InputDecoration(labelText: l.prepMinutes),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              for (final (label, value, set, hint) in [
                (l.showPhoto, _showPhoto, (bool v) => _showPhoto = v, _showPhoto ? null : l.showPhotoHint),
                (l.visible, _visible, (bool v) => _visible = v, null),
                if (can.soldOut) (l.soldOut, _soldOut, (bool v) => _soldOut = v, null),
                (l.markNew, _isNew, (bool v) => _isNew = v, null),
                (l.markPopular, _popular, (bool v) => _popular = v, null),
              ])
                SwitchListTile(
                  contentPadding: EdgeInsets.zero,
                  title: Text(label),
                  subtitle: hint == null ? null : Text(hint),
                  value: value,
                  onChanged: readOnly && label != l.soldOut
                      ? null
                      : (v) => setState(() {
                          set(v);
                          _dirty = true;
                        }),
                ),
            ],
          ),
        ),
      ),
    );
    return PopScope(
      canPop: !_dirty,
      onPopInvokedWithResult: (didPop, _) async {
        if (!didPop && await _confirmLeave() && context.mounted) {
          setState(() => _dirty = false);
          context.pop();
        }
      },
      child: scaffold,
    );
  }

  Widget _priceSection(BuildContext context, bool locked, String currency) {
    final l = context.l;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (locked && !ref.read(canProvider).editPrices)
          Padding(
            padding: const EdgeInsets.only(bottom: 8),
            child: Text(l.pricesLocked, style: TextStyle(color: context.colors.onSurfaceVariant, fontSize: 13)),
          ),
        SwitchListTile(
          contentPadding: EdgeInsets.zero,
          title: Text(l.usesSizes),
          value: _useSizes,
          onChanged: locked
              ? null
              : (v) => setState(() {
                  _useSizes = v;
                  if (v && _sizes.isEmpty) _sizes.addAll([_SizeRow(price: _price.text), _SizeRow()]);
                  _dirty = true;
                }),
        ),
        if (!_useSizes)
          TextFormField(
            key: const Key('dishPrice'),
            controller: _price,
            readOnly: locked,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[0-9.,]'))],
            decoration: InputDecoration(labelText: l.price, suffixText: currency),
          )
        else ...[
          for (var i = 0; i < _sizes.length; i++)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: Row(
                children: [
                  Expanded(
                    flex: 3,
                    child: TextFormField(
                      controller: _sizes[i].name,
                      readOnly: locked,
                      decoration: InputDecoration(labelText: l.sizeName),
                      validator: (v) => (v ?? '').trim().isEmpty ? l.requiredField : null,
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    flex: 2,
                    child: TextFormField(
                      controller: _sizes[i].price,
                      readOnly: locked,
                      keyboardType: const TextInputType.numberWithOptions(decimal: true),
                      decoration: InputDecoration(labelText: l.price),
                      validator: (v) => textToMinor(v ?? '') == null ? l.requiredField : null,
                    ),
                  ),
                  if (!locked) IconButton(tooltip: l.delete, onPressed: () => setState(() => _sizes.removeAt(i)), icon: const Icon(AppIcons.x)),
                ],
              ),
            ),
          if (!locked && _sizes.length < 20)
            TextButton.icon(onPressed: () => setState(() => _sizes.add(_SizeRow())), icon: const Icon(AppIcons.plus), label: Text(l.addSize)),
        ],
      ],
    );
  }
}

class _PhotoStrip extends StatelessWidget {
  const _PhotoStrip({required this.photos, required this.onChanged, required this.onAdd, this.upload, this.readOnly = false});
  final List<Map<String, dynamic>> photos;
  final ValueChanged<List<Map<String, dynamic>>> onChanged;
  final VoidCallback onAdd;
  final double? upload;
  final bool readOnly;

  @override
  Widget build(BuildContext context) {
    final l = context.l;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          height: 116,
          child: ReorderableListView(
            scrollDirection: Axis.horizontal,
            buildDefaultDragHandles: !readOnly,
            onReorderItem: (from, to) {
              final next = [...photos];
              final p = next.removeAt(from);
              next.insert(to, p);
              onChanged(next);
            },
            footer: readOnly || photos.length >= maxPhotos
                ? null
                : Padding(
                    padding: const EdgeInsets.only(left: 4),
                    child: SizedBox(
                      width: 108,
                      child: OutlinedButton(
                        onPressed: upload == null ? onAdd : null,
                        style: OutlinedButton.styleFrom(
                          padding: EdgeInsets.zero,
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                        ),
                        child: upload != null
                            ? Column(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  CircularProgressIndicator(value: upload),
                                  const SizedBox(height: 6),
                                  Text(l.uploading, style: const TextStyle(fontSize: 12)),
                                ],
                              )
                            : Column(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  const Icon(AppIcons.imagePlus),
                                  const SizedBox(height: 6),
                                  Text(l.addPhoto, style: const TextStyle(fontSize: 12)),
                                ],
                              ),
                      ),
                    ),
                  ),
            children: [
              for (var i = 0; i < photos.length; i++)
                Padding(
                  key: ValueKey(photos[i]['id']),
                  padding: const EdgeInsets.only(right: 8),
                  child: GestureDetector(
                    onTap: readOnly
                        ? null
                        : () => showModalBottomSheet<void>(
                            context: context,
                            showDragHandle: true,
                            builder: (c) => SafeArea(
                              child: Column(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  if (i > 0)
                                    ListTile(
                                      leading: const Icon(AppIcons.star),
                                      title: Text(c.l.makeMain),
                                      onTap: () {
                                        Navigator.pop(c);
                                        onChanged([photos[i], ...photos.where((p) => p != photos[i])]);
                                      },
                                    ),
                                  ListTile(
                                    leading: Icon(AppIcons.trash2, color: c.colors.error),
                                    title: Text(c.l.removePhoto),
                                    onTap: () {
                                      Navigator.pop(c);
                                      onChanged([...photos]..removeAt(i));
                                    },
                                  ),
                                ],
                              ),
                            ),
                          ),
                    child: Stack(
                      children: [
                        NetImage(photoUrl(photos[i]), size: 108, radius: 10, semanticLabel: '${l.photos} ${i + 1}'),
                        if (i == 0)
                          Positioned(
                            left: 6,
                            top: 6,
                            child: Container(
                              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                              decoration: BoxDecoration(color: Colors.black87, borderRadius: BorderRadius.circular(6)),
                              child: Text(
                                l.mainPhoto,
                                style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w700),
                              ),
                            ),
                          ),
                      ],
                    ),
                  ),
                ),
            ],
          ),
        ),
        const SizedBox(height: 6),
        Text(l.photosCount(photos.length, maxPhotos), style: TextStyle(fontSize: 12, color: context.colors.onSurfaceVariant)),
      ],
    );
  }
}

class _CategoryPicker extends ConsumerWidget {
  const _CategoryPicker({required this.menuId, required this.value, required this.onChanged, this.readOnly = false});
  final int? menuId;
  final int? value;
  final ValueChanged<int?> onChanged;
  final bool readOnly;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final t = ref.watch(tenantProvider)!;
    final menus = ref.watch(cachedProvider(tenantPath(t.id, 'menus'))).value;
    final menuIds = menuId != null ? [menuId!] : (menus == null ? <int>[] : [for (final m in J.dataList(menus.data)) m.i('id')]);
    final cats = <J>[];
    for (final id in menuIds) {
      final tree = ref.watch(cachedProvider(tenantPath(t.id, 'menus/$id/tree'))).value;
      if (tree != null) cats.addAll(J.data(tree.data).list('categories'));
    }
    final current = value ?? (cats.isNotEmpty ? cats.first.i('id') : null);
    if (value == null && current != null) WidgetsBinding.instance.addPostFrameCallback((_) => onChanged(current));
    return DropdownButtonFormField<int>(
      initialValue: cats.any((c) => c.i('id') == current) ? current : null,
      isExpanded: true,
      decoration: InputDecoration(labelText: context.l.category),
      items: [for (final c in cats) DropdownMenuItem(value: c.i('id'), child: Text(c.t('name')))],
      onChanged: readOnly ? null : onChanged,
      validator: (v) => v == null ? context.l.requiredField : null,
    );
  }
}

class _TagChips extends ConsumerWidget {
  const _TagChips({required this.selected, required this.onChanged, this.readOnly = false});
  final Set<int> selected;
  final ValueChanged<Set<int>> onChanged;
  final bool readOnly;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final t = ref.watch(tenantProvider)!;
    final v = ref.watch(cachedProvider(tenantPath(t.id, 'tags'))).value;
    final tags = v == null ? <J>[] : J.dataList(v.data);
    return Wrap(
      spacing: 8,
      runSpacing: 8,
      children: [
        for (final tag in tags)
          FilterChip(
            label: Text(tagLabel(context, tag)),
            selected: selected.contains(tag.i('id')),
            onSelected: readOnly ? null : (on) => onChanged(on ? {...selected, tag.i('id')} : ({...selected}..remove(tag.i('id')))),
          ),
      ],
    );
  }
}

class _AddonPicker extends ConsumerWidget {
  const _AddonPicker({required this.selected, required this.onChanged, this.readOnly = false});
  final Set<int> selected;
  final ValueChanged<Set<int>> onChanged;
  final bool readOnly;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final t = ref.watch(tenantProvider)!;
    final v = ref.watch(cachedProvider(tenantPath(t.id, 'addon-groups'))).value;
    final groups = v == null ? <J>[] : J.dataList(v.data);
    if (groups.isEmpty) return Text('—', style: TextStyle(color: context.colors.onSurfaceVariant));
    return Column(
      children: [
        for (final g in groups)
          CheckboxListTile(
            contentPadding: EdgeInsets.zero,
            value: selected.contains(g.i('id')),
            title: Text(g.t('name')),
            subtitle: Text(g.list('options').map((o) => o.t('name')).join(', '), maxLines: 1, overflow: TextOverflow.ellipsis),
            onChanged: readOnly ? null : (on) => onChanged(on == true ? {...selected, g.i('id')} : ({...selected}..remove(g.i('id')))),
          ),
      ],
    );
  }
}
