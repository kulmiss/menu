import 'package:drift/drift.dart';
import 'package:drift_flutter/drift_flutter.dart';

part 'cache_db.g.dart';

/// Offline-first read cache (QUESTIONS M2): one row per API answer.
class CacheEntries extends Table {
  TextColumn get key => text()();
  TextColumn get scope => text()();
  TextColumn get json => text()();
  DateTimeColumn get fetchedAt => dateTime()();

  @override
  Set<Column> get primaryKey => {scope, key};
}

@DriftDatabase(tables: [CacheEntries])
class CacheDb extends _$CacheDb {
  CacheDb([QueryExecutor? executor]) : super(executor ?? driftDatabase(name: 'menu_admin_cache'));

  @override
  int get schemaVersion => 1;

  Future<CacheEntry?> read(String scope, String key) => (select(cacheEntries)..where((t) => t.scope.equals(scope) & t.key.equals(key))).getSingleOrNull();

  Future<void> write(String scope, String key, String json) =>
      into(cacheEntries).insertOnConflictUpdate(CacheEntriesCompanion.insert(key: key, scope: scope, json: json, fetchedAt: DateTime.now()));

  Future<void> remove(String scope, String key) => (delete(cacheEntries)..where((t) => t.scope.equals(scope) & t.key.equals(key))).go();

  /// Drops every entry whose key starts with [prefix] (after a change).
  Future<void> removePrefix(String scope, String prefix) =>
      (delete(cacheEntries)..where((t) => t.scope.equals(scope) & t.key.like('${prefix.replaceAll('%', r'\%')}%'))).go();

  Future<void> clearScope(String scope) => (delete(cacheEntries)..where((t) => t.scope.equals(scope))).go();

  Future<void> clearAll() => delete(cacheEntries).go();
}
