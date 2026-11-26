// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'cache_db.dart';

// ignore_for_file: type=lint
class $CacheEntriesTable extends CacheEntries with TableInfo<$CacheEntriesTable, CacheEntry> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $CacheEntriesTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _keyMeta = const VerificationMeta('key');
  @override
  late final GeneratedColumn<String> key = GeneratedColumn<String>('key', aliasedName, false, type: DriftSqlType.string, requiredDuringInsert: true);
  static const VerificationMeta _scopeMeta = const VerificationMeta('scope');
  @override
  late final GeneratedColumn<String> scope = GeneratedColumn<String>('scope', aliasedName, false, type: DriftSqlType.string, requiredDuringInsert: true);
  static const VerificationMeta _jsonMeta = const VerificationMeta('json');
  @override
  late final GeneratedColumn<String> json = GeneratedColumn<String>('json', aliasedName, false, type: DriftSqlType.string, requiredDuringInsert: true);
  static const VerificationMeta _fetchedAtMeta = const VerificationMeta('fetchedAt');
  @override
  late final GeneratedColumn<DateTime> fetchedAt = GeneratedColumn<DateTime>(
    'fetched_at',
    aliasedName,
    false,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: true,
  );
  @override
  List<GeneratedColumn> get $columns => [key, scope, json, fetchedAt];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'cache_entries';
  @override
  VerificationContext validateIntegrity(Insertable<CacheEntry> instance, {bool isInserting = false}) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('key')) {
      context.handle(_keyMeta, key.isAcceptableOrUnknown(data['key']!, _keyMeta));
    } else if (isInserting) {
      context.missing(_keyMeta);
    }
    if (data.containsKey('scope')) {
      context.handle(_scopeMeta, scope.isAcceptableOrUnknown(data['scope']!, _scopeMeta));
    } else if (isInserting) {
      context.missing(_scopeMeta);
    }
    if (data.containsKey('json')) {
      context.handle(_jsonMeta, json.isAcceptableOrUnknown(data['json']!, _jsonMeta));
    } else if (isInserting) {
      context.missing(_jsonMeta);
    }
    if (data.containsKey('fetched_at')) {
      context.handle(_fetchedAtMeta, fetchedAt.isAcceptableOrUnknown(data['fetched_at']!, _fetchedAtMeta));
    } else if (isInserting) {
      context.missing(_fetchedAtMeta);
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {scope, key};
  @override
  CacheEntry map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return CacheEntry(
      key: attachedDatabase.typeMapping.read(DriftSqlType.string, data['${effectivePrefix}key'])!,
      scope: attachedDatabase.typeMapping.read(DriftSqlType.string, data['${effectivePrefix}scope'])!,
      json: attachedDatabase.typeMapping.read(DriftSqlType.string, data['${effectivePrefix}json'])!,
      fetchedAt: attachedDatabase.typeMapping.read(DriftSqlType.dateTime, data['${effectivePrefix}fetched_at'])!,
    );
  }

  @override
  $CacheEntriesTable createAlias(String alias) {
    return $CacheEntriesTable(attachedDatabase, alias);
  }
}

