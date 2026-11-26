import 'dart:io';
import 'dart:typed_data';
import 'dart:ui' as ui;

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:path_provider/path_provider.dart';
import 'package:share_plus/share_plus.dart';

import '../../app/theme.dart';
import '../../core/cache/swr.dart';
import '../../core/providers.dart';
import '../../core/util/files.dart';
import '../../core/util/json.dart';
import '../../core/widgets/auth_image.dart';
import '../../core/widgets/kit.dart';

String qrKindLabel(BuildContext c, String kind) => switch (kind) {
  'restaurant' => c.l.qrRestaurant,
  'branch' => c.l.qrBranch,
  'table' => c.l.qrTable,
  _ => c.l.qrExtra,
};

/// QR codes for the restaurant, branches, tables and extra places.
class QrScreen extends ConsumerWidget {
  const QrScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final t = ref.watch(tenantProvider)!;
    final path = tenantPath(t.id, 'qr-codes');
    final l = context.l;
    return Scaffold(
      appBar: AppBar(
        title: Text(l.qrCodes),
        actions: [
          IconButton(
            tooltip: l.menuPdf,
            icon: const Icon(AppIcons.fileText),
            onPressed: () =>
                t.feature('pdf_menu') ? downloadAndOpen(context, ref, tenantPath(t.id, 'menu-pdf'), '${t.slug}-menu.pdf') : showUpgradeSheet(context),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        icon: const Icon(AppIcons.plus),
        label: Text(l.newQr),
        onPressed: () async {
          final label = await showDialog<String>(
            context: context,
            builder: (c) {
              final ctl = TextEditingController();
              return AlertDialog(
                title: Text(l.newQr),
                content: TextField(
                  controller: ctl,
                  autofocus: true,
                  maxLength: 60,
                  decoration: InputDecoration(labelText: l.qrLabel),
                ),
                actions: [
                  TextButton(onPressed: () => Navigator.pop(c), child: Text(l.cancel)),
                  FilledButton(onPressed: () => Navigator.pop(c, ctl.text.trim()), child: Text(l.add)),
                ],
              );
            },
          );
          if (label == null || label.isEmpty || !context.mounted) return;
          final api = ref.read(apiProvider), container = context.container;
          await runOk(context, () => api.post(path, data: {'label': label}));
          container.invalidate(cachedProvider(path));
        },
      ),
      body: CachedView(
        path: path,
        builder: (context, data, _) {
          final codes = J.dataList(data);
          return GridView.builder(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 96),
            gridDelegate: SliverGridDelegateWithMaxCrossAxisExtent(
              maxCrossAxisExtent: 260,
              // Fixed parts (code image, button) + the two text lines, which grow with the text size.
              mainAxisExtent: 244 + MediaQuery.textScalerOf(context).scale(56),
              crossAxisSpacing: 12,
              mainAxisSpacing: 12,
            ),
            itemCount: codes.length,
            itemBuilder: (context, i) {
              final q = codes[i];
              final id = q.i('id');
              final name = q.s('description', q.s('label', qrKindLabel(context, q.s('kind'))));
              return Card(
                child: Padding(
                  padding: const EdgeInsets.all(12),
                  child: Column(
                    children: [
                      Container(
                        color: Colors.white,
                        padding: const EdgeInsets.all(6),
                        child: AuthImage(tenantPath(t.id, 'qr-codes/$id.png'), size: 150, semanticLabel: name),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        name,
                        maxLines: 2,
                        textAlign: TextAlign.center,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(fontWeight: FontWeight.w600),
                      ),
                      Text(
                        '${qrKindLabel(context, q.s('kind'))} · ${q.s('branch')}',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(fontSize: 12, color: context.colors.onSurfaceVariant),
                      ),
                      const Spacer(),
                      PopupMenuButton<String>(
                        tooltip: l.share,
                        icon: const Icon(AppIcons.share),
                        onSelected: (v) async {
                          if (v == 'delete') {
                            if (await confirm(context, title: l.confirmDeleteTitle(name), action: l.delete, danger: true) && context.mounted) {
                              final api = ref.read(apiProvider), container = context.container;
                              await runOk(context, () => api.delete(tenantPath(t.id, 'qr-codes/$id')));
                              container.invalidate(cachedProvider(path));
                            }
                            return;
                          }
                          if (!context.mounted) return;
                          await downloadAndOpen(context, ref, tenantPath(t.id, 'qr-codes/$id.$v'), '${t.slug}-${q.s('code')}.$v', share: v != 'pdf');
                        },
                        itemBuilder: (c) => [
                          PopupMenuItem(value: 'png', child: Text(l.downloadPng)),
                          PopupMenuItem(value: 'svg', child: Text(l.downloadSvg)),
                          PopupMenuItem(value: 'pdf', child: Text(l.downloadPdf)),
                          if (q.s('kind') == 'extra')
                            PopupMenuItem(
                              value: 'delete',
                              child: Text(l.delete, style: TextStyle(color: c.colors.error)),
                            ),
                        ],
                      ),
                    ],
                  ),
                ),
              );
            },
          );
        },
      ),
    );
  }
}

