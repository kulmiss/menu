import 'package:flutter/material.dart';

/// The app icon (concept B: menu card + QR corner) drawn in code.
class AppLogo extends StatelessWidget {
  const AppLogo({super.key, this.size = 72});
  final double size;

  @override
  Widget build(BuildContext context) => Semantics(
    label: 'Menu Admin',
    image: true,
    child: CustomPaint(size: Size.square(size), painter: _LogoPainter()),
  );
}

class _LogoPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final s = size.width / 108;
    canvas.scale(s);
    final dark = Paint()..color = const Color(0xFF0F172A);
    final teal = Paint()..color = const Color(0xFF14B8A6);
    RRect r(double x, double y, double w, double h, double rad) => RRect.fromRectAndRadius(Rect.fromLTWH(x, y, w, h), Radius.circular(rad));
    canvas.drawRRect(r(0, 0, 108, 108, 24), dark);
    canvas.drawRRect(r(28, 22, 52, 64, 9), teal);
    canvas.drawRRect(r(37, 33, 26, 5, 2.5), dark);
    final dim = Paint()..color = const Color(0x8C0F172A);
    canvas.drawRRect(r(37, 44, 34, 4, 2), dim);
    canvas.drawRRect(r(37, 53, 20, 4, 2), dim);
    canvas.drawRRect(r(56, 62, 18, 18, 4), dark);
    canvas.drawRRect(r(60, 66, 10, 10, 2), teal);
    canvas.drawRRect(r(63, 69, 4, 4, 1), dark);
    canvas.drawRRect(r(37, 68, 5, 5, 1.2), dark);
    canvas.drawRRect(r(45, 73, 5, 5, 1.2), dark);
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
