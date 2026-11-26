import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:open_filex/open_filex.dart';
import 'package:share_plus/share_plus.dart';

import '../providers.dart';
import '../widgets/kit.dart';

/// Downloads a PDF/CSV/PNG with the token, then opens it in the phone's
/// viewer or shares it (QUESTIONS M18: no PDF engine inside the app).
Future<void> downloadAndOpen(BuildContext context, WidgetRef ref, String path, String fileName, {bool share = false, Map<String, dynamic>? query}) async {
  final messenger = ScaffoldMessenger.of(context);
  messenger.showSnackBar(SnackBar(content: Text(context.l.loading), duration: const Duration(seconds: 20)));
  try {
    final file = await ref.read(apiProvider).download(path, fileName, query: query);
    messenger.hideCurrentSnackBar();
    if (share) {
      await SharePlus.instance.share(ShareParams(files: [XFile(file.path)], subject: fileName));
    } else {
      final r = await OpenFilex.open(file.path);
      if (r.type != ResultType.done) await SharePlus.instance.share(ShareParams(files: [XFile(file.path)], subject: fileName));
    }
  } catch (e) {
    messenger.hideCurrentSnackBar();
    if (context.mounted) showSnack(context, errorText(context, e));
  }
}

Future<void> shareText(String text, {String? subject}) => SharePlus.instance.share(ShareParams(text: text, subject: subject));
