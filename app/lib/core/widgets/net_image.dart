import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../providers.dart';
import '../../app/icons.dart';

/// A cached photo; relative URLs are resolved against the server.
class NetImage extends ConsumerWidget {
  const NetImage(this.url, {super.key, this.size = 48, this.width, this.height, this.radius = 8, this.fit = BoxFit.cover, this.semanticLabel});
  final String? url;
  final double size;
  final double? width;
  final double? height;
  final double radius;
  final BoxFit fit;
  final String? semanticLabel;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final w = width ?? size, h = height ?? size;
    final placeholder = Container(
      width: w,
      height: h,
      decoration: BoxDecoration(color: Theme.of(context).colorScheme.surfaceContainerHigh, borderRadius: BorderRadius.circular(radius)),
      child: Icon(AppIcons.image, size: (w < h ? w : h) * .4, color: Theme.of(context).colorScheme.onSurfaceVariant),
    );
    if (url == null || url!.isEmpty) return placeholder;
    final full = url!.startsWith('http') ? url! : '${ref.read(apiProvider).siteUrl}${url!.replaceFirst(RegExp('^/'), '')}';
    final dpr = MediaQuery.devicePixelRatioOf(context);
    return ClipRRect(
      borderRadius: BorderRadius.circular(radius),
      child: CachedNetworkImage(
        imageUrl: full,
        width: w,
        height: h,
        fit: fit,
        memCacheWidth: (w * dpr).round(),
        fadeInDuration: MediaQuery.of(context).disableAnimations ? Duration.zero : const Duration(milliseconds: 150),
        placeholder: (_, _) => placeholder,
        errorWidget: (_, _, _) => placeholder,
      ),
    );
  }
}
