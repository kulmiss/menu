import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:intl/intl.dart' as intl;

import 'app_localizations_en.dart';

// ignore_for_file: type=lint

/// Callers can lookup localized strings with an instance of L
/// returned by `L.of(context)`.
///
/// Applications need to include `L.delegate()` in their app's
/// `localizationDelegates` list, and the locales they support in the app's
/// `supportedLocales` list. For example:
///
/// ```dart
/// import 'l10n/app_localizations.dart';
///
/// return MaterialApp(
///   localizationsDelegates: L.localizationsDelegates,
///   supportedLocales: L.supportedLocales,
///   home: MyApplicationHome(),
/// );
/// ```
///
/// ## Update pubspec.yaml
///
/// Please make sure to update your pubspec.yaml to include the following
/// packages:
///
/// ```yaml
/// dependencies:
///   # Internationalization support.
///   flutter_localizations:
///     sdk: flutter
///   intl: any # Use the pinned version from flutter_localizations
///
///   # Rest of dependencies
/// ```
///
/// ## iOS Applications
///
/// iOS applications define key application metadata, including supported
/// locales, in an Info.plist file that is built into the application bundle.
/// To configure the locales supported by your app, you’ll need to edit this
/// file.
///
/// First, open your project’s ios/Runner.xcworkspace Xcode workspace file.
/// Then, in the Project Navigator, open the Info.plist file under the Runner
/// project’s Runner folder.
///
/// Next, select the Information Property List item, select Add Item from the
/// Editor menu, then select Localizations from the pop-up menu.
///
/// Select and expand the newly-created Localizations item then, for each
/// locale your application supports, add a new item and select the locale
/// you wish to add from the pop-up menu in the Value field. This list should
/// be consistent with the languages listed in the L.supportedLocales
/// property.
abstract class L {
  L(String locale) : localeName = intl.Intl.canonicalizedLocale(locale.toString());

  final String localeName;

  static L of(BuildContext context) {
    return Localizations.of<L>(context, L)!;
  }

  static const LocalizationsDelegate<L> delegate = _LDelegate();

  /// A list of this localizations delegate along with the default localizations
  /// delegates.
  ///
  /// Returns a list of localizations delegates containing this delegate along with
  /// GlobalMaterialLocalizations.delegate, GlobalCupertinoLocalizations.delegate,
  /// and GlobalWidgetsLocalizations.delegate.
  ///
  /// Additional delegates can be added by appending to this list in
  /// MaterialApp. This list does not have to be used at all if a custom list
  /// of delegates is preferred or required.
  static const List<LocalizationsDelegate<dynamic>> localizationsDelegates = <LocalizationsDelegate<dynamic>>[
    delegate,
    GlobalMaterialLocalizations.delegate,
    GlobalCupertinoLocalizations.delegate,
    GlobalWidgetsLocalizations.delegate,
  ];

  /// A list of this localizations delegate's supported locales.
  static const List<Locale> supportedLocales = <Locale>[Locale('en')];

  /// No description provided for @ok.
  ///
  /// In en, this message translates to:
  /// **'OK'**
  String get ok;

  /// No description provided for @cancel.
  ///
  /// In en, this message translates to:
  /// **'Cancel'**
  String get cancel;

  /// No description provided for @save.
  ///
  /// In en, this message translates to:
  /// **'Save'**
  String get save;

  /// No description provided for @saved.
  ///
  /// In en, this message translates to:
  /// **'Saved'**
  String get saved;

  /// No description provided for @delete.
  ///
  /// In en, this message translates to:
  /// **'Delete'**
  String get delete;

  /// No description provided for @edit.
  ///
  /// In en, this message translates to:
  /// **'Edit'**
  String get edit;

  /// No description provided for @add.
  ///
  /// In en, this message translates to:
  /// **'Add'**
  String get add;

  /// No description provided for @close.
  ///
  /// In en, this message translates to:
  /// **'Close'**
  String get close;

  /// No description provided for @retry.
  ///
  /// In en, this message translates to:
  /// **'Try again'**
  String get retry;

  /// No description provided for @refresh.
  ///
  /// In en, this message translates to:
  /// **'Refresh'**
  String get refresh;

  /// No description provided for @done.
  ///
  /// In en, this message translates to:
  /// **'Done'**
  String get done;

  /// No description provided for @search.
  ///
  /// In en, this message translates to:
  /// **'Search'**
  String get search;

  /// No description provided for @more.
  ///
  /// In en, this message translates to:
  /// **'More'**
  String get more;

  /// No description provided for @share.
  ///
  /// In en, this message translates to:
  /// **'Share'**
  String get share;

  /// No description provided for @open.
  ///
  /// In en, this message translates to:
  /// **'Open'**
  String get open;

  /// No description provided for @copy.
  ///
  /// In en, this message translates to:
  /// **'Copy'**
  String get copy;

  /// No description provided for @copied.
  ///
  /// In en, this message translates to:
  /// **'Copied'**
  String get copied;

  /// No description provided for @copiedSecret.
  ///
  /// In en, this message translates to:
  /// **'Copied. It is removed from the clipboard in 1 minute.'**
  String get copiedSecret;

  /// No description provided for @next.
  ///
  /// In en, this message translates to:
  /// **'Next'**
  String get next;

  /// No description provided for @none.
  ///
  /// In en, this message translates to:
  /// **'None'**
  String get none;

  /// No description provided for @all.
  ///
  /// In en, this message translates to:
  /// **'All'**
  String get all;

  /// No description provided for @loading.
  ///
  /// In en, this message translates to:
  /// **'Loading…'**
  String get loading;

  /// No description provided for @optional.
  ///
  /// In en, this message translates to:
  /// **'Optional'**
  String get optional;

  /// No description provided for @offlineBanner.
  ///
  /// In en, this message translates to:
  /// **'You\'re offline. Showing saved data; changes need a connection.'**
  String get offlineBanner;

  /// No description provided for @savedCopy.
  ///
  /// In en, this message translates to:
  /// **'Saved copy · {time}'**
  String savedCopy(Object time);

  /// No description provided for @errorOffline.
  ///
  /// In en, this message translates to:
  /// **'No connection. Check your internet and try again.'**
  String get errorOffline;

  /// No description provided for @errorTimeout.
  ///
  /// In en, this message translates to:
  /// **'The server took too long. Try again.'**
  String get errorTimeout;

  /// No description provided for @errorServer.
  ///
  /// In en, this message translates to:
  /// **'Something went wrong on the server. Try again in a moment.'**
  String get errorServer;

  /// No description provided for @errorNotFound.
  ///
  /// In en, this message translates to:
  /// **'This was not found. It may have been deleted.'**
  String get errorNotFound;

  /// No description provided for @errorForbidden.
  ///
  /// In en, this message translates to:
  /// **'You don\'t have permission to do this.'**
  String get errorForbidden;

  /// No description provided for @errorRateLimited.
  ///
  /// In en, this message translates to:
  /// **'Too many tries. Wait a minute and try again.'**
  String get errorRateLimited;

  /// No description provided for @errorUnknown.
  ///
  /// In en, this message translates to:
  /// **'Something went wrong. Try again.'**
  String get errorUnknown;

  /// No description provided for @emptyTitle.
  ///
  /// In en, this message translates to:
  /// **'Nothing here yet'**
  String get emptyTitle;

  /// No description provided for @upgradeTitle.
  ///
  /// In en, this message translates to:
  /// **'Upgrade to use this'**
  String get upgradeTitle;

  /// No description provided for @upgradeBody.
  ///
  /// In en, this message translates to:
  /// **'Your plan doesn\'t include this. Upgrade to unlock it.'**
  String get upgradeBody;

  /// No description provided for @upgradeButton.
  ///
  /// In en, this message translates to:
  /// **'Upgrade'**
  String get upgradeButton;

  /// No description provided for @upgradeBadge.
  ///
  /// In en, this message translates to:
  /// **'Upgrade'**
  String get upgradeBadge;

  /// No description provided for @readOnlyBanner.
  ///
  /// In en, this message translates to:
  /// **'Your plan has ended. Your menu is read-only until you renew.'**
  String get readOnlyBanner;

  /// No description provided for @confirmDeleteTitle.
  ///
  /// In en, this message translates to:
  /// **'Delete {name}?'**
  String confirmDeleteTitle(Object name);

  /// No description provided for @confirmDeleteBody.
  ///
  /// In en, this message translates to:
  /// **'This can\'t be undone.'**
  String get confirmDeleteBody;

  /// No description provided for @requiredField.
  ///
  /// In en, this message translates to:
  /// **'Required'**
  String get requiredField;

  /// No description provided for @signInTitle.
  ///
  /// In en, this message translates to:
  /// **'Sign in to Menu Admin'**
  String get signInTitle;

  /// No description provided for @signInSubtitle.
  ///
  /// In en, this message translates to:
  /// **'For restaurant teams and the platform admin'**
  String get signInSubtitle;

  /// No description provided for @email.
  ///
  /// In en, this message translates to:
  /// **'E-mail'**
  String get email;

  /// No description provided for @password.
  ///
  /// In en, this message translates to:
  /// **'Password'**
  String get password;

  /// No description provided for @signIn.
  ///
  /// In en, this message translates to:
  /// **'Sign in'**
  String get signIn;

  /// No description provided for @forgotPassword.
  ///
  /// In en, this message translates to:
  /// **'Forgot password?'**
  String get forgotPassword;

  /// No description provided for @newRestaurant.
  ///
  /// In en, this message translates to:
  /// **'New restaurant? Sign up on the website.'**
  String get newRestaurant;

  /// No description provided for @continueWithGoogle.
  ///
  /// In en, this message translates to:
  /// **'Continue with Google'**
  String get continueWithGoogle;

  /// No description provided for @orDivider.
  ///
  /// In en, this message translates to:
  /// **'or'**
  String get orDivider;

  /// No description provided for @googleLinked.
  ///
  /// In en, this message translates to:
  /// **'Google is now linked. Next time, tap “Continue with Google”.'**
  String get googleLinked;

  /// No description provided for @googleUnavailable.
  ///
  /// In en, this message translates to:
  /// **'Google sign-in is not available on this phone. Use your e-mail and password.'**
  String get googleUnavailable;

  /// No description provided for @googleFinishTitle.
  ///
  /// In en, this message translates to:
  /// **'Almost done'**
  String get googleFinishTitle;

  /// No description provided for @googleFinishBody.
  ///
  /// In en, this message translates to:
  /// **'You are signing up with Google as {email}. Type your restaurant name.'**
  String googleFinishBody(Object email);

  /// No description provided for @restaurantName.
  ///
  /// In en, this message translates to:
  /// **'Restaurant name'**
  String get restaurantName;

  /// No description provided for @acceptTerms.
  ///
  /// In en, this message translates to:
  /// **'I accept the Terms of Service and the Privacy Policy.'**
  String get acceptTerms;

  /// No description provided for @acceptTermsRequired.
  ///
  /// In en, this message translates to:
  /// **'Please accept the terms to continue.'**
  String get acceptTermsRequired;

  /// No description provided for @createMyFreeMenu.
  ///
  /// In en, this message translates to:
  /// **'Create my free menu'**
  String get createMyFreeMenu;

  /// No description provided for @privacy.
  ///
  /// In en, this message translates to:
  /// **'Privacy'**
  String get privacy;