class CacheEntry extends DataClass implements Insertable<CacheEntry> {
  final String key;
  final String scope;
  final String json;
  final DateTime fetchedAt;
  const CacheEntry({required this.key, required this.scope, required this.json, required this.fetchedAt});
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['key'] = Variable<String>(key);
    map['scope'] = Variable<String>(scope);
    map['json'] = Variable<String>(json);
    map['fetched_at'] = Variable<DateTime>(fetchedAt);
    return map;
  }

  CacheEntriesCompanion toCompanion(bool nullToAbsent) {
    return CacheEntriesCompanion(key: Value(key), scope: Value(scope), json: Value(json), fetchedAt: Value(fetchedAt));
  }

  factory CacheEntry.fromJson(Map<String, dynamic> json, {ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return CacheEntry(
      key: serializer.fromJson<String>(json['key']),
      scope: serializer.fromJson<String>(json['scope']),
      json: serializer.fromJson<String>(json['json']),
      fetchedAt: serializer.fromJson<DateTime>(json['fetchedAt']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'key': serializer.toJson<String>(key),
      'scope': serializer.toJson<String>(scope),
      'json': serializer.toJson<String>(json),
      'fetchedAt': serializer.toJson<DateTime>(fetchedAt),
    };
  }

  CacheEntry copyWith({String? key, String? scope, String? json, DateTime? fetchedAt}) =>
      CacheEntry(key: key ?? this.key, scope: scope ?? this.scope, json: json ?? this.json, fetchedAt: fetchedAt ?? this.fetchedAt);
  CacheEntry copyWithCompanion(CacheEntriesCompanion data) {
    return CacheEntry(
      key: data.key.present ? data.key.value : this.key,
      scope: data.scope.present ? data.scope.value : this.scope,
      json: data.json.present ? data.json.value : this.json,
      fetchedAt: data.fetchedAt.present ? data.fetchedAt.value : this.fetchedAt,
    );
  }

  @override
  String toString() {
    return (StringBuffer('CacheEntry(')
          ..write('key: $key, ')
          ..write('scope: $scope, ')
          ..write('json: $json, ')
          ..write('fetchedAt: $fetchedAt')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(key, scope, json, fetchedAt);
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is CacheEntry && other.key == this.key && other.scope == this.scope && other.json == this.json && other.fetchedAt == this.fetchedAt);
}

class CacheEntriesCompanion extends UpdateCompanion<CacheEntry> {
  final Value<String> key;
  final Value<String> scope;
  final Value<String> json;
  final Value<DateTime> fetchedAt;
  final Value<int> rowid;
  const CacheEntriesCompanion({
    this.key = const Value.absent(),
    this.scope = const Value.absent(),
    this.json = const Value.absent(),
    this.fetchedAt = const Value.absent(),
    this.rowid = const Value.absent(),
  });
  CacheEntriesCompanion.insert({
    required String key,
    required String scope,
    required String json,
    required DateTime fetchedAt,
    this.rowid = const Value.absent(),
  }) : key = Value(key),
       scope = Value(scope),
       json = Value(json),
       fetchedAt = Value(fetchedAt);
  static Insertable<CacheEntry> custom({
    Expression<String>? key,
    Expression<String>? scope,
    Expression<String>? json,
    Expression<DateTime>? fetchedAt,
    Expression<int>? rowid,
  }) {
    return RawValuesInsertable({
      if (key != null) 'key': key,
      if (scope != null) 'scope': scope,
      if (json != null) 'json': json,
      if (fetchedAt != null) 'fetched_at': fetchedAt,
      if (rowid != null) 'rowid': rowid,
    });
  }

  CacheEntriesCompanion copyWith({Value<String>? key, Value<String>? scope, Value<String>? json, Value<DateTime>? fetchedAt, Value<int>? rowid}) {
    return CacheEntriesCompanion(
      key: key ?? this.key,
      scope: scope ?? this.scope,
      json: json ?? this.json,
      fetchedAt: fetchedAt ?? this.fetchedAt,
      rowid: rowid ?? this.rowid,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (key.present) {
      map['key'] = Variable<String>(key.value);
    }
    if (scope.present) {
      map['scope'] = Variable<String>(scope.value);
    }
    if (json.present) {
      map['json'] = Variable<String>(json.value);
    }
    if (fetchedAt.present) {
      map['fetched_at'] = Variable<DateTime>(fetchedAt.value);
    }
    if (rowid.present) {
      map['rowid'] = Variable<int>(rowid.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('CacheEntriesCompanion(')
          ..write('key: $key, ')
          ..write('scope: $scope, ')
          ..write('json: $json, ')
          ..write('fetchedAt: $fetchedAt, ')
          ..write('rowid: $rowid')
          ..write(')'))
        .toString();
  }
}

abstract class _$CacheDb extends GeneratedDatabase {
  _$CacheDb(QueryExecutor e) : super(e);
  $CacheDbManager get managers => $CacheDbManager(this);
  late final $CacheEntriesTable cacheEntries = $CacheEntriesTable(this);
  @override
  Iterable<TableInfo<Table, Object?>> get allTables => allSchemaEntities.whereType<TableInfo<Table, Object?>>();
  @override
  List<DatabaseSchemaEntity> get allSchemaEntities => [cacheEntries];
}

typedef $$CacheEntriesTableCreateCompanionBuilder = CacheEntriesCompanion Function({
  required String key,
  required String scope,
  required String json,
  required DateTime fetchedAt,
  Value<int> rowid,
});
typedef $$CacheEntriesTableUpdateCompanionBuilder = CacheEntriesCompanion Function({
  Value<String> key,
  Value<String> scope,
  Value<String> json,
  Value<DateTime> fetchedAt,
  Value<int> rowid,
});

class $$CacheEntriesTableFilterComposer extends Composer<_$CacheDb, $CacheEntriesTable> {
  $$CacheEntriesTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<String> get key => $composableBuilder(column: $table.key, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get scope => $composableBuilder(column: $table.scope, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get json => $composableBuilder(column: $table.json, builder: (column) => ColumnFilters(column));

  ColumnFilters<DateTime> get fetchedAt => $composableBuilder(column: $table.fetchedAt, builder: (column) => ColumnFilters(column));
}

class $$CacheEntriesTableOrderingComposer extends Composer<_$CacheDb, $CacheEntriesTable> {
  $$CacheEntriesTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<String> get key => $composableBuilder(column: $table.key, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get scope => $composableBuilder(column: $table.scope, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get json => $composableBuilder(column: $table.json, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<DateTime> get fetchedAt => $composableBuilder(column: $table.fetchedAt, builder: (column) => ColumnOrderings(column));
}

class $$CacheEntriesTableAnnotationComposer extends Composer<_$CacheDb, $CacheEntriesTable> {
  $$CacheEntriesTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<String> get key => $composableBuilder(column: $table.key, builder: (column) => column);

  GeneratedColumn<String> get scope => $composableBuilder(column: $table.scope, builder: (column) => column);

  GeneratedColumn<String> get json => $composableBuilder(column: $table.json, builder: (column) => column);

  GeneratedColumn<DateTime> get fetchedAt => $composableBuilder(column: $table.fetchedAt, builder: (column) => column);
}

class $$CacheEntriesTableTableManager
    extends
        RootTableManager<
          _$CacheDb,
          $CacheEntriesTable,
          CacheEntry,
          $$CacheEntriesTableFilterComposer,
          $$CacheEntriesTableOrderingComposer,
          $$CacheEntriesTableAnnotationComposer,
          $$CacheEntriesTableCreateCompanionBuilder,
          $$CacheEntriesTableUpdateCompanionBuilder,
          (CacheEntry, BaseReferences<_$CacheDb, $CacheEntriesTable, CacheEntry>),
          CacheEntry,
          PrefetchHooks Function()
        > {
  $$CacheEntriesTableTableManager(_$CacheDb db, $CacheEntriesTable table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () => $$CacheEntriesTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () => $$CacheEntriesTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () => $$CacheEntriesTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback: ({
            Value<String> key = const Value.absent(),
            Value<String> scope = const Value.absent(),
            Value<String> json = const Value.absent(),
            Value<DateTime> fetchedAt = const Value.absent(),
            Value<int> rowid = const Value.absent(),
          }) => CacheEntriesCompanion(key: key, scope: scope, json: json, fetchedAt: fetchedAt, rowid: rowid),
          createCompanionCallback: ({
            required String key,
            required String scope,
            required String json,
            required DateTime fetchedAt,
            Value<int> rowid = const Value.absent(),
          }) => CacheEntriesCompanion.insert(key: key, scope: scope, json: json, fetchedAt: fetchedAt, rowid: rowid),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable<$CacheEntriesTable, CacheEntry>(table), BaseReferences<_$CacheDb, $CacheEntriesTable, CacheEntry>(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$CacheEntriesTableProcessedTableManager =
    ProcessedTableManager<
      _$CacheDb,
      $CacheEntriesTable,
      CacheEntry,
      $$CacheEntriesTableFilterComposer,
      $$CacheEntriesTableOrderingComposer,
      $$CacheEntriesTableAnnotationComposer,
      $$CacheEntriesTableCreateCompanionBuilder,
      $$CacheEntriesTableUpdateCompanionBuilder,
      (CacheEntry, BaseReferences<_$CacheDb, $CacheEntriesTable, CacheEntry>),
      CacheEntry,
      PrefetchHooks Function()
    >;

class $CacheDbManager {
  final _$CacheDb _db;
  $CacheDbManager(this._db);
  $$CacheEntriesTableTableManager get cacheEntries => $$CacheEntriesTableTableManager(_db, _db.cacheEntries);
}
