// Runs the same flows on a real phone: `flutter test integration_test`.
import 'package:flutter_test/flutter_test.dart';
import 'package:integration_test/integration_test.dart';

import '../test/widgets/flows.dart';

void main() {
  IntegrationTestWidgetsFlutterBinding.ensureInitialized();
  testWidgets('login + two-factor', loginWithTwoFactor);
  testWidgets('sold-out toggle', toggleSoldOut);
  testWidgets('edit a dish', editDish);
  testWidgets('Super Admin switch', superAdminSwitchFlow);
}
