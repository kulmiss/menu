# Flutter and plugins keep their own rules; these cover reflection used by plugins.
-keep class com.yalantis.ucrop.** { *; }
-dontwarn com.yalantis.ucrop.**
-keep class io.flutter.plugins.firebase.** { *; }
-dontwarn com.google.android.play.core.**
