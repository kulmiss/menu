import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../app/router.dart';
import '../../core/app_config.dart';
import '../../core/cache/swr.dart';
import '../../core/platform/app_lock.dart';
import '../../core/platform/device_integrity.dart';
import '../../core/platform/secure_screen.dart';
import '../../core/platform/updates.dart';
import '../../core/providers.dart';
import '../../core/widgets/app_logo.dart';
import '../../core/widgets/kit.dart';

class SplashScreen extends StatelessWidget {
  const SplashScreen({super.key});
  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: Theme.of(context).colorScheme.surface,
    body: const Center(child: AppLogo(size: 96)),
  );
}

class NoRestaurantScreen extends ConsumerWidget {
  const NoRestaurantScreen({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final me = ref.watch(sessionProvider).me;
    final unverified = me != null && !me.emailVerified;
    return Scaffold(
      body: SafeArea(
        child: EmptyState(
          icon: unverified ? AppIcons.mailWarning : AppIcons.store,
          title: unverified ? context.l.emailUnverifiedTitle : context.l.noRestaurantTitle,
          body: unverified ? context.l.emailUnverifiedBody(me.email) : context.l.noRestaurantBody,
          action: Column(
            children: [
              if (unverified) FilledButton(onPressed: () => ref.read(sessionProvider.notifier).refreshMe(), child: Text(context.l.iConfirmed)),
              TextButton(onPressed: () => ref.read(sessionProvider.notifier).signOut(), child: Text(context.l.signOut)),
            ],
          ),
        ),
      ),
    );
  }
}

/// Wraps the whole app: Super Admin → Mobile app (forced update, maintenance,
/// app off, update prompt, banner — backend QUESTIONS #193), the app lock and
/// the root warning. The config is read from the device first and refreshed in
/// the background at start and on every return to the app; it never blocks.
class AppGates extends ConsumerStatefulWidget {
  const AppGates({super.key, required this.child});
  final Widget child;
  @override
  ConsumerState<AppGates> createState() => _AppGatesState();
}

class _AppGatesState extends ConsumerState<AppGates> with WidgetsBindingObserver {
  bool _cover = false;
  bool _rootWarning = false;
  bool _maintenanceSignIn = false;
  String? _skippedUpdate;
  String? _closedBanner;
  DateTime _lastRefresh = DateTime(2000);

