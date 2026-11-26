import 'package:flutter/gestures.dart';
import 'package:flutter/material.dart';

/// The small Markdown the help articles use (backend resources/help/README.md):
/// headings, paragraphs, numbered and bullet lists, **bold**, `code`, links,
/// tables, code blocks and screenshots. Desktop screenshots are left out on
/// the phone; `help:slug` links call [onHelpLink].
class HelpMarkdown extends StatefulWidget {
  const HelpMarkdown(this.text, {super.key, required this.siteUrl, required this.onHelpLink, required this.onLink});
  final String text;

  /// The server's address, for `/help/shots/…` pictures.
  final String siteUrl;
  final void Function(String slug) onHelpLink;
  final void Function(String url) onLink;

  @override
  State<HelpMarkdown> createState() => _HelpMarkdownState();
}

class _HelpMarkdownState extends State<HelpMarkdown> {
  final _taps = <TapGestureRecognizer>[];

  @override
  void dispose() {
    for (final t in _taps) {
      t.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    for (final t in _taps) {
      t.dispose();
    }
    _taps.clear();
    final theme = Theme.of(context);
    final text = theme.textTheme.bodyLarge!.copyWith(height: 1.5);
    final blocks = <Widget>[];
    final lines = widget.text.replaceAll('\r\n', '\n').split('\n');
    var i = 0;
    Widget gap(Widget w) => Padding(padding: const EdgeInsets.only(bottom: 12), child: w);
    while (i < lines.length) {
      final line = lines[i];
      if (line.trim().isEmpty) {
        i++;
        continue;
      }
      if (line.startsWith('```')) {
        final code = <String>[];
        i++;
        while (i < lines.length && !lines[i].startsWith('```')) {
          code.add(lines[i++]);
        }
        i++;
        blocks.add(
          gap(
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(color: theme.colorScheme.surfaceContainerHighest, borderRadius: BorderRadius.circular(8)),
              child: SingleChildScrollView(
                scrollDirection: Axis.horizontal,
                child: SelectableText(code.join('\n'), style: const TextStyle(fontFamily: 'monospace', fontSize: 13)),
              ),
            ),
          ),
        );
        continue;
      }
      final h = RegExp(r'^(#{1,4})\s+(.*)$').firstMatch(line);
      if (h != null) {
        final style = h.group(1)!.length <= 2 ? theme.textTheme.titleMedium : theme.textTheme.titleSmall;
        blocks.add(
          Padding(
            padding: const EdgeInsets.only(top: 8, bottom: 8),
            child: Semantics(header: true, child: Text.rich(_inline(h.group(2)!, style!.copyWith(fontWeight: FontWeight.w600)))),
          ),
        );
        i++;
        continue;
      }
      if (line.trimLeft().startsWith('|')) {
        final rows = <List<String>>[];
        while (i < lines.length && lines[i].trimLeft().startsWith('|')) {
          final cells = lines[i].trim().replaceAll(RegExp(r'^\||\|$'), '').split('|').map((c) => c.trim()).toList();
          if (!cells.every((c) => RegExp(r'^:?-+:?$').hasMatch(c))) rows.add(cells);
          i++;
        }
        blocks.add(gap(_table(rows, text, theme)));
        continue;
      }
      final item = RegExp(r'^\s*(\d+\.|[-*])\s+');
      if (item.hasMatch(line)) {
        final items = <(String, String)>[];
        while (i < lines.length && lines[i].trim().isNotEmpty) {
          final m = item.firstMatch(lines[i]);
          if (m != null) {
            items.add((m.group(1)!, lines[i].substring(m.end)));
          } else if (items.isNotEmpty) {
            items[items.length - 1] = (items.last.$1, '${items.last.$2} ${lines[i].trim()}');
          }
          i++;
        }
        blocks.add(
          gap(
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                for (final (mark, t) in items)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 6),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        SizedBox(width: 28, child: Text(mark == '-' || mark == '*' ? '•' : mark, style: text)),
                        Expanded(child: Text.rich(_inline(t, text))),
                      ],
                    ),
                  ),
              ],
            ),
          ),
        );
        continue;
      }
      final para = <String>[];
      while (i < lines.length &&
          lines[i].trim().isNotEmpty &&
          !lines[i].startsWith('#') &&
          !lines[i].startsWith('```') &&
          !item.hasMatch(lines[i]) &&
          !lines[i].trimLeft().startsWith('|')) {
        para.add(lines[i++].trim());
      }
      final p = para.join(' ');
      final images = RegExp(r'!\[([^\]]*)\]\((\S+?)(?:\s+"([^"]*)")?\)').allMatches(p).toList();
      final rest = p.replaceAll(RegExp(r'!\[([^\]]*)\]\((\S+?)(?:\s+"([^"]*)")?\)'), '').trim();
      if (rest.isNotEmpty) blocks.add(gap(Text.rich(_inline(rest, text))));
      for (final m in images.where((m) => m.group(3) != 'desktop')) {
        blocks.add(gap(_image(m.group(2)!, m.group(1)!)));
      }
    }
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: blocks);
  }

  Widget _image(String src, String alt) {
    final url = src.startsWith('/') ? '${widget.siteUrl.replaceAll(RegExp(r'/$'), '')}$src' : src;
    return Center(
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 300),
        child: ClipRRect(
          borderRadius: BorderRadius.circular(8),
          child: Image.network(
            url,
            semanticLabel: alt,
            // Offline: the words are enough.
            errorBuilder: (context, _, _) => const SizedBox.shrink(),
          ),
        ),
      ),
    );
  }

  Widget _table(List<List<String>> rows, TextStyle text, ThemeData theme) => SingleChildScrollView(
    scrollDirection: Axis.horizontal,
    child: Table(
      defaultColumnWidth: const IntrinsicColumnWidth(),
      border: TableBorder(horizontalInside: BorderSide(color: theme.dividerColor)),
      children: [
        for (final (n, r) in rows.indexed)
          TableRow(
            children: [
              for (final c in r)
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
                  child: Text.rich(_inline(c, n == 0 ? text.copyWith(fontWeight: FontWeight.w600, fontSize: 14) : text.copyWith(fontSize: 14))),
                ),
            ],
          ),
      ],
    ),
  );

  /// **bold**, `code`, [text](href).
  InlineSpan _inline(String s, TextStyle base) {
    final spans = <InlineSpan>[];
    final re = RegExp(r'\*\*(.+?)\*\*|`([^`]+)`|\[([^\]]+)\]\(([^)\s]+)\)');
    var at = 0;
    for (final m in re.allMatches(s)) {
      if (m.start > at) spans.add(TextSpan(text: s.substring(at, m.start)));
      if (m.group(1) != null) {
        spans.add(
          TextSpan(
            children: [_inline(m.group(1)!, base)],
            style: const TextStyle(fontWeight: FontWeight.w600),
          ),
        );
      } else if (m.group(2) != null) {
        spans.add(
          TextSpan(
            text: m.group(2),
            style: const TextStyle(fontFamily: 'monospace', fontSize: 14),
          ),
        );
      } else {
        final href = m.group(4)!;
        final tap = TapGestureRecognizer()..onTap = () => href.startsWith('help:') ? widget.onHelpLink(href.substring(5)) : widget.onLink(href);
        _taps.add(tap);
        spans.add(
          TextSpan(
            text: m.group(3),
            recognizer: tap,
            style: TextStyle(color: Theme.of(context).colorScheme.primary, decoration: TextDecoration.underline, fontWeight: FontWeight.w500),
          ),
        );
      }
      at = m.end;
    }
    if (at < s.length) spans.add(TextSpan(text: s.substring(at)));
    return TextSpan(style: base, children: spans);
  }
}
