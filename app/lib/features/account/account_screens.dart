import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_error.dart';
import '../../core/cache/swr.dart';
import '../../core/platform/push.dart';
import '../../core/platform/secure_screen.dart';
import '../../core/providers.dart';
import '../../core/util/json.dart';
import '../../core/widgets/kit.dart';
import 'app_settings_screen.dart';

List<RouteBase> accountRoutes() => [
  GoRoute(
    path: '/account',
    builder: (_, _) => const ProfileScreen(),
    routes: [
      GoRoute(path: 'password', builder: (_, _) => const PasswordScreen()),
      GoRoute(
        path: 'two-factor',
        builder: (_, state) => TwoFactorScreen(startSetup: state.uri.queryParameters['setup'] == '1'),
      ),
      GoRoute(path: 'devices', builder: (_, _) => const DevicesScreen()),
      GoRoute(path: 'notifications', builder: (_, _) => const NotificationSettingsScreen()),
      GoRoute(path: 'app', builder: (_, _) => const AppSettingsScreen()),
    ],
  ),
];

/// Profile: name and e-mail, password, two-step sign-in, devices, sign out.
class ProfileScreen extends ConsumerStatefulWidget {
  const ProfileScreen({super.key});
  @override
  ConsumerState<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends ConsumerState<ProfileScreen> {
  late final _name = TextEditingController(text: ref.read(sessionProvider).me?.name);
  late final _email = TextEditingController(text: ref.read(sessionProvider).me?.email);
  Map<String, List<String>> _errors = {};
  bool _busy = false;

  @override
  Widget build(BuildContext context) {
    final s = ref.watch(sessionProvider);
    final me = s.me!;
    final l = context.l;
    return Scaffold(
      appBar: AppBar(title: Text(l.profileTitle)),
      body: ListView(
        padding: const EdgeInsets.only(bottom: 40),
        children: [
          Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              children: [
                TextField(
                  controller: _name,
                  decoration: InputDecoration(labelText: l.name, errorText: _errors['name']?.first),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _email,
                  keyboardType: TextInputType.emailAddress,
                  decoration: InputDecoration(
                    labelText: l.email,
                    errorText: _errors['email']?.first ?? _errors['current_password']?.first,
                    helperText: me.pendingEmail != null ? l.emailPending(me.pendingEmail!) : null,
                    helperMaxLines: 3,
                  ),
                ),
                const SizedBox(height: 12),
                Align(
                  alignment: Alignment.centerRight,
                  child: FilledButton(
                    onPressed: _busy || s.impersonating
                        ? null
                        : () async {
                            setState(() {
                              _busy = true;
                              _errors = {};
                            });
                            try {
                              final newEmail = _email.text.trim();
                              final changed = newEmail.toLowerCase() != me.email.toLowerCase();
                              // A new e-mail needs the current password; the address changes after its link (H3).
                              String? password;
                              if (changed) {
                                password = await askPassword(context);
                                if (password == null) return;
                              }
                              final api = ref.read(apiProvider), session = ref.read(sessionProvider.notifier);
                              await api.put('mobile/account/profile', data: {'name': _name.text.trim(), 'email': newEmail, 'current_password': ?password});
                              await session.refreshMe();
                              if (changed) _email.text = me.email;
                              if (context.mounted) showSnack(context, changed ? l.emailChangeSent(newEmail) : l.saved);
                            } catch (e) {
                              if (!context.mounted) return;
                              setState(() => _errors = ApiError.from(e).fieldErrors);
                              showSnack(context, errorText(context, e));
                            } finally {
                              if (mounted) setState(() => _busy = false);
                            }
                          },
                    child: Text(l.save),
                  ),
                ),
              ],
            ),
          ),
          Group(
            children: [
              ListTile(
                leading: const Icon(AppIcons.rectangleEllipsis),
                title: Text(l.changePassword),
                trailing: const Icon(AppIcons.chevronRight),
                enabled: !s.impersonating,
                onTap: () => context.push('/account/password'),
              ),
              ListTile(
                leading: const Icon(AppIcons.shieldCheck),
                title: Text(l.twoFactor),
                subtitle: Text(me.twoFactorEnabled ? l.twoFactorOn : l.twoFactorOff),
                trailing: const Icon(AppIcons.chevronRight),
                enabled: !s.impersonating,
                onTap: () => context.push('/account/two-factor'),
              ),
              ListTile(
                leading: const Icon(AppIcons.monitorSmartphone),
                title: Text(l.devices),
                trailing: const Icon(AppIcons.chevronRight),
                enabled: !s.impersonating,
                onTap: () => context.push('/account/devices'),
              ),
              ListTile(
                leading: const Icon(AppIcons.bell),
                title: Text(l.notificationSettings),
                trailing: const Icon(AppIcons.chevronRight),
                onTap: () => context.push('/account/notifications'),
              ),
              ListTile(
                leading: const Icon(AppIcons.settings),
                title: Text(l.appSettings),
                trailing: const Icon(AppIcons.chevronRight),
                onTap: () => context.push('/account/app'),
              ),
            ],
          ),
          const SizedBox(height: 24),
          Group(
            children: [
              ListTile(
                leading: Icon(AppIcons.logOut, color: context.colors.error),
                title: Text(l.signOut, style: TextStyle(color: context.colors.error)),
                onTap: () async {
                  await ref.read(pushServiceProvider).unregister();
                  await ref.read(sessionProvider.notifier).signOut();
                },
              ),
              if (!s.impersonating)
                ListTile(
                  leading: Icon(AppIcons.monitorOff, color: context.colors.error),
                  title: Text(l.signOutEverywhere, style: TextStyle(color: context.colors.error)),
                  onTap: () async {
                    if (await confirm(context, title: l.signOutEverywhere, action: l.signOut, danger: true)) {
                      await ref.read(pushServiceProvider).unregister();
                      await ref.read(sessionProvider.notifier).signOut(everywhere: true);
                    }
                  },
                ),
            ],
          ),
        ],
      ),
    );
  }
}