/// Print materials (PDF made on the server) and social media pictures (drawn on the phone).
class PrintScreen extends ConsumerStatefulWidget {
  const PrintScreen({super.key});
  @override
  ConsumerState<PrintScreen> createState() => _PrintScreenState();
}

class _PrintScreenState extends ConsumerState<PrintScreen> {
  String _material = 'tent';
  String _design = 'classic';
  bool _dark = false;
  bool _bleed = false;
  final _headline = TextEditingController();
  Set<int>? _codes;

  String _materialLabel(String k) => switch (k) {
    'tent' => context.l.material_tent,
    'poster_a5' => context.l.material_poster_a5,
    'poster_a4' => context.l.material_poster_a4,
    'window' => context.l.material_window,
    'sticker_round' => context.l.material_sticker_round,
    _ => context.l.material_sticker_square,
  };

  @override
  void dispose() {
    _headline.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = ref.watch(tenantProvider)!;
    final l = context.l;
    final codesV = ref.watch(cachedProvider(tenantPath(t.id, 'qr-codes'))).value;
    final info = ref.watch(cachedProvider(tenantPath(t.id, 'print-materials'))).value;
    final codes = codesV == null ? <J>[] : J.dataList(codesV.data);
    _codes ??= {for (final c in codes.where((c) => c.s('kind') != 'table').take(1)) c.i('id')};
    final infoJ = info == null ? null : J.data(info.data);
    return Scaffold(
      appBar: AppBar(title: Text(l.printMaterials)),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 40),
        children: [
          DropdownButtonFormField<String>(
            isExpanded: true,
            initialValue: _material,
            decoration: InputDecoration(labelText: l.material),
            items: [
              for (final m in ['tent', 'poster_a5', 'poster_a4', 'window', 'sticker_round', 'sticker_square'])
                DropdownMenuItem(value: m, child: Text(_materialLabel(m))),
            ],
            onChanged: (v) => setState(() => _material = v!),
          ),
          const SizedBox(height: 12),
          SegmentedButton<String>(
            segments: [
              ButtonSegment(value: 'classic', label: Text(l.design_classic)),
              ButtonSegment(value: 'bold', label: Text(l.design_bold)),
              ButtonSegment(value: 'minimal', label: Text(l.design_minimal)),
            ],
            selected: {_design},
            onSelectionChanged: (s) => setState(() => _design = s.first),
          ),
          SwitchListTile(contentPadding: EdgeInsets.zero, title: Text(l.darkPrint), value: _dark, onChanged: (v) => setState(() => _dark = v)),
          TextField(
            controller: _headline,
            maxLength: 40,
            decoration: InputDecoration(labelText: l.headline, hintText: infoJ?.s('headline')),
          ),
          if (_material.startsWith('poster') || _material == 'window')
            SwitchListTile(contentPadding: EdgeInsets.zero, title: Text(l.bleed), value: _bleed, onChanged: (v) => setState(() => _bleed = v)),
          SectionHeader(l.chooseCodes),
          for (final c in codes)
            CheckboxListTile(
              contentPadding: EdgeInsets.zero,
              value: _codes!.contains(c.i('id')),
              title: Text(c.s('description', qrKindLabel(context, c.s('kind')))),
              onChanged: (v) => setState(() => v == true ? _codes!.add(c.i('id')) : _codes!.remove(c.i('id'))),
            ),
          const SizedBox(height: 12),
          FilledButton.icon(
            onPressed: _codes!.isEmpty
                ? null
                : () => downloadAndOpen(
                    context,
                    ref,
                    tenantPath(t.id, 'print-materials.pdf'),
                    '${t.slug}-$_material.pdf',
                    query: {
                      'material': _material,
                      'design': _design,
                      'theme': _dark ? 'dark' : 'light',
                      if (_headline.text.trim().isNotEmpty) 'headline': _headline.text.trim(),
                      'codes': _codes!.join(','),
                      if (_bleed) 'bleed': '1',
                    },
                  ),
            icon: const Icon(AppIcons.fileText),
            label: Text(l.printPdf),
          ),
          SectionHeader(l.socialImages),
          Row(
            children: [
              Expanded(
                child: OutlinedButton(onPressed: infoJ == null || _codes!.isEmpty ? null : () => _social(infoJ, 1080, 1080), child: Text(l.socialPost)),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: OutlinedButton(onPressed: infoJ == null || _codes!.isEmpty ? null : () => _social(infoJ, 1080, 1920), child: Text(l.socialStory)),
              ),
            ],
          ),
        ],
      ),
    );
  }

  /// Draws the social picture like the web does (brand colour, QR, name).
  Future<void> _social(J info, int w, int h) async {
    final t = ref.read(tenantProvider)!;
    try {
      final r = await ref
          .read(apiProvider)
          .dio
          .get<List<int>>(tenantPath(t.id, 'qr-codes/${_codes!.first}.png'), options: Options(responseType: ResponseType.bytes));
      final qr = await decodeImageFromList(Uint8List.fromList(r.data!));
      final brand = parseHex(info.s('colour')) ?? AppColors.teal;
      final fg = ThemeData.estimateBrightnessForColor(brand) == Brightness.dark ? Colors.white : Colors.black;
      final rec = ui.PictureRecorder();
      final c = Canvas(rec);
      c.drawRect(Rect.fromLTWH(0, 0, w.toDouble(), h.toDouble()), Paint()..color = _dark ? const Color(0xFF0F172A) : brand);
      final card = 640.0;
      final top = (h - card) / 2 + (h > w ? 80 : 60);
      c.drawRRect(RRect.fromRectAndRadius(Rect.fromLTWH((w - card) / 2, top, card, card), const Radius.circular(48)), Paint()..color = Colors.white);
      paintImage(canvas: c, rect: Rect.fromLTWH((w - card) / 2 + 60, top + 60, card - 120, card - 120), image: qr, filterQuality: FilterQuality.none);
      void text(String s, double y, double size, FontWeight weight) {
        final p = TextPainter(
          text: TextSpan(
            text: s,
            style: TextStyle(fontFamily: 'Inter', fontSize: size, fontWeight: weight, color: _dark ? Colors.white : fg),
          ),
          textAlign: TextAlign.center,
          textDirection: TextDirection.ltr,
          maxLines: 2,
        )..layout(maxWidth: w - 160.0);
        p.paint(c, Offset((w - p.width) / 2, y));
      }

      text(info.s('name', t.name), top - (h > w ? 300 : 220), 72, FontWeight.w700);
      text(_headline.text.trim().isEmpty ? info.s('headline') : _headline.text.trim(), top - (h > w ? 160 : 110), 44, FontWeight.w500);
      text(info.s('scan'), top + card + 50, 34, FontWeight.w400);
      final img = await rec.endRecording().toImage(w, h);
      final png = await img.toByteData(format: ui.ImageByteFormat.png);
      final dir = await getTemporaryDirectory();
      final f = File('${dir.path}/${t.slug}-${w}x$h.png')..writeAsBytesSync(png!.buffer.asUint8List());
      await SharePlus.instance.share(ShareParams(files: [XFile(f.path)]));
    } catch (e) {
      if (mounted) showSnack(context, errorText(context, e));
    }
  }
}