  /// No description provided for @terms.
  ///
  /// In en, this message translates to:
  /// **'Terms'**
  String get terms;

  /// No description provided for @twoFactorTitle.
  ///
  /// In en, this message translates to:
  /// **'Two-step sign-in'**
  String get twoFactorTitle;

  /// No description provided for @twoFactorBody.
  ///
  /// In en, this message translates to:
  /// **'Enter the 6-digit code from your authenticator app.'**
  String get twoFactorBody;

  /// No description provided for @twoFactorCode.
  ///
  /// In en, this message translates to:
  /// **'Code'**
  String get twoFactorCode;

  /// No description provided for @verify.
  ///
  /// In en, this message translates to:
  /// **'Verify'**
  String get verify;

  /// No description provided for @useRecoveryCode.
  ///
  /// In en, this message translates to:
  /// **'Use a recovery code'**
  String get useRecoveryCode;

  /// No description provided for @useAuthenticatorCode.
  ///
  /// In en, this message translates to:
  /// **'Use the code from the app'**
  String get useAuthenticatorCode;

  /// No description provided for @recoveryCode.
  ///
  /// In en, this message translates to:
  /// **'Recovery code'**
  String get recoveryCode;

  /// No description provided for @challengeExpired.
  ///
  /// In en, this message translates to:
  /// **'The sign-in took too long. Please sign in again.'**
  String get challengeExpired;

  /// No description provided for @serverAddress.
  ///
  /// In en, this message translates to:
  /// **'Server address'**
  String get serverAddress;

  /// No description provided for @serverHint.
  ///
  /// In en, this message translates to:
  /// **'e.g. http://192.168.1.20:8000'**
  String get serverHint;

  /// No description provided for @updateRequiredTitle.
  ///
  /// In en, this message translates to:
  /// **'Update required'**
  String get updateRequiredTitle;

  /// No description provided for @updateAvailable.
  ///
  /// In en, this message translates to:
  /// **'A new version of the app is ready.'**
  String get updateAvailable;

  /// No description provided for @maintenanceTitle.
  ///
  /// In en, this message translates to:
  /// **'Down for maintenance'**
  String get maintenanceTitle;

  /// No description provided for @maintenanceBody.
  ///
  /// In en, this message translates to:
  /// **'We\'re updating the system. Please try again in a few minutes.'**
  String get maintenanceBody;

  /// No description provided for @superAdminSignIn.
  ///
  /// In en, this message translates to:
  /// **'Super Admin sign-in'**
  String get superAdminSignIn;

  /// No description provided for @appOffTitle.
  ///
  /// In en, this message translates to:
  /// **'The app is not available'**
  String get appOffTitle;

  /// No description provided for @appOffBody.
  ///
  /// In en, this message translates to:
  /// **'The app is not available for your account right now. Please use the website.'**
  String get appOffBody;

  /// No description provided for @openWebsite.
  ///
  /// In en, this message translates to:
  /// **'Open the website'**
  String get openWebsite;

  /// No description provided for @updateRequiredBody.
  ///
  /// In en, this message translates to:
  /// **'This version of the app is too old. Please update to keep using it.'**
  String get updateRequiredBody;

  /// No description provided for @updateNow.
  ///
  /// In en, this message translates to:
  /// **'Update now'**
  String get updateNow;

  /// No description provided for @emailUnverifiedTitle.
  ///
  /// In en, this message translates to:
  /// **'Confirm your e-mail'**
  String get emailUnverifiedTitle;

  /// No description provided for @emailUnverifiedBody.
  ///
  /// In en, this message translates to:
  /// **'We sent a link to {email}. Open it to start using your restaurant, then come back.'**
  String emailUnverifiedBody(Object email);

  /// No description provided for @iConfirmed.
  ///
  /// In en, this message translates to:
  /// **'I\'ve confirmed it'**
  String get iConfirmed;

  /// No description provided for @noRestaurantTitle.
  ///
  /// In en, this message translates to:
  /// **'No restaurant yet'**
  String get noRestaurantTitle;

  /// No description provided for @noRestaurantBody.
  ///
  /// In en, this message translates to:
  /// **'Your account isn\'t part of a restaurant. Ask the owner for an invitation, or sign up on the website.'**
  String get noRestaurantBody;

  /// No description provided for @signOut.
  ///
  /// In en, this message translates to:
  /// **'Sign out'**
  String get signOut;

  /// No description provided for @signOutEverywhere.
  ///
  /// In en, this message translates to:
  /// **'Sign out on all devices'**
  String get signOutEverywhere;

  /// No description provided for @navHome.
  ///
  /// In en, this message translates to:
  /// **'Home'**
  String get navHome;

  /// No description provided for @navMenu.
  ///
  /// In en, this message translates to:
  /// **'Menu'**
  String get navMenu;

  /// No description provided for @saTitle.
  ///
  /// In en, this message translates to:
  /// **'Super Admin'**
  String get saTitle;

  /// No description provided for @saRestaurants.
  ///
  /// In en, this message translates to:
  /// **'Restaurants'**
  String get saRestaurants;

  /// No description provided for @saActive.
  ///
  /// In en, this message translates to:
  /// **'Paying'**
  String get saActive;

  /// No description provided for @saTrial.
  ///
  /// In en, this message translates to:
  /// **'On trial'**
  String get saTrial;

  /// No description provided for @saSignups30.
  ///
  /// In en, this message translates to:
  /// **'New sign-ups · 30 days'**
  String get saSignups30;

  /// No description provided for @saViews30.
  ///
  /// In en, this message translates to:
  /// **'Menu views · 30 days'**
  String get saViews30;

  /// No description provided for @saEmergency.
  ///
  /// In en, this message translates to:
  /// **'Emergency switches'**
  String get saEmergency;

  /// No description provided for @saEmergencyHint.
  ///
  /// In en, this message translates to:
  /// **'Take effect on every phone within a minute.'**
  String get saEmergencyHint;

  /// No description provided for @saMaintenance.
  ///
  /// In en, this message translates to:
  /// **'App maintenance'**
  String get saMaintenance;

  /// No description provided for @saMaintenanceHint.
  ///
  /// In en, this message translates to:
  /// **'Restaurant users see a maintenance screen. Super Admins still get in.'**
  String get saMaintenanceHint;

  /// No description provided for @saMaintenanceConfirm.
  ///
  /// In en, this message translates to:
  /// **'Put the app in maintenance? Every restaurant user is locked out of the app until you turn it off.'**
  String get saMaintenanceConfirm;

  /// No description provided for @saSignup.
  ///
  /// In en, this message translates to:
  /// **'Self sign-up'**
  String get saSignup;

  /// No description provided for @saSignupHint.
  ///
  /// In en, this message translates to:
  /// **'New restaurants can create an account on the website.'**
  String get saSignupHint;

  /// No description provided for @saPush.
  ///
  /// In en, this message translates to:
  /// **'Push notifications'**
  String get saPush;

  /// No description provided for @saPushHint.
  ///
  /// In en, this message translates to:
  /// **'Phones receive notifications from the app.'**
  String get saPushHint;

  /// No description provided for @saSaSignIn.
  ///
  /// In en, this message translates to:
  /// **'Super Admin sign-in in the app'**
  String get saSaSignIn;

  /// No description provided for @saSaSignInHint.
  ///
  /// In en, this message translates to:
  /// **'Off: Super Admins use the website only.'**
  String get saSaSignInHint;

  /// No description provided for @saSaSignInConfirm.
  ///
  /// In en, this message translates to:
  /// **'Turn off Super Admin sign-in? This phone loses Super Admin access at once; turn it back on from the website.'**
  String get saSaSignInConfirm;

  /// No description provided for @saOpenWeb.
  ///
  /// In en, this message translates to:
  /// **'Open Super Admin on the web'**
  String get saOpenWeb;

  /// No description provided for @saOpenWebHint.
  ///
  /// In en, this message translates to:
  /// **'Restaurants, plans, payments, messages, backups and every other setting.'**
  String get saOpenWebHint;

  /// No description provided for @turnOff.
  ///
  /// In en, this message translates to:
  /// **'Turn off'**
  String get turnOff;

  /// No description provided for @turnOn.
  ///
  /// In en, this message translates to:
  /// **'Turn on'**
  String get turnOn;

  /// No description provided for @navOrders.
  ///
  /// In en, this message translates to:
  /// **'Orders'**
  String get navOrders;

  /// No description provided for @homeTodaySubtitle.
  ///
  /// In en, this message translates to:
  /// **'Today · {date}'**
  String homeTodaySubtitle(Object date);

  /// No description provided for @soldOutNow.
  ///
  /// In en, this message translates to:
  /// **'Sold out now'**
  String get soldOutNow;

  /// No description provided for @quickActions.
  ///
  /// In en, this message translates to:
  /// **'Quick actions'**
  String get quickActions;

  /// No description provided for @actionChangePrice.
  ///
  /// In en, this message translates to:
  /// **'Change a price'**
  String get actionChangePrice;

  /// No description provided for @actionHideDish.
  ///
  /// In en, this message translates to:
  /// **'Hide or show a dish'**
  String get actionHideDish;

  /// No description provided for @actionAddDish.
  ///
  /// In en, this message translates to:
  /// **'Add dish with photo'**
  String get actionAddDish;

  /// No description provided for @actionShareMenu.
  ///
  /// In en, this message translates to:
  /// **'Share menu link / QR'**
  String get actionShareMenu;

  /// No description provided for @actionSoldOut.
  ///
  /// In en, this message translates to:
  /// **'Mark sold out'**
  String get actionSoldOut;

  /// No description provided for @manageSection.
  ///
  /// In en, this message translates to:
  /// **'Manage'**
  String get manageSection;

  /// No description provided for @ordersOff.
  ///
  /// In en, this message translates to:
  /// **'“My order” is off. Guests can\'t build a list to show the waiter.'**
  String get ordersOff;

  /// No description provided for @ordersTurnOn.
  ///
  /// In en, this message translates to:
  /// **'Turn on in Appearance'**
  String get ordersTurnOn;

  /// No description provided for @ordersEmpty.
  ///
  /// In en, this message translates to:
  /// **'No lists shown yet. They appear here when guests use “My order”.'**
  String get ordersEmpty;

  /// No description provided for @listsShown.
  ///
  /// In en, this message translates to:
  /// **'Lists shown'**
  String get listsShown;

  /// No description provided for @averageList.
  ///
  /// In en, this message translates to:
  /// **'Average list'**
  String get averageList;

  /// No description provided for @days7.
  ///
  /// In en, this message translates to:
  /// **'7 days'**
  String get days7;

  /// No description provided for @days30.
  ///
  /// In en, this message translates to:
  /// **'30 days'**
  String get days30;

  /// No description provided for @weekViews.
  ///
  /// In en, this message translates to:
  /// **'{count} views this week'**
  String weekViews(int count);

  /// No description provided for @navSoldOut.
  ///
  /// In en, this message translates to:
  /// **'Sold out'**
  String get navSoldOut;

  /// No description provided for @navMore.
  ///
  /// In en, this message translates to:
  /// **'More'**
  String get navMore;

  /// No description provided for @viewingAs.
  ///
  /// In en, this message translates to:
  /// **'Viewing as {name}'**
  String viewingAs(Object name);

  /// No description provided for @backToPlatform.
  ///
  /// In en, this message translates to:
  /// **'Back to platform'**
  String get backToPlatform;

