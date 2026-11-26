import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/cache/swr.dart';
import '../../core/platform/updates.dart';
import '../../core/providers.dart';
import '../../core/util/json.dart';
import '../../core/widgets/kit.dart';
import '../../core/widgets/net_image.dart';
import 'dish_editor_screen.dart' show minorToText, textToMinor;
import 'menu_data.dart';

/// Quick edits in bottom sheets (redesign 2026-11): the top jobs without
/// opening the full dish editor. Home → "Change a price" → pick → type → Save.

/// Search the dishes and pick one (null when closed).
Future<J?> pickDish(BuildContext context, {required String title}) => showAppSheet<J>(context, title: title, builder: (c) => const _DishSearch());

/// Change a price: pick a dish, then the price sheet.
Future<void> changePriceFlow(BuildContext context, WidgetRef ref) async {
  final dish = await pickDish(context, title: context.l.actionChangePrice);
  if (dish == null || !context.mounted) return;
  await showPriceSheet(context, ref, dish.i('id'), name: dish.t('name'));
}

/// The price (or each size's price) of one dish; saves with PATCH items/{id}/price.
Future<bool> showPriceSheet(BuildContext context, WidgetRef ref, int itemId, {String? name}) async {
  final saved = await showAppSheet<bool>(
    context,
    title: name ?? context.l.price,
    description: context.l.price,
    builder: (c) => _PriceForm(itemId: itemId),
  );
  return saved ?? false;
}

/// Hide or show dishes: a searchable list of switches, each saved at once.
Future<void> showVisibilitySheet(BuildContext context) =>
    showAppSheet<void>(context, title: context.l.actionHideDish, description: context.l.visible, builder: (c) => const _DishSearch(visibility: true));

class _DishSearch extends ConsumerStatefulWidget {
  const _DishSearch({this.visibility = false});
  final bool visibility;
  @override
  ConsumerState<_DishSearch> createState() => _DishSearchState();
}

class _DishSearchState extends ConsumerState<_DishSearch> {
  final _q = TextEditingController();
  Timer? _debounce;
  List<J>? _rows;
  Object? _error;
  final Map<int, bool> _visible = {};
  int _seq = 0;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _q.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    final seq = ++_seq;
    final t = ref.read(tenantProvider)!;
    try {
      final r = await ref
          .read(apiProvider)
          .get(tenantPath(t.id, 'items'), query: {if (_q.text.trim().isNotEmpty) 'q': _q.text.trim(), 'sort': 'name', 'per': 30, 'page': 1});
      if (!mounted || seq != _seq) return;
      setState(() {
        _rows = J.dataList(r);
        _error = null;
      });
    } catch (e) {
      if (mounted && seq == _seq) setState(() => _error = e);
    }
  }

  Future<void> _toggle(J row, bool v) async {
    final id = row.i('id');
    final t = ref.read(tenantProvider)!;
    final api = ref.read(apiProvider);
    final container = context.container;
    setState(() => _visible[id] = v);
    try {
      await api.post(
        tenantPath(t.id, 'items/bulk'),
        data: {
          'ids': [id],
          'action': v ? 'show' : 'hide',
        },
      );
      await dishesChanged(container, t.id);
    } catch (e) {
      if (!mounted) return;
      setState(() => _visible.remove(id));
      showSnack(context, errorText(context, e), kind: ToastKind.error);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l;
    final currency = ref.watch(tenantProvider)?.currency ?? '';
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        TextField(
          controller: _q,
          autofocus: !widget.visibility,
          textInputAction: TextInputAction.search,
          decoration: InputDecoration(hintText: l.search, prefixIcon: const Icon(AppIcons.search, size: 18)),
          onChanged: (_) {
            _debounce?.cancel();
            _debounce = Timer(const Duration(milliseconds: 250), _load);
          },
        ),
        const SizedBox(height: 8),
        ConstrainedBox(
          constraints: BoxConstraints(maxHeight: MediaQuery.sizeOf(context).height * 0.5),
          child: switch ((_rows, _error)) {
            (_, final Object e) => ErrorView(error: e, onRetry: _load),
            (null, _) => const Padding(
              padding: EdgeInsets.all(24),
              child: Center(child: CircularProgressIndicator()),
            ),
            (final rows?, _) when rows.isEmpty => EmptyState(icon: AppIcons.searchX, title: l.noMatches),
            (final rows?, _) => ListView.builder(
              shrinkWrap: true,
              itemCount: rows.length,
              itemBuilder: (context, i) {
                final row = rows[i];
                final id = row.i('id');
                final leading = NetImage(row.sn('photo'), size: 40);
                final subtitle = [row.o('category').t('name'), priceLine(row, currency)].where((s) => s.isNotEmpty).join(' · ');
                if (widget.visibility) {
                  final on = _visible[id] ?? row.b('is_visible', true);
                  return SwitchListTile(
                    secondary: leading,
                    contentPadding: EdgeInsets.zero,
                    title: Text(row.t('name')),
                    subtitle: Text(on ? l.show : l.hide),
                    value: on,
                    onChanged: (v) => _toggle(row, v),
                  );
                }
                return ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: leading,
                  title: Text(row.t('name')),
                  subtitle: subtitle.isEmpty ? null : Text(subtitle),
                  trailing: const Icon(AppIcons.chevronRight, size: 18),
                  onTap: () => Navigator.pop(context, row),
                );
              },
            ),
          },
        ),
      ],
    );
  }
}

