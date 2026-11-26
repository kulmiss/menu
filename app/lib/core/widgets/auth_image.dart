import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../providers.dart';
import '../../app/icons.dart';

final _bytesProvider = FutureProvider.autoDispose.family<Uint8List, String>((ref, path) async {
  // Everything that needs the Ref happens before the await (it may be disposed by then).
  final dio = ref.watch(apiProvider).dio;
  final cancel = CancelToken();
  ref.onDispose(() => cancel.cancel('provider disposed'));
  final r = await dio.get<List<int>>(
    path,
    options: Options(responseType: ResponseType.bytes, headers: {'Accept': '*/*'}),
    cancelToken: cancel,
  );
  if (ref.mounted) ref.keepAlive();
  return Uint8List.fromList(r.data ?? const []);
});

/// An image the API serves only with the token (QR codes).
class AuthImage extends ConsumerWidget {
  const AuthImage(this.path, {super.key, this.size = 200, this.semanticLabel});
  final String path;
  final double size;
  final String? semanticLabel;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final bytes = ref.watch(_bytesProvider(path));
    return SizedBox.square(
      dimension: size,
      child: bytes.when(
        data: (b) => Image.memory(b, fit: BoxFit.contain, semanticLabel: semanticLabel, filterQuality: FilterQuality.none),
        loading: () => const Center(child: CircularProgressIndicator(strokeWidth: 2)),
        error: (_, _) => const Icon(AppIcons.imageOff),
      ),
    );
  }
}