  /// No description provided for @switchRestaurant.
  ///
  /// In en, this message translates to:
  /// **'Switch restaurant'**
  String get switchRestaurant;

  /// No description provided for @platform.
  ///
  /// In en, this message translates to:
  /// **'Platform'**
  String get platform;

  /// No description provided for @openPlatform.
  ///
  /// In en, this message translates to:
  /// **'Open the platform'**
  String get openPlatform;

  /// No description provided for @manage.
  ///
  /// In en, this message translates to:
  /// **'Manage'**
  String get manage;

  /// No description provided for @setupTitle.
  ///
  /// In en, this message translates to:
  /// **'Get your menu ready'**
  String get setupTitle;

  /// No description provided for @setupProgress.
  ///
  /// In en, this message translates to:
  /// **'{done} of {total}'**
  String setupProgress(Object done, Object total);

  /// No description provided for @setup_logo.
  ///
  /// In en, this message translates to:
  /// **'Add your logo'**
  String get setup_logo;

  /// No description provided for @setup_banner.
  ///
  /// In en, this message translates to:
  /// **'Add a banner photo'**
  String get setup_banner;

  /// No description provided for @setup_dishes.
  ///
  /// In en, this message translates to:
  /// **'Add at least 10 dishes'**
  String get setup_dishes;

  /// No description provided for @setup_photos.
  ///
  /// In en, this message translates to:
  /// **'Add photos to your dishes'**
  String get setup_photos;

  /// No description provided for @setup_hours.
  ///
  /// In en, this message translates to:
  /// **'Set your opening hours'**
  String get setup_hours;

  /// No description provided for @setup_info.
  ///
  /// In en, this message translates to:
  /// **'Add your address or phone'**
  String get setup_info;

  /// No description provided for @setup_qr.
  ///
  /// In en, this message translates to:
  /// **'Download or print your QR codes'**
  String get setup_qr;

  /// No description provided for @viewsToday.
  ///
  /// In en, this message translates to:
  /// **'Views today'**
  String get viewsToday;

  /// No description provided for @scansToday.
  ///
  /// In en, this message translates to:
  /// **'Scans today'**
  String get scansToday;

  /// No description provided for @vsYesterday.
  ///
  /// In en, this message translates to:
  /// **'Yesterday {count}'**
  String vsYesterday(Object count);

  /// No description provided for @viewsChart.
  ///
  /// In en, this message translates to:
  /// **'Menu views'**
  String get viewsChart;

  /// No description provided for @missingPhotosTitle.
  ///
  /// In en, this message translates to:
  /// **'Dishes guests look at, without a photo'**
  String get missingPhotosTitle;

  /// No description provided for @missingPhotosMore.
  ///
  /// In en, this message translates to:
  /// **'{count} dishes have no photo'**
  String missingPhotosMore(Object count);

  /// No description provided for @myOrderTitle.
  ///
  /// In en, this message translates to:
  /// **'“My order” lists'**
  String get myOrderTitle;

  /// No description provided for @myOrderTop.
  ///
  /// In en, this message translates to:
  /// **'Most added'**
  String get myOrderTop;

  /// No description provided for @planTrial.
  ///
  /// In en, this message translates to:
  /// **'Trial'**
  String get planTrial;

  /// No description provided for @planEndsIn.
  ///
  /// In en, this message translates to:
  /// **'Ends in {days} days'**
  String planEndsIn(Object days);

  /// No description provided for @planEnded.
  ///
  /// In en, this message translates to:
  /// **'Ended'**
  String get planEnded;

  /// No description provided for @unlimited.
  ///
  /// In en, this message translates to:
  /// **'Unlimited'**
  String get unlimited;

  /// No description provided for @usage_items.
  ///
  /// In en, this message translates to:
  /// **'Dishes'**
  String get usage_items;

  /// No description provided for @usage_photos.
  ///
  /// In en, this message translates to:
  /// **'Photos'**
  String get usage_photos;

  /// No description provided for @usage_branches.
  ///
  /// In en, this message translates to:
  /// **'Branches'**
  String get usage_branches;

  /// No description provided for @upgradeTo.
  ///
  /// In en, this message translates to:
  /// **'Upgrade to {plan}'**
  String upgradeTo(Object plan);

  /// No description provided for @newsTitle.
  ///
  /// In en, this message translates to:
  /// **'News from the platform'**
  String get newsTitle;

  /// No description provided for @openMyMenu.
  ///
  /// In en, this message translates to:
  /// **'Open my menu'**
  String get openMyMenu;

  /// No description provided for @menuLink.
  ///
  /// In en, this message translates to:
  /// **'Your menu link'**
  String get menuLink;

  /// No description provided for @copyLink.
  ///
  /// In en, this message translates to:
  /// **'Copy link'**
  String get copyLink;

  /// No description provided for @showQr.
  ///
  /// In en, this message translates to:
  /// **'QR code'**
  String get showQr;

  /// No description provided for @newMenu.
  ///
  /// In en, this message translates to:
  /// **'New menu'**
  String get newMenu;

  /// No description provided for @menuName.
  ///
  /// In en, this message translates to:
  /// **'Menu name'**
  String get menuName;

  /// No description provided for @renameMenu.
  ///
  /// In en, this message translates to:
  /// **'Rename menu'**
  String get renameMenu;

  /// No description provided for @duplicate.
  ///
  /// In en, this message translates to:
  /// **'Duplicate'**
  String get duplicate;

  /// No description provided for @hiddenFromGuests.
  ///
  /// In en, this message translates to:
  /// **'Hidden from guests'**
  String get hiddenFromGuests;

  /// No description provided for @showToGuests.
  ///
  /// In en, this message translates to:
  /// **'Show to guests'**
  String get showToGuests;

  /// No description provided for @hide.
  ///
  /// In en, this message translates to:
  /// **'Hide'**
  String get hide;

  /// No description provided for @show.
  ///
  /// In en, this message translates to:
  /// **'Show'**
  String get show;

  /// No description provided for @schedule.
  ///
  /// In en, this message translates to:
  /// **'Schedule'**
  String get schedule;

  /// No description provided for @scheduleAlways.
  ///
  /// In en, this message translates to:
  /// **'All day, every day'**
  String get scheduleAlways;

  /// No description provided for @scheduleTimes.
  ///
  /// In en, this message translates to:
  /// **'At set times'**
  String get scheduleTimes;

  /// No description provided for @from.
  ///
  /// In en, this message translates to:
  /// **'From'**
  String get from;

  /// No description provided for @to.
  ///
  /// In en, this message translates to:
  /// **'To'**
  String get to;

  /// No description provided for @addTime.
  ///
  /// In en, this message translates to:
  /// **'Add a time'**
  String get addTime;

  /// No description provided for @branchesShown.
  ///
  /// In en, this message translates to:
  /// **'Branches'**
  String get branchesShown;

  /// No description provided for @allBranches.
  ///
  /// In en, this message translates to:
  /// **'All branches'**
  String get allBranches;

  /// No description provided for @categories.
  ///
  /// In en, this message translates to:
  /// **'Categories'**
  String get categories;

  /// No description provided for @newCategory.
  ///
  /// In en, this message translates to:
  /// **'New category'**
  String get newCategory;

  /// No description provided for @categoryName.
  ///
  /// In en, this message translates to:
  /// **'Category name'**
  String get categoryName;

  /// No description provided for @renameCategory.
  ///
  /// In en, this message translates to:
  /// **'Rename category'**
  String get renameCategory;

  /// No description provided for @noCategories.
  ///
  /// In en, this message translates to:
  /// **'No categories yet'**
  String get noCategories;

  /// No description provided for @noCategoriesBody.
  ///
  /// In en, this message translates to:
  /// **'Add a category such as “Coffee” or “Breakfast”, then add dishes to it.'**
  String get noCategoriesBody;

  /// No description provided for @noDishes.
  ///
  /// In en, this message translates to:
  /// **'No dishes in this category'**
  String get noDishes;

  /// No description provided for @dish.
  ///
  /// In en, this message translates to:
  /// **'Dish'**
  String get dish;

  /// No description provided for @dishes.
  ///
  /// In en, this message translates to:
  /// **'Dishes'**
  String get dishes;

  /// No description provided for @reorder.
  ///
  /// In en, this message translates to:
  /// **'Reorder'**
  String get reorder;

  /// No description provided for @reorderDone.
  ///
  /// In en, this message translates to:
  /// **'Done reordering'**
  String get reorderDone;

  /// No description provided for @dragToReorder.
  ///
  /// In en, this message translates to:
  /// **'Drag to reorder'**
  String get dragToReorder;

  /// No description provided for @available.
  ///
  /// In en, this message translates to:
  /// **'Available'**
  String get available;

  /// No description provided for @soldOut.
  ///
  /// In en, this message translates to:
  /// **'Sold out'**
  String get soldOut;

  /// No description provided for @availableAgain.
  ///
  /// In en, this message translates to:
  /// **'Available again'**
  String get availableAgain;

  /// No description provided for @markSoldOut.
  ///
  /// In en, this message translates to:
  /// **'Sold out'**
  String get markSoldOut;

  /// No description provided for @priceFrom.
  ///
  /// In en, this message translates to:
  /// **'from {price}'**
  String priceFrom(Object price);

  /// No description provided for @allDishes.
  ///
  /// In en, this message translates to:
  /// **'All dishes'**
  String get allDishes;

  /// No description provided for @noMenus.
  ///
  /// In en, this message translates to:
  /// **'No menus yet'**
  String get noMenus;

  /// No description provided for @noMenusBody.
  ///
  /// In en, this message translates to:
  /// **'Create your first menu, then add categories and dishes.'**
  String get noMenusBody;

  /// No description provided for @takePhoto.
  ///
  /// In en, this message translates to:
  /// **'Take a photo'**
  String get takePhoto;

  /// No description provided for @choosePhoto.
  ///
  /// In en, this message translates to:
  /// **'Choose from gallery'**
  String get choosePhoto;

  /// No description provided for @cropPhoto.
  ///
  /// In en, this message translates to:
  /// **'Crop photo'**
  String get cropPhoto;

  /// No description provided for @photoAccessDenied.
  ///
  /// In en, this message translates to:
  /// **'The app may not use the camera or photos. Allow it in your phone\'s Settings › Apps › Menu Admin › Permissions.'**
  String get photoAccessDenied;

  /// No description provided for @photoUnreadable.
  ///
  /// In en, this message translates to:
  /// **'This photo can\'t be opened. Try another one.'**
  String get photoUnreadable;

  /// No description provided for @photos.
  ///
  /// In en, this message translates to:
  /// **'Photos'**
  String get photos;

  /// No description provided for @photosCount.
  ///
  /// In en, this message translates to:
  /// **'{count} of {max} photos'**
  String photosCount(Object count, Object max);

  /// No description provided for @mainPhoto.
  ///
  /// In en, this message translates to:
  /// **'Main'**
  String get mainPhoto;

  /// No description provided for @makeMain.
  ///
  /// In en, this message translates to:
  /// **'Make main photo'**
  String get makeMain;

  /// No description provided for @removePhoto.
  ///
  /// In en, this message translates to:
  /// **'Remove photo'**
  String get removePhoto;

  /// No description provided for @uploading.
  ///
  /// In en, this message translates to:
  /// **'Uploading…'**
  String get uploading;

