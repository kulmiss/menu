import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';

import 'icons.dart';

/// Tailwind slate scale and a few brand colours. Screens should use
/// [AppTokens] (`context.tokens`) instead; these stay for brand previews.
abstract final class AppColors {
  static const slate50 = Color(0xFFF8FAFC);
  static const slate100 = Color(0xFFF1F5F9);
  static const slate200 = Color(0xFFE2E8F0);
  static const slate300 = Color(0xFFCBD5E1);
  static const slate400 = Color(0xFF94A3B8);
  static const slate500 = Color(0xFF64748B);
  static const slate600 = Color(0xFF475569);
  static const slate700 = Color(0xFF334155);
  static const slate800 = Color(0xFF1E293B);
  static const slate900 = Color(0xFF0F172A);
  static const slate950 = Color(0xFF020617);
  static const teal = Color(0xFF0F766E);
  static const tealLight = Color(0xFF14B8A6);
  static const danger = Color(0xFFB91C1C);
  static const dangerDark = Color(0xFFF87171);
  static const success = Color(0xFF15803D);
  static const warning = Color(0xFFB45309);
}

double _luminance(Color c) => c.computeLuminance();

/// WCAG contrast ratio between two colours.
double contrastRatio(Color a, Color b) {
  final la = _luminance(a), lb = _luminance(b);
  final hi = la > lb ? la : lb, lo = la > lb ? lb : la;
  return (hi + 0.05) / (lo + 0.05);
}

/// The restaurant's brand colour when it reads on white (≥ 4.5:1), else teal.
Color accentFor(Color? brand) => brand != null && contrastRatio(brand, Colors.white) >= 4.5 ? brand : AppColors.teal;

/// Parses #RRGGBB.
Color? parseHex(String? hex) {
  if (hex == null) return null;
  final h = hex.replaceFirst('#', '');
  if (h.length != 6) return null;
  final v = int.tryParse(h, radix: 16);
  return v == null ? null : Color(0xFF000000 | v);
}

/// The web admin's shadcn design tokens (frontend/src/styles/theme.css,
/// `:root` and `.dark`), converted from OKLCH to sRGB. One place for both
/// themes; the primary is the web slate everywhere (no tenant accent).
@immutable
class AppTokens extends ThemeExtension<AppTokens> {
  const AppTokens({
    required this.background,
    required this.foreground,
    required this.card,
    required this.cardForeground,
    required this.primary,
    required this.primaryForeground,
    required this.secondary,
    required this.secondaryForeground,
    required this.muted,
    required this.mutedForeground,
    required this.accent,
    required this.accentForeground,
    required this.destructive,
    required this.destructiveForeground,
    required this.border,
    required this.input,
    required this.ring,
    required this.success,
    required this.warning,
    required this.warningSurface,
  });

  final Color background, foreground, card, cardForeground;
  final Color primary, primaryForeground, secondary, secondaryForeground;
  final Color muted, mutedForeground, accent, accentForeground;
  final Color destructive, destructiveForeground, border, input, ring;

  /// Not in theme.css: status colours the web takes from Tailwind.
  final Color success, warning, warningSurface;

  /// `--radius: 0.625rem` and the derived radii.
  static const double radius = 10;
  static const double radiusSm = radius - 4;
  static const double radiusMd = radius - 2;
  static const double radiusLg = radius;
  static const double radiusXl = radius + 4;

  /// Screen padding and minimum touch target.
  static const double pad = 16;
  static const double touch = 48;

  static const light = AppTokens(
    background: Color(0xFFFFFFFF), // oklch(1 0 0)
    foreground: Color(0xFF020618), // oklch(0.129 0.042 264.695)
    card: Color(0xFFFFFFFF),
    cardForeground: Color(0xFF020618),
    primary: Color(0xFF0F172B), // oklch(0.208 0.042 265.755)
    primaryForeground: Color(0xFFF8FAFC), // oklch(0.984 0.003 247.858)
    secondary: Color(0xFFF1F5F9), // oklch(0.968 0.007 247.896)
    secondaryForeground: Color(0xFF0F172B),
    muted: Color(0xFFF1F5F9),
    mutedForeground: Color(0xFF62748E), // oklch(0.554 0.046 257.417)
    accent: Color(0xFFF1F5F9),
    accentForeground: Color(0xFF0F172B),
    destructive: Color(0xFFE7000B), // oklch(0.577 0.245 27.325)
    destructiveForeground: Color(0xFFFFFFFF),
    border: Color(0xFFE2E8F0), // oklch(0.929 0.013 255.508)
    input: Color(0xFFE2E8F0),
    ring: Color(0xFF90A1B9), // oklch(0.704 0.04 256.788)
    success: Color(0xFF15803D),
    warning: Color(0xFF92400E),
    warningSurface: Color(0xFFFEF3C7),
  );

