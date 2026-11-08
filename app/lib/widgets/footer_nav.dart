/// The app's bottom navigation: a soft raised bar along the foot of the screen. Each page is a moulded pill
/// sitting on the same surface it is cut from — lit on top-left, shadowed bottom-right — and the page you are on
/// presses in and takes the brand's lime. The bar carries the pages a phone can show at a comfortable size;
/// when a desk has more than that, the rest wait behind *More*, which opens the shell's own sidebar.
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

  /// A desk with more pages than the bar can carry passes this: the tail opens the sidebar instead of
  /// shrinking into unreadable slivers.
  final VoidCallback? onMore;

  const FooterNav({
    super.key,
    required this.items,
    required this.current,
    required this.onOpen,
    this.onMore,
  });

  /// The narrowest a pill can be and still read on a phone, and the gap between pills.
  static const _slot = 57.0;
  static const _spacing = 6.0;

  @override
  Widget build(BuildContext context) {
    return Container(
      color: page,
      child: SafeArea(
        top: false,
        child: LayoutBuilder(builder: (context, box) {
          final avail = box.maxWidth - 12;
          final slots = ((avail + _spacing) / (_slot + _spacing)).floor().clamp(2, 8);
          final more = onMore != null && items.length + 1 > slots;
          final shown = more ? items.take(slots - 1).toList() : items;
          final tucked = more && !shown.any((item) => item.key == current);

          final cells = <Widget>[
            for (final item in shown) _pill(item, item.key == current),
            if (more) _more(tucked),
          ];

          final rows = cells.length > slots ? 2 : 1;
          final perRow = (cells.length / rows).ceil();
          final width =
              ((avail - _spacing * (perRow - 1)) / perRow).clamp(46.0, 78.0).toDouble();

          return Padding(
            padding: const EdgeInsets.fromLTRB(6, 9, 6, 9),
            child: Wrap(
              spacing: _spacing,
              runSpacing: 6,
              alignment: WrapAlignment.center,
              children: [
                for (final cell in cells) SizedBox(width: width, child: cell),
              ],
            ),
          );
        }),
      ),
    );
  }

  Widget _pill(FooterItem item, bool selected) => _moulded(
        selected: selected,
        onTap: () => onOpen(item.key),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Stack(
              clipBehavior: Clip.none,
              children: [
                Icon(item.icon, size: 21, color: selected ? onLime : muted),
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
                    fontSize: 10,
                    height: 1.1,
                    fontWeight: selected ? FontWeight.w800 : FontWeight.w600,
                    color: selected ? onLime : muted)),
          ],
        ),
      );

  Widget _more(bool selected) => _moulded(
        selected: selected,
        onTap: onMore!,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.view_module_outlined, size: 21, color: selected ? onLime : muted),
            const SizedBox(height: 4),
            Text('More',
                maxLines: 1,
                style: TextStyle(
                    fontSize: 10,
                    height: 1.1,
                    fontWeight: selected ? FontWeight.w800 : FontWeight.w600,
                    color: selected ? onLime : muted)),
          ],
        ),
      );

  Widget _moulded(
          {required bool selected,
          required VoidCallback onTap,
          required Widget child}) =>
      GestureDetector(
        behavior: HitTestBehavior.opaque,
        onTap: onTap,
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
          child: child,
        ),
      );
}
