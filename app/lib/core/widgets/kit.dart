import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../l10n/app_localizations.dart';
import '../api/api_error.dart';
import '../cache/swr.dart';
import '../util/links.dart';
import '../../app/theme.dart';
import 'ui.dart';

export '../../app/theme.dart' show AppTokens, AppTokensX;
export 'ui.dart';

extension LX on BuildContext {
  L get l => L.of(this);
  ColorScheme get colors => Theme.of(this).colorScheme;
  TextTheme get text => Theme.of(this).textTheme;
  bool get reduceMotion => MediaQuery.of(this).disableAnimations;
}

String errorText(BuildContext context, Object error) {
  final e = ApiError.from(error);
  final l = context.l;
  if (e.kind == ApiErrorKind.validation && e.fieldErrors.isNotEmpty) return e.fieldErrors.values.first.first;
  if (e.message != null && e.message!.isNotEmpty && e.kind != ApiErrorKind.server && e.kind != ApiErrorKind.unknown) return e.message!;
  return switch (e.kind) {
    ApiErrorKind.offline => l.errorOffline,
    ApiErrorKind.timeout => l.errorTimeout,
    ApiErrorKind.server => l.errorServer,
    ApiErrorKind.notFound => l.errorNotFound,
    ApiErrorKind.forbidden => l.errorForbidden,
    ApiErrorKind.rateLimited => l.errorRateLimited,
    _ => l.errorUnknown,
  };
}

void showSnack(BuildContext context, String message, {SnackBarAction? action, ToastKind kind = ToastKind.info}) =>
    showToast(context, message, kind: kind, action: action);

/// Runs a change with a clear success/failure message. Returns the result or null.
Future<T?> runAction<T>(BuildContext context, Future<T> Function() action, {String? success}) async {
  try {
    final r = await action();
    if (context.mounted && success != null) showSnack(context, success, kind: ToastKind.success);
    return r;
  } catch (e) {
    if (context.mounted) {
      final err = ApiError.from(e);
      if (err.needsUpgrade) {
        await showUpgradeSheet(context, err.message);
      } else {
        showSnack(context, errorText(context, e), kind: ToastKind.error);
      }
    }
    return null;
  }
}

/// Like [runAction] but only says whether it worked (for answers without a body).
Future<bool> runOk(BuildContext context, Future<Object?> Function() action, {String? success}) async {
  var ok = false;
  await runAction<void>(context, () async {
    await action();
    ok = true;
  }, success: success);
  return ok;
}

Future<bool> confirm(BuildContext context, {required String title, String? body, String? action, bool danger = false}) async {
  final l = context.l;
  final ok = await showDialog<bool>(
    context: context,
    builder: (c) => AlertDialog(
      title: Text(title),
      content: body == null ? null : Text(body),
      actions: [
        TextButton(onPressed: () => Navigator.pop(c, false), child: Text(l.cancel)),
        FilledButton(
          style: danger ? FilledButton.styleFrom(backgroundColor: c.colors.error) : null,
          onPressed: () => Navigator.pop(c, true),
          child: Text(action ?? l.ok),
        ),
      ],
    ),
  );
  return ok ?? false;
}

Future<void> showUpgradeSheet(BuildContext context, [String? message]) => showModalBottomSheet<void>(
  context: context,
  showDragHandle: true,
  builder: (c) => SafeArea(
    child: Padding(
      padding: const EdgeInsets.fromLTRB(20, 0, 20, 20),
      child: UpgradeCard(message: message, flat: true),
    ),
  ),
);

/// The same "Upgrade" hint as the web admin (QUESTIONS #184).
class UpgradeCard extends ConsumerWidget {
  const UpgradeCard({super.key, this.message, this.flat = false, this.upgrade});
  final String? message;
  final bool flat;
  final Map<String, dynamic>? upgrade;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l;
    final whatsapp = upgrade?['whatsapp'] as String?;
    final email = upgrade?['email'] as String?;
    final body = Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Row(
          children: [
            Icon(AppIcons.crown, color: context.colors.primary),
            const SizedBox(width: 10),
            Expanded(
              child: Text(l.upgradeTitle, style: context.text.titleMedium?.copyWith(fontWeight: FontWeight.w600)),
            ),
          ],
        ),
        const SizedBox(height: 8),
        Text(message ?? l.upgradeBody, style: TextStyle(color: context.colors.onSurfaceVariant)),
        const SizedBox(height: 14),
        FilledButton.icon(
          onPressed: () {
            final url = whatsapp != null && whatsapp.isNotEmpty ? whatsapp : (email != null ? 'mailto:$email' : null);
            if (url != null) launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication);
          },
          icon: const Icon(AppIcons.arrowUp),
          label: Text(l.upgradeButton),
        ),
      ],
    );
    return flat
        ? body
        : Card(
            child: Padding(padding: const EdgeInsets.all(16), child: body),
          );
  }
}