  /// No description provided for @addPhoto.
  ///
  /// In en, this message translates to:
  /// **'Add photo'**
  String get addPhoto;

  /// No description provided for @newDish.
  ///
  /// In en, this message translates to:
  /// **'New dish'**
  String get newDish;

  /// No description provided for @editDish.
  ///
  /// In en, this message translates to:
  /// **'Edit dish'**
  String get editDish;

  /// No description provided for @name.
  ///
  /// In en, this message translates to:
  /// **'Name'**
  String get name;

  /// No description provided for @shortDescription.
  ///
  /// In en, this message translates to:
  /// **'Short description'**
  String get shortDescription;

  /// No description provided for @description.
  ///
  /// In en, this message translates to:
  /// **'Description'**
  String get description;

  /// No description provided for @price.
  ///
  /// In en, this message translates to:
  /// **'Price'**
  String get price;

  /// No description provided for @sizes.
  ///
  /// In en, this message translates to:
  /// **'Sizes'**
  String get sizes;

  /// No description provided for @addSize.
  ///
  /// In en, this message translates to:
  /// **'Add size'**
  String get addSize;

  /// No description provided for @sizeName.
  ///
  /// In en, this message translates to:
  /// **'Size'**
  String get sizeName;

  /// No description provided for @usesSizes.
  ///
  /// In en, this message translates to:
  /// **'Different sizes or prices'**
  String get usesSizes;

  /// No description provided for @category.
  ///
  /// In en, this message translates to:
  /// **'Category'**
  String get category;

  /// No description provided for @addons.
  ///
  /// In en, this message translates to:
  /// **'Add-ons'**
  String get addons;

  /// No description provided for @tags.
  ///
  /// In en, this message translates to:
  /// **'Tags'**
  String get tags;

  /// No description provided for @allergens.
  ///
  /// In en, this message translates to:
  /// **'Allergens'**
  String get allergens;

  /// No description provided for @calories.
  ///
  /// In en, this message translates to:
  /// **'Calories (kcal)'**
  String get calories;

  /// No description provided for @prepMinutes.
  ///
  /// In en, this message translates to:
  /// **'Preparation (minutes)'**
  String get prepMinutes;

  /// No description provided for @showPhoto.
  ///
  /// In en, this message translates to:
  /// **'Show photo on menu'**
  String get showPhoto;

  /// No description provided for @showPhotoHint.
  ///
  /// In en, this message translates to:
  /// **'Off: the menu shows this dish without a photo.'**
  String get showPhotoHint;

  /// No description provided for @visible.
  ///
  /// In en, this message translates to:
  /// **'Visible to guests'**
  String get visible;

  /// No description provided for @markNew.
  ///
  /// In en, this message translates to:
  /// **'Mark as new'**
  String get markNew;

  /// No description provided for @markPopular.
  ///
  /// In en, this message translates to:
  /// **'Mark as popular'**
  String get markPopular;

  /// No description provided for @dishSaved.
  ///
  /// In en, this message translates to:
  /// **'Dish saved'**
  String get dishSaved;

  /// No description provided for @deleteDish.
  ///
  /// In en, this message translates to:
  /// **'Delete dish'**
  String get deleteDish;

  /// No description provided for @unsavedTitle.
  ///
  /// In en, this message translates to:
  /// **'Discard changes?'**
  String get unsavedTitle;

  /// No description provided for @unsavedBody.
  ///
  /// In en, this message translates to:
  /// **'Your changes to this dish are not saved.'**
  String get unsavedBody;

  /// No description provided for @discard.
  ///
  /// In en, this message translates to:
  /// **'Discard'**
  String get discard;

  /// No description provided for @pricesLocked.
  ///
  /// In en, this message translates to:
  /// **'You can\'t change prices. Ask the owner.'**
  String get pricesLocked;

  /// No description provided for @allergen_gluten.
  ///
  /// In en, this message translates to:
  /// **'Gluten'**
  String get allergen_gluten;

  /// No description provided for @allergen_crustaceans.
  ///
  /// In en, this message translates to:
  /// **'Crustaceans'**
  String get allergen_crustaceans;

  /// No description provided for @allergen_eggs.
  ///
  /// In en, this message translates to:
  /// **'Eggs'**
  String get allergen_eggs;

  /// No description provided for @allergen_fish.
  ///
  /// In en, this message translates to:
  /// **'Fish'**
  String get allergen_fish;

  /// No description provided for @allergen_peanuts.
  ///
  /// In en, this message translates to:
  /// **'Peanuts'**
  String get allergen_peanuts;

  /// No description provided for @allergen_soy.
  ///
  /// In en, this message translates to:
  /// **'Soy'**
  String get allergen_soy;

  /// No description provided for @allergen_milk.
  ///
  /// In en, this message translates to:
  /// **'Milk'**
  String get allergen_milk;

  /// No description provided for @allergen_nuts.
  ///
  /// In en, this message translates to:
  /// **'Nuts'**
  String get allergen_nuts;

  /// No description provided for @allergen_celery.
  ///
  /// In en, this message translates to:
  /// **'Celery'**
  String get allergen_celery;

  /// No description provided for @allergen_mustard.
  ///
  /// In en, this message translates to:
  /// **'Mustard'**
  String get allergen_mustard;

  /// No description provided for @allergen_sesame.
  ///
  /// In en, this message translates to:
  /// **'Sesame'**
  String get allergen_sesame;

  /// No description provided for @allergen_sulphites.
  ///
  /// In en, this message translates to:
  /// **'Sulphites'**
  String get allergen_sulphites;

  /// No description provided for @allergen_lupin.
  ///
  /// In en, this message translates to:
  /// **'Lupin'**
  String get allergen_lupin;

  /// No description provided for @allergen_molluscs.
  ///
  /// In en, this message translates to:
  /// **'Molluscs'**
  String get allergen_molluscs;

  /// No description provided for @tag_vegan.
  ///
  /// In en, this message translates to:
  /// **'Vegan'**
  String get tag_vegan;

  /// No description provided for @tag_vegetarian.
  ///
  /// In en, this message translates to:
  /// **'Vegetarian'**
  String get tag_vegetarian;

  /// No description provided for @tag_spicy.
  ///
  /// In en, this message translates to:
  /// **'Spicy'**
  String get tag_spicy;

  /// No description provided for @tag_halal.
  ///
  /// In en, this message translates to:
  /// **'Halal'**
  String get tag_halal;

  /// No description provided for @tag_gluten_free.
  ///
  /// In en, this message translates to:
  /// **'Gluten-free'**
  String get tag_gluten_free;

  /// No description provided for @tag_new.
  ///
  /// In en, this message translates to:
  /// **'New'**
  String get tag_new;

  /// No description provided for @tag_popular.
  ///
  /// In en, this message translates to:
  /// **'Popular'**
  String get tag_popular;

  /// No description provided for @soldOutTab.
  ///
  /// In en, this message translates to:
  /// **'Sold out'**
  String get soldOutTab;

  /// No description provided for @allDishesTab.
  ///
  /// In en, this message translates to:
  /// **'All dishes'**
  String get allDishesTab;

  /// No description provided for @soldOutEmpty.
  ///
  /// In en, this message translates to:
  /// **'Nothing is sold out'**
  String get soldOutEmpty;

  /// No description provided for @soldOutEmptyBody.
  ///
  /// In en, this message translates to:
  /// **'Tap “Sold out” on a dish when it runs out. Guests see it within seconds.'**
  String get soldOutEmptyBody;

  /// No description provided for @soldOutOffTitle.
  ///
  /// In en, this message translates to:
  /// **'“Sold out” is turned off'**
  String get soldOutOffTitle;

  /// No description provided for @soldOutOffBody.
  ///
  /// In en, this message translates to:
  /// **'Your menu shows every dish as available. Existing marks are kept and come back if it is turned on again.'**
  String get soldOutOffBody;

  /// No description provided for @soldOutBranchNote.
  ///
  /// In en, this message translates to:
  /// **'Sold out for every branch. For one branch only, use Branches › Prices and availability.'**
  String get soldOutBranchNote;

  /// No description provided for @filterAll.
  ///
  /// In en, this message translates to:
  /// **'All'**
  String get filterAll;

  /// No description provided for @filterSoldOut.
  ///
  /// In en, this message translates to:
  /// **'Sold out'**
  String get filterSoldOut;

  /// No description provided for @filterHidden.
  ///
  /// In en, this message translates to:
  /// **'Hidden'**
  String get filterHidden;

  /// No description provided for @filterNoPhoto.
  ///
  /// In en, this message translates to:
  /// **'No photo'**
  String get filterNoPhoto;

  /// No description provided for @filterNew.
  ///
  /// In en, this message translates to:
  /// **'New'**
  String get filterNew;

  /// No description provided for @filterPopular.
  ///
  /// In en, this message translates to:
  /// **'Popular'**
  String get filterPopular;

  /// No description provided for @selectedCount.
  ///
  /// In en, this message translates to:
  /// **'{count} selected'**
  String selectedCount(Object count);

  /// No description provided for @bulkSoldOut.
  ///
  /// In en, this message translates to:
  /// **'Mark sold out'**
  String get bulkSoldOut;

  /// No description provided for @bulkAvailable.
  ///
  /// In en, this message translates to:
  /// **'Mark available'**
  String get bulkAvailable;

  /// No description provided for @bulkHide.
  ///
  /// In en, this message translates to:
  /// **'Hide'**
  String get bulkHide;

  /// No description provided for @bulkShow.
  ///
  /// In en, this message translates to:
  /// **'Show'**
  String get bulkShow;

  /// No description provided for @bulkMove.
  ///
  /// In en, this message translates to:
  /// **'Move to…'**
  String get bulkMove;

  /// No description provided for @bulkPrice.
  ///
  /// In en, this message translates to:
  /// **'Change prices'**
  String get bulkPrice;

  /// No description provided for @bulkDelete.
  ///
  /// In en, this message translates to:
  /// **'Delete'**
  String get bulkDelete;

  /// No description provided for @bulkDeleteTitle.
  ///
  /// In en, this message translates to:
  /// **'Delete {count} dishes?'**
  String bulkDeleteTitle(Object count);

  /// No description provided for @bulkPriceTitle.
  ///
  /// In en, this message translates to:
  /// **'Change prices'**
  String get bulkPriceTitle;

  /// No description provided for @bulkPricePercent.
  ///
  /// In en, this message translates to:
  /// **'Change by (%)'**
  String get bulkPricePercent;

  /// No description provided for @bulkPriceHint.
  ///
  /// In en, this message translates to:
  /// **'e.g. 10 for +10 %, -5 for −5 %'**
  String get bulkPriceHint;

  /// No description provided for @bulkPriceRound.
  ///
  /// In en, this message translates to:
  /// **'Round to'**
  String get bulkPriceRound;

  /// No description provided for @noRounding.
  ///
  /// In en, this message translates to:
  /// **'No rounding'**
  String get noRounding;

  /// No description provided for @exportCsv.
  ///
  /// In en, this message translates to:
  /// **'Export CSV'**
  String get exportCsv;

  /// No description provided for @sortBy.
  ///
  /// In en, this message translates to:
  /// **'Sort'**
  String get sortBy;

  /// No description provided for @sortCategory.
  ///
  /// In en, this message translates to:
  /// **'Menu order'**
  String get sortCategory;