class PasswordScreen extends ConsumerStatefulWidget {
  const PasswordScreen({super.key});
  @override
  ConsumerState<PasswordScreen> createState() => _PasswordScreenState();
}

class _PasswordScreenState extends ConsumerState<PasswordScreen> {
  final _form = GlobalKey<FormState>();
  final _current = TextEditingController(), _new = TextEditingController(), _again = TextEditingController();
  Map<String, List<String>> _errors = {};
  bool _busy = false;

  @override
  Widget build(BuildContext context) {
    final l = context.l;
    return Scaffold(
      appBar: AppBar(title: Text(l.changePassword)),
      body: Form(
        key: _form,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            TextFormField(
              controller: _current,
              obscureText: true,
              autofillHints: const [AutofillHints.password],
              decoration: InputDecoration(labelText: l.currentPassword, errorText: _errors['current_password']?.first),
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _new,
              obscureText: true,
              autofillHints: const [AutofillHints.newPassword],
              decoration: InputDecoration(labelText: l.newPassword, errorText: _errors['password']?.first),
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _again,
              obscureText: true,
              decoration: InputDecoration(labelText: l.confirmPassword),
              validator: (v) => v != _new.text ? l.passwordsDontMatch : null,
            ),
            const SizedBox(height: 20),
            FilledButton(
              onPressed: _busy
                  ? null
                  : () async {
                      if (!_form.currentState!.validate()) return;
                      setState(() {
                        _busy = true;
                        _errors = {};
                      });
                      try {
                        await ref
                            .read(apiProvider)
                            .put(
                              'mobile/account/password',
                              data: {'current_password': _current.text, 'password': _new.text, 'password_confirmation': _again.text},
                            );
                        if (context.mounted) {
                          showSnack(context, l.passwordChanged);
                          context.pop();
                        }
                      } catch (e) {
                        if (!context.mounted) return;
                        setState(() => _errors = ApiError.from(e).fieldErrors);
                        if (_errors.isEmpty) showSnack(context, errorText(context, e));
                      } finally {
                        if (mounted) setState(() => _busy = false);
                      }
                    },
              child: Text(l.save),
            ),
          ],
        ),
      ),
    );
  }
}