  static const dark = AppTokens(
    background: Color(0xFF020618), // oklch(0.129 0.042 264.695)
    foreground: Color(0xFFF8FAFC), // oklch(0.984 0.003 247.858)
    card: Color(0xFF020919), // oklch(0.14 0.04 259.21)
    cardForeground: Color(0xFFF8FAFC),
    primary: Color(0xFFE2E8F0), // oklch(0.929 0.013 255.508)
    primaryForeground: Color(0xFF0F172B), // oklch(0.208 0.042 265.755)
    secondary: Color(0xFF1D293D), // oklch(0.279 0.041 260.031)
    secondaryForeground: Color(0xFFF8FAFC),
    muted: Color(0xFF1D293D),
    mutedForeground: Color(0xFF90A1B9), // oklch(0.704 0.04 256.788)
    accent: Color(0xFF1D293D),
    accentForeground: Color(0xFFF8FAFC),
    destructive: Color(0xFFFF6467), // oklch(0.704 0.191 22.216)
    destructiveForeground: Color(0xFF020618),
    border: Color(0x1AFFFFFF), // oklch(1 0 0 / 10%)
    input: Color(0x26FFFFFF), // oklch(1 0 0 / 15%)
    ring: Color(0xFF6A7282), // oklch(0.551 0.027 264.364)
    success: Color(0xFF4ADE80),
    warning: Color(0xFFFCD34D),
    warningSurface: Color(0xFF3A2A06),
  );

  @override
  AppTokens copyWith() => this;

  @override
  AppTokens lerp(AppTokens? other, double t) {
    if (other == null) return this;
    Color c(Color a, Color b) => Color.lerp(a, b, t)!;
    return AppTokens(
      background: c(background, other.background),
      foreground: c(foreground, other.foreground),
      card: c(card, other.card),
      cardForeground: c(cardForeground, other.cardForeground),
      primary: c(primary, other.primary),
      primaryForeground: c(primaryForeground, other.primaryForeground),
      secondary: c(secondary, other.secondary),
      secondaryForeground: c(secondaryForeground, other.secondaryForeground),
      muted: c(muted, other.muted),
      mutedForeground: c(mutedForeground, other.mutedForeground),
      accent: c(accent, other.accent),
      accentForeground: c(accentForeground, other.accentForeground),
      destructive: c(destructive, other.destructive),
      destructiveForeground: c(destructiveForeground, other.destructiveForeground),
      border: c(border, other.border),
      input: c(input, other.input),
      ring: c(ring, other.ring),
      success: c(success, other.success),
      warning: c(warning, other.warning),
      warningSurface: c(warningSurface, other.warningSurface),
    );
  }
}

extension AppTokensX on BuildContext {
  AppTokens get tokens => Theme.of(this).extension<AppTokens>() ?? AppTokens.light;
}

