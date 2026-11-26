import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/providers.dart';

/// Light / dark / follow the phone (Settings › Appearance). Default: phone.
class ThemeModeNotifier extends Notifier<ThemeMode> {
  static const key = 'theme_mode';

  @override
  ThemeMode build() => ThemeMode.system;

  Future<void> load() async {
    final saved = await ref.read(tokenStoreProvider).read(key);
    state = ThemeMode.values.firstWhere((m) => m.name == saved, orElse: () => ThemeMode.system);
  }

  Future<void> set(ThemeMode mode) async {
    state = mode;
    await ref.read(tokenStoreProvider).write(key, mode == ThemeMode.system ? null : mode.name);
  }
}

final themeModeProvider = NotifierProvider<ThemeModeNotifier, ThemeMode>(ThemeModeNotifier.new);
