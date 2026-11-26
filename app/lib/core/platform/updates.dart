import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:in_app_review/in_app_review.dart';
import 'package:in_app_update/in_app_update.dart';

import '../auth/token_store.dart';
import '../providers.dart';

/// Google Play in-app update (QUESTIONS M21): flexible normally, immediate when
/// this version is below the server's minimum.
Future<void> checkPlayUpdate({required bool mustUpdate}) async {
  if (defaultTargetPlatform != TargetPlatform.android || kDebugMode) return;
  try {
    final info = await InAppUpdate.checkForUpdate();
    if (info.updateAvailability != UpdateAvailability.updateAvailable) return;
    if (mustUpdate && info.immediateUpdateAllowed) {
      await InAppUpdate.performImmediateUpdate();
    } else if (info.flexibleUpdateAllowed) {
      final r = await InAppUpdate.startFlexibleUpdate();
      if (r == AppUpdateResult.success) await InAppUpdate.completeFlexibleUpdate();
    }
  } catch (_) {
    // Not installed from Play (debug, side-loaded): nothing to do.
  }
}

final reviewPromptProvider = Provider<ReviewPrompt>((ref) => ReviewPrompt(ref.watch(tokenStoreProvider)));

/// Polite review prompt: after 5 sessions on 3 different days and 3 saves,
/// never after an error, at most every 120 days (PLAN.md §7).
class ReviewPrompt {
  ReviewPrompt(this._store);
  final TokenStore _store;

  Future<void> recordSession() async {
    final today = DateTime.now().toIso8601String().substring(0, 10);
    final days = {...(await _store.read('review_days') ?? '').split(',').where((d) => d.isNotEmpty), today};
    await _store.write('review_days', days.take(30).join(','));
    await _store.write('review_sessions', '${(int.tryParse(await _store.read('review_sessions') ?? '') ?? 0) + 1}');
  }

  Future<void> recordSave() async => _store.write('review_saves', '${(int.tryParse(await _store.read('review_saves') ?? '') ?? 0) + 1}');

  Future<bool> shouldAsk() async {
    final sessions = int.tryParse(await _store.read('review_sessions') ?? '') ?? 0;
    final saves = int.tryParse(await _store.read('review_saves') ?? '') ?? 0;
    final days = (await _store.read('review_days') ?? '').split(',').where((d) => d.isNotEmpty).length;
    final last = DateTime.tryParse(await _store.read('review_asked') ?? '');
    return sessions >= 5 && days >= 3 && saves >= 3 && (last == null || DateTime.now().difference(last).inDays >= 120);
  }

  /// After a save that worked: count it, and maybe ask (a good moment).
  Future<void> afterSave() async {
    await recordSave();
    await maybeAsk();
  }

  Future<void> maybeAsk() async {
    if (!await shouldAsk()) return;
    await _store.write('review_asked', DateTime.now().toIso8601String());
    try {
      final r = InAppReview.instance;
      if (await r.isAvailable()) await r.requestReview();
    } catch (_) {}
  }
}
