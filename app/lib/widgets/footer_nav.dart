/// The app's bottom navigation: a soft raised bar along the foot of the screen. Each page is a moulded pill
/// sitting on the same surface it is cut from — lit on top-left, shadowed bottom-right — and the page you are on
/// presses in and takes the brand's lime. Everything a desk offers stays one press away instead of behind a menu.
library;

import 'package:flutter/material.dart';
import 'ui.dart';

class FooterItem {
  final String key;
  final String label;
  final IconData icon;
  final int badge;

  const FooterItem(this.key, this.label, this.icon, {this.badge = 0});
}

class FooterNav extends StatelessWidget {
  final List<FooterItem> items;
  final String current;
  final ValueChanged<String> onOpen;

  const FooterNav({
    super.key,
    required this.items,
    required this.current,
    required this.onOpen,
  });

  /// Every page the desk offers is on show at once — the pills split into rows rather than hiding
  /// the tail of the bar behind a swipe.
  @override
  Widget build(BuildContext context) {
    return Container(
      color: page,
      child: SafeArea(
        top: false,
        child: LayoutBuilder(builder: (context, box) {
          const spacing = 6.0;
          final avail = box.maxWidth - 12;
          final rows = items.length > 6 ? 2 : 1;
          final perRow = (items.length / rows).ceil();
          final width =
              ((avail - spacing * (perRow - 1)) / perRow).clamp(46.0, 78.0).toDouble();

          return Padding(
            padding: const EdgeInsets.fromLTRB(6, 9, 6, 9),
            child: Wrap(
              spacing: spacing,
              runSpacing: 6,
              alignment: WrapAlignment.center,
              children: [
                for (final item in items)
                  SizedBox(width: width, child: _pill(item, item.key == current, width)),
              ],
            ),
          );
        }),
      ),
    );
  }

  Widget _pill(FooterItem item, bool selected, double width) {
    final tight = width < 66;
    return GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTap: () => onOpen(item.key),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 170),
        curve: Curves.easeOut,
        padding: const EdgeInsets.symmetric(vertical: 7),
        decoration: BoxDecoration(
          color: selected ? lime : page,
          borderRadius: BorderRadius.circular(18),
          boxShadow: selected
              ? const [BoxShadow(color: Color(0x2E264027), offset: Offset(0, 2), blurRadius: 4)]
              : const [
                  BoxShadow(color: Color(0x33264027), offset: Offset(3, 3), blurRadius: 7),
                  BoxShadow(color: Colors.white, offset: Offset(-3, -3), blurRadius: 7),
                ],
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Stack(
              clipBehavior: Clip.none,
              children: [
                Icon(item.icon, size: tight ? 18 : 21, color: selected ? onLime : muted),
                if (item.badge > 0)
                  Positioned(
                    right: -7,
                    top: -5,
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1),
                      decoration: BoxDecoration(
                          color: selected ? onLime : green,
                          borderRadius: BorderRadius.circular(9)),
                      child: Text('${item.badge}',
                          style: TextStyle(
                              color: selected ? lime : Colors.white,
                              fontSize: 9.5,
                              fontWeight: FontWeight.w800)),
                    ),
                  ),
              ],
            ),
            const SizedBox(height: 4),
            Text(item.label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                    fontSize: tight ? 9 : 10,
                    height: 1.1,
                    fontWeight: selected ? FontWeight.w800 : FontWeight.w600,
                    color: selected ? onLime : muted)),
          ],
        ),
      ),
    );
  }
}
