/// The trainer's own app. A trainer who is not an E-Fundi signs in with their office account and lands
/// here — the teaching papers and the weekly time log, the two screens the web office gives them, without
/// the rest of the desk. An E-Fundi who trains gets the same pages beside their own member screens.
library;

import 'dart:async';

import 'package:flutter/material.dart';
import '../services/api_client.dart';
import '../widgets/ui.dart';
import 'login_screen.dart';
import 'papers/teaching_desk_screen.dart';
import 'time_sheet_screen.dart';

class TrainerShell extends StatefulWidget {
  final ApiClient api;

  /// The name on the sign-in, so the drawer header reads as the account's own.
  final String name;
  const TrainerShell({super.key, required this.api, this.name = ''});

  @override
  State<TrainerShell> createState() => _TrainerShellState();
}

class _TrainerShellState extends State<TrainerShell> with WidgetsBindingObserver {
  final _scaffold = GlobalKey<ScaffoldState>();
  String _page = 'teaching';

  /// Bumping the key rebuilds the page underneath, so Refresh reloads the paper rather than redrawing it.
  int _tick = 0;

  Timer? _idle;
  DateTime _lastTouch = DateTime.now();
  bool _expired = false;

  static const _pages = [
    MemberTile('teaching', 'My teaching', Icons.school_outlined),
    MemberTile('week', 'My time log', Icons.watch_later_outlined),
  ];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _idle = Timer.periodic(const Duration(seconds: 20), (_) => _checkIdle());
  }

  @override
  void dispose() {
    _idle?.cancel();
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) _checkIdle();
  }

  /// The same window the website uses: the server sends idle_minutes, the phone obeys it.
  void _checkIdle() {
    if (_expired) return;
    if (DateTime.now().difference(_lastTouch) < Duration(minutes: widget.api.idleMinutes)) return;
    _expired = true;
    _idle?.cancel();
    widget.api.clearToken();
    if (!mounted) return;
    Navigator.of(context).pushAndRemoveUntil(
      MaterialPageRoute(
        builder: (_) => LoginScreen(
          api: widget.api,
          notice: 'You were signed out after ${widget.api.idleMinutes} minutes without a tap.',
        ),
      ),
      (_) => false,
    );
  }

  Widget _alive(Widget child) => Listener(
        behavior: HitTestBehavior.translucent,
        onPointerDown: (_) => _lastTouch = DateTime.now(),
        child: child,
      );

  Future<void> _logout() async {
    await widget.api.logout();
    if (mounted) {
      Navigator.of(context).pushAndRemoveUntil(
        MaterialPageRoute(builder: (_) => LoginScreen(api: widget.api)),
        (_) => false,
      );
    }
  }

  void _open(String key) {
    setState(() => _page = key);
    _scaffold.currentState?.closeDrawer();
  }

  @override
  Widget build(BuildContext context) {
    final current = _pages.firstWhere((p) => p.key == _page, orElse: () => _pages.first);

    return _alive(Scaffold(
      key: _scaffold,
      drawer: Drawer(
        child: SafeArea(
          child: Column(
            children: [
              Container(
                decoration: const BoxDecoration(
                  gradient: LinearGradient(
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                      colors: heroColors),
                ),
                padding: const EdgeInsets.fromLTRB(18, 18, 18, 16),
                child: Row(
                  children: [
                    CircleAvatar(
                      radius: 23,
                      backgroundColor: Colors.white,
                      foregroundColor: greenDark,
                      child: Text(widget.name.isNotEmpty ? widget.name[0].toUpperCase() : '?',
                          style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 18)),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(widget.name.isEmpty ? 'Trainer' : widget.name,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                  fontWeight: FontWeight.w800, fontSize: 16, color: Colors.white)),
                          const Text('Teaching desk',
                              style: TextStyle(color: Color(0xFFCDE6C6), fontSize: 12)),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              const Divider(height: 1),
              Expanded(
                child: ListView(
                  padding: const EdgeInsets.symmetric(vertical: 6),
                  children: [
                    for (final p in _pages)
                      ListTile(
                        selected: p.key == current.key,
                        selectedTileColor: lime,
                        selectedColor: onLime,
                        shape: const RoundedRectangleBorder(
                            borderRadius: BorderRadius.all(Radius.circular(12))),
                        minVerticalPadding: 6,
                        leading: Icon(p.icon,
                            color: p.key == current.key ? onLime : muted),
                        title: Text(p.label,
                            style: TextStyle(
                                fontWeight: p.key == current.key
                                    ? FontWeight.w800
                                    : FontWeight.w600,
                                color: p.key == current.key ? onLime : ink)),
                        onTap: () => _open(p.key),
                      ),
                    ListTile(
                      leading: const Icon(Icons.logout, color: muted),
                      title: const Text('Log out', style: TextStyle(color: muted)),
                      onTap: _logout,
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
      appBar: AppBar(
        title: Text(current.label),
        actions: [
          IconButton(
            tooltip: 'Refresh',
            icon: const Icon(Icons.refresh),
            onPressed: () => setState(() => _tick++),
          ),
        ],
      ),
      body: _page == 'week'
          ? TimeSheetScreen(key: ValueKey('week-$_tick'), api: widget.api)
          : TeachingDeskScreen(key: ValueKey('teaching-$_tick'), api: widget.api),
    ));
  }
}

class MemberTile {
  final String key;
  final String label;
  final IconData icon;
  const MemberTile(this.key, this.label, this.icon);
}
