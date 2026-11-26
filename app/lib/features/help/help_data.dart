import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/cache/swr.dart';
import '../../core/providers.dart';
import '../../core/util/json.dart';

/// Help centre (backend QUESTIONS #194): the same articles as the web admin,
/// kept on the phone by the offline cache, so Help also works without internet.
class HelpArticle {
  HelpArticle(this.j);
  final J j;
  String get slug => j.s('slug');
  String get topic => j.s('topic');
  String get title => j.s('title');
  String get summary => j.s('summary');
  String get body => j.s('body');
  List<String> get app => [for (final p in (j.m['app'] as List? ?? const [])) '$p'];
  List<String> get keywords => [for (final k in (j.m['keywords'] as List? ?? const [])) '$k'];
}

class HelpLibrary {
  HelpLibrary(J data)
    : topics = [for (final t in data.list('topics')) (key: t.s('key'), title: t.s('title'))],
      articles = [for (final a in data.list('articles')) HelpArticle(a)],
      voted = {for (final e in data.o('voted').m.entries) e.key: e.value == true};
  final List<({String key, String title})> topics;
  final List<HelpArticle> articles;
  final Map<String, bool> voted;

  HelpArticle? bySlug(String slug) => articles.where((a) => a.slug == slug).firstOrNull;
}

/// Which help to load: a restaurant's, the Super Admin guide, or (signed out) the public articles.
sealed class HelpSource {
  const HelpSource();
  String get path;
  String get audience;
}

class RestaurantHelp extends HelpSource {
  const RestaurantHelp(this.tenantId);
  final int tenantId;
  @override
  String get path => tenantPath(tenantId, 'help');
  @override
  String get audience => 'restaurant';
  @override
  bool operator ==(Object other) => other is RestaurantHelp && other.tenantId == tenantId;
  @override
  int get hashCode => tenantId;
}

class PlatformHelp extends HelpSource {
  const PlatformHelp();
  @override
  String get path => 'super/help';
  @override
  String get audience => 'platform';
}

class PublicHelp extends HelpSource {
  const PublicHelp();
  @override
  String get path => 'help/public';
  @override
  String get audience => 'restaurant';
}

/// The help for whoever is using the app now.
final helpSourceProvider = Provider<HelpSource>((ref) {
  final s = ref.watch(sessionProvider);
  if (s.status != SessionStatus.signedIn) return const PublicHelp();
  final t = ref.watch(tenantProvider);
  if (t != null) return RestaurantHelp(t.id);
  return (s.me?.isSuperAdmin ?? false) ? const PlatformHelp() : const PublicHelp();
});

final helpLibraryProvider = Provider.family<AsyncValue<HelpLibrary>, HelpSource>((ref, source) {
  final v = ref.watch(cachedProvider(source.path));
  return v.whenData((c) => HelpLibrary(J.data(c.data)));
});

/// The article for an app screen: the longest `app` pattern that matches (`:tid`, `:id` = any number).
HelpArticle? articleForLocation(List<HelpArticle> articles, String location) {
  final here = location.split('?').first.replaceAll(RegExp(r'/+$'), '');
  HelpArticle? best;
  var bestLen = -1;
  for (final a in articles) {
    for (final p in a.app) {
      final re = RegExp('^${RegExp.escape(p).replaceAll(RegExp(r':[a-z]+'), r'[^/]+')}\$');
      if (re.hasMatch(here) && p.length > bestLen) {
        best = a;
        bestLen = p.length;
      }
    }
  }
  return best;
}

/// Every word must appear; titles and keywords count most.
List<HelpArticle> searchHelp(List<HelpArticle> articles, String query) {
  final words = query.toLowerCase().split(RegExp(r'\s+')).where((w) => w.isNotEmpty).toList();
  if (words.isEmpty) return articles;
  final scored = <(HelpArticle, int)>[];
  for (final a in articles) {
    final title = a.title.toLowerCase(), keys = a.keywords.join(' ').toLowerCase(), summary = a.summary.toLowerCase(), body = a.body.toLowerCase();
    var score = 0;
    var all = true;
    for (final w in words) {
      final s = (title.contains(w) ? 6 : 0) + (keys.contains(w) ? 4 : 0) + (summary.contains(w) ? 2 : 0) + (body.contains(w) ? 1 : 0);
      if (s == 0) {
        all = false;
        break;
      }
      score += s;
    }
    if (all) scored.add((a, score));
  }
  scored.sort((x, y) => y.$2.compareTo(x.$2));
  return [for (final s in scored) s.$1];
}
