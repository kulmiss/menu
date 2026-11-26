import 'package:flutter/material.dart';

import '../../core/widgets/kit.dart';
import 'team_screen.dart' show BillingScreenBody;

class BillingScreen extends StatelessWidget {
  const BillingScreen({super.key});
  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Text(context.l.billing)),
    body: const BillingScreenBody(),
  );
}
