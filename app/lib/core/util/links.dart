import '../../app/app_identity.dart';

/// Only http(s) leaves the app (PLAN.md §7).
Uri? safeExternalUri(String url) {
  final uri = Uri.tryParse(url.trim());
  if (uri == null || !(uri.isScheme('https') || uri.isScheme('http')) || uri.host.isEmpty) return null;
  return uri;
}

const _appPrefixes = ['/r/', '/p/', '/p', '/account', '/inbox'];

/// An in-app path from a push payload or a `menuadmin://open/...` link, or null
/// when it isn't one of ours.
String? appPathFrom(String? link) {
  if (link == null || link.isEmpty) return null;
  String path;
  if (link.startsWith('/')) {
    path = link;
  } else {
    final uri = Uri.tryParse(link);
    if (uri == null || uri.scheme != AppIdentity.scheme || uri.host != 'open') return null;
    path = uri.path + (uri.hasQuery ? '?${uri.query}' : '');
  }
  if (path.contains('..') || path.contains('//')) return null;
  return _appPrefixes.any((p) => path == p || path.startsWith(p.endsWith('/') ? p : '$p/') || path.startsWith('$p?')) ? path : null;
}

/// Restaurant id of an in-app path like /r/12/billing.
int? tenantOfPath(String path) {
  final m = RegExp(r'^/r/(\d+)(/|$)').firstMatch(path);
  return m == null ? null : int.parse(m.group(1)!);
}
