import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/cache/swr.dart';
import '../../core/providers.dart';
import '../../core/util/json.dart';
import '../../core/widgets/kit.dart';

/// Optimistic sold-out switches shared by every screen (PLAN.md §6): the
/// switch flips at once, the request follows, and a failure flips it back.
class SoldOutOverrides extends Notifier<Map<int, bool>> {
  @override
  Map<int, bool> build() {
    ref.watch(tenantProvider.select((t) => t?.id));
    return {};
  }

  bool value(int id, bool server) => state[id] ?? server;

  Future<void> set(BuildContext context, int itemId, bool soldOut) async {
    final tenant = ref.read(tenantProvider)!;
    final before = state[itemId];
    state = {...state, itemId: soldOut};
    try {
      await ref.read(apiProvider).patch(tenantPath(tenant.id, 'items/$itemId/sold-out'), data: {'is_sold_out': soldOut});
      await dishesChanged(ref, tenant.id);
    } catch (e) {
      state = {...state}..remove(itemId);
      if (before != null) state = {...state, itemId: before};
      if (context.mounted) showSnack(context, errorText(context, e));
    }
  }

  void clear() => state = {};
}

final soldOutProvider = NotifierProvider<SoldOutOverrides, Map<int, bool>>(SoldOutOverrides.new);

/// After a dish changed: the menu, every dish list (all dishes, sold out) and
/// Home's cards reload. [ref]: see [invalidatePrefixes].
Future<void> dishesChanged(Object ref, int tenantId) => invalidatePrefixes(ref, [
  for (final p in ['menus', 'items', 'dashboard', 'widgets/missing-photos']) tenantPath(tenantId, p),
]);

/// The restaurant's main language for translated fields ({"en": "…"}).
String mainLocale(WidgetRef ref) => ref.read(tenantProvider)?.json['default_locale'] as String? ?? 'en';

/// Keeps other languages when the main one is edited.
Map<String, String> translated(WidgetRef ref, Object? old, String value) {
  final m = <String, String>{if (old is Map) ...old.map((k, v) => MapEntry('$k', '$v'))};
  m[mainLocale(ref)] = value.trim();
  m.removeWhere((k, v) => v.isEmpty && k != mainLocale(ref));
  return m;
}

/// Price line: "60", "Small 60 · Large 80" or "from 60".
String priceLine(J item, String currency) {
  final variants = item.list('variants');
  if (variants.isNotEmpty) {
    return variants.map((v) => '${v.t('name')} ${formatMoney(v.i('price_minor'), '')}').join(' · ');
  }
  final p = item.iN('price_minor');
  return p == null ? '' : formatMoney(p, currency);
}

/// Asks for a short text (names).
Future<String?> askText(BuildContext context, {required String title, String? label, String? initial, int max = 80}) async {
  final c = TextEditingController(text: initial);
  final v = await showDialog<String>(
    context: context,
    builder: (ctx) => AlertDialog(
      title: Text(title),
      content: TextField(
        controller: c,
        autofocus: true,
        maxLength: max,
        textCapitalization: TextCapitalization.sentences,
        decoration: InputDecoration(labelText: label),
        onSubmitted: (v) => Navigator.pop(ctx, v.trim()),
      ),
      actions: [
        TextButton(onPressed: () => Navigator.pop(ctx), child: Text(ctx.l.cancel)),
        FilledButton(onPressed: () => Navigator.pop(ctx, c.text.trim()), child: Text(ctx.l.save)),
      ],
    ),
  );
  return (v == null || v.isEmpty) ? null : v;
}