class UpgradeBadge extends StatelessWidget {
  const UpgradeBadge({super.key});
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
    decoration: BoxDecoration(color: context.colors.surfaceContainerHigh, borderRadius: BorderRadius.circular(6)),
    child: Text(context.l.upgradeBadge, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
  );
}

/// Grey placeholder blocks instead of a spinner (PLAN.md §8).
class Skeleton extends StatefulWidget {
  const Skeleton({super.key, this.lines = 6, this.height = 64});
  final int lines;
  final double height;
  @override
  State<Skeleton> createState() => _SkeletonState();
}

class _SkeletonState extends State<Skeleton> with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(vsync: this, duration: const Duration(milliseconds: 1100));

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (context.reduceMotion) {
      _c.stop();
    } else if (!_c.isAnimating) {
      _c.repeat(reverse: true);
    }
  }

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final base = context.tokens.muted;
    return Semantics(
      label: context.l.loading,
      child: ListView.separated(
        physics: const NeverScrollableScrollPhysics(),
        padding: const EdgeInsets.all(16),
        itemCount: widget.lines,
        separatorBuilder: (_, _) => const SizedBox(height: 12),
        itemBuilder: (_, _) => FadeTransition(
          opacity: Tween(begin: 0.55, end: 1.0).animate(_c),
          child: Container(
            height: widget.height,
            decoration: BoxDecoration(color: base, borderRadius: BorderRadius.circular(AppTokens.radiusXl)),
          ),
        ),
      ),
    );
  }
}

class EmptyState extends StatelessWidget {
  const EmptyState({super.key, this.icon = AppIcons.inbox, this.title, this.body, this.action});
  final IconData icon;
  final String? title;
  final String? body;
  final Widget? action;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(32),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 56,
            height: 56,
            decoration: BoxDecoration(color: context.tokens.muted, shape: BoxShape.circle),
            child: Icon(icon, size: 24, color: context.tokens.mutedForeground),
          ),
          const SizedBox(height: 16),
          Text(
            title ?? context.l.emptyTitle,
            style: context.text.titleMedium?.copyWith(fontWeight: FontWeight.w600),
            textAlign: TextAlign.center,
          ),
          if (body != null) ...[
            const SizedBox(height: 6),
            Text(
              body!,
              textAlign: TextAlign.center,
              style: TextStyle(color: context.colors.onSurfaceVariant),
            ),
          ],
          if (action != null) ...[const SizedBox(height: 16), action!],
        ],
      ),
    ),
  );
}

class ErrorView extends StatelessWidget {
  const ErrorView({super.key, required this.error, this.onRetry});
  final Object error;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) {
    final e = ApiError.from(error);
    if (e.needsUpgrade) {
      return Padding(
        padding: const EdgeInsets.all(16),
        child: UpgradeCard(message: e.message),
      );
    }
    return EmptyState(
      icon: e.isOffline ? AppIcons.wifiOff : AppIcons.circleAlert,
      title: errorText(context, error),
      action: onRetry == null ? null : OutlinedButton.icon(onPressed: onRetry, icon: const Icon(AppIcons.refreshCw), label: Text(context.l.retry)),
    );
  }
}

/// Renders a cached GET: skeleton only on the very first load, saved data
/// instantly afterwards, pull-to-refresh, and a quiet "saved copy" line.
class CachedView extends ConsumerWidget {
  const CachedView({super.key, required this.path, required this.builder, this.skeleton, this.refreshable = true});

  final String path;
  final Widget Function(BuildContext context, dynamic data, Cached<dynamic> meta) builder;
  final Widget? skeleton;
  final bool refreshable;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(cachedProvider(path));
    return async.when(
      skipLoadingOnRefresh: true,
      skipLoadingOnReload: true,
      loading: () => skeleton ?? const Skeleton(),
      error: (e, _) => ErrorView(error: e, onRetry: () => ref.invalidate(cachedProvider(path))),
      data: (c) {
        Widget child = builder(context, c.data, c);
        if (c.error != null) {
          child = Column(
            children: [
              StaleBanner(meta: c),
              Expanded(child: child),
            ],
          );
        }
        return refreshable ? RefreshIndicator(onRefresh: () => ref.refreshPath(path), child: child) : child;
      },
    );
  }
}