Future<String?> askPassword(BuildContext context) async {
  final c = TextEditingController();
  return showDialog<String>(
    context: context,
    builder: (ctx) => AlertDialog(
      title: Text(ctx.l.confirmPasswordTitle),
      content: TextField(
        controller: c,
        obscureText: true,
        autofocus: true,
        decoration: InputDecoration(labelText: ctx.l.password),
        onSubmitted: (v) => Navigator.pop(ctx, v),
      ),
      actions: [
        TextButton(onPressed: () => Navigator.pop(ctx), child: Text(ctx.l.cancel)),
        FilledButton(onPressed: () => Navigator.pop(ctx, c.text), child: Text(ctx.l.ok)),
      ],
    ),
  );
}

/// Two-step sign-in: turn on (QR + key, then a code), recovery codes, turn off.
class TwoFactorScreen extends ConsumerStatefulWidget {
  const TwoFactorScreen({super.key, this.startSetup = false});

  /// One tap from the dashboard card: ask for the password at once (M17).
  final bool startSetup;
  @override
  ConsumerState<TwoFactorScreen> createState() => _TwoFactorScreenState();
}

class _TwoFactorScreenState extends ConsumerState<TwoFactorScreen> {
  J? _setup;
  List<String>? _codes;
  final _code = TextEditingController();
  String? _error;

  @override
  void initState() {
    super.initState();
    final me = ref.read(sessionProvider).me;
    if ((widget.startSetup || (me?.twoFactorRequired ?? false)) && !(me?.twoFactorEnabled ?? true)) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) _start();
      });
    }
  }

  Future<void> _start() async {
    final pw = await askPassword(context);
    if (pw == null || !mounted) return;
    final api = ref.read(apiProvider);
    try {
      await api.post('mobile/account/two-factor', data: {'password': pw});
      final s = J.data(await api.get('mobile/account/two-factor'));
      if (mounted) setState(() => _setup = s);
    } catch (e) {
      if (mounted) showSnack(context, errorText(context, e));
    }
  }

  Future<void> _confirm() async {
    final api = ref.read(apiProvider), session = ref.read(sessionProvider.notifier);
    try {
      final r = J.data(await api.post('mobile/account/two-factor/confirm', data: {'code': _code.text}));
      await session.refreshMe();
      if (!mounted) return;
      setState(() {
        _codes = [for (final c in (r['recovery_codes'] as List)) '$c'];
        _setup = null;
      });
    } catch (e) {
      if (mounted) setState(() => _error = errorText(context, e));
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l;
    final me = ref.watch(sessionProvider).me!;
    // The key, the QR code and the recovery codes are secrets: no screenshots (M13).
    return SecureScreen(
      active: _codes != null || _setup != null,
      child: Scaffold(
        appBar: AppBar(title: Text(l.twoFactor)),
        body: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            if (_codes != null) ...[
              Text(l.recoveryCodes, style: context.text.titleMedium?.copyWith(fontWeight: FontWeight.w600)),
              const SizedBox(height: 6),
              Text(l.recoveryCodesHint),
              const SizedBox(height: 12),
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: SelectableText(_codes!.join('\n'), style: const TextStyle(fontFamily: 'monospace', fontSize: 16, height: 1.6)),
                ),
              ),
              const SizedBox(height: 8),
              OutlinedButton.icon(
                onPressed: () async {
                  await SecureScreens.copySecret(_codes!.join('\n'));
                  if (context.mounted) showSnack(context, l.copiedSecret);
                },
                icon: const Icon(AppIcons.copy),
                label: Text(l.copy),
              ),
            ] else if (_setup != null) ...[
              Text(l.twoFactorScan),
              const SizedBox(height: 12),
              Center(
                child: Container(
                  color: Colors.white,
                  padding: const EdgeInsets.all(12),
                  child: _QrSvg(svg: _setup!.s('svg')),
                ),
              ),
              const SizedBox(height: 12),
              SelectableText('${l.twoFactorKey}: ${_setup!.s('secret')}', textAlign: TextAlign.center),
              const SizedBox(height: 16),
              TextField(
                controller: _code,
                keyboardType: TextInputType.number,
                inputFormatters: [FilteringTextInputFormatter.digitsOnly, LengthLimitingTextInputFormatter(6)],
                decoration: InputDecoration(labelText: l.twoFactorConfirm, errorText: _error),
              ),
              const SizedBox(height: 12),
              FilledButton(onPressed: _confirm, child: Text(l.verify)),
            ] else ...[
              if (me.twoFactorRequired) ...[
                Card(
                  color: context.colors.errorContainer,
                  child: Padding(padding: const EdgeInsets.all(12), child: Text(l.twoFactorRequiredText)),
                ),
                const SizedBox(height: 8),
              ],
              ListTile(contentPadding: EdgeInsets.zero, title: Text(l.twoFactor), trailing: Text(me.twoFactorEnabled ? l.twoFactorOn : l.twoFactorOff)),
              const SizedBox(height: 12),
              if (!me.twoFactorEnabled)
                FilledButton(onPressed: _start, child: Text(l.twoFactorTurnOn))
              else ...[
                OutlinedButton(
                  onPressed: () async {
                    final pw = await askPassword(context);
                    if (pw == null || !context.mounted) return;
                    final api = ref.read(apiProvider);
                    final r = await runAction(context, () => api.post('mobile/account/two-factor/recovery-codes', data: {'password': pw}));
                    if (r != null && context.mounted) setState(() => _codes = [for (final c in (J.data(r)['recovery_codes'] as List)) '$c']);
                  },
                  child: Text(l.newRecoveryCodes),
                ),
                const SizedBox(height: 8),
                if (!me.isSuperAdmin)
                  TextButton(
                    onPressed: () async {
                      final pw = await askPassword(context);
                      if (pw == null || !context.mounted) return;
                      final api = ref.read(apiProvider), session = ref.read(sessionProvider.notifier);
                      if (await runOk(context, () => api.delete('mobile/account/two-factor', data: {'password': pw}))) {
                        await session.refreshMe();
                      }
                    },
                    child: Text(l.twoFactorTurnOff, style: TextStyle(color: context.colors.error)),
                  ),
              ],
            ],
          ],
        ),
      ),
    );
  }
}