  /// No description provided for @sortName.
  ///
  /// In en, this message translates to:
  /// **'Name'**
  String get sortName;

  /// No description provided for @sortPrice.
  ///
  /// In en, this message translates to:
  /// **'Price'**
  String get sortPrice;

  /// No description provided for @sortUpdated.
  ///
  /// In en, this message translates to:
  /// **'Last changed'**
  String get sortUpdated;

  /// No description provided for @noMatches.
  ///
  /// In en, this message translates to:
  /// **'Nothing matches'**
  String get noMatches;

  /// No description provided for @branches.
  ///
  /// In en, this message translates to:
  /// **'Branches'**
  String get branches;

  /// No description provided for @newBranch.
  ///
  /// In en, this message translates to:
  /// **'New branch'**
  String get newBranch;

  /// No description provided for @branchName.
  ///
  /// In en, this message translates to:
  /// **'Branch name'**
  String get branchName;

  /// No description provided for @address.
  ///
  /// In en, this message translates to:
  /// **'Address'**
  String get address;

  /// No description provided for @phone.
  ///
  /// In en, this message translates to:
  /// **'Phone'**
  String get phone;

  /// No description provided for @mapsLink.
  ///
  /// In en, this message translates to:
  /// **'Map link (https)'**
  String get mapsLink;

  /// No description provided for @mainBranch.
  ///
  /// In en, this message translates to:
  /// **'Main branch'**
  String get mainBranch;

  /// No description provided for @active.
  ///
  /// In en, this message translates to:
  /// **'Active'**
  String get active;

  /// No description provided for @inactive.
  ///
  /// In en, this message translates to:
  /// **'Inactive'**
  String get inactive;

  /// No description provided for @tables.
  ///
  /// In en, this message translates to:
  /// **'Tables'**
  String get tables;

  /// No description provided for @tablesCount.
  ///
  /// In en, this message translates to:
  /// **'{count} tables'**
  String tablesCount(Object count);

  /// No description provided for @addTables.
  ///
  /// In en, this message translates to:
  /// **'Add tables'**
  String get addTables;

  /// No description provided for @tablesFrom.
  ///
  /// In en, this message translates to:
  /// **'From number'**
  String get tablesFrom;

  /// No description provided for @tablesTo.
  ///
  /// In en, this message translates to:
  /// **'To number'**
  String get tablesTo;

  /// No description provided for @tablesPrefix.
  ///
  /// In en, this message translates to:
  /// **'Prefix (optional)'**
  String get tablesPrefix;

  /// No description provided for @tableCardsPdf.
  ///
  /// In en, this message translates to:
  /// **'Print table cards (PDF)'**
  String get tableCardsPdf;

  /// No description provided for @pricesAvailability.
  ///
  /// In en, this message translates to:
  /// **'Prices and availability'**
  String get pricesAvailability;

  /// No description provided for @branchPrice.
  ///
  /// In en, this message translates to:
  /// **'Price here'**
  String get branchPrice;

  /// No description provided for @samePrice.
  ///
  /// In en, this message translates to:
  /// **'Same as menu'**
  String get samePrice;

  /// No description provided for @hiddenHere.
  ///
  /// In en, this message translates to:
  /// **'Hidden here'**
  String get hiddenHere;

  /// No description provided for @soldOutHere.
  ///
  /// In en, this message translates to:
  /// **'Sold out here'**
  String get soldOutHere;

  /// No description provided for @openBranchPage.
  ///
  /// In en, this message translates to:
  /// **'Open this branch\'s menu'**
  String get openBranchPage;

  /// No description provided for @qrCodes.
  ///
  /// In en, this message translates to:
  /// **'QR codes'**
  String get qrCodes;

  /// No description provided for @printMaterials.
  ///
  /// In en, this message translates to:
  /// **'Print materials'**
  String get printMaterials;

  /// No description provided for @qrRestaurant.
  ///
  /// In en, this message translates to:
  /// **'Whole restaurant'**
  String get qrRestaurant;

  /// No description provided for @qrBranch.
  ///
  /// In en, this message translates to:
  /// **'Branch'**
  String get qrBranch;

  /// No description provided for @qrTable.
  ///
  /// In en, this message translates to:
  /// **'Table'**
  String get qrTable;

  /// No description provided for @qrExtra.
  ///
  /// In en, this message translates to:
  /// **'Extra code'**
  String get qrExtra;

  /// No description provided for @newQr.
  ///
  /// In en, this message translates to:
  /// **'New QR code'**
  String get newQr;

  /// No description provided for @qrLabel.
  ///
  /// In en, this message translates to:
  /// **'Name (e.g. “Window”)'**
  String get qrLabel;

  /// No description provided for @downloadPng.
  ///
  /// In en, this message translates to:
  /// **'Share PNG'**
  String get downloadPng;

  /// No description provided for @downloadSvg.
  ///
  /// In en, this message translates to:
  /// **'Share SVG'**
  String get downloadSvg;

  /// No description provided for @downloadPdf.
  ///
  /// In en, this message translates to:
  /// **'Open PDF'**
  String get downloadPdf;

  /// No description provided for @printPdf.
  ///
  /// In en, this message translates to:
  /// **'Make PDF'**
  String get printPdf;

  /// No description provided for @material.
  ///
  /// In en, this message translates to:
  /// **'Material'**
  String get material;

  /// No description provided for @darkPrint.
  ///
  /// In en, this message translates to:
  /// **'Dark'**
  String get darkPrint;

  /// No description provided for @headline.
  ///
  /// In en, this message translates to:
  /// **'Headline'**
  String get headline;

  /// No description provided for @bleed.
  ///
  /// In en, this message translates to:
  /// **'Add 3 mm bleed for a print shop'**
  String get bleed;

  /// No description provided for @chooseCodes.
  ///
  /// In en, this message translates to:
  /// **'QR codes'**
  String get chooseCodes;

  /// No description provided for @material_tent.
  ///
  /// In en, this message translates to:
  /// **'Table tent'**
  String get material_tent;

  /// No description provided for @material_poster_a5.
  ///
  /// In en, this message translates to:
  /// **'Poster A5'**
  String get material_poster_a5;

  /// No description provided for @material_poster_a4.
  ///
  /// In en, this message translates to:
  /// **'Poster A4'**
  String get material_poster_a4;

  /// No description provided for @material_window.
  ///
  /// In en, this message translates to:
  /// **'Window sign'**
  String get material_window;

  /// No description provided for @material_sticker_round.
  ///
  /// In en, this message translates to:
  /// **'Round stickers'**
  String get material_sticker_round;

  /// No description provided for @material_sticker_square.
  ///
  /// In en, this message translates to:
  /// **'Square stickers'**
  String get material_sticker_square;

  /// No description provided for @design_classic.
  ///
  /// In en, this message translates to:
  /// **'Classic'**
  String get design_classic;

  /// No description provided for @design_bold.
  ///
  /// In en, this message translates to:
  /// **'Bold'**
  String get design_bold;

  /// No description provided for @design_minimal.
  ///
  /// In en, this message translates to:
  /// **'Minimal'**
  String get design_minimal;

  /// No description provided for @socialImages.
  ///
  /// In en, this message translates to:
  /// **'Social media pictures'**
  String get socialImages;

  /// No description provided for @socialPost.
  ///
  /// In en, this message translates to:
  /// **'Post 1080 × 1080'**
  String get socialPost;

  /// No description provided for @socialStory.
  ///
  /// In en, this message translates to:
  /// **'Story 1080 × 1920'**
  String get socialStory;

  /// No description provided for @menuPdf.
  ///
  /// In en, this message translates to:
  /// **'Printable menu (PDF)'**
  String get menuPdf;

  /// No description provided for @appearance.
  ///
  /// In en, this message translates to:
  /// **'Appearance'**
  String get appearance;

  /// No description provided for @menuStyle.
  ///
  /// In en, this message translates to:
  /// **'Menu style'**
  String get menuStyle;

  /// No description provided for @style_editorial.
  ///
  /// In en, this message translates to:
  /// **'A · Editorial'**
  String get style_editorial;

  /// No description provided for @style_grid.
  ///
  /// In en, this message translates to:
  /// **'B · Grid'**
  String get style_grid;

  /// No description provided for @style_bistro.
  ///
  /// In en, this message translates to:
  /// **'C · Bistro'**
  String get style_bistro;

  /// No description provided for @style_minimal.
  ///
  /// In en, this message translates to:
  /// **'D · Minimal'**
  String get style_minimal;

  /// No description provided for @style_mesob.
  ///
  /// In en, this message translates to:
  /// **'E · Mesob'**
  String get style_mesob;

  /// No description provided for @style_lounge.
  ///
  /// In en, this message translates to:
  /// **'F · Night Lounge'**
  String get style_lounge;

  /// No description provided for @style_board.
  ///
  /// In en, this message translates to:
  /// **'G · Street Board'**
  String get style_board;

  /// No description provided for @style_arabic.
  ///
  /// In en, this message translates to:
  /// **'H · Arabic'**
  String get style_arabic;

  /// No description provided for @style_islamic.
  ///
  /// In en, this message translates to:
  /// **'I · Islamic'**
  String get style_islamic;

  /// No description provided for @style_somali.
  ///
  /// In en, this message translates to:
  /// **'J · Somali'**
  String get style_somali;

  /// No description provided for @style_mono.
  ///
  /// In en, this message translates to:
  /// **'K · Black & White'**
  String get style_mono;

  /// No description provided for @style_colour.
  ///
  /// In en, this message translates to:
  /// **'L · Colourful'**
  String get style_colour;

  /// No description provided for @style_coffee.
  ///
  /// In en, this message translates to:
  /// **'M · Coffee House'**
  String get style_coffee;

  /// No description provided for @style_grill.
  ///
  /// In en, this message translates to:
  /// **'N · Grill & BBQ'**
  String get style_grill;

  /// No description provided for @style_garden.
  ///
  /// In en, this message translates to:
  /// **'O · Garden Fresh'**
  String get style_garden;

  /// No description provided for @style_coastal.
  ///
  /// In en, this message translates to:
  /// **'P · Coastal'**
  String get style_coastal;

  /// No description provided for @style_luxe.
  ///
  /// In en, this message translates to:
  /// **'Q · Fine Dining'**
  String get style_luxe;

  /// No description provided for @styleOptions.
  ///
  /// In en, this message translates to:
  /// **'Options for this style'**
  String get styleOptions;

  /// No description provided for @menuDirection.
  ///
  /// In en, this message translates to:
  /// **'Menu direction'**
  String get menuDirection;

  /// No description provided for @menuDirectionHint.
  ///
  /// In en, this message translates to:
  /// **'Right-to-left puts the text and buttons on the right side, for Arabic menus.'**
  String get menuDirectionHint;

  /// No description provided for @direction_auto.
  ///
  /// In en, this message translates to:
  /// **'Automatic (follows your menu\'s language)'**
  String get direction_auto;

  /// No description provided for @direction_ltr.
  ///
  /// In en, this message translates to:
  /// **'Left to right'**
  String get direction_ltr;

  /// No description provided for @direction_rtl.
  ///
  /// In en, this message translates to:
  /// **'Right to left'**
  String get direction_rtl;

  /// No description provided for @opt_show_halal.
  ///
  /// In en, this message translates to:
  /// **'Show a \"Halal\" badge'**
  String get opt_show_halal;