class StaleBanner extends StatelessWidget {
  const StaleBanner({super.key, required this.meta});
  final Cached<dynamic> meta;
  @override
  Widget build(BuildContext context) => Material(
    color: context.colors.surfaceContainerHigh,
    child: Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      child: Row(
        children: [
          Icon(meta.error!.isOffline ? AppIcons.wifiOff : AppIcons.history, size: 16, color: context.colors.onSurfaceVariant),
          const SizedBox(width: 8),
          Expanded(
            child: Text(context.l.savedCopy(timeAgo(context, meta.fetchedAt)), style: TextStyle(fontSize: 13, color: context.colors.onSurfaceVariant)),
          ),
        ],
      ),
    ),
  );
}

String timeAgo(BuildContext context, DateTime t) {
  final d = DateTime.now().difference(t);
  if (d.inMinutes < 1) return 'just now';
  if (d.inHours < 1) return '${d.inMinutes} min ago';
  if (d.inDays < 1) return '${d.inHours} h ago';
  return DateFormat.yMMMd(Localizations.localeOf(context).toString()).format(t);
}

String formatDate(BuildContext context, String? iso, {bool time = false}) {
  final d = iso == null ? null : DateTime.tryParse(iso)?.toLocal();
  if (d == null) return '—';
  final loc = Localizations.localeOf(context).toString();
  return time ? DateFormat.yMMMd(loc).add_Hm().format(d) : DateFormat.yMMMd(loc).format(d);
}

/// Money in minor units, like the API.
String formatMoney(num? minor, String currency) {
  if (minor == null) return '—';
  final v = minor / 100;
  final s = v == v.roundToDouble() ? NumberFormat('#,##0').format(v) : NumberFormat('#,##0.00').format(v);
  return currency.isEmpty ? s : '$currency $s';
}

class SectionHeader extends StatelessWidget {
  const SectionHeader(this.title, {super.key, this.trailing});
  final String title;
  final Widget? trailing;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(16, 20, 16, 8),
    child: Row(
      children: [
        Expanded(
          child: Semantics(
            header: true,
            child: Text(
              title.toUpperCase(),
              style: TextStyle(fontSize: 12, letterSpacing: .6, fontWeight: FontWeight.w600, color: context.colors.onSurfaceVariant),
            ),
          ),
        ),
        ?trailing,
      ],
    ),
  );
}

/// A rounded group of rows, like shadcn cards.
class Group extends StatelessWidget {
  const Group({super.key, required this.children, this.margin = const EdgeInsets.symmetric(horizontal: 16)});
  final List<Widget> children;
  final EdgeInsets margin;
  @override
  Widget build(BuildContext context) => Padding(
    padding: margin,
    child: Card(
      clipBehavior: Clip.antiAlias,
      child: Column(
        children: [
          for (var i = 0; i < children.length; i++) ...[if (i > 0) const Divider(height: 1), children[i]],
        ],
      ),
    ),
  );
}

class StatTile extends StatelessWidget {
  const StatTile({super.key, required this.label, required this.value, this.hint, this.hintColor, this.onTap});
  final String label;
  final String value;
  final String? hint;
  final Color? hintColor;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) => Card(
    child: InkWell(
      borderRadius: BorderRadius.circular(12),
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(label, style: TextStyle(fontSize: 12, color: context.colors.onSurfaceVariant)),
            const SizedBox(height: 4),
            // One line, shrunk to fit: big numbers broke in two at large text sizes.
            FittedBox(
              fit: BoxFit.scaleDown,
              alignment: AlignmentDirectional.centerStart,
              child: Text(value, maxLines: 1, style: const TextStyle(fontSize: 24, fontWeight: FontWeight.w700)),
            ),
            if (hint != null) ...[const SizedBox(height: 2), Text(hint!, style: TextStyle(fontSize: 12, color: hintColor ?? context.colors.onSurfaceVariant))],
          ],
        ),
      ),
    ),
  );
}

/// Opens an external https link in the browser (only http/https allowed).
Future<void> openExternal(BuildContext context, String url) async {
  final uri = safeExternalUri(url);
  if (uri == null) {
    showSnack(context, context.l.errorUnknown);
    return;
  }
  await launchUrl(uri, mode: LaunchMode.externalApplication);
}
