import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/cache/swr.dart';
import '../../core/providers.dart';
import '../../core/widgets/kit.dart';
import 'help_data.dart';
import 'help_markdown.dart';
import 'tour.dart';

/// Help: search, articles by topic, and one article at a time (works offline).
class HelpScreen extends ConsumerStatefulWidget {
  const HelpScreen({super.key});
  @override
  ConsumerState<HelpScreen> createState() => _HelpScreenState();
}

class _HelpScreenState extends ConsumerState<HelpScreen> {
  String _query = '';

  @override
  Widget build(BuildContext context) {
    final l = context.l;
    final source = ref.watch(helpSourceProvider);
    final lib = ref.watch(helpLibraryProvider(source));
    final s = ref.watch(sessionProvider);
    final tenant = ref.watch(tenantProvider);
    return Scaffold(
      appBar: AppBar(
        title: Text(l.helpTitle),
        actions: [if (tenant != null) TextButton.icon(onPressed: () => showAppTour(context, ref), icon: const Icon(AppIcons.compass), label: Text(l.tourOpen))],
      ),
      body: switch (lib) {
        AsyncData(:final value) || AsyncError(error: _, :final value?) => RefreshIndicator(
          onRefresh: () => ref.refreshPath(source.path),
          child: _list(context, value, s.status == SessionStatus.signedIn),
        ),
        AsyncError(:final error) => ErrorView(error: error, onRetry: () => ref.refreshPath(source.path)),
        _ => const Center(child: CircularProgressIndicator()),
      },
    );
  }

  Widget _list(BuildContext context, HelpLibrary lib, bool signedIn) {
    final l = context.l;
    final found = searchHelp(lib.articles, _query);
    return ListView(
      padding: const EdgeInsets.only(bottom: 32),
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
          child: TextField(
            decoration: InputDecoration(hintText: l.helpSearch, prefixIcon: const Icon(AppIcons.search), border: const OutlineInputBorder()),
            textInputAction: TextInputAction.search,
            onChanged: (v) => setState(() => _query = v),
          ),
        ),
        if (_query.trim().isNotEmpty)
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: Semantics(liveRegion: true, child: Text(l.helpResults(found.length))),
          ),
        if (found.isEmpty)
          EmptyState(
            icon: AppIcons.lifeBuoy,
            title: l.helpNothing,
            action: lib.bySlug('contact-support') == null
                ? null
                : TextButton(onPressed: () => context.push('/help/contact-support'), child: Text(l.helpContact)),
          )
        else
          for (final topic in lib.topics)
            if (found.any((a) => a.topic == topic.key)) ...[
              SectionHeader(topic.title),
              Group(
                children: [
                  for (final a in found.where((a) => a.topic == topic.key))
                    ListTile(
                      title: Text(a.title),
                      subtitle: a.summary.isEmpty ? null : Text(a.summary),
                      trailing: const Icon(AppIcons.chevronRight),
                      onTap: () => context.push('/help/${a.slug}'),
                    ),
                ],
              ),
            ],
      ],
    );
  }
}

/// One article, full screen.
class HelpArticleScreen extends ConsumerWidget {
  const HelpArticleScreen({super.key, required this.slug});
  final String slug;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final source = ref.watch(helpSourceProvider);
    final lib = ref.watch(helpLibraryProvider(source)).value;
    final a = lib?.bySlug(slug);
    return Scaffold(
      appBar: AppBar(title: Text(a?.title ?? context.l.helpTitle)),
      body: lib == null
          ? const Center(child: CircularProgressIndicator())
          : a == null
          ? EmptyState(icon: AppIcons.searchX, title: context.l.helpNotFound)
          : ListView(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
              children: [HelpArticleBody(article: a, source: source, voted: lib.voted[a.slug], onOpen: (s) => context.push('/help/$s'))],
            ),
    );
  }
}