/// Renders the server's QR SVG (simple rect-only SVG from bacon/qr) without a package.
class _QrSvg extends StatelessWidget {
  const _QrSvg({required this.svg});
  final String svg;

  @override
  Widget build(BuildContext context) {
    final vb = RegExp(r'viewBox="([\d.\s-]+)"').firstMatch(svg)?.group(1)?.split(RegExp(r'\s+')).map(double.parse).toList();
    final paths = RegExp(r'<path[^>]*\sd="([^"]+)"').allMatches(svg).map((m) => m.group(1)!).toList();
    final rects = RegExp(r'<rect[^>]*x="([\d.]+)"[^>]*y="([\d.]+)"[^>]*width="([\d.]+)"[^>]*height="([\d.]+)"').allMatches(svg).toList();
    return Semantics(
      label: context.l.twoFactor,
      image: true,
      child: CustomPaint(size: const Size.square(200), painter: _SvgPainter(vb, paths, rects)),
    );
  }
}

class _SvgPainter extends CustomPainter {
  _SvgPainter(this.vb, this.paths, this.rects);
  final List<double>? vb;
  final List<String> paths;
  final List<RegExpMatch> rects;

  @override
  void paint(Canvas canvas, Size size) {
    final w = vb != null && vb!.length == 4 ? vb![2] : 100.0;
    canvas.scale(size.width / w);
    final p = Paint()..color = Colors.black;
    for (final r in rects) {
      canvas.drawRect(Rect.fromLTWH(double.parse(r.group(1)!), double.parse(r.group(2)!), double.parse(r.group(3)!), double.parse(r.group(4)!)), p);
    }
    for (final d in paths) {
      final path = Path();
      final tokens = RegExp(r'([MmHhVvLlZz])|(-?[\d.]+)').allMatches(d).map((m) => m.group(0)!).toList();
      var x = 0.0, y = 0.0, sx = 0.0, sy = 0.0;
      var cmd = 'M';
      var i = 0;
      double num() => double.parse(tokens[i++]);
      while (i < tokens.length) {
        if (RegExp('[A-Za-z]').hasMatch(tokens[i])) cmd = tokens[i++];
        switch (cmd) {
          case 'M' || 'm':
            final nx = num(), ny = num();
            x = cmd == 'm' ? x + nx : nx;
            y = cmd == 'm' ? y + ny : ny;
            sx = x;
            sy = y;
            path.moveTo(x, y);
            cmd = cmd == 'm' ? 'l' : 'L';
          case 'L' || 'l':
            final nx = num(), ny = num();
            x = cmd == 'l' ? x + nx : nx;
            y = cmd == 'l' ? y + ny : ny;
            path.lineTo(x, y);
          case 'H' || 'h':
            final n = num();
            x = cmd == 'h' ? x + n : n;
            path.lineTo(x, y);
          case 'V' || 'v':
            final n = num();
            y = cmd == 'v' ? y + n : n;
            path.lineTo(x, y);
          case 'Z' || 'z':
            path.close();
            x = sx;
            y = sy;
          default:
            i++;
        }
      }
      canvas.drawPath(path, p);
    }
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}

class DevicesScreen extends ConsumerWidget {
  const DevicesScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l;
    return Scaffold(
      appBar: AppBar(title: Text(l.devices)),
      body: CachedView(
        path: 'mobile/devices',
        builder: (context, data, _) => ListView(
          children: [
            for (final d in J.dataList(data))
              ListTile(
                leading: Icon(d.s('platform') == 'ios' ? AppIcons.smartphone : AppIcons.smartphone),
                title: Text(d.s('name')),
                subtitle: Text(
                  [
                    if (d.b('current')) l.thisDevice,
                    if (d.sn('app_version') != null) d.s('app_version'),
                    l.lastUsed(formatDate(context, d.sn('last_used_at') ?? d.sn('created_at'), time: true)),
                  ].join(' · '),
                ),
                trailing: d.b('current')
                    ? null
                    : IconButton(
                        tooltip: l.signOutDevice,
                        icon: const Icon(AppIcons.logOut),
                        onPressed: () async {
                          if (await confirm(context, title: l.signOutDeviceConfirm(d.s('name')), action: l.signOut, danger: true) && context.mounted) {
                            final api = ref.read(apiProvider), container = context.container;
                            await runOk(context, () => api.delete('mobile/devices/${d.i('id')}'));
                            container.invalidate(cachedProvider('mobile/devices'));
                          }
                        },
                      ),
              ),
          ],
        ),
      ),
    );
  }
}