  static const _warnedKey = 'integrity_warned';
  static const _skipKey = 'update_skipped';
  static const _bannerKey = 'banner_closed';

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    ref.read(tokenHolderProvider).onGate = (code, message) {
      if (mounted) ref.read(serverGateProvider.notifier).set(ServerGate(code, message));
      refreshConfig();
    };
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      await _checkDevice();
      final store = ref.read(tokenStoreProvider);
      final skipped = await store.read(_skipKey), closed = await store.read(_bannerKey);
      if (mounted) {
        setState(() {
          _skippedUpdate = skipped;
          _closedBanner = closed;
        });
      }
    });
  }

  /// Fetch `mobile/config` again (cheap: cached 60 s on the server).
  void refreshConfig() => ref.invalidate(cachedProvider('mobile/config'));

  void _retry() {
    ref.read(serverGateProvider.notifier).set(null);
    refreshConfig();
  }

  /// Rooted / jailbroken phone: one warning, then never again (L12).
  Future<void> _checkDevice() async {
    final store = ref.read(tokenStoreProvider);
    if (await store.read(_warnedKey) == '1') return;
    if (await DeviceIntegrity.looksCompromised() && mounted) setState(() => _rootWarning = true);
  }

  Future<void> _dismissRootWarning() async {
    await ref.read(tokenStoreProvider).write(_warnedKey, '1');
    if (mounted) setState(() => _rootWarning = false);
  }

  Future<void> _skipUpdate(String version) async {
    setState(() => _skippedUpdate = version);
    await ref.read(tokenStoreProvider).write(_skipKey, version);
  }

  Future<void> _closeBanner(String text) async {
    setState(() => _closedBanner = '${text.hashCode}');
    await ref.read(tokenStoreProvider).write(_bannerKey, '${text.hashCode}');
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    ref.read(appLockProvider.notifier).onLifecycle(state);
    final lockOn = ref.read(appLockProvider).enabled;
    // Also cover a secret screen (two-factor key, recovery codes) in the app switcher (M13).
    final secret = SecureScreens.open.value > 0;
    setState(() => _cover = (lockOn || secret) && state != AppLifecycleState.resumed);
    // Back in the app: pick up maintenance, forced updates and the banner (at most every 30 s).
    if (state == AppLifecycleState.resumed && DateTime.now().difference(_lastRefresh) > const Duration(seconds: 30)) {
      _lastRefresh = DateTime.now();
      _retry();
    }
  }

  @override
  Widget build(BuildContext context) {
    final config = ref.watch(appConfigProvider);
    final version = ref.watch(appVersionProvider);
    final lock = ref.watch(appLockProvider);
    final gate = ref.watch(serverGateProvider);
    final session = ref.watch(sessionProvider);
    final signedIn = session.status == SessionStatus.signedIn;
    final superAdmin = session.me?.isSuperAdmin ?? false;
    final block = appBlock(config: config, version: version, signedIn: signedIn, superAdmin: superAdmin, gate: gate, maintenanceSignIn: _maintenanceSignIn);
    final l = context.l;
    final web = config?.link('web_admin') ?? '${ref.read(apiProvider).siteUrl}admin';
    switch (block) {
      case AppBlock.update:
        return _UpdateRequired(config: config);
      case AppBlock.maintenance:
        return BlockedScreen(
          key: const Key('maintenance'),
          icon: AppIcons.construction,
          title: l.maintenanceTitle,
          body: config?.maintenanceMessage ?? gate?.message ?? l.maintenanceBody,
          onRetry: _retry,
          extra: [
            if (!signedIn) TextButton(onPressed: () => setState(() => _maintenanceSignIn = true), child: Text(l.superAdminSignIn)),
            if (signedIn) TextButton(onPressed: () => ref.read(sessionProvider.notifier).signOut(), child: Text(l.signOut)),
          ],
        );
      case AppBlock.appOff:
        return BlockedScreen(
          key: const Key('app-off'),
          icon: AppIcons.monitorOff,
          title: l.appOffTitle,
          body: config?.signInMessage ?? gate?.message ?? l.appOffBody,
          onRetry: _retry,
          extra: [
            FilledButton.tonal(onPressed: () => openExternal(context, web), child: Text(l.openWebsite)),
            if (signedIn) TextButton(onPressed: () => ref.read(sessionProvider.notifier).signOut(), child: Text(l.signOut)),
          ],
        );
      case AppBlock.none:
        break;
    }
    final recommended = config?.recommendedVersion;
    final offerUpdate = recommended != null && compareVersions(version, recommended) < 0 && _skippedUpdate != recommended;
    final banner = config?.bannerFor(superAdmin: superAdmin);
    final showBanner = banner != null && _closedBanner != '${banner.text.hashCode}';
    final lockAllowed = config?.appFeature('biometric_lock') ?? true;
    final top = [
      if (offerUpdate)
        _TopCard(
          key: const Key('update-prompt'),
          icon: AppIcons.smartphone,
          text: [l.updateAvailable, if (config!.releaseNotes != null) config.releaseNotes!].join('\n'),
          action: TextButton(onPressed: () => _openStore(context, config, mustUpdate: false), child: Text(l.updateNow)),
          onClose: () => _skipUpdate(recommended),
        ),
      if (showBanner)
        _TopCard(
          key: const Key('app-banner'),
          icon: AppIcons.megaphone,
          text: banner.text,
          onTap: banner.link == null ? null : () => _openLink(context, banner.link!),
          onClose: () => _closeBanner(banner.text),
        ),
    ];
    Widget app = widget.child;
    if (top.isNotEmpty) {
      app = Column(
        children: [
          Material(
            color: Theme.of(context).colorScheme.surface,
            child: SafeArea(bottom: false, child: Column(children: top)),
          ),
          Expanded(
            child: MediaQuery.removePadding(context: context, removeTop: true, child: widget.child),
          ),
        ],
      );
    }
    return Stack(
      children: [
        app,
        if (signedIn && lockAllowed && lock.enabled && lock.locked) const Positioned.fill(child: LockScreen()),
        if (_rootWarning)
          Positioned(
            left: 0,
            right: 0,
            top: 0,
            child: SafeArea(child: RootWarning(onClose: _dismissRootWarning)),
          ),
        if (_cover)
          Positioned.fill(
            child: ColoredBox(
              color: Theme.of(context).colorScheme.surface,
              child: const Center(child: AppLogo()),
            ),
          ),
      ],
    );
  }

  void _openLink(BuildContext context, String link) {
    if (link.startsWith('/')) {
      ref.read(routerProvider).push(link);
    } else {
      openExternal(context, link);
    }
  }
}

/// Play: in-app update; iPhone (or not from Play): the store page from the config.
Future<void> _openStore(BuildContext context, AppConfig? config, {required bool mustUpdate}) async {
  final ios = defaultTargetPlatform == TargetPlatform.iOS;
  final store = config?.link(ios ? 'app_store' : 'play_store');
  if (!ios) await checkPlayUpdate(mustUpdate: mustUpdate);
  if (store != null && (ios || !kReleaseMode) && context.mounted) await openExternal(context, store);
}

/// A full-screen message instead of the app (maintenance, app off).
class BlockedScreen extends StatelessWidget {
  const BlockedScreen({super.key, required this.icon, required this.title, required this.body, required this.onRetry, this.extra = const []});
  final IconData icon;
  final String title;
  final String body;
  final VoidCallback onRetry;
  final List<Widget> extra;

  @override
  Widget build(BuildContext context) => Scaffold(
    body: SafeArea(
      child: EmptyState(
        icon: icon,
        title: title,
        body: body,
        action: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            FilledButton(onPressed: onRetry, child: Text(context.l.retry)),
            const SizedBox(height: 8),
            ...extra,
          ],
        ),
      ),
    ),
  );
}

