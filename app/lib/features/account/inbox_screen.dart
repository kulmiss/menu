import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/cache/swr.dart';
import '../../core/providers.dart';
import '../../core/util/links.dart';
import '../../core/widgets/kit.dart';
import '../../core/widgets/paged_list.dart';

IconData notificationIcon(String type) => switch (type) {
  'plan_expiring' || 'plan_expired' => AppIcons.calendarX,
  'monthly_report' => AppIcons.chartLine,
  'invitation_accepted' => AppIcons.userRoundPlus,
  'platform_announcement' => AppIcons.megaphone,
  'signup_new' || 'signup_pending' => AppIcons.store,
  'payment_recorded' => AppIcons.banknote,
  'backup_failed' => AppIcons.cloudUpload,
  'health_warning' => AppIcons.activity,
  'followups_due' => AppIcons.calendarDays,
  _ => AppIcons.bell,
};

/// The notification inbox (last 90 days, from the server).
class InboxScreen extends ConsumerWidget {
  const InboxScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l;
    return Scaffold(
      appBar: AppBar(
        title: Text(l.inbox),
        actions: [
          TextButton(
            onPressed: () async {
              final api = ref.read(apiProvider), container = context.container;
              await runOk(context, () => api.post('mobile/notifications/read', data: {'all': true}));
              container.invalidate(cachedProvider('mobile/notifications'));
            },
            child: Text(l.markAllRead),
          ),
        ],
      ),
      body: PagedList(
        path: 'mobile/notifications',
        cursor: true,
        empty: EmptyState(icon: AppIcons.bell, title: l.inboxEmpty),
        itemBuilder: (context, n, _) => ListTile(
          leading: Icon(notificationIcon(n.s('type')), color: n.b('read') ? context.colors.onSurfaceVariant : context.colors.primary),
          title: Text(n.s('title'), style: TextStyle(fontWeight: n.b('read') ? FontWeight.w400 : FontWeight.w600)),
          subtitle: Text('${n.s('body')}\n${formatDate(context, n.sn('created_at'), time: true)}'),
          isThreeLine: true,
          onTap: () {
            ref
                .read(apiProvider)
                .post(
                  'mobile/notifications/read',
                  data: {
                    'ids': [n.i('id')],
                  },
                )
                .ignore();
            final path = appPathFrom(n.s('link'));
            if (path != null) context.push(path);
          },
        ),
      ),
    );
  }
}
