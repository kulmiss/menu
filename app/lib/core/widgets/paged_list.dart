import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../cache/swr.dart';
import '../providers.dart';
import '../util/json.dart';
import 'kit.dart';

/// Infinite scroll over the API's DataTable answers (`{data, meta: {page, pages}}`)
/// or cursor answers (`{data, meta: {next_cursor}}`). The first page comes
/// from the cache (instant), later pages from the network.
class PagedList extends ConsumerStatefulWidget {
  const PagedList({
    super.key,
    required this.path,
    required this.itemBuilder,
    this.query = const {},
    this.perPage = 50,
    this.empty,
    this.header,
    this.separated = true,
    this.padding = const EdgeInsets.only(bottom: 96),
    this.cursor = false,
  });

  final String path;
  final Map<String, String> query;
  final int perPage;
  final Widget Function(BuildContext context, J row, int index) itemBuilder;
  final Widget? empty;
  final Widget? header;
  final bool separated;
  final EdgeInsets padding;
  final bool cursor;

  @override
  ConsumerState<PagedList> createState() => PagedListState();
}

class PagedListState extends ConsumerState<PagedList> {
  final List<J> _more = [];
  int _page = 1;
  String? _cursor;
  bool _loading = false;
  bool _done = false;
  Object? _error;

  /// When the first page that [_more] follows was fetched; a newer first page
  /// (pull to refresh, a change made elsewhere) makes [_more] out of date.
  DateTime? _basis;

  /// Bumped by [reset]: an older page arriving afterwards is dropped.
  int _gen = 0;

  String get _firstPath {
    final q = {
      ...widget.query,
      if (!widget.cursor) ...{'per': '${widget.perPage}', 'page': '1'},
    };
    final qs = q.entries.where((e) => e.value.isNotEmpty).map((e) => '${e.key}=${Uri.encodeQueryComponent(e.value)}').join('&');
    return qs.isEmpty ? widget.path : '${widget.path}?$qs';
  }

  @override
  void didUpdateWidget(covariant PagedList oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.path != widget.path || oldWidget.query.toString() != widget.query.toString()) reset();
  }

  void reset() => setState(() {
    _gen++;
    _more.clear();
    _page = 1;
    _cursor = null;
    _done = false;
    _error = null;
    _loading = false;
    _basis = null;
  });

  Future<void> refresh() async {
    reset();
    await ref.refreshPath(_firstPath);
  }

  Future<void> _loadMore(J firstMeta, DateTime basis) async {
    if (_loading || _done) return;
    final pages = firstMeta.i('pages', 1);
    if (!widget.cursor && _page >= pages) {
      _done = true;
      return;
    }
    final next = widget.cursor ? (_cursor ?? firstMeta.sn('next_cursor')) : null;
    if (widget.cursor && next == null) {
      _done = true;
      return;
    }
    final gen = _gen;
    final api = ref.read(apiProvider);
    setState(() {
      _loading = true;
      _basis ??= basis;
    });
    try {
      final q = {
        ...widget.query,
        if (widget.cursor) 'cursor': next! else ...{'per': '${widget.perPage}', 'page': '${_page + 1}'},
      };
      final r = J.of(await api.get(widget.path, query: q));
      if (!mounted || gen != _gen) return;
      setState(() {
        _more.addAll(r.list('data'));
        _page++;
        _cursor = r.o('meta').sn('next_cursor');
        if (widget.cursor && _cursor == null) _done = true;
      });
    } catch (e) {
      if (mounted && gen == _gen) setState(() => _error = e);
    } finally {
      if (mounted && gen == _gen) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return CachedView(
      path: _firstPath,
      builder: (context, data, cached) {
        final first = J.of(data);
        final fresh = _basis == null || _basis == cached.fetchedAt;
        if (!fresh) {
          // The first page was reloaded: load the next pages again too.
          WidgetsBinding.instance.addPostFrameCallback((_) => mounted ? reset() : null);
        }
        final rows = [...first.list('data'), if (fresh) ..._more];
        if (rows.isEmpty) {
          return ListView(children: [?widget.header, widget.empty ?? const EmptyState()]);
        }
        final meta = first.o('meta');
        final hasMore = !_done && (widget.cursor ? (meta.sn('next_cursor') != null) : meta.i('pages', 1) > _page);
        final count = rows.length + (widget.header != null ? 1 : 0) + (hasMore || _error != null ? 1 : 0);
        return NotificationListener<ScrollNotification>(
          onNotification: (n) {
            if (n.metrics.extentAfter < 600 && hasMore && fresh) unawaited(_loadMore(meta, cached.fetchedAt));
            return false;
          },
          child: ListView.builder(
            padding: widget.padding,
            itemCount: count,
            itemBuilder: (context, i) {
              if (widget.header != null) {
                if (i == 0) return widget.header!;
                i--;
              }
              if (i >= rows.length) {
                if (_error != null) {
                  return Padding(
                    padding: const EdgeInsets.all(16),
                    child: OutlinedButton(onPressed: () => setState(() => _error = null), child: Text(context.l.retry)),
                  );
                }
                if (!_loading && fresh) WidgetsBinding.instance.addPostFrameCallback((_) => mounted ? _loadMore(meta, cached.fetchedAt) : null);
                return const Padding(
                  padding: EdgeInsets.all(20),
                  child: Center(child: CircularProgressIndicator(strokeWidth: 2)),
                );
              }
              final tile = widget.itemBuilder(context, rows[i], i);
              return widget.separated && i > 0 ? Column(mainAxisSize: MainAxisSize.min, children: [const Divider(height: 1, indent: 16), tile]) : tile;
            },
          ),
        );
      },
    );
  }
}

/// Text field that reports its value 300 ms after the last key.
class SearchField extends StatefulWidget {
  const SearchField({super.key, required this.onChanged, this.hint, this.autofocus = false});
  final ValueChanged<String> onChanged;
  final String? hint;
  final bool autofocus;

  @override
  State<SearchField> createState() => _SearchFieldState();
}

class _SearchFieldState extends State<SearchField> {
  final _c = TextEditingController();
  Timer? _t;

  @override
  void dispose() {
    _t?.cancel();
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => TextField(
    controller: _c,
    autofocus: widget.autofocus,
    textInputAction: TextInputAction.search,
    decoration: InputDecoration(
      prefixIcon: const Icon(AppIcons.search),
      hintText: widget.hint ?? context.l.search,
      isDense: true,
      suffixIcon: ListenableBuilder(
        listenable: _c,
        builder: (_, _) => _c.text.isEmpty
            ? const SizedBox.shrink()
            : IconButton(
                tooltip: context.l.close,
                icon: const Icon(AppIcons.x),
                onPressed: () {
                  _c.clear();
                  widget.onChanged('');
                },
              ),
      ),
    ),
    onChanged: (v) {
      _t?.cancel();
      _t = Timer(const Duration(milliseconds: 300), () => widget.onChanged(v.trim()));
    },
  );
}