ThemeData buildTheme(Brightness brightness) {
  final dark = brightness == Brightness.dark;
  final t = dark ? AppTokens.dark : AppTokens.light;
  // Opaque border for places that can't take alpha (dark border is 10% white).
  final solidBorder = Color.alphaBlend(t.border, t.background);
  final scheme = ColorScheme(
    brightness: brightness,
    primary: t.primary,
    onPrimary: t.primaryForeground,
    primaryContainer: t.secondary,
    onPrimaryContainer: t.secondaryForeground,
    secondary: t.secondary,
    onSecondary: t.secondaryForeground,
    secondaryContainer: t.secondary,
    onSecondaryContainer: t.secondaryForeground,
    tertiary: t.success,
    onTertiary: t.background,
    surface: t.card,
    onSurface: t.foreground,
    onSurfaceVariant: t.mutedForeground,
    surfaceContainerLowest: t.background,
    surfaceContainerLow: t.background,
    surfaceContainer: t.muted,
    surfaceContainerHigh: t.muted,
    surfaceContainerHighest: t.muted,
    outline: Color.alphaBlend(t.input, t.background),
    outlineVariant: solidBorder,
    error: t.destructive,
    onError: t.destructiveForeground,
    errorContainer: t.destructive.withValues(alpha: 0.1),
    onErrorContainer: t.destructive,
    inverseSurface: t.foreground,
    onInverseSurface: t.background,
    shadow: Colors.black,
    scrim: Colors.black54,
    surfaceTint: Colors.transparent,
  );
  final radius = BorderRadius.circular(AppTokens.radiusMd);
  final shape = RoundedRectangleBorder(borderRadius: radius);
  final border = OutlineInputBorder(
    borderRadius: radius,
    borderSide: BorderSide(color: t.input),
  );
  const label = TextStyle(fontFamily: 'Inter', fontWeight: FontWeight.w500, fontSize: 15);
  const touch = Size(AppTokens.touch, AppTokens.touch);
  final base = ThemeData(useMaterial3: true, colorScheme: scheme, fontFamily: 'Inter', brightness: brightness);
  final text = base.textTheme.copyWith(
    headlineMedium: base.textTheme.headlineMedium?.copyWith(fontSize: 28, fontWeight: FontWeight.w700, letterSpacing: -0.5),
    headlineSmall: base.textTheme.headlineSmall?.copyWith(fontSize: 24, fontWeight: FontWeight.w700, letterSpacing: -0.4),
    titleLarge: base.textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w600, letterSpacing: -0.2),
    titleMedium: base.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w600),
  );
  return base.copyWith(
    extensions: [t],
    textTheme: text,
    scaffoldBackgroundColor: t.background,
    canvasColor: t.background,
    splashFactory: InkSparkle.constantTurbulenceSeedSplashFactory,
    visualDensity: VisualDensity.standard,
    materialTapTargetSize: MaterialTapTargetSize.padded,
    focusColor: t.ring.withValues(alpha: 0.5),
    iconTheme: IconThemeData(color: t.foreground, size: 22),
    actionIconTheme: ActionIconThemeData(backButtonIconBuilder: (_) => const Icon(AppIcons.arrowLeft), closeButtonIconBuilder: (_) => const Icon(AppIcons.x)),
    appBarTheme: AppBarTheme(
      backgroundColor: t.background,
      foregroundColor: t.foreground,
      surfaceTintColor: Colors.transparent,
      elevation: 0,
      scrolledUnderElevation: 0,
      centerTitle: false,
      titleTextStyle: TextStyle(fontFamily: 'Inter', fontSize: 20, fontWeight: FontWeight.w700, letterSpacing: -0.3, color: t.foreground),
      shape: Border(bottom: BorderSide(color: t.border)),
    ),
    cardTheme: CardThemeData(
      color: t.card,
      elevation: 0,
      margin: EdgeInsets.zero,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(AppTokens.radiusXl),
        side: BorderSide(color: t.border),
      ),
    ),
    dividerTheme: DividerThemeData(color: t.border, space: 1, thickness: 1),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: dark ? t.input.withValues(alpha: 0.06) : Colors.transparent,
      border: border,
      enabledBorder: border,
      // The web's ring is 2.6:1 on white; primary keeps the focus outline ≥ 3:1.
      focusedBorder: border.copyWith(borderSide: BorderSide(color: t.primary, width: 2)),
      errorBorder: border.copyWith(borderSide: BorderSide(color: t.destructive)),
      focusedErrorBorder: border.copyWith(borderSide: BorderSide(color: t.destructive, width: 2)),
      hintStyle: TextStyle(color: t.mutedForeground),
      helperStyle: TextStyle(color: t.mutedForeground),
      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 14),
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        backgroundColor: t.primary,
        foregroundColor: t.primaryForeground,
        minimumSize: const Size(64, AppTokens.touch),
        shape: shape,
        elevation: 0,
        textStyle: label,
      ),
    ),
    elevatedButtonTheme: ElevatedButtonThemeData(
      style: ElevatedButton.styleFrom(
        backgroundColor: t.secondary,
        foregroundColor: t.secondaryForeground,
        minimumSize: const Size(64, AppTokens.touch),
        shape: shape,
        elevation: 0,
        textStyle: label,
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        minimumSize: const Size(64, AppTokens.touch),
        shape: shape,
        side: BorderSide(color: t.input),
        foregroundColor: t.foreground,
        textStyle: label,
      ),
    ),
    textButtonTheme: TextButtonThemeData(
      style: TextButton.styleFrom(minimumSize: touch, shape: shape, foregroundColor: t.foreground, textStyle: label),
    ),
    iconButtonTheme: IconButtonThemeData(
      style: IconButton.styleFrom(minimumSize: touch, shape: shape),
    ),
    listTileTheme: ListTileThemeData(
      minVerticalPadding: 10,
      minTileHeight: AppTokens.touch + 8,
      iconColor: t.mutedForeground,
      subtitleTextStyle: TextStyle(fontFamily: 'Inter', fontSize: 14, color: t.mutedForeground),
      shape: shape,
    ),
    navigationBarTheme: NavigationBarThemeData(
      backgroundColor: t.background,
      indicatorColor: t.secondary,
      indicatorShape: shape,
      surfaceTintColor: Colors.transparent,
      elevation: 0,
      height: 68,
      iconTheme: WidgetStateProperty.resolveWith((s) => IconThemeData(color: s.contains(WidgetState.selected) ? t.foreground : t.mutedForeground, size: 22)),
      labelTextStyle: WidgetStateProperty.resolveWith(
        (s) => TextStyle(
          fontFamily: 'Inter',
          fontSize: 12,
          fontWeight: s.contains(WidgetState.selected) ? FontWeight.w600 : FontWeight.w500,
          color: s.contains(WidgetState.selected) ? t.foreground : t.mutedForeground,
        ),
      ),
    ),
    navigationRailTheme: NavigationRailThemeData(
      backgroundColor: t.background,
      indicatorColor: t.secondary,
      indicatorShape: shape,
      labelType: NavigationRailLabelType.all,
    ),
    chipTheme: ChipThemeData(
      shape: RoundedRectangleBorder(
        borderRadius: radius,
        side: BorderSide(color: t.input),
      ),
      backgroundColor: t.background,
      selectedColor: t.secondary,
      side: BorderSide(color: t.input),
    ),
    tabBarTheme: TabBarThemeData(
      labelColor: t.foreground,
      unselectedLabelColor: t.mutedForeground,
      indicatorColor: t.foreground,
      dividerColor: t.border,
      labelStyle: label.copyWith(fontSize: 14),
    ),
    bottomSheetTheme: BottomSheetThemeData(
      backgroundColor: t.background,
      surfaceTintColor: Colors.transparent,
      showDragHandle: true,
      dragHandleColor: t.muted,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(AppTokens.radiusXl))),
    ),
    dialogTheme: DialogThemeData(
      backgroundColor: t.background,
      surfaceTintColor: Colors.transparent,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(AppTokens.radiusLg),
        side: BorderSide(color: t.border),
      ),
    ),
    popupMenuTheme: PopupMenuThemeData(
      color: t.background,
      surfaceTintColor: Colors.transparent,
      shape: RoundedRectangleBorder(
        borderRadius: radius,
        side: BorderSide(color: t.border),
      ),
    ),
    snackBarTheme: SnackBarThemeData(
      behavior: SnackBarBehavior.floating,
      backgroundColor: t.background,
      contentTextStyle: TextStyle(fontFamily: 'Inter', fontSize: 14, color: t.foreground),
      actionTextColor: t.foreground,
      elevation: 4,
      shape: RoundedRectangleBorder(
        borderRadius: radius,
        side: BorderSide(color: t.border),
      ),
    ),
    floatingActionButtonTheme: FloatingActionButtonThemeData(
      backgroundColor: t.primary,
      foregroundColor: t.primaryForeground,
      elevation: 2,
      focusElevation: 2,
      hoverElevation: 3,
      highlightElevation: 3,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(AppTokens.radiusLg)),
      extendedTextStyle: label,
    ),
    progressIndicatorTheme: ProgressIndicatorThemeData(color: t.primary, linearTrackColor: t.secondary),
    checkboxTheme: CheckboxThemeData(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(4)),
      side: BorderSide(color: t.primary),
      fillColor: WidgetStateProperty.resolveWith((s) => s.contains(WidgetState.selected) ? t.primary : Colors.transparent),
      checkColor: WidgetStatePropertyAll(t.primaryForeground),
    ),
    radioTheme: RadioThemeData(fillColor: WidgetStatePropertyAll(t.primary)),
    switchTheme: SwitchThemeData(
      trackOutlineColor: const WidgetStatePropertyAll(Colors.transparent),
      // shadcn: thumb is background (light) / foreground (dark) when off, primary-foreground when on.
      thumbColor: WidgetStateProperty.resolveWith((s) => s.contains(WidgetState.selected) ? t.primaryForeground : (dark ? t.foreground : t.background)),
      thumbIcon: const WidgetStatePropertyAll(Icon(null)),
      trackColor: WidgetStateProperty.resolveWith((s) => s.contains(WidgetState.selected) ? t.primary : Color.alphaBlend(t.input, t.background)),
    ),
    segmentedButtonTheme: SegmentedButtonThemeData(
      style: SegmentedButton.styleFrom(
        selectedBackgroundColor: t.secondary,
        selectedForegroundColor: t.foreground,
        side: BorderSide(color: t.input),
        shape: shape,
      ),
    ),
    pageTransitionsTheme: const PageTransitionsTheme(
      builders: {TargetPlatform.android: FadeForwardsPageTransitionsBuilder(), TargetPlatform.iOS: CupertinoPageTransitionsBuilder()},
    ),
  );
}