  /// No description provided for @opt_show_halal_hint.
  ///
  /// In en, this message translates to:
  /// **'A small badge under your restaurant\'s name. Turn it on only if all your food is halal.'**
  String get opt_show_halal_hint;

  /// No description provided for @opt_ramadan_mode.
  ///
  /// In en, this message translates to:
  /// **'Ramadan mode'**
  String get opt_ramadan_mode;

  /// No description provided for @opt_ramadan_mode_hint.
  ///
  /// In en, this message translates to:
  /// **'A night-sky top with a crescent. Turn it off again after Ramadan.'**
  String get opt_ramadan_mode_hint;

  /// No description provided for @opt_photos_greyscale.
  ///
  /// In en, this message translates to:
  /// **'Black-and-white photos'**
  String get opt_photos_greyscale;

  /// No description provided for @opt_photos_greyscale_hint.
  ///
  /// In en, this message translates to:
  /// **'Shows your dish photos and banner in black and white.'**
  String get opt_photos_greyscale_hint;

  /// No description provided for @opt_mono_accent.
  ///
  /// In en, this message translates to:
  /// **'Use your brand colour as a small accent'**
  String get opt_mono_accent;

  /// No description provided for @opt_mono_accent_hint.
  ///
  /// In en, this message translates to:
  /// **'Your colour on the \"+\" buttons, badges and the chosen category. Prices keep the text colour.'**
  String get opt_mono_accent_hint;

  /// No description provided for @opt_show_decoration.
  ///
  /// In en, this message translates to:
  /// **'Show the style\'s pictures and icons'**
  String get opt_show_decoration;

  /// No description provided for @opt_show_decoration_hint.
  ///
  /// In en, this message translates to:
  /// **'The header picture, textures and category icons. Your banner replaces the header picture; a category photo replaces its icon.'**
  String get opt_show_decoration_hint;

  /// No description provided for @brandColour.
  ///
  /// In en, this message translates to:
  /// **'Brand colour'**
  String get brandColour;

  /// No description provided for @font.
  ///
  /// In en, this message translates to:
  /// **'Font'**
  String get font;

  /// No description provided for @font_system.
  ///
  /// In en, this message translates to:
  /// **'Clean (system)'**
  String get font_system;

  /// No description provided for @font_serif.
  ///
  /// In en, this message translates to:
  /// **'Classic (serif)'**
  String get font_serif;

  /// No description provided for @font_rounded.
  ///
  /// In en, this message translates to:
  /// **'Friendly (rounded)'**
  String get font_rounded;

  /// No description provided for @logo.
  ///
  /// In en, this message translates to:
  /// **'Logo'**
  String get logo;

  /// No description provided for @banner.
  ///
  /// In en, this message translates to:
  /// **'Banner'**
  String get banner;

  /// No description provided for @bannerText.
  ///
  /// In en, this message translates to:
  /// **'Name on the banner'**
  String get bannerText;

  /// No description provided for @bannerOver.
  ///
  /// In en, this message translates to:
  /// **'Over the photo'**
  String get bannerOver;

  /// No description provided for @bannerUnder.
  ///
  /// In en, this message translates to:
  /// **'Under the photo'**
  String get bannerUnder;

  /// No description provided for @bannerNone.
  ///
  /// In en, this message translates to:
  /// **'Logo and name only'**
  String get bannerNone;

  /// No description provided for @change.
  ///
  /// In en, this message translates to:
  /// **'Change'**
  String get change;

  /// No description provided for @remove.
  ///
  /// In en, this message translates to:
  /// **'Remove'**
  String get remove;

  /// No description provided for @showOnMenu.
  ///
  /// In en, this message translates to:
  /// **'Show on the menu'**
  String get showOnMenu;

  /// No description provided for @show_prices.
  ///
  /// In en, this message translates to:
  /// **'Prices'**
  String get show_prices;

  /// No description provided for @show_photos.
  ///
  /// In en, this message translates to:
  /// **'Photos'**
  String get show_photos;

  /// No description provided for @show_descriptions.
  ///
  /// In en, this message translates to:
  /// **'Descriptions'**
  String get show_descriptions;

  /// No description provided for @show_calories.
  ///
  /// In en, this message translates to:
  /// **'Calories'**
  String get show_calories;

  /// No description provided for @show_logo.
  ///
  /// In en, this message translates to:
  /// **'Logo'**
  String get show_logo;

  /// No description provided for @show_banner.
  ///
  /// In en, this message translates to:
  /// **'Banner'**
  String get show_banner;

  /// No description provided for @show_share.
  ///
  /// In en, this message translates to:
  /// **'Share button'**
  String get show_share;

  /// No description provided for @show_pdf_link.
  ///
  /// In en, this message translates to:
  /// **'“Download menu (PDF)” link'**
  String get show_pdf_link;

  /// No description provided for @show_order_list.
  ///
  /// In en, this message translates to:
  /// **'“My order” list'**
  String get show_order_list;

  /// No description provided for @show_other_branches.
  ///
  /// In en, this message translates to:
  /// **'Other branches in the footer'**
  String get show_other_branches;

  /// No description provided for @livePreview.
  ///
  /// In en, this message translates to:
  /// **'Live preview'**
  String get livePreview;

  /// No description provided for @contrastWarning.
  ///
  /// In en, this message translates to:
  /// **'This colour is light; we darken it a little on the menu so text stays readable.'**
  String get contrastWarning;

  /// No description provided for @unsavedChanges.
  ///
  /// In en, this message translates to:
  /// **'Unsaved changes'**
  String get unsavedChanges;

  /// No description provided for @infoHours.
  ///
  /// In en, this message translates to:
  /// **'Info and hours'**
  String get infoHours;

  /// No description provided for @about.
  ///
  /// In en, this message translates to:
  /// **'About'**
  String get about;

  /// No description provided for @contact.
  ///
  /// In en, this message translates to:
  /// **'Contact'**
  String get contact;

  /// No description provided for @whatsapp.
  ///
  /// In en, this message translates to:
  /// **'WhatsApp'**
  String get whatsapp;

  /// No description provided for @telegram.
  ///
  /// In en, this message translates to:
  /// **'Telegram'**
  String get telegram;

  /// No description provided for @socials.
  ///
  /// In en, this message translates to:
  /// **'Social links'**
  String get socials;

  /// No description provided for @wifi.
  ///
  /// In en, this message translates to:
  /// **'Wi-Fi'**
  String get wifi;

  /// No description provided for @wifiName.
  ///
  /// In en, this message translates to:
  /// **'Network name'**
  String get wifiName;

  /// No description provided for @wifiPassword.
  ///
  /// In en, this message translates to:
  /// **'Password'**
  String get wifiPassword;

  /// No description provided for @showWifi.
  ///
  /// In en, this message translates to:
  /// **'Show Wi-Fi on the menu'**
  String get showWifi;

  /// No description provided for @openingHours.
  ///
  /// In en, this message translates to:
  /// **'Opening hours'**
  String get openingHours;

  /// No description provided for @closed.
  ///
  /// In en, this message translates to:
  /// **'Closed'**
  String get closed;

  /// No description provided for @addBreak.
  ///
  /// In en, this message translates to:
  /// **'Add hours'**
  String get addBreak;

  /// No description provided for @copyMonday.
  ///
  /// In en, this message translates to:
  /// **'Copy Monday to Tue–Fri'**
  String get copyMonday;

  /// No description provided for @holidays.
  ///
  /// In en, this message translates to:
  /// **'Holidays and closures'**
  String get holidays;

  /// No description provided for @addClosure.
  ///
  /// In en, this message translates to:
  /// **'Add closure'**
  String get addClosure;

  /// No description provided for @note.
  ///
  /// In en, this message translates to:
  /// **'Note'**
  String get note;

  /// No description provided for @nextDay.
  ///
  /// In en, this message translates to:
  /// **'next day'**
  String get nextDay;

  /// No description provided for @forBranch.
  ///
  /// In en, this message translates to:
  /// **'For'**
  String get forBranch;

  /// No description provided for @promotions.
  ///
  /// In en, this message translates to:
  /// **'Promotions'**
  String get promotions;

  /// No description provided for @special.
  ///
  /// In en, this message translates to:
  /// **'Special'**
  String get special;

  /// No description provided for @announcement.
  ///
  /// In en, this message translates to:
  /// **'Announcement'**
  String get announcement;

  /// No description provided for @newSpecial.
  ///
  /// In en, this message translates to:
  /// **'New special'**
  String get newSpecial;

  /// No description provided for @newAnnouncement.
  ///
  /// In en, this message translates to:
  /// **'New announcement'**
  String get newAnnouncement;

  /// No description provided for @title.
  ///
  /// In en, this message translates to:
  /// **'Title'**
  String get title;

  /// No description provided for @text.
  ///
  /// In en, this message translates to:
  /// **'Text'**
  String get text;

  /// No description provided for @startsAt.
  ///
  /// In en, this message translates to:
  /// **'Starts'**
  String get startsAt;

  /// No description provided for @endsAt.
  ///
  /// In en, this message translates to:
  /// **'Ends'**
  String get endsAt;

  /// No description provided for @noEnd.
  ///
  /// In en, this message translates to:
  /// **'No end'**
  String get noEnd;

  /// No description provided for @on.
  ///
  /// In en, this message translates to:
  /// **'On'**
  String get on;

  /// No description provided for @happyHour.
  ///
  /// In en, this message translates to:
  /// **'Happy hour'**
  String get happyHour;

  /// No description provided for @pricePeriods.
  ///
  /// In en, this message translates to:
  /// **'Time-based prices'**
  String get pricePeriods;

  /// No description provided for @newPricePeriod.
  ///
  /// In en, this message translates to:
  /// **'New price period'**
  String get newPricePeriod;

  /// No description provided for @periodName.
  ///
  /// In en, this message translates to:
  /// **'Name shown to guests'**
  String get periodName;

  /// No description provided for @fixedPrice.
  ///
  /// In en, this message translates to:
  /// **'New price'**
  String get fixedPrice;

  /// No description provided for @percentOff.
  ///
  /// In en, this message translates to:
  /// **'% off'**
  String get percentOff;

  /// No description provided for @value.
  ///
  /// In en, this message translates to:
  /// **'Value'**
  String get value;

  /// No description provided for @addDishes.
  ///
  /// In en, this message translates to:
  /// **'Add dishes'**
  String get addDishes;

  /// No description provided for @insights.
  ///
  /// In en, this message translates to:
  /// **'Insights'**
  String get insights;

  /// No description provided for @days90.
  ///
  /// In en, this message translates to:
  /// **'90 days'**
  String get days90;

  /// No description provided for @menuViews.
  ///
  /// In en, this message translates to:
  /// **'Menu views'**
  String get menuViews;

  /// No description provided for @qrScans.
  ///
  /// In en, this message translates to:
  /// **'QR scans'**
  String get qrScans;

  /// No description provided for @dishViews.
  ///
  /// In en, this message translates to:
  /// **'Dish views'**
  String get dishViews;

  /// No description provided for @topDishes.
  ///
  /// In en, this message translates to:
  /// **'Most viewed dishes'**
  String get topDishes;

  /// No description provided for @scansPerCode.
  ///
  /// In en, this message translates to:
  /// **'Scans per QR code'**
  String get scansPerCode;

  /// No description provided for @export.
  ///
  /// In en, this message translates to:
  /// **'Export'**
  String get export;

