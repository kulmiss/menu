// The shadcn building blocks in both themes (redesign 2026-11).
@Tags(['golden'])
library;

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:menu_admin/core/widgets/kit.dart';

import '../helpers/fake_api.dart';
import '../helpers/fonts.dart';

class _Kit extends StatelessWidget {
  const _Kit();
  @override
  Widget build(BuildContext context) => Scaffold(
    body: ListView(
      padding: const EdgeInsets.all(16),
      children: [
        const PageTitle('Components', subtitle: 'Web admin tokens'),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [
            for (final v in ButtonVariant.values) AppButton(label: v.name, variant: v, onPressed: () {}),
            const AppButton(label: 'Disabled', onPressed: null),
            AppButton(label: 'Add dish', icon: AppIcons.plus, onPressed: () {}),
            AppButton(label: 'Saving', loading: true, onPressed: () {}),
          ],
        ),
        const SizedBox(height: 16),
        Wrap(spacing: 8, runSpacing: 8, children: [for (final v in BadgeVariant.values) AppBadge(v.name, variant: v)]),
        const SizedBox(height: 16),
        const TextField(
          decoration: InputDecoration(labelText: 'Price', hintText: '0.00'),
        ),
        const SizedBox(height: 16),
        AppCard(
          child: Row(
            children: [
              const Expanded(child: Text('Show on menu')),
              Switch(value: true, onChanged: (_) {}),
              Switch(value: false, onChanged: (_) {}),
            ],
          ),
        ),
        const SizedBox(height: 16),
        const DefaultTabController(
          length: 3,
          child: TabBar(
            tabs: [
              Tab(text: 'Today'),
              Tab(text: '7 days'),
              Tab(text: '30 days'),
            ],
          ),
        ),
        const SizedBox(height: 16),
        const Row(
          children: [
            SkeletonBox(width: 48, height: 48),
            SizedBox(width: 12),
            Expanded(child: SkeletonBox()),
          ],
        ),
        const SizedBox(height: 8),
        const SizedBox(
          height: 200,
          child: EmptyState(icon: AppIcons.utensils, title: 'No dishes yet'),
        ),
      ],
    ),
  );
}

void main() {
  setUpAll(loadTestFonts);
  for (final b in Brightness.values) {
    testWidgets('ui kit ${b.name}', (tester) async {
      tester.view.physicalSize = const Size(390, 900) * 2;
      tester.view.devicePixelRatio = 2;
      addTearDown(tester.view.reset);
      await tester.pumpWidget(testApp(const _Kit(), brightness: b));
      await tester.pump(const Duration(milliseconds: 100));
      await expectLater(find.byType(_Kit), matchesGoldenFile('images/ui_kit_${b.name}.png'));
    });
  }

  testWidgets('toast shows its message with the right icon', (tester) async {
    await tester.pumpWidget(
      testApp(
        Builder(
          builder: (c) => Scaffold(
            body: Center(
              child: TextButton(
                onPressed: () => showToast(c, 'Price saved', kind: ToastKind.success),
                child: const Text('go'),
              ),
            ),
          ),
        ),
      ),
    );
    await tester.tap(find.text('go'));
    await tester.pump();
    expect(find.text('Price saved'), findsOneWidget);
    expect(find.byIcon(AppIcons.circleCheck), findsOneWidget);
  });

  testWidgets('every button and badge variant meets 48 px / renders', (tester) async {
    await tester.pumpWidget(testApp(const _Kit()));
    await tester.pump(const Duration(milliseconds: 100));
    for (final v in ButtonVariant.values) {
      expect(tester.getSize(find.widgetWithText(AppButton, v.name)).height, greaterThanOrEqualTo(48));
    }
    await expectLater(tester, meetsGuideline(androidTapTargetGuideline));
  });
}
