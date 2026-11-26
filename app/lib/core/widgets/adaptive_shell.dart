import 'package:flutter/material.dart';

class NavItem {
  const NavItem(this.label, this.icon, this.selectedIcon, this.path, {this.badge});
  final String label;
  final IconData icon;
  final IconData selectedIcon;
  final String path;
  final String? badge;
}

/// Phones: bottom navigation bar. Tablets/foldables (≥ 600 dp): navigation
/// rail, extended with labels beside icons from 1200 dp (QUESTIONS M24).
class AdaptiveShell extends StatelessWidget {
  const AdaptiveShell({super.key, required this.items, required this.location, required this.onSelect, required this.child, this.header, this.railLeading});

  final List<NavItem> items;
  final String location;
  final ValueChanged<String> onSelect;
  final Widget child;
  final Widget? header;
  final Widget? railLeading;

  int get _index {
    var best = 0, bestLen = -1;
    for (var i = 0; i < items.length; i++) {
      final p = items[i].path;
      if ((location == p || location.startsWith('$p/')) && p.length > bestLen) {
        best = i;
        bestLen = p.length;
      }
    }
    return best;
  }

  Widget _icon(NavItem i, bool selected) {
    final icon = Icon(selected ? i.selectedIcon : i.icon);
    return i.badge == null ? icon : Badge(label: Text(i.badge!), child: icon);
  }

  @override
  Widget build(BuildContext context) {
    final width = MediaQuery.sizeOf(context).width;
    final body = Column(
      children: [
        ?header,
        Expanded(child: child),
      ],
    );
    // One screen (Super Admin): no navigation bar.
    if (items.length < 2) return Scaffold(body: body);
    if (width < 600) {
      return Scaffold(
        body: body,
        bottomNavigationBar: NavigationBar(
          selectedIndex: _index,
          onDestinationSelected: (i) => onSelect(items[i].path),
          destinations: [for (final i in items) NavigationDestination(icon: _icon(i, false), selectedIcon: _icon(i, true), label: i.label, tooltip: '')],
        ),
      );
    }
    final extended = width >= 1200;
    return Scaffold(
      body: Row(
        children: [
          SafeArea(
            right: false,
            child: NavigationRail(
              extended: extended,
              labelType: extended ? NavigationRailLabelType.none : NavigationRailLabelType.all,
              leading: railLeading,
              selectedIndex: _index,
              onDestinationSelected: (i) => onSelect(items[i].path),
              destinations: [for (final i in items) NavigationRailDestination(icon: _icon(i, false), selectedIcon: _icon(i, true), label: Text(i.label))],
            ),
          ),
          VerticalDivider(width: 1, color: Theme.of(context).colorScheme.outlineVariant),
          Expanded(child: body),
        ],
      ),
    );
  }
}

/// List on the left, detail on the right on wide screens (≥ 840 dp);
/// on phones only the list (the detail is pushed as its own page).
class TwoPane extends StatelessWidget {
  const TwoPane({super.key, required this.list, this.detail, this.listWidth = 380, this.emptyDetail});
  final Widget list;
  final Widget? detail;
  final Widget? emptyDetail;
  final double listWidth;

  static bool isWide(BuildContext context) => MediaQuery.sizeOf(context).width >= 840;

  @override
  Widget build(BuildContext context) {
    if (!isWide(context)) return list;
    return Row(
      children: [
        SizedBox(width: listWidth, child: list),
        VerticalDivider(width: 1, color: Theme.of(context).colorScheme.outlineVariant),
        Expanded(child: detail ?? emptyDetail ?? const SizedBox.shrink()),
      ],
    );
  }
}