  /// No description provided for @exportViews.
  ///
  /// In en, this message translates to:
  /// **'Views and scans per day (CSV)'**
  String get exportViews;

  /// No description provided for @exportQr.
  ///
  /// In en, this message translates to:
  /// **'Scans per QR code (CSV)'**
  String get exportQr;

  /// No description provided for @exportDishes.
  ///
  /// In en, this message translates to:
  /// **'Dishes (CSV)'**
  String get exportDishes;

  /// No description provided for @exportPdf.
  ///
  /// In en, this message translates to:
  /// **'Report (PDF)'**
  String get exportPdf;

  /// No description provided for @monthlyReport.
  ///
  /// In en, this message translates to:
  /// **'Monthly report by e-mail'**
  String get monthlyReport;

  /// No description provided for @monthlyReportHint.
  ///
  /// In en, this message translates to:
  /// **'On the 1st, owners get last month\'s report.'**
  String get monthlyReportHint;

  /// No description provided for @deletedCode.
  ///
  /// In en, this message translates to:
  /// **'Deleted code'**
  String get deletedCode;

  /// No description provided for @team.
  ///
  /// In en, this message translates to:
  /// **'Team'**
  String get team;

  /// No description provided for @invite.
  ///
  /// In en, this message translates to:
  /// **'Invite'**
  String get invite;

  /// No description provided for @inviteTitle.
  ///
  /// In en, this message translates to:
  /// **'Invite to your team'**
  String get inviteTitle;

  /// No description provided for @role.
  ///
  /// In en, this message translates to:
  /// **'Role'**
  String get role;

  /// No description provided for @role_owner.
  ///
  /// In en, this message translates to:
  /// **'Owner'**
  String get role_owner;

  /// No description provided for @role_manager.
  ///
  /// In en, this message translates to:
  /// **'Manager'**
  String get role_manager;

  /// No description provided for @role_staff.
  ///
  /// In en, this message translates to:
  /// **'Staff'**
  String get role_staff;

  /// No description provided for @canEditPrices.
  ///
  /// In en, this message translates to:
  /// **'Can change prices'**
  String get canEditPrices;

  /// No description provided for @pendingInvitations.
  ///
  /// In en, this message translates to:
  /// **'Waiting for an answer'**
  String get pendingInvitations;

  /// No description provided for @expires.
  ///
  /// In en, this message translates to:
  /// **'Expires {date}'**
  String expires(Object date);

  /// No description provided for @removeMember.
  ///
  /// In en, this message translates to:
  /// **'Remove from team'**
  String get removeMember;

  /// No description provided for @you.
  ///
  /// In en, this message translates to:
  /// **'You'**
  String get you;

  /// No description provided for @inviteSent.
  ///
  /// In en, this message translates to:
  /// **'Invitation sent'**
  String get inviteSent;

  /// No description provided for @billing.
  ///
  /// In en, this message translates to:
  /// **'Billing'**
  String get billing;

  /// No description provided for @paidUntil.
  ///
  /// In en, this message translates to:
  /// **'Paid until'**
  String get paidUntil;

  /// No description provided for @trialEnds.
  ///
  /// In en, this message translates to:
  /// **'Trial ends'**
  String get trialEnds;

  /// No description provided for @invoices.
  ///
  /// In en, this message translates to:
  /// **'Invoices'**
  String get invoices;

  /// No description provided for @noInvoices.
  ///
  /// In en, this message translates to:
  /// **'No invoices yet'**
  String get noInvoices;

  /// No description provided for @status.
  ///
  /// In en, this message translates to:
  /// **'Status'**
  String get status;

  /// No description provided for @state_trial.
  ///
  /// In en, this message translates to:
  /// **'Trial'**
  String get state_trial;

  /// No description provided for @state_active.
  ///
  /// In en, this message translates to:
  /// **'Active'**
  String get state_active;

  /// No description provided for @state_grace.
  ///
  /// In en, this message translates to:
  /// **'Grace period'**
  String get state_grace;

  /// No description provided for @state_read_only.
  ///
  /// In en, this message translates to:
  /// **'Read-only'**
  String get state_read_only;

  /// No description provided for @state_unavailable.
  ///
  /// In en, this message translates to:
  /// **'Unavailable'**
  String get state_unavailable;

  /// No description provided for @state_free.
  ///
  /// In en, this message translates to:
  /// **'Free'**
  String get state_free;

  /// No description provided for @state_pending.
  ///
  /// In en, this message translates to:
  /// **'Pending'**
  String get state_pending;

  /// No description provided for @activity.
  ///
  /// In en, this message translates to:
  /// **'Activity'**
  String get activity;

  /// No description provided for @news.
  ///
  /// In en, this message translates to:
  /// **'News'**
  String get news;

  /// No description provided for @restaurantSection.
  ///
  /// In en, this message translates to:
  /// **'Restaurant'**
  String get restaurantSection;

  /// No description provided for @account.
  ///
  /// In en, this message translates to:
  /// **'Account'**
  String get account;

  /// No description provided for @profile.
  ///
  /// In en, this message translates to:
  /// **'Profile'**
  String get profile;

  /// No description provided for @notifications.
  ///
  /// In en, this message translates to:
  /// **'Notifications'**
  String get notifications;

  /// No description provided for @settings.
  ///
  /// In en, this message translates to:
  /// **'Settings'**
  String get settings;

  /// No description provided for @help.
  ///
  /// In en, this message translates to:
  /// **'Help and support'**
  String get help;

  /// No description provided for @helpTitle.
  ///
  /// In en, this message translates to:
  /// **'Help'**
  String get helpTitle;

  /// No description provided for @helpSearch.
  ///
  /// In en, this message translates to:
  /// **'Search help'**
  String get helpSearch;

  /// No description provided for @helpResults.
  ///
  /// In en, this message translates to:
  /// **'{count, plural, =1{1 article found} other{{count} articles found}}'**
  String helpResults(int count);

  /// No description provided for @helpNothing.
  ///
  /// In en, this message translates to:
  /// **'Nothing found. Try other words.'**
  String get helpNothing;

  /// No description provided for @helpContact.
  ///
  /// In en, this message translates to:
  /// **'Contact support'**
  String get helpContact;

  /// No description provided for @helpNotFound.
  ///
  /// In en, this message translates to:
  /// **'This article is not available.'**
  String get helpNotFound;

  /// No description provided for @helpHelpful.
  ///
  /// In en, this message translates to:
  /// **'Was this helpful?'**
  String get helpHelpful;

  /// No description provided for @helpThanks.
  ///
  /// In en, this message translates to:
  /// **'Thank you! You can change your answer.'**
  String get helpThanks;

  /// No description provided for @yes.
  ///
  /// In en, this message translates to:
  /// **'Yes'**
  String get yes;

  /// No description provided for @no.
  ///
  /// In en, this message translates to:
  /// **'No'**
  String get no;

  /// No description provided for @helpThisScreen.
  ///
  /// In en, this message translates to:
  /// **'Help for this screen'**
  String get helpThisScreen;

  /// No description provided for @helpNoArticle.
  ///
  /// In en, this message translates to:
  /// **'Pick an article in Help.'**
  String get helpNoArticle;

  /// No description provided for @helpOpenCentre.
  ///
  /// In en, this message translates to:
  /// **'Open Help'**
  String get helpOpenCentre;

  /// No description provided for @back.
  ///
  /// In en, this message translates to:
  /// **'Back'**
  String get back;

  /// No description provided for @tourOpen.
  ///
  /// In en, this message translates to:
  /// **'Take the tour'**
  String get tourOpen;

  /// No description provided for @tourStep.
  ///
  /// In en, this message translates to:
  /// **'{n} of {total}'**
  String tourStep(int n, int total);

  /// No description provided for @tourSkip.
  ///
  /// In en, this message translates to:
  /// **'Skip tour'**
  String get tourSkip;

  /// No description provided for @tourNext.
  ///
  /// In en, this message translates to:
  /// **'Next'**
  String get tourNext;

  /// No description provided for @tourDone.
  ///
  /// In en, this message translates to:
  /// **'Done'**
  String get tourDone;

  /// No description provided for @tourHomeTitle.
  ///
  /// In en, this message translates to:
  /// **'This is Home'**
  String get tourHomeTitle;

  /// No description provided for @tourHomeText.
  ///
  /// In en, this message translates to:
  /// **'Today’s menu views and QR scans, and the quick actions for the jobs you do every day.'**
  String get tourHomeText;

  /// No description provided for @tourDishTitle.
  ///
  /// In en, this message translates to:
  /// **'Add your first dish'**
  String get tourDishTitle;

  /// No description provided for @tourDishText.
  ///
  /// In en, this message translates to:
  /// **'Tap Add dish with photo, take the photo, type the name and price, and save.'**
  String get tourDishText;

  /// No description provided for @tourDishGo.
  ///
  /// In en, this message translates to:
  /// **'Add a dish'**
  String get tourDishGo;

  /// No description provided for @tourSoldOutTitle.
  ///
  /// In en, this message translates to:
  /// **'Mark dishes sold out'**
  String get tourSoldOutTitle;

  /// No description provided for @tourSoldOutText.
  ///
  /// In en, this message translates to:
  /// **'Finished for today? Mark it sold out. Tap Available again when it is back.'**
  String get tourSoldOutText;

  /// No description provided for @tourSoldOutGo.
  ///
  /// In en, this message translates to:
  /// **'Open Sold out'**
  String get tourSoldOutGo;

  /// No description provided for @tourLookTitle.
  ///
  /// In en, this message translates to:
  /// **'Choose your menu look'**
  String get tourLookTitle;

  /// No description provided for @tourLookText.
  ///
  /// In en, this message translates to:
  /// **'Pick a style, your colour, your logo and a banner photo.'**
  String get tourLookText;

  /// No description provided for @tourLookGo.
  ///
  /// In en, this message translates to:
  /// **'Choose a look'**
  String get tourLookGo;

  /// No description provided for @tourQrTitle.
  ///
  /// In en, this message translates to:
  /// **'Share your QR code'**
  String get tourQrTitle;

  /// No description provided for @tourQrText.
  ///
  /// In en, this message translates to:
  /// **'Share your menu link on WhatsApp, and print your QR code for the tables.'**
  String get tourQrText;

  /// No description provided for @tourQrGo.
  ///
  /// In en, this message translates to:
  /// **'Get my QR code'**
  String get tourQrGo;

  /// No description provided for @profileTitle.
  ///
  /// In en, this message translates to:
  /// **'Profile'**
  String get profileTitle;

  /// No description provided for @changePassword.
  ///
  /// In en, this message translates to:
  /// **'Change password'**
  String get changePassword;

  /// No description provided for @currentPassword.
  ///
  /// In en, this message translates to:
  /// **'Current password'**
  String get currentPassword;

  /// No description provided for @newPassword.
  ///
  /// In en, this message translates to:
  /// **'New password'**
  String get newPassword;

  /// No description provided for @confirmPassword.
  ///
  /// In en, this message translates to:
  /// **'Repeat new password'**
  String get confirmPassword;

  /// No description provided for @passwordChanged.
  ///
  /// In en, this message translates to:
  /// **'Password changed. Other devices were signed out.'**
  String get passwordChanged;

  /// No description provided for @passwordsDontMatch.
  ///
  /// In en, this message translates to:
  /// **'The passwords don\'t match'**
  String get passwordsDontMatch;

