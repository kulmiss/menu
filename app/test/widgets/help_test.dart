// Help centre and welcome tour in the app (backend QUESTIONS #194, #195).
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:menu_admin/app/router.dart';
import 'package:menu_admin/features/help/help_data.dart';
import 'package:menu_admin/features/help/help_markdown.dart';
import 'package:menu_admin/features/help/tour.dart';
import 'package:menu_admin/core/util/json.dart';

import '../helpers/fake_api.dart';
import 'flows.dart';

Map<String, dynamic> article(String slug, String title, String body, {String topic = 'menu', List<String> app = const [], String summary = ''}) => {
  'slug': slug,
  'topic': topic,
  'title': title,
  'summary': summary,
  'pages': [],
  'app': app,
  'keywords': [],
  'body': body,
  'html': '',
  'updated_at': null,
};

Map<String, dynamic> library({Map<String, bool> voted = const {}}) => {
  'data': {
    'topics': [
      {'key': 'menu', 'title': 'Your menu'},
      {'key': 'faq', 'title': 'FAQ and contact'},
    ],
    'articles': [
      article(
        'add-a-dish',
        'Add a dish',
        '1. Open **Menu editor**.\n2. Tap **Add item**.\n\nSee [Dish photos](help:dish-photos).\n\n![Editor](/help/shots/dish-editor-phone.webp "phone") ![Editor](/help/shots/dish-editor-desktop.webp "desktop")',
        app: ['/r/:tid/menu'],
        summary: 'Name, price, photo',
      ),
      article('dish-photos', 'Dish photos', 'Up to 5 photos.'),
      article('contact-support', 'Contact support', 'WhatsApp us.', topic: 'faq'),
    ],
    'voted': voted,
  },
};

void main() {
  setUp(resetAppTourForTests);

  test('finds the article for a screen and searches', () {
    final lib = HelpLibrary(J.data(library()));
    expect(articleForLocation(lib.articles, '/r/7/menu')?.slug, 'add-a-dish');
    expect(articleForLocation(lib.articles, '/r/7/menu/'), isNotNull);
    expect(articleForLocation(lib.articles, '/r/7/more'), isNull);
    expect(searchHelp(lib.articles, 'photo').map((a) => a.slug), ['dish-photos', 'add-a-dish']);
    expect(searchHelp(lib.articles, 'photo zebra'), isEmpty);
  });

  testWidgets('markdown: lists, bold, help links and only the phone picture', (tester) async {
    String? opened;
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: HelpMarkdown(
            HelpArticle(J.of(library()['data']['articles'][0])).body,
            siteUrl: 'https://menu.test/',
            onHelpLink: (s) => opened = s,
            onLink: (_) {},
          ),
        ),
      ),
    );
    expect(find.text('1.'), findsOneWidget);
    expect(find.text('2.'), findsOneWidget);
    expect(find.textContaining('Add item', findRichText: true), findsOneWidget);
    final images = tester.widgetList<Image>(find.byType(Image)).toList();
    expect(images, hasLength(1));
    expect((images.single.image as NetworkImage).url, 'https://menu.test/help/shots/dish-editor-phone.webp');
    await tester.tapOnText(find.textRange.ofSubstring('Dish photos'));
    expect(opened, 'dish-photos');
  });

  testWidgets('Help: topics, search, article, link to another, "Was this helpful?"', (tester) async {
    final api = restaurantServer()
      ..json('GET restaurant/7/help', library())
      ..on('POST help/feedback', (_) => (204, null));
    final c = await startApp(tester, api, token: 'tok');
    c.read(routerProvider).push('/help');
    await tester.pumpAndSettle();
    expect(find.text('YOUR MENU'), findsOneWidget);
    expect(find.text('Add a dish'), findsOneWidget);

    await tester.enterText(find.byType(TextField), 'zebra');
    await tester.pumpAndSettle();
    expect(find.text('Nothing found. Try other words.'), findsOneWidget);
    await tester.enterText(find.byType(TextField), 'photo');
    await tester.pumpAndSettle();
    expect(find.text('2 articles found'), findsOneWidget);

    await tester.tap(find.text('Add a dish'));
    await tester.pumpAndSettle();
    expect(find.text('Was this helpful?'), findsOneWidget);
    await tester.tap(find.text('Yes'));
    await tester.pumpAndSettle();
    expect(api.called('POST', 'help/feedback').single.body, {'audience': 'restaurant', 'slug': 'add-a-dish', 'helpful': true});

    await tester.tapOnText(find.textRange.ofSubstring('Dish photos'));
    await tester.pumpAndSettle();
    expect(find.text('Up to 5 photos.'), findsOneWidget);
  });

  testWidgets('Help works offline from the saved copy', (tester) async {
    final api = restaurantServer()..json('GET restaurant/7/help', library());
    final c = await startApp(tester, api, token: 'tok');
    c.read(routerProvider).push('/help');
    await tester.pumpAndSettle();
    expect(find.text('Add a dish'), findsOneWidget);
    c.read(routerProvider).pop();
    await tester.pumpAndSettle();

    api.on('GET restaurant/7/help', (_) => throw const SocketException('offline'));
    c.read(routerProvider).push('/help');
    await tester.pumpAndSettle();
    expect(find.text('Add a dish'), findsOneWidget);
  });

  testWidgets('the "?" on a screen opens its article in a sheet', (tester) async {
    final api = restaurantServer()..json('GET restaurant/7/help', library());
    final c = await startApp(tester, api, token: 'tok');
    c.read(routerProvider).go('/r/7/menu');
    await tester.pumpAndSettle();
    await tester.tap(find.byTooltip('Help for this screen'));
    await tester.pumpAndSettle();
    expect(find.text('Add a dish'), findsOneWidget);
    expect(find.text('Open Help'), findsOneWidget);
  });

  testWidgets('signed out: Help from the sign-in screen shows the public articles', (tester) async {
    final api = restaurantServer()..json('GET help/public', library());
    await startApp(tester, api);
    await tester.tap(find.text('Help'));
    await tester.pumpAndSettle();
    expect(find.text('Contact support'), findsWidgets);
    await tester.tap(find.text('Add a dish'));
    await tester.pumpAndSettle();
    expect(find.text('Was this helpful?'), findsNothing, reason: 'feedback needs an account');
  });

  testWidgets('welcome tour: once on Home, skippable, not again', (tester) async {
    final me = meJson();
    (me['data'] as Map)['onboarding'] = {'web_tour': true, 'app_tour': false, 'setup_needed': false};
    final api = restaurantServer()
      ..json('GET me', me)
      ..on('PUT me/onboarding', (_) => (204, null));
    await startApp(tester, api, token: 'tok');
    expect(find.byKey(const Key('app-tour')), findsOneWidget);
    expect(find.text('This is Home'), findsOneWidget);
    for (final title in ['Add your first dish', 'Choose your menu look', 'Share your QR code']) {
      await tester.tap(find.text('Next'));
      await tester.pumpAndSettle();
      expect(find.text(title), findsOneWidget);
    }
    await tester.tap(find.text('Skip tour'));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('app-tour')), findsNothing);
    expect(api.called('PUT', 'me/onboarding').single.body, {'tour': 'app_tour', 'seen': true});
  });

  testWidgets('no tour when the server says it was seen (or is too old to say)', (tester) async {
    await startApp(tester, restaurantServer(), token: 'tok');
    expect(find.byKey(const Key('app-tour')), findsNothing);
  });
}
