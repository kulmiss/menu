import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:menu_admin/app/theme.dart';
import 'package:menu_admin/app/theme_mode.dart';
import 'package:menu_admin/core/auth/token_store.dart';
import 'package:menu_admin/core/providers.dart';

void main() {
  for (final (name, t) in [('light', AppTokens.light), ('dark', AppTokens.dark)]) {
    test('$name tokens meet WCAG AA for text', () {
      Color solid(Color c) => Color.alphaBlend(c, t.background);
      expect(contrastRatio(t.foreground, t.background), greaterThanOrEqualTo(4.5));
      expect(contrastRatio(t.cardForeground, t.card), greaterThanOrEqualTo(4.5));
      expect(contrastRatio(t.mutedForeground, t.background), greaterThanOrEqualTo(4.5));
      expect(contrastRatio(t.mutedForeground, t.muted), greaterThanOrEqualTo(4.0)); // small labels on chips
      expect(contrastRatio(t.primaryForeground, t.primary), greaterThanOrEqualTo(4.5));
      expect(contrastRatio(t.secondaryForeground, t.secondary), greaterThanOrEqualTo(4.5));
      expect(contrastRatio(t.destructive, t.background), greaterThanOrEqualTo(4.5));
      expect(contrastRatio(t.destructiveForeground, t.destructive), greaterThanOrEqualTo(4.5));
      expect(contrastRatio(t.warning, t.warningSurface), greaterThanOrEqualTo(4.5));
      expect(contrastRatio(t.foreground, t.warningSurface), greaterThanOrEqualTo(4.5));
      expect(contrastRatio(t.success, t.background), greaterThanOrEqualTo(4.5));
      // WCAG 1.4.11: the focus outline must reach 3:1. The web's light ring is
      // 2.6:1, so focused inputs use the primary colour instead (see buildTheme).
      expect(solid(t.primary), isNotNull);
      expect(contrastRatio(t.primary, t.background), greaterThanOrEqualTo(3.0));
    });
  }

  test('theme carries the tokens and the web radius', () {
    final light = buildTheme(Brightness.light), dark = buildTheme(Brightness.dark);
    expect(light.extension<AppTokens>(), AppTokens.light);
    expect(dark.extension<AppTokens>(), AppTokens.dark);
    expect(light.colorScheme.primary, const Color(0xFF0F172B));
    expect(dark.colorScheme.primary, const Color(0xFFE2E8F0));
    expect(AppTokens.radius, 10);
  });

  test('theme choice is saved and restored', () async {
    final store = MemoryTokenStore();
    final c = ProviderContainer(overrides: [tokenStoreProvider.overrideWithValue(store)]);
    addTearDown(c.dispose);
    expect(c.read(themeModeProvider), ThemeMode.system);
    await c.read(themeModeProvider.notifier).set(ThemeMode.dark);
    expect(store.values[ThemeModeNotifier.key], 'dark');
    final c2 = ProviderContainer(overrides: [tokenStoreProvider.overrideWithValue(store)]);
    addTearDown(c2.dispose);
    await c2.read(themeModeProvider.notifier).load();
    expect(c2.read(themeModeProvider), ThemeMode.dark);
    await c2.read(themeModeProvider.notifier).set(ThemeMode.system);
    expect(store.values.containsKey(ThemeModeNotifier.key), isFalse);
  });
}
