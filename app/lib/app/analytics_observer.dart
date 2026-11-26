import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/platform/firebase.dart';

/// Anonymous screen views (route names without ids).
class AppRouteObserver extends NavigatorObserver {
  AppRouteObserver(this._ref);
  final Ref _ref;

  @override
  void didPush(Route<dynamic> route, Route<dynamic>? previousRoute) {
    final name = route.settings.name;
    if (name != null) _ref.read(analyticsProvider).screen(name);
  }
}