class NotificationSettingsScreen extends ConsumerStatefulWidget {
  const NotificationSettingsScreen({super.key});
  @override
  ConsumerState<NotificationSettingsScreen> createState() => _NotificationSettingsScreenState();
}

class _NotificationSettingsScreenState extends ConsumerState<NotificationSettingsScreen> {
  final Map<String, bool> _local = {};

  @override
  Widget build(BuildContext context) {
    final l = context.l;
    final push = ref.watch(pushServiceProvider);
    return Scaffold(
      appBar: AppBar(title: Text(l.notificationSettings)),
      body: CachedView(
        path: 'mobile/notification-settings',
        builder: (context, data, _) {
          final rows = J.dataList(data);
          Widget group(String g, String title) {
            final items = rows.where((r) => r.s('group') == g).toList();
            if (items.isEmpty) return const SizedBox.shrink();
            return Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                SectionHeader(title),
                Group(
                  children: [
                    for (final r in items)
                      SwitchListTile(
                        title: Text(r.s('label')),
                        value: _local[r.s('type')] ?? r.b('enabled', true),
                        onChanged: (v) async {
                          setState(() => _local[r.s('type')] = v);
                          final ok = await runOk(
                            context,
                            () => ref
                                .read(apiProvider)
                                .put(
                                  'mobile/notification-settings',
                                  data: {
                                    'settings': {r.s('type'): v},
                                  },
                                ),
                          );
                          if (!ok && mounted) setState(() => _local[r.s('type')] = !v);
                        },
                      ),
                  ],
                ),
              ],
            );
          }

          return ListView(
            padding: const EdgeInsets.only(bottom: 32),
            children: [
              if (!push.available)
                Padding(
                  padding: const EdgeInsets.all(16),
                  child: Text(l.pushNotSetUp, style: TextStyle(color: context.colors.onSurfaceVariant)),
                ),
              group('restaurant', l.notificationsGroupRestaurant),
              group('platform', l.notificationsGroupPlatform),
            ],
          );
        },
      ),
    );
  }
}
