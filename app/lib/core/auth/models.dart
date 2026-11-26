/// Core models read from `/me` (hand-written: QUESTIONS M31).
library;

enum Role { owner, manager, staff }

Role roleFrom(String? v) => switch (v) {
  'owner' => Role.owner,
  'manager' => Role.manager,
  _ => Role.staff,
};

class TenantInfo {
  TenantInfo(this.json);
  final Map<String, dynamic> json;

  int get id => json['id'] as int;
  String get name => json['name'] as String? ?? '';
  String get slug => json['slug'] as String? ?? '';
  String get currency => json['currency_code'] as String? ?? '';
  String get timezone => json['timezone'] as String? ?? 'UTC';
  Map<String, dynamic> get _sub => (json['subscription'] as Map?)?.cast<String, dynamic>() ?? const {};
  String get state => _sub['state'] as String? ?? 'active';
  bool get allowsWrites => _sub['allows_writes'] as bool? ?? true;
  bool get showsBanner => _sub['shows_banner'] as bool? ?? false;
  String? get stageEndsAt => _sub['stage_ends_at'] as String?;
  Map<String, dynamic> get features => (json['features'] as Map?)?.cast<String, dynamic>() ?? const {};
  bool feature(String key) => features[key] == true;

  /// For switches that are on unless the server says otherwise (newer keys
  /// such as `sold_out`, `share`, `print_materials`: an older server doesn't
  /// send them yet). Global OFF (Super Admin → Settings → Features) arrives
  /// here as `false` too.
  bool featureOn(String key) => features[key] != false;
  Map<String, dynamic> get upgrade => (json['upgrade'] as Map?)?.cast<String, dynamic>() ?? const {};
  Map<String, dynamic>? get _membership => (json['membership'] as Map?)?.cast<String, dynamic>();
  Role get role => roleFrom(_membership?['role'] as String?);
  bool get canEditPricesFlag => _membership?['can_edit_prices'] as bool? ?? false;
}

class Impersonation {
  Impersonation(this.json);
  final Map<String, dynamic> json;
  int get tenantId => json['tenant_id'] as int;
  String get tenantName => json['tenant_name'] as String? ?? '';
  DateTime? get endsAt => DateTime.tryParse(json['ends_at'] as String? ?? '');
}

class Me {
  Me(this.json);
  final Map<String, dynamic> json;

  int get id => json['id'] as int;
  String get name => json['name'] as String? ?? '';
  String get email => json['email'] as String? ?? '';
  bool get emailVerified => json['email_verified'] as bool? ?? true;
  bool get twoFactorEnabled => json['two_factor_enabled'] as bool? ?? false;

  /// A new e-mail address waiting for its confirmation link (SECURITY_REPORT.md H3).
  String? get pendingEmail => json['pending_email'] as String?;

  /// The password is on the common/leaked list: a gentle banner (M3).
  bool get passwordWeak => json['password_weak'] as bool? ?? false;

  /// The Super Admin requires two-factor for Owners and it isn't on yet (M17).
  bool get twoFactorRequired => json['two_factor_required'] as bool? ?? false;
  bool get isSuperAdmin => json['is_super_admin'] as bool? ?? false;

  /// The Super Admin's web area: `/super` or the server's secret path (Super Admins only).
  String get superAdminPath => json['super_admin_url'] as String? ?? '/super';
  Impersonation? get impersonation => json['impersonation'] is Map ? Impersonation((json['impersonation'] as Map).cast<String, dynamic>()) : null;
  List<TenantInfo> get tenants => [for (final t in (json['tenants'] as List? ?? const [])) TenantInfo((t as Map).cast<String, dynamic>())];

  TenantInfo? tenant(int? id) {
    for (final t in tenants) {
      if (t.id == id) return t;
    }
    return null;
  }
}
