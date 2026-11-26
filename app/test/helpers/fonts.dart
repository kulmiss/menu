import 'dart:io';

import 'package:flutter/services.dart';

/// Real fonts for goldens and screenshots: Inter, Lucide icons, Material icons.
Future<void> loadTestFonts() async {
  final inter = FontLoader('Inter');
  for (final w in ['Regular', 'Medium', 'SemiBold', 'Bold']) {
    inter.addFont(rootBundle.load('assets/fonts/Inter-$w.ttf'));
  }
  await inter.load();
  await (FontLoader('Lucide')..addFont(rootBundle.load('assets/fonts/Lucide.ttf'))).load();
  final root = Platform.environment['FLUTTER_ROOT'] ?? '/opt/sdk/flutter';
  final icons = File('$root/bin/cache/artifacts/material_fonts/MaterialIcons-Regular.otf');
  if (icons.existsSync()) {
    await (FontLoader('MaterialIcons')..addFont(Future.value(ByteData.sublistView(icons.readAsBytesSync())))).load();
  }
}