class _PriceForm extends ConsumerStatefulWidget {
  const _PriceForm({required this.itemId});
  final int itemId;
  @override
  ConsumerState<_PriceForm> createState() => _PriceFormState();
}

class _PriceFormState extends ConsumerState<_PriceForm> {
  final _form = GlobalKey<FormState>();
  final Map<int?, TextEditingController> _c = {};
  bool _busy = false;
  bool _filled = false;

  @override
  void dispose() {
    for (final c in _c.values) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _save(J item) async {
    if (!(_form.currentState?.validate() ?? false)) return;
    setState(() => _busy = true);
    final t = ref.read(tenantProvider)!;
    final api = ref.read(apiProvider);
    final container = context.container;
    final variants = item.list('variants');
    final data = variants.isEmpty
        ? {'price_minor': textToMinor(_c[null]!.text)}
        : {
            'variants': [
              for (final v in variants) {'id': v.i('id'), 'price_minor': textToMinor(_c[v.i('id')]!.text)},
            ],
          };
    final ok = await runOk(context, () => api.patch(tenantPath(t.id, 'items/${widget.itemId}/price'), data: data), success: context.l.saved);
    if (ok) {
      await dishesChanged(container, t.id);
      unawaited(container.read(reviewPromptProvider).afterSave());
    }
    if (!mounted) return;
    setState(() => _busy = false);
    if (ok) Navigator.pop(context, true);
  }

  @override
  Widget build(BuildContext context) {
    final t = ref.watch(tenantProvider)!;
    final v = ref.watch(cachedProvider(tenantPath(t.id, 'items/${widget.itemId}')));
    final item = v.value == null ? null : J.data(v.value!.data);
    if (item == null) {
      return v.hasError
          ? ErrorView(error: v.error!, onRetry: () => ref.refreshPath(tenantPath(t.id, 'items/${widget.itemId}')))
          : const Padding(
              padding: EdgeInsets.all(24),
              child: Center(child: CircularProgressIndicator()),
            );
    }
    final variants = item.list('variants');
    if (!_filled) {
      _filled = true;
      if (variants.isEmpty) {
        _c[null] = TextEditingController(text: minorToText(item.iN('price_minor')));
      } else {
        for (final x in variants) {
          _c[x.i('id')] = TextEditingController(text: minorToText(x.i('price_minor')));
        }
      }
    }
    String? check(String? s) => textToMinor(s ?? '') == null || textToMinor(s!)! < 0 ? context.l.price : null;
    Widget field(TextEditingController c, String label, {bool focus = false}) => TextFormField(
      controller: c,
      autofocus: focus,
      keyboardType: const TextInputType.numberWithOptions(decimal: true),
      inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[0-9.,]'))],
      decoration: InputDecoration(labelText: label, suffixText: t.currency),
      validator: check,
      onFieldSubmitted: (_) => _save(item),
    );
    return Form(
      key: _form,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (variants.isEmpty)
            field(_c[null]!, context.l.price, focus: true)
          else
            for (final (i, x) in variants.indexed) ...[if (i > 0) const SizedBox(height: 12), field(_c[x.i('id')]!, x.t('name'), focus: i == 0)],
          const SizedBox(height: 16),
          AppButton(label: context.l.save, loading: _busy, expand: true, onPressed: () => _save(item)),
        ],
      ),
    );
  }
}
