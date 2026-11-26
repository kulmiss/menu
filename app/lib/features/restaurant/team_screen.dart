import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/auth/models.dart';
import '../../core/cache/swr.dart';
import '../../core/providers.dart';
import '../../core/util/files.dart';
import '../../core/util/json.dart';
import '../../core/widgets/kit.dart';
import 'more_screen.dart' show roleLabel;

/// Team: members and roles, invitations, removal (server checks who may do what).
class TeamScreen extends ConsumerWidget {
  const TeamScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final t = ref.watch(tenantProvider)!;
    final can = ref.watch(canProvider);
    final me = ref.watch(sessionProvider).me;
    final l = context.l;
    final members = tenantPath(t.id, 'members');
    final invites = tenantPath(t.id, 'invitations');
    final inv = ref.watch(cachedProvider(invites)).value;
    return Scaffold(
      appBar: AppBar(title: Text(l.team)),
      floatingActionButton: FloatingActionButton.extended(onPressed: () => _invite(context, ref), icon: const Icon(AppIcons.userPlus), label: Text(l.invite)),
      body: CachedView(
        path: members,
        builder: (context, data, _) => ListView(
          padding: const EdgeInsets.only(bottom: 96),
          children: [
            for (final m in J.dataList(data))
              ListTile(
                leading: CircleAvatar(child: Text(m.o('user').s('name').characters.firstOrNull?.toUpperCase() ?? '?')),
                title: Text(m.o('user').i('id') == me?.id ? '${m.o('user').s('name')} (${l.you})' : m.o('user').s('name')),
                subtitle: Text(
                  '${roleLabel(context, m.s('role'))} · ${m.o('user').s('email')}${m.s('role') == 'staff' && m.b('can_edit_prices') ? ' · ${l.canEditPrices}' : ''}',
                ),
                trailing: m.o('user').i('id') == me?.id || !can.manage(roleFrom(m.s('role')))
                    ? null
                    : PopupMenuButton<String>(
                        onSelected: (v) async {
                          final api = ref.read(apiProvider), container = context.container;
                          if (v == 'remove') {
                            if (await confirm(context, title: l.removeMember, body: m.o('user').s('name'), action: l.remove, danger: true) && context.mounted) {
                              await runOk(context, () => api.delete('$members/${m.i('id')}'));
                            }
                          } else if (v == 'prices') {
                            await runOk(context, () => api.patch('$members/${m.i('id')}', data: {'can_edit_prices': !m.b('can_edit_prices')}));
                          } else {
                            await runOk(context, () => api.patch('$members/${m.i('id')}', data: {'role': v}));
                          }
                          container.invalidate(cachedProvider(members));
                        },
                        itemBuilder: (c) => [
                          for (final r in ['owner', 'manager', 'staff'])
                            if (r != m.s('role') && can.manage(roleFrom(r)) && (r != 'owner' || can.manageAccount))
                              PopupMenuItem(value: r, child: Text(roleLabel(c, r))),
                          if (m.s('role') == 'staff') CheckedPopupMenuItem(value: 'prices', checked: m.b('can_edit_prices'), child: Text(l.canEditPrices)),
                          PopupMenuItem(
                            value: 'remove',
                            child: Text(l.removeMember, style: TextStyle(color: c.colors.error)),
                          ),
                        ],
                      ),
              ),
            if (inv != null && J.dataList(inv.data).isNotEmpty) ...[
              SectionHeader(l.pendingInvitations),
              for (final i in J.dataList(inv.data))
                ListTile(
                  leading: const Icon(AppIcons.mail),
                  title: Text(i.s('email')),
                  subtitle: Text('${roleLabel(context, i.s('role'))} · ${l.expires(formatDate(context, i.sn('expires_at')))}'),
                  trailing: IconButton(
                    tooltip: l.delete,
                    icon: const Icon(AppIcons.x),
                    onPressed: () async {
                      final api = ref.read(apiProvider), container = context.container;
                      await runOk(context, () => api.delete('$invites/${i.i('id')}'));
                      container.invalidate(cachedProvider(invites));
                    },
                  ),
                ),
            ],
          ],
        ),
      ),
    );
  }

  Future<void> _invite(BuildContext context, WidgetRef ref) async {
    final l = context.l;
    final can = ref.read(canProvider);
    final email = TextEditingController();
    var role = 'staff';
    var prices = false;
    final ok = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (c) => StatefulBuilder(
        builder: (c, set) => Padding(
          padding: EdgeInsets.fromLTRB(16, 0, 16, 16 + MediaQuery.viewInsetsOf(c).bottom),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(l.inviteTitle, style: c.text.titleMedium?.copyWith(fontWeight: FontWeight.w600)),
              const SizedBox(height: 12),
              TextField(
                controller: email,
                autofocus: true,
                keyboardType: TextInputType.emailAddress,
                decoration: InputDecoration(labelText: l.email),
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
                isExpanded: true,
                initialValue: role,
                decoration: InputDecoration(labelText: l.role),
                items: [
                  for (final r in ['manager', 'staff'])
                    if (can.manage(roleFrom(r))) DropdownMenuItem(value: r, child: Text(roleLabel(c, r))),
                ],
                onChanged: (v) => set(() => role = v!),
              ),
              if (role == 'staff')
                SwitchListTile(contentPadding: EdgeInsets.zero, title: Text(l.canEditPrices), value: prices, onChanged: (v) => set(() => prices = v)),
              const SizedBox(height: 12),
              FilledButton(onPressed: () => Navigator.pop(c, true), child: Text(l.invite)),
            ],
          ),
        ),
      ),
    );
    if (ok != true || !context.mounted) return;
    final t = ref.read(tenantProvider)!;
    final api = ref.read(apiProvider), container = context.container;
    if (await runOk(
      context,
      () => api.post(tenantPath(t.id, 'invitations'), data: {'email': email.text.trim(), 'role': role, 'can_edit_prices': prices}),
      success: l.inviteSent,
    )) {
      container.invalidate(cachedProvider(tenantPath(t.id, 'invitations')));
    }
  }
}

