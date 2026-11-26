/// Small typed reads of API JSON (QUESTIONS M31: no codegen for the many
/// admin answers; core models in core/auth/models.dart).
extension type J(Map<String, dynamic> m) {
  static J of(Object? v) => J(v is Map ? v.cast<String, dynamic>() : <String, dynamic>{});

  /// `{data: …}` answers.
  static J data(Object? v) => J.of(J.of(v).m['data']);
  static List<J> dataList(Object? v) => J.of(v).list('data');

  dynamic operator [](String k) => m[k];
  String s(String k, [String fallback = '']) => m[k]?.toString() ?? fallback;
  String? sn(String k) => m[k]?.toString();
  int i(String k, [int fallback = 0]) => (m[k] is num) ? (m[k] as num).toInt() : int.tryParse('${m[k]}') ?? fallback;
  int? iN(String k) => (m[k] is num) ? (m[k] as num).toInt() : int.tryParse('${m[k] ?? ''}');
  double d(String k, [double fallback = 0]) => (m[k] is num) ? (m[k] as num).toDouble() : double.tryParse('${m[k]}') ?? fallback;
  bool b(String k, [bool fallback = false]) => m[k] is bool ? m[k] as bool : (m[k] == 1 || m[k] == '1' ? true : fallback);
  bool? bN(String k) => m[k] == null ? null : b(k);
  J o(String k) => J.of(m[k]);
  bool has(String k) => m[k] != null;
  List<J> list(String k) => [for (final e in (m[k] is List ? m[k] as List : const [])) J.of(e)];
  List<int> ints(String k) => [
    for (final e in (m[k] is List ? m[k] as List : const []))
      if (e is num) e.toInt(),
  ];

  /// A translated field ({"en": "…"}) in English, else the first language.
  String t(String k) => tr(m[k]);

  /// Photo URL of a media object: the size closest to [width] px.
  String? photo(String k, {int width = 320}) => photoUrl(m[k], width: width);
}

String tr(Object? v) {
  if (v is String) return v;
  if (v is Map) {
    final en = v['en'];
    if (en is String && en.isNotEmpty) return en;
    for (final x in v.values) {
      if (x is String && x.isNotEmpty) return x;
    }
  }
  return '';
}

String? photoUrl(Object? media, {int width = 320}) {
  if (media is! Map) return null;
  final urls = media['urls'];
  if (urls is! Map || urls.isEmpty) return null;
  for (final w in [width, 640, 320, 800, 1280]) {
    final u = urls['$w'];
    if (u is String) return u;
  }
  final jpg = urls['jpg'];
  return jpg is String ? jpg : urls.values.whereType<String>().firstOrNull;
}
