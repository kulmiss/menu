import 'dart:io';

import 'package:flutter/services.dart';

/// A rooted / jailbroken phone lets other apps read this app's storage (tokens,
/// cache). The app only WARNS, once, and keeps working (owner, 2026-11-14;
/// SECURITY_REPORT.md L12). Simple local checks, no SDK, nothing sent anywhere.
class DeviceIntegrity {
  DeviceIntegrity._();
  static const _channel = MethodChannel('menuadmin/secure');

  static const iosPaths = [
    '/Applications/Cydia.app',
    '/Applications/Sileo.app',
    '/Library/MobileSubstrate/MobileSubstrate.dylib',
    '/bin/bash',
    '/usr/sbin/sshd',
    '/etc/apt',
    '/private/var/lib/apt/',
    '/var/jb',
  ];

  static Future<bool> looksCompromised() async {
    try {
      if (Platform.isAndroid) return await _channel.invokeMethod<bool>('looksRooted') ?? false;
      if (Platform.isIOS) {
        for (final p in iosPaths) {
          if (await FileSystemEntity.type(p) != FileSystemEntityType.notFound) return true;
        }
        // A normal app can't write outside its sandbox.
        try {
          final f = File('/private/menuadmin-jb-check.txt');
          await f.writeAsString('x');
          await f.delete();
          return true;
        } on FileSystemException {
          return false;
        }
      }
    } on MissingPluginException {
      return false;
    } on PlatformException {
      return false;
    }
    return false;
  }
}
