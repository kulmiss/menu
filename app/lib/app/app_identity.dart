/// Everything that names the app, in one place (PLAN.md §1).
/// The Android label lives in android/app/src/main/AndroidManifest.xml
/// (`@string/app_name`) and the iOS one in ios/Runner/Info.plist.
abstract final class AppIdentity {
  static const name = 'Menu Admin';
  static const androidPackage = 'com.kulmis.menu_admin';
  static const iosBundleId = 'com.kulmis.menuadmin';

  /// Custom scheme for links into the app: `menuadmin://open/<path>`.
  static const scheme = 'menuadmin';
}