  /// No description provided for @twoFactor.
  ///
  /// In en, this message translates to:
  /// **'Two-step sign-in'**
  String get twoFactor;

  /// No description provided for @twoFactorOn.
  ///
  /// In en, this message translates to:
  /// **'On'**
  String get twoFactorOn;

  /// No description provided for @twoFactorOff.
  ///
  /// In en, this message translates to:
  /// **'Off'**
  String get twoFactorOff;

  /// No description provided for @twoFactorTurnOn.
  ///
  /// In en, this message translates to:
  /// **'Turn on'**
  String get twoFactorTurnOn;

  /// No description provided for @twoFactorTurnOff.
  ///
  /// In en, this message translates to:
  /// **'Turn off'**
  String get twoFactorTurnOff;

  /// No description provided for @twoFactorScan.
  ///
  /// In en, this message translates to:
  /// **'Scan this code with an authenticator app (Google Authenticator, Microsoft Authenticator, 1Password…), or enter the key.'**
  String get twoFactorScan;

  /// No description provided for @twoFactorKey.
  ///
  /// In en, this message translates to:
  /// **'Key'**
  String get twoFactorKey;

  /// No description provided for @twoFactorConfirm.
  ///
  /// In en, this message translates to:
  /// **'Enter the 6-digit code to finish'**
  String get twoFactorConfirm;

  /// No description provided for @recoveryCodes.
  ///
  /// In en, this message translates to:
  /// **'Recovery codes'**
  String get recoveryCodes;

  /// No description provided for @recoveryCodesHint.
  ///
  /// In en, this message translates to:
  /// **'Keep these somewhere safe. Each works once if you lose your phone.'**
  String get recoveryCodesHint;

  /// No description provided for @newRecoveryCodes.
  ///
  /// In en, this message translates to:
  /// **'New recovery codes'**
  String get newRecoveryCodes;

  /// No description provided for @confirmPasswordTitle.
  ///
  /// In en, this message translates to:
  /// **'Confirm your password'**
  String get confirmPasswordTitle;

  /// No description provided for @devices.
  ///
  /// In en, this message translates to:
  /// **'Devices'**
  String get devices;

  /// No description provided for @thisDevice.
  ///
  /// In en, this message translates to:
  /// **'This device'**
  String get thisDevice;

  /// No description provided for @lastUsed.
  ///
  /// In en, this message translates to:
  /// **'Last used {time}'**
  String lastUsed(Object time);

  /// No description provided for @signOutDevice.
  ///
  /// In en, this message translates to:
  /// **'Sign out this device'**
  String get signOutDevice;

  /// No description provided for @signOutDeviceConfirm.
  ///
  /// In en, this message translates to:
  /// **'Sign out {name}?'**
  String signOutDeviceConfirm(Object name);

  /// No description provided for @emailChangeSent.
  ///
  /// In en, this message translates to:
  /// **'We sent a link to {email}. Your address changes when you click it.'**
  String emailChangeSent(String email);

  /// No description provided for @emailPending.
  ///
  /// In en, this message translates to:
  /// **'Waiting for confirmation: {email}. Click the link we sent there (valid for 1 hour).'**
  String emailPending(String email);

  /// No description provided for @passwordWeakBanner.
  ///
  /// In en, this message translates to:
  /// **'Your password is on a list of common or leaked passwords.'**
  String get passwordWeakBanner;

  /// No description provided for @passwordWeakAction.
  ///
  /// In en, this message translates to:
  /// **'Change it'**
  String get passwordWeakAction;

  /// No description provided for @twoFactorCardTitle.
  ///
  /// In en, this message translates to:
  /// **'Protect your restaurant with two-step sign-in'**
  String get twoFactorCardTitle;

  /// No description provided for @twoFactorCardText.
  ///
  /// In en, this message translates to:
  /// **'A 6-digit code from your phone at sign-in, so a stolen password alone can\'t open your menu, prices or team.'**
  String get twoFactorCardText;

  /// No description provided for @twoFactorCardAction.
  ///
  /// In en, this message translates to:
  /// **'Turn it on'**
  String get twoFactorCardAction;

  /// No description provided for @twoFactorRequiredText.
  ///
  /// In en, this message translates to:
  /// **'Your platform requires two-step sign-in for restaurant owners. Turn it on to continue.'**
  String get twoFactorRequiredText;

  /// No description provided for @notificationSettings.
  ///
  /// In en, this message translates to:
  /// **'Notifications'**
  String get notificationSettings;

  /// No description provided for @notificationsGroupRestaurant.
  ///
  /// In en, this message translates to:
  /// **'Restaurant'**
  String get notificationsGroupRestaurant;

  /// No description provided for @notificationsGroupPlatform.
  ///
  /// In en, this message translates to:
  /// **'Platform'**
  String get notificationsGroupPlatform;

  /// No description provided for @pushNotSetUp.
  ///
  /// In en, this message translates to:
  /// **'Push notifications are not set up on this server yet. You\'ll still see notifications in the app.'**
  String get pushNotSetUp;

  /// No description provided for @inbox.
  ///
  /// In en, this message translates to:
  /// **'Notifications'**
  String get inbox;

  /// No description provided for @inboxEmpty.
  ///
  /// In en, this message translates to:
  /// **'No notifications'**
  String get inboxEmpty;

  /// No description provided for @markAllRead.
  ///
  /// In en, this message translates to:
  /// **'Mark all as read'**
  String get markAllRead;

  /// No description provided for @appSettings.
  ///
  /// In en, this message translates to:
  /// **'App settings'**
  String get appSettings;

  /// No description provided for @themeLight.
  ///
  /// In en, this message translates to:
  /// **'Light'**
  String get themeLight;

  /// No description provided for @themeDark.
  ///
  /// In en, this message translates to:
  /// **'Dark'**
  String get themeDark;

  /// No description provided for @appLock.
  ///
  /// In en, this message translates to:
  /// **'App lock'**
  String get appLock;

  /// No description provided for @appLockHint.
  ///
  /// In en, this message translates to:
  /// **'Ask for your fingerprint, face or PIN when opening the app.'**
  String get appLockHint;

  /// No description provided for @autoLock.
  ///
  /// In en, this message translates to:
  /// **'Lock after'**
  String get autoLock;

  /// No description provided for @minutes.
  ///
  /// In en, this message translates to:
  /// **'{count} min'**
  String minutes(Object count);

  /// No description provided for @setPin.
  ///
  /// In en, this message translates to:
  /// **'Set a PIN'**
  String get setPin;

  /// No description provided for @changePin.
  ///
  /// In en, this message translates to:
  /// **'Change PIN'**
  String get changePin;

  /// No description provided for @enterPin.
  ///
  /// In en, this message translates to:
  /// **'Enter your PIN'**
  String get enterPin;

  /// No description provided for @pinHint.
  ///
  /// In en, this message translates to:
  /// **'4 to 6 digits'**
  String get pinHint;

  /// No description provided for @pinWrong.
  ///
  /// In en, this message translates to:
  /// **'Wrong PIN'**
  String get pinWrong;

  /// No description provided for @rootWarning.
  ///
  /// In en, this message translates to:
  /// **'This phone looks rooted or jailbroken. The app works, but other apps on it may be able to read your restaurant data. If you can, use a phone that is not modified.'**
  String get rootWarning;

  /// No description provided for @pinWait.
  ///
  /// In en, this message translates to:
  /// **'Too many wrong PINs. Try again in {seconds} s.'**
  String pinWait(int seconds);

  /// No description provided for @pinTriesLeft.
  ///
  /// In en, this message translates to:
  /// **'{count, plural, =1{1 try left before the app signs out} other{{count} tries left before the app signs out}}'**
  String pinTriesLeft(int count);

  /// No description provided for @pinSignedOut.
  ///
  /// In en, this message translates to:
  /// **'Too many wrong PINs, so the app signed out. Sign in again with your password.'**
  String get pinSignedOut;

  /// No description provided for @unlock.
  ///
  /// In en, this message translates to:
  /// **'Unlock'**
  String get unlock;

  /// No description provided for @useBiometrics.
  ///
  /// In en, this message translates to:
  /// **'Use fingerprint or face'**
  String get useBiometrics;

  /// No description provided for @crashReports.
  ///
  /// In en, this message translates to:
  /// **'Send crash reports'**
  String get crashReports;

  /// No description provided for @crashReportsHint.
  ///
  /// In en, this message translates to:
  /// **'When the app crashes, send a technical report (no menu content, no personal data) so we can fix it.'**
  String get crashReportsHint;

  /// No description provided for @usageStats.
  ///
  /// In en, this message translates to:
  /// **'Anonymous usage statistics'**
  String get usageStats;

  /// No description provided for @usageStatsHint.
  ///
  /// In en, this message translates to:
  /// **'Which screens and features are used, to improve the app. Never your menu content.'**
  String get usageStatsHint;

  /// No description provided for @theme.
  ///
  /// In en, this message translates to:
  /// **'Theme'**
  String get theme;

  /// No description provided for @themeSystem.
  ///
  /// In en, this message translates to:
  /// **'Phone'**
  String get themeSystem;

  /// No description provided for @version.
  ///
  /// In en, this message translates to:
  /// **'Version {version}'**
  String version(Object version);

  /// No description provided for @licences.
  ///
  /// In en, this message translates to:
  /// **'Open-source licences'**
  String get licences;

  /// No description provided for @deleteAccount.
  ///
  /// In en, this message translates to:
  /// **'Delete my account'**
  String get deleteAccount;

  /// No description provided for @supportWhatsapp.
  ///
  /// In en, this message translates to:
  /// **'WhatsApp support'**
  String get supportWhatsapp;

  /// No description provided for @supportEmail.
  ///
  /// In en, this message translates to:
  /// **'E-mail support'**
  String get supportEmail;

  /// No description provided for @checkUpdates.
  ///
  /// In en, this message translates to:
  /// **'Check for updates'**
  String get checkUpdates;

  /// No description provided for @lockedTitle.
  ///
  /// In en, this message translates to:
  /// **'Menu Admin is locked'**
  String get lockedTitle;

  /// No description provided for @owner.
  ///
  /// In en, this message translates to:
  /// **'Owner'**
  String get owner;

  /// No description provided for @method.
  ///
  /// In en, this message translates to:
  /// **'Method'**
  String get method;

  /// No description provided for @featureOn.
  ///
  /// In en, this message translates to:
  /// **'On for this restaurant'**
  String get featureOn;

  /// No description provided for @message.
  ///
  /// In en, this message translates to:
  /// **'Message'**
  String get message;
}

class _LDelegate extends LocalizationsDelegate<L> {
  const _LDelegate();

  @override
  Future<L> load(Locale locale) {
    return SynchronousFuture<L>(lookupL(locale));
  }

  @override
  bool isSupported(Locale locale) => <String>['en'].contains(locale.languageCode);

  @override
  bool shouldReload(_LDelegate old) => false;
}

L lookupL(Locale locale) {
  // Lookup logic when only language code is specified.
  switch (locale.languageCode) {
    case 'en':
      return LEn();
  }

  throw FlutterError(
    'L.delegate failed to load unsupported locale "$locale". This is likely '
    'an issue with the localizations generation tool. Please file an issue '
    'on GitHub with a reproducible sample app and the gen-l10n configuration '
    'that was used.',
  );
}
