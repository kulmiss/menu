import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../app/app_identity.dart';
import '../../app/theme_mode.dart';
import '../../core/app_config.dart';
import '../../core/platform/app_lock.dart';
import '../../core/platform/firebase.dart';
import '../../core/platform/updates.dart';
import '../../core/providers.dart';
import '../../core/widgets/kit.dart';

/// App lock, auto-lock time, analytics switch, about, links.
class AppSettingsScreen extends ConsumerStatefulWidget {
  const AppSettingsScreen({super.key});
  @override
  ConsumerState<AppSettingsScreen> createState() => _AppSettingsScreenState();
}

class _AppSettingsScreenState extends ConsumerState<AppSettingsScreen> {
  Future<String?> _askPin() async {
    final c = TextEditingController();
    return showDialog<String>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(ctx.l.setPin),
        content: TextField(
          controller: c,
          autofocus: true,
          obscureText: true,
          keyboardType: TextInputType.number,
          inputFormatters: [FilteringTextInputFormatter.digitsOnly, LengthLimitingTextInputFormatter(6)],
          decoration: InputDecoration(helperText: ctx.l.pinHint),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: Text(ctx.l.cancel)),
          FilledButton(onPressed: () => c.text.length >= 4 ? Navigator.pop(ctx, c.text) : null, child: Text(ctx.l.save)),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l;
    final lock = ref.watch(appLockProvider);
    final analytics = ref.watch(analyticsProvider);
    final config = ref.watch(appConfigProvider);
    final version = ref.watch(appVersionProvider);
    return Scaffold(
      appBar: AppBar(title: Text(l.appSettings)),
      body: ListView(
        padding: const EdgeInsets.only(bottom: 40),
        children: [
          SectionHeader(l.theme),
          Group(
            children: [
              Padding(
                padding: const EdgeInsets.all(12),
                child: SegmentedButton<ThemeMode>(
                  showSelectedIcon: false,
                  segments: [
                    ButtonSegment(value: ThemeMode.system, label: Text(l.themeSystem)),
                    ButtonSegment(value: ThemeMode.light, label: Text(l.themeLight)),
                    ButtonSegment(value: ThemeMode.dark, label: Text(l.themeDark)),
                  ],
                  selected: {ref.watch(themeModeProvider)},
                  onSelectionChanged: (s) => ref.read(themeModeProvider.notifier).set(s.single),
                ),
              ),
            ],
          ),
          // Super Admin → Mobile app → App features (#193).
          if (config?.appFeature('biometric_lock') ?? true) ...[
            SectionHeader(l.appLock),
            Group(
              children: [
                SwitchListTile(
                  title: Text(l.appLock),
                  subtitle: Text(l.appLockHint),
                  value: lock.enabled,
                  onChanged: (v) async {
                    final n = ref.read(appLockProvider.notifier);
                    if (v && !lock.hasPin) {
                      final pin = await _askPin();
                      if (pin == null) return;
                      await n.setPin(pin);
                    }
                    await n.setEnabled(v);
                  },
                ),
                if (lock.enabled) ...[
                  ListTile(
                    title: Text(l.autoLock),
                    trailing: DropdownButton<int>(
                      value: lock.minutes,
                      underline: const SizedBox.shrink(),
                      items: [
                        for (final m in [1, 5, 15, 30]) DropdownMenuItem(value: m, child: Text(l.minutes(m))),
                      ],
                      onChanged: (m) => ref.read(appLockProvider.notifier).setMinutes(m!),
                    ),
                  ),
                  SwitchListTile(title: Text(l.useBiometrics), value: lock.biometrics, onChanged: (v) => ref.read(appLockProvider.notifier).setBiometrics(v)),
                  ListTile(
                    title: Text(l.changePin),
                    onTap: () async {
                      final pin = await _askPin();
                      if (pin != null) await ref.read(appLockProvider.notifier).setPin(pin);
                    },
                  ),
                ],
              ],
            ),
          ],
          SectionHeader(l.privacy),
          Group(
            children: [
              if (config?.appFeature('analytics') ?? true)
                SwitchListTile(
                  title: Text(l.usageStats),
                  subtitle: Text(l.usageStatsHint),
                  value: analytics.enabled,
                  onChanged: (v) async {
                    await analytics.setEnabled(v);
                    setState(() {});
                  },
                ),
              if (config?.appFeature('crash_reports') ?? true)
                SwitchListTile(
                  title: Text(l.crashReports),
                  subtitle: Text(l.crashReportsHint),
                  value: analytics.crashes,
                  onChanged: (v) async {
                    await analytics.setCrashes(v);
                    setState(() {});
                  },
                ),
              ListTile(
                title: Text(l.privacy),
                trailing: const Icon(AppIcons.externalLink),
                onTap: () => openExternal(context, config?.link('privacy') ?? '${ref.read(apiProvider).siteUrl}privacy'),
              ),
              ListTile(
                title: Text(l.terms),
                trailing: const Icon(AppIcons.externalLink),
                onTap: () => openExternal(context, config?.link('terms') ?? '${ref.read(apiProvider).siteUrl}terms'),
              ),
              ListTile(
                title: Text(l.deleteAccount),
                trailing: const Icon(AppIcons.externalLink),
                // The website's section id drops the number: "10. Your rights" → #your-rights.
                onTap: () => openExternal(context, '${config?.link('privacy') ?? '${ref.read(apiProvider).siteUrl}privacy'}#your-rights'),
              ),
            ],
          ),
          SectionHeader(l.help),
          Group(
            children: [
              ListTile(leading: const Icon(AppIcons.lifeBuoy), title: Text(l.helpTitle), onTap: () => context.push('/help')),
              if (config?.link('support_whatsapp') != null)
                ListTile(
                  leading: const Icon(AppIcons.messageCircle),
                  title: Text(l.supportWhatsapp),
                  onTap: () => openExternal(context, config!.link('support_whatsapp')!),
                ),
              if (config?.link('support_email') != null)
                ListTile(leading: const Icon(AppIcons.mail), title: Text(l.supportEmail), subtitle: SelectableText(config!.link('support_email')!)),
              ListTile(leading: const Icon(AppIcons.smartphone), title: Text(l.checkUpdates), onTap: () => checkPlayUpdate(mustUpdate: false)),
            ],
          ),
          SectionHeader(l.about),
          Group(
            children: [
              ListTile(title: Text(AppIdentity.name), subtitle: Text(l.version(version))),
              ListTile(
                title: Text(l.licences),
                onTap: () => showLicensePage(context: context, applicationName: AppIdentity.name, applicationVersion: version),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