/// Plan, paid-until and invoices (open or share the PDF). Owners only.
class BillingScreenBody extends ConsumerWidget {
  const BillingScreenBody({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final t = ref.watch(tenantProvider)!;
    final l = context.l;
    return CachedView(
      path: tenantPath(t.id, 'billing'),
      builder: (context, data, _) {
        final d = J.data(data);
        final plan = d.o('plan');
        return ListView(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 40),
          children: [
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(plan.s('name', '—'), style: context.text.titleLarge?.copyWith(fontWeight: FontWeight.w700)),
                    if (plan.iN('price_minor') != null) Text('${formatMoney(plan.i('price_minor'), plan.s('currency'))} / ${plan.s('interval')}'),
                    const SizedBox(height: 10),
                    Text('${l.status}: ${stateLabel(context, d.s('state'))}'),
                    if (d.sn('paid_until') != null) Text('${l.paidUntil}: ${formatDate(context, d.s('paid_until'))}'),
                    if (d.sn('trial_ends_at') != null && d.sn('paid_until') == null) Text('${l.trialEnds}: ${formatDate(context, d.s('trial_ends_at'))}'),
                    const SizedBox(height: 12),
                    OutlinedButton.icon(onPressed: () => showUpgradeSheet(context), icon: const Icon(AppIcons.arrowUp), label: Text(l.upgradeButton)),
                  ],
                ),
              ),
            ),
            SectionHeader(l.invoices),
            if (d.list('invoices').isEmpty) Text(l.noInvoices, style: TextStyle(color: context.colors.onSurfaceVariant)),
            for (final i in d.list('invoices'))
              Card(
                margin: const EdgeInsets.only(bottom: 8),
                child: ListTile(
                  title: Text(i.s('number')),
                  subtitle: Text(
                    '${formatDate(context, i.s('issued_at'))} · ${formatMoney(i.i('total_minor'), i.s('currency'))}${i.s('status') == 'void' ? ' · void' : ''}',
                  ),
                  trailing: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      IconButton(
                        tooltip: l.open,
                        icon: const Icon(AppIcons.fileText),
                        onPressed: () => downloadAndOpen(context, ref, tenantPath(t.id, 'billing/invoices/${i.i('id')}/pdf'), '${i.s('number')}.pdf'),
                      ),
                      IconButton(
                        tooltip: l.share,
                        icon: const Icon(AppIcons.share2),
                        onPressed: () =>
                            downloadAndOpen(context, ref, tenantPath(t.id, 'billing/invoices/${i.i('id')}/pdf'), '${i.s('number')}.pdf', share: true),
                      ),
                    ],
                  ),
                ),
              ),
          ],
        );
      },
    );
  }
}

String stateLabel(BuildContext c, String s) => switch (s) {
  'trial' => c.l.state_trial,
  'active' => c.l.state_active,
  'grace' => c.l.state_grace,
  'read_only' => c.l.state_read_only,
  'unavailable' => c.l.state_unavailable,
  'free' => c.l.state_free,
  'pending' => c.l.state_pending,
  _ => s,
};
