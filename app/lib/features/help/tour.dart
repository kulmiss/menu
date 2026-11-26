import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/auth/permissions.dart';
import '../../core/providers.dart';
import '../../core/widgets/kit.dart';

/// One slide of the welcome tour.
typedef TourSlide = ({String key, IconData icon, String? path});

/// The app's welcome tour (backend QUESTIONS #195): at most 4 slides — your
/// Home, add your first dish, choose your menu look, share your QR code —
/// without the ones the role or a switched-off feature rules out.
List<TourSlide> tourSlides(Can can, int tenantId) => [
  (key: 'home', icon: AppIcons.house, path: null),
  if (can.manageMenu)
    (key: 'dish', icon: AppIcons.imagePlus, path: '/r/$tenantId/dishes/new?photo=1')
  else if (can.toggleSoldOut)
    (key: 'soldOut', icon: AppIcons.circleMinus, path: '/r/$tenantId/sold-out'),
  if (can.manageRestaurant) (key: 'look', icon: AppIcons.palette, path: '/r/$tenantId/appearance'),
  if (can.manageRestaurant) (key: 'qr', icon: AppIcons.qrCode, path: '/r/$tenantId/qr'),
];

bool _shownThisRun = false;

@visibleForTesting
void resetAppTourForTests() => _shownThisRun = false;

/// Opens the tour once on the first visit to Home, if the server says it wasn't seen.
void maybeShowAppTour(BuildContext context, WidgetRef ref) {
  final me = ref.read(sessionProvider).me;
  if (_shownThisRun || me == null || ref.read(sessionProvider).impersonating) return;
  final seen = (me.json['onboarding'] as Map?)?['app_tour'];
  if (seen != false) return; // missing (older server) = seen
  _shownThisRun = true;
  WidgetsBinding.instance.addPostFrameCallback((_) {
    if (context.mounted) showAppTour(context, ref);
  });
}

Future<void> showAppTour(BuildContext context, WidgetRef ref) async {
  final tenant = ref.read(tenantProvider);
  if (tenant == null) return;
  final slides = tourSlides(ref.read(canProvider), tenant.id);
  final go = await showDialog<String>(
    context: context,
    builder: (_) => _TourDialog(slides: slides),
  );
  // Seen (or skipped): not again by itself. Reopen from Help.
  try {
    await ref.read(apiProvider).put('me/onboarding', data: {'tour': 'app_tour', 'seen': true});
    await ref.read(sessionProvider.notifier).refreshMe();
  } catch (_) {}
  if (go != null && context.mounted) context.push(go);
}

class _TourDialog extends StatefulWidget {
  const _TourDialog({required this.slides});
  final List<TourSlide> slides;
  @override
  State<_TourDialog> createState() => _TourDialogState();
}

class _TourDialogState extends State<_TourDialog> {
  int _i = 0;

  @override
  Widget build(BuildContext context) {
    final l = context.l;
    final s = widget.slides[_i];
    final last = _i == widget.slides.length - 1;
    final cs = Theme.of(context).colorScheme;
    final (title, text, go) = switch (s.key) {
      'home' => (l.tourHomeTitle, l.tourHomeText, l.tourDone),
      'dish' => (l.tourDishTitle, l.tourDishText, l.tourDishGo),
      'soldOut' => (l.tourSoldOutTitle, l.tourSoldOutText, l.tourSoldOutGo),
      'look' => (l.tourLookTitle, l.tourLookText, l.tourLookGo),
      _ => (l.tourQrTitle, l.tourQrText, l.tourQrGo),
    };
    return AlertDialog(
      key: const Key('app-tour'),
      contentPadding: const EdgeInsets.fromLTRB(24, 20, 24, 8),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(l.tourStep(_i + 1, widget.slides.length), style: TextStyle(color: cs.onSurfaceVariant)),
          const SizedBox(height: 16),
          Container(
            width: 88,
            height: 88,
            decoration: BoxDecoration(color: cs.primaryContainer, shape: BoxShape.circle),
            child: Icon(s.icon, size: 40, color: cs.onPrimaryContainer),
          ),
          const SizedBox(height: 16),
          Text(title, textAlign: TextAlign.center, style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 8),
          Text(text, textAlign: TextAlign.center),
          const SizedBox(height: 16),
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              for (var n = 0; n < widget.slides.length; n++)
                Container(
                  width: 8,
                  height: 8,
                  margin: const EdgeInsets.symmetric(horizontal: 3),
                  decoration: BoxDecoration(shape: BoxShape.circle, color: n == _i ? cs.primary : cs.outlineVariant),
                ),
            ],
          ),
        ],
      ),
      actionsAlignment: MainAxisAlignment.spaceBetween,
      actions: [
        TextButton(onPressed: () => Navigator.of(context).pop(), child: Text(l.tourSkip)),
        Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (_i > 0) IconButton(tooltip: l.back, onPressed: () => setState(() => _i--), icon: const Icon(AppIcons.chevronLeft)),
            FilledButton(onPressed: () => last ? Navigator.of(context).pop(s.path) : setState(() => _i++), child: Text(last ? go : l.tourNext)),
          ],
        ),
      ],
    );
  }
}
