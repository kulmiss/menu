import 'package:flutter/material.dart';

import '../../app/icons.dart';
import '../../app/theme.dart';

export '../../app/icons.dart';

/// shadcn/ui building blocks with the web admin's tokens (redesign 2026-11).
/// Input, Switch, Dialog and Tabs come from the Material theme in buildTheme.

enum ButtonVariant { primary, secondary, outline, ghost, destructive }

/// Button: default / secondary / outline / ghost / destructive, 48 px tall.
class AppButton extends StatelessWidget {
  const AppButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.variant = ButtonVariant.primary,
    this.icon,
    this.loading = false,
    this.expand = false,
  });

  final String label;
  final VoidCallback? onPressed;
  final ButtonVariant variant;
  final IconData? icon;
  final bool loading;
  final bool expand;

  @override
  Widget build(BuildContext context) {
    final t = context.tokens;
    final (bg, fg, side) = switch (variant) {
      ButtonVariant.primary => (t.primary, t.primaryForeground, BorderSide.none),
      ButtonVariant.secondary => (t.secondary, t.secondaryForeground, BorderSide.none),
      ButtonVariant.outline => (Colors.transparent, t.foreground, BorderSide(color: t.input)),
      ButtonVariant.ghost => (Colors.transparent, t.foreground, BorderSide.none),
      ButtonVariant.destructive => (t.destructive, t.destructiveForeground, BorderSide.none),
    };
    final style = TextButton.styleFrom(
      backgroundColor: bg,
      foregroundColor: fg,
      disabledBackgroundColor: bg == Colors.transparent ? null : bg.withValues(alpha: 0.5),
      disabledForegroundColor: fg.withValues(alpha: 0.5),
      overlayColor: variant == ButtonVariant.primary || variant == ButtonVariant.destructive ? fg : t.foreground,
      side: side,
      minimumSize: Size(expand ? double.infinity : 64, AppTokens.touch),
      padding: const EdgeInsets.symmetric(horizontal: 16),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(AppTokens.radiusMd)),
      textStyle: const TextStyle(fontFamily: 'Inter', fontWeight: FontWeight.w500, fontSize: 15),
    );
    final busy = SizedBox.square(dimension: 16, child: CircularProgressIndicator(strokeWidth: 2, color: fg));
    final lead = loading ? busy : (icon == null ? null : Icon(icon, size: 18));
    final text = Text(label, overflow: TextOverflow.ellipsis);
    return TextButton(
      style: style,
      onPressed: loading ? null : onPressed,
      child: lead == null
          ? text
          : Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                lead,
                const SizedBox(width: 8),
                Flexible(child: text),
              ],
            ),
    );
  }
}

enum BadgeVariant { primary, secondary, outline, destructive, success, warning }

/// Badge: small rounded label.
class AppBadge extends StatelessWidget {
  const AppBadge(this.label, {super.key, this.variant = BadgeVariant.secondary, this.icon});
  final String label;
  final BadgeVariant variant;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    final t = context.tokens;
    final (bg, fg, border) = switch (variant) {
      BadgeVariant.primary => (t.primary, t.primaryForeground, Colors.transparent),
      BadgeVariant.secondary => (t.secondary, t.secondaryForeground, Colors.transparent),
      BadgeVariant.outline => (Colors.transparent, t.foreground, t.border),
      BadgeVariant.destructive => (t.destructive, t.destructiveForeground, Colors.transparent),
      BadgeVariant.success => (t.success.withValues(alpha: 0.12), t.success, Colors.transparent),
      BadgeVariant.warning => (t.warningSurface, t.warning, Colors.transparent),
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
      decoration: BoxDecoration(
        color: bg,
        border: Border.all(color: border),
        borderRadius: BorderRadius.circular(AppTokens.radiusMd),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (icon != null) ...[Icon(icon, size: 12, color: fg), const SizedBox(width: 4)],
          Text(
            label,
            style: TextStyle(fontFamily: 'Inter', fontSize: 12, fontWeight: FontWeight.w500, color: fg),
          ),
        ],
      ),
    );
  }
}

/// Card: flat, 1 px border, xl radius, 16 px padding.
class AppCard extends StatelessWidget {
  const AppCard({super.key, required this.child, this.onTap, this.padding = const EdgeInsets.all(AppTokens.pad), this.color});
  final Widget child;
  final VoidCallback? onTap;
  final EdgeInsets padding;
  final Color? color;

  @override
  Widget build(BuildContext context) {
    final t = context.tokens;
    final radius = BorderRadius.circular(AppTokens.radiusXl);
    return Material(
      color: color ?? t.card,
      shape: RoundedRectangleBorder(
        borderRadius: radius,
        side: BorderSide(color: t.border),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Padding(padding: padding, child: child),
      ),
    );
  }
}

/// Sheet: a bottom sheet for quick edits with a title, optional description,
/// and room for the keyboard.
Future<T?> showAppSheet<T>(BuildContext context, {required String title, String? description, required WidgetBuilder builder}) {
  return showModalBottomSheet<T>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    builder: (c) {
      final t = c.tokens;
      return Padding(
        padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(c).bottom),
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(AppTokens.pad, 0, AppTokens.pad, AppTokens.pad),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Semantics(header: true, child: Text(title, style: Theme.of(c).textTheme.titleLarge)),
              if (description != null) ...[const SizedBox(height: 4), Text(description, style: TextStyle(color: t.mutedForeground))],
              const SizedBox(height: 16),
              builder(c),
            ],
          ),
        ),
      );
    },
  );
}

enum ToastKind { info, success, error }

/// Toast (Sonner-like): a bordered card floating at the bottom, with an icon.
void showToast(BuildContext context, String message, {ToastKind kind = ToastKind.info, SnackBarAction? action}) {
  final messenger = ScaffoldMessenger.maybeOf(context);
  if (messenger == null) return;
  final t = context.tokens;
  final (icon, color) = switch (kind) {
    ToastKind.info => (AppIcons.info, t.foreground),
    ToastKind.success => (AppIcons.circleCheck, t.success),
    ToastKind.error => (AppIcons.circleAlert, t.destructive),
  };
  messenger
    ..hideCurrentSnackBar()
    ..showSnackBar(
      SnackBar(
        content: Row(
          children: [
            Icon(icon, size: 18, color: color),
            const SizedBox(width: 10),
            Expanded(child: Text(message)),
          ],
        ),
        action: action,
      ),
    );
}

/// One skeleton block (for custom loading layouts; lists use [Skeleton]).
class SkeletonBox extends StatelessWidget {
  const SkeletonBox({super.key, this.width, this.height = 16, this.radius = AppTokens.radiusMd});
  final double? width;
  final double height;
  final double radius;

  @override
  Widget build(BuildContext context) => Container(
    width: width,
    height: height,
    decoration: BoxDecoration(color: context.tokens.muted, borderRadius: BorderRadius.circular(radius)),
  );
}

/// Large screen title (shadcn page heading) with an optional line under it.
class PageTitle extends StatelessWidget {
  const PageTitle(this.title, {super.key, this.subtitle, this.trailing});
  final String title;
  final String? subtitle;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    final t = context.tokens;
    return Padding(
      padding: const EdgeInsets.fromLTRB(AppTokens.pad, 8, AppTokens.pad, 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Semantics(
                  header: true,
                  child: Text(title, style: Theme.of(context).textTheme.headlineMedium?.copyWith(color: t.foreground)),
                ),
                if (subtitle != null) ...[const SizedBox(height: 2), Text(subtitle!, style: TextStyle(color: t.mutedForeground))],
              ],
            ),
          ),
          ?trailing,
        ],
      ),
    );
  }
}
