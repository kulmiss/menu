import 'package:flutter_test/flutter_test.dart';

import 'flows.dart';

void main() {
  testWidgets('login with two-factor lands on the restaurant dashboard', loginWithTwoFactor);
  testWidgets('sold-out toggle is optimistic and sends one keyed request', toggleSoldOut);
  testWidgets('editing a dish saves name and price in minor units', editDish);
  testWidgets('Super Admin: overview and an emergency switch', superAdminSwitchFlow);
}
