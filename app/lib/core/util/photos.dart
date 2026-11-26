import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_image_compress/flutter_image_compress.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_cropper/image_cropper.dart';
import 'package:image_picker/image_picker.dart';
import 'package:path_provider/path_provider.dart';

import '../widgets/kit.dart';

/// The three phone steps of adding a photo, replaceable in tests.
class PhotoSteps {
  const PhotoSteps({this.pick = _pick, this.crop = _crop, this.shrink = _shrink});

  /// Camera or gallery (Android photo picker: no storage permission). Null: cancelled.
  final Future<String?> Function(ImageSource source) pick;

  /// The crop screen. Null: the user backed out of it.
  final Future<String?> Function(BuildContext context, String path, CropAspectRatio? aspect) crop;

  /// ≤ 2048 px JPEG (QUESTIONS M17). Null: the photo can't be read.
  final Future<String?> Function(String path) shrink;

  static Future<String?> _pick(ImageSource source) async =>
      (await ImagePicker().pickImage(source: source, maxWidth: 4096, maxHeight: 4096, imageQuality: 95, requestFullMetadata: false))?.path;

  static Future<String?> _crop(BuildContext context, String path, CropAspectRatio? aspect) async {
    final colors = Theme.of(context).colorScheme;
    final cropped = await ImageCropper().cropImage(
      sourcePath: path,
      aspectRatio: aspect,
      uiSettings: [
        AndroidUiSettings(
          toolbarTitle: context.l.cropPhoto,
          toolbarColor: colors.surface,
          toolbarWidgetColor: colors.onSurface,
          activeControlsWidgetColor: colors.primary,
          lockAspectRatio: aspect != null,
          initAspectRatio: aspect == null ? CropAspectRatioPreset.original : null,
        ),
        IOSUiSettings(title: context.l.cropPhoto, aspectRatioLockEnabled: aspect != null),
      ],
    );
    return cropped?.path;
  }

  static Future<String?> _shrink(String path) async {
    final dir = await getTemporaryDirectory();
    final out = '${dir.path}/upload_${DateTime.now().millisecondsSinceEpoch}.jpg';
    final r = await FlutterImageCompress.compressAndGetFile(
      path,
      out,
      minWidth: 2048,
      minHeight: 2048,
      quality: 85,
      format: CompressFormat.jpeg,
      keepExif: false,
    );
    return r?.path;
  }
}

final photoStepsProvider = Provider<PhotoSteps>((ref) => const PhotoSteps());

/// Camera or gallery, then crop, then shrink. Null when the user cancels any
/// step (backing out of the crop screen too) or when it fails; a failure
/// (camera access off, a photo that can't be read) is explained in a toast.
Future<File?> pickPhoto(BuildContext context, WidgetRef ref, {ImageSource? source, CropAspectRatio? aspect}) async {
  final steps = ref.read(photoStepsProvider);
  source ??= await showModalBottomSheet<ImageSource>(
    context: context,
    showDragHandle: true,
    builder: (c) => SafeArea(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          ListTile(leading: const Icon(AppIcons.camera), title: Text(c.l.takePhoto), onTap: () => Navigator.pop(c, ImageSource.camera)),
          ListTile(leading: const Icon(AppIcons.images), title: Text(c.l.choosePhoto), onTap: () => Navigator.pop(c, ImageSource.gallery)),
        ],
      ),
    ),
  );
  if (source == null || !context.mounted) return null;
  try {
    final picked = await steps.pick(source);
    if (picked == null || !context.mounted) return null;
    final cropped = await steps.crop(context, picked, aspect);
    if (cropped == null) return null;
    final small = await steps.shrink(cropped);
    if (small == null) throw const FormatException('unreadable photo');
    return File(small);
  } on PlatformException catch (e) {
    if (context.mounted) {
      final denied = e.code.contains('access_denied') || e.code.contains('permission');
      showSnack(context, denied ? context.l.photoAccessDenied : context.l.photoUnreadable, kind: ToastKind.error);
    }
  } catch (_) {
    if (context.mounted) showSnack(context, context.l.photoUnreadable, kind: ToastKind.error);
  }
  return null;
}