/// One line above the app (update prompt, announcement banner), closable.
/// It sits above the Navigator (no Overlay), so buttons carry a label, not a tooltip.
class _TopCard extends StatelessWidget {
  const _TopCard({super.key, required this.icon, required this.text, required this.onClose, this.action, this.onTap});
  final IconData icon;
  final String text;
  final VoidCallback onClose;
  final Widget? action;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final c = Theme.of(context).colorScheme;
    return Material(
      color: c.primaryContainer,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 4, 4, 4),
          child: Row(
            children: [
              Icon(icon, color: c.onPrimaryContainer),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  text,
                  maxLines: 3,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(color: c.onPrimaryContainer),
                ),
              ),
              ?action,
              Semantics(
                button: true,
                label: context.l.close,
                child: IconButton(onPressed: onClose, icon: const Icon(AppIcons.x), color: c.onPrimaryContainer),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// "This phone is rooted": a warning only; the app keeps working (L12).
class RootWarning extends StatelessWidget {
  const RootWarning({super.key, required this.onClose});
  final VoidCallback onClose;
  @override
  Widget build(BuildContext context) => Material(
    color: Colors.amber.shade100,
    elevation: 2,
    child: Padding(
      padding: const EdgeInsets.fromLTRB(16, 12, 4, 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(AppIcons.triangleAlert, color: Colors.amber.shade900),
          const SizedBox(width: 12),
          Expanded(
            child: Text(context.l.rootWarning, style: TextStyle(color: Colors.brown.shade900)),
          ),
          Semantics(
            button: true,
            label: context.l.close,
            child: IconButton(onPressed: onClose, icon: const Icon(AppIcons.x)),
          ),
        ],
      ),
    ),
  );
}

class _UpdateRequired extends StatefulWidget {
  const _UpdateRequired({required this.config});
  final AppConfig? config;
  @override
  State<_UpdateRequired> createState() => _UpdateRequiredState();
}

class _UpdateRequiredState extends State<_UpdateRequired> {
  @override
  void initState() {
    super.initState();
    checkPlayUpdate(mustUpdate: true);
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    key: const Key('update-required'),
    body: SafeArea(
      child: EmptyState(
        icon: AppIcons.smartphone,
        title: context.l.updateRequiredTitle,
        body: [context.l.updateRequiredBody, if (widget.config?.releaseNotes != null) widget.config!.releaseNotes!].join('\n\n'),
        action: FilledButton(onPressed: () => _openStore(context, widget.config, mustUpdate: true), child: Text(context.l.updateNow)),
      ),
    ),
  );
}

class LockScreen extends ConsumerStatefulWidget {
  const LockScreen({super.key});
  @override
  ConsumerState<LockScreen> createState() => _LockScreenState();
}

class _LockScreenState extends ConsumerState<LockScreen> {
  final _pin = TextEditingController();
  String? _error;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _bio());
  }

  Future<void> _bio() async {
    final lock = ref.read(appLockProvider);
    if (lock.biometrics && await ref.read(appLockProvider.notifier).canUseBiometrics() && mounted) {
      await ref.read(appLockProvider.notifier).biometric(context.l.lockedTitle);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    body: SafeArea(
      child: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 360),
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const AppLogo(),
                const SizedBox(height: 16),
                Text(context.l.lockedTitle, style: context.text.titleLarge?.copyWith(fontWeight: FontWeight.w700)),
                const SizedBox(height: 20),
                TextField(
                  controller: _pin,
                  obscureText: true,
                  autofocus: true,
                  textAlign: TextAlign.center,
                  keyboardType: TextInputType.number,
                  inputFormatters: [FilteringTextInputFormatter.digitsOnly, LengthLimitingTextInputFormatter(6)],
                  style: const TextStyle(fontSize: 24, letterSpacing: 10),
                  decoration: InputDecoration(labelText: context.l.enterPin, errorText: _error),
                  onSubmitted: (_) => _check(),
                ),
                const SizedBox(height: 12),
                FilledButton(onPressed: _check, child: Text(context.l.unlock)),
                TextButton.icon(onPressed: _bio, icon: const Icon(AppIcons.fingerprint), label: Text(context.l.useBiometrics)),
              ],
            ),
          ),
        ),
      ),
    ),
  );

  Future<void> _check() async {
    final r = await ref.read(appLockProvider.notifier).checkPin(_pin.text);
    if (!mounted || r.ok) return;
    final l = context.l;
    _pin.clear();
    setState(
      () => _error = switch (r.kind) {
        PinOutcome.wait => l.pinWait(r.wait.inSeconds),
        PinOutcome.signedOut => l.pinSignedOut,
        _ when r.wait > Duration.zero => '${l.pinWrong} · ${l.pinWait(r.wait.inSeconds)}',
        _ when r.triesLeft <= 5 => '${l.pinWrong} · ${l.pinTriesLeft(r.triesLeft)}',
        _ => l.pinWrong,
      },
    );
  }
}