/// The article text and "Was this helpful?".
class HelpArticleBody extends ConsumerWidget {
  const HelpArticleBody({super.key, required this.article, required this.source, required this.voted, required this.onOpen});
  final HelpArticle article;
  final HelpSource source;
  final bool? voted;
  final void Function(String slug) onOpen;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (article.summary.isNotEmpty)
          Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: Text(article.summary, style: TextStyle(color: Theme.of(context).colorScheme.onSurfaceVariant)),
          ),
        HelpMarkdown(article.body, siteUrl: ref.read(apiProvider).siteUrl, onHelpLink: onOpen, onLink: (u) => openExternal(context, u)),
        if (source is! PublicHelp) ...[
          const Divider(height: 32),
          Text(voted == null ? l.helpHelpful : l.helpThanks, style: const TextStyle(fontWeight: FontWeight.w600)),
          const SizedBox(height: 8),
          Wrap(
            spacing: 8,
            children: [
              for (final yes in [true, false])
                (voted == yes ? FilledButton.icon : OutlinedButton.icon)(
                  onPressed: () async {
                    final api = ref.read(apiProvider), container = context.container;
                    final ok = await runOk(context, () => api.post('help/feedback', data: {'audience': source.audience, 'slug': article.slug, 'helpful': yes}));
                    if (ok) container.invalidate(cachedProvider(source.path));
                  },
                  icon: Icon(yes ? AppIcons.thumbsUp : AppIcons.thumbsDown),
                  label: Text(yes ? l.yes : l.no),
                ),
            ],
          ),
        ],
      ],
    );
  }
}

/// The "?" in an app bar: the article for this screen in a sheet.
class HelpButton extends ConsumerWidget {
  const HelpButton({super.key, this.source});

  /// Default: whoever is signed in (restaurant, Super Admin guide, or public).
  final HelpSource? source;

  @override
  Widget build(BuildContext context, WidgetRef ref) => IconButton(
    tooltip: context.l.helpThisScreen,
    icon: const Icon(AppIcons.circleHelp),
    onPressed: () => showHelpSheet(context, ref, source ?? ref.read(helpSourceProvider), GoRouterState.of(context).uri.path),
  );
}

Future<void> showHelpSheet(BuildContext context, WidgetRef ref, HelpSource source, String location) => showModalBottomSheet<void>(
  context: context,
  isScrollControlled: true,
  useRootNavigator: true,
  useSafeArea: true,
  showDragHandle: true,
  builder: (context) => DraggableScrollableSheet(
    expand: false,
    initialChildSize: 0.75,
    maxChildSize: 0.95,
    builder: (context, scroll) => _HelpSheet(source: source, location: location, scroll: scroll),
  ),
);

class _HelpSheet extends ConsumerStatefulWidget {
  const _HelpSheet({required this.source, required this.location, required this.scroll});
  final HelpSource source;
  final String location;
  final ScrollController scroll;
  @override
  ConsumerState<_HelpSheet> createState() => _HelpSheetState();
}

class _HelpSheetState extends ConsumerState<_HelpSheet> {
  final _trail = <String>[];

  @override
  Widget build(BuildContext context) {
    final l = context.l;
    final lib = ref.watch(helpLibraryProvider(widget.source)).value;
    final here = lib == null ? null : articleForLocation(lib.articles, widget.location);
    final a = _trail.isEmpty ? here : lib?.bySlug(_trail.last);
    return ListView(
      controller: widget.scroll,
      padding: const EdgeInsets.fromLTRB(16, 0, 16, 32),
      children: [
        if (_trail.isNotEmpty)
          Align(
            alignment: AlignmentDirectional.centerStart,
            child: TextButton.icon(onPressed: () => setState(_trail.removeLast), icon: const Icon(AppIcons.arrowLeft), label: Text(l.back)),
          ),
        if (lib == null)
          const Padding(
            padding: EdgeInsets.all(32),
            child: Center(child: CircularProgressIndicator()),
          )
        else if (a == null)
          Text(l.helpNoArticle)
        else ...[
          Text(a.title, style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 8),
          HelpArticleBody(article: a, source: widget.source, voted: lib.voted[a.slug], onOpen: (s) => setState(() => _trail.add(s))),
        ],
        const SizedBox(height: 16),
        OutlinedButton.icon(
          onPressed: () {
            Navigator.of(context).pop();
            context.push(a == null ? '/help' : '/help/${a.slug}');
          },
          icon: const Icon(AppIcons.bookOpen),
          label: Text(l.helpOpenCentre),
        ),
      ],
    );
  }
}
