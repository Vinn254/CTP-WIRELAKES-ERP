/// The trainer's own app. A trainer who is not an E-Fundi signs in with their office account and lands
/// here — the teaching papers, the Registrar's shelf and the weekly time log, the pages the web desk gives them.
/// The bar along the foot carries the pages a class day runs on; *More* opens the sidebar for the rest, so no
/// page is ever more than two presses away and none is behind a card you have to open first.
library;

import 'dart:async';

import 'package:flutter/material.dart';
import '../models/teaching_models.dart';
import '../services/api_client.dart';
import '../widgets/footer_nav.dart';
import '../widgets/ui.dart';
import 'leave_screen.dart';
import 'login_screen.dart';
import 'papers/cohort_papers_screen.dart';
import 'papers/teaching_desk_screen.dart';
import 'staff_profile_screen.dart';
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

  /// The class the five papers are written on. One class makes it automatic; several are chosen on My teaching.
  TeachingDeskBoard? _board;
  int? _cohortId;
  String _cohortLabel = '';

  /// Bumping the key rebuilds the page underneath, so Refresh reloads the paper rather than redrawing it.
  int _tick = 0;

  Timer? _idle;
  DateTime _lastTouch = DateTime.now();
  bool _expired = false;

  /// The pages the web desk gives this account. The bar carries the five a class day runs on and the shelf;
  /// *More* opens the sidebar, which lists every one of them. My profile only shows for an office account —
  /// an E-Fundi who trains keeps the member profile their own portal holds.
  List<FooterItem> get _footer => [
        const FooterItem('teaching', 'My teaching', Icons.school_outlined),
        for (final key in ['register', 'marks', 'timetable', 'library', 'lessons', 'materials'])
          paperPages.firstWhere((p) => p.key == key),
        const FooterItem('week', 'Time log', Icons.watch_later_outlined),
        const FooterItem('leave', 'My leave', Icons.weekend_outlined),
        if (_board?.staff ?? widget.api.isStaff)
          const FooterItem('profile', 'My profile', Icons.badge_outlined),
      ];

  static const _paperKeys = {'register', 'marks', 'timetable', 'lessons', 'materials'};

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _idle = Timer.periodic(const Duration(seconds: 20), (_) => _checkIdle());
    _loadDesk();
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

  Future<void> _loadDesk() async {
    try {
      final board = await widget.api.teachingDesk();
      if (!mounted) return;
      setState(() {
        _board = board;
        // A single class needs no choosing; the bar should open straight onto its papers.
        if (_cohortId == null && board.cohorts.length == 1) _choose(board.cohorts.first);
      });
    } catch (_) {
      // My teaching reports the failure in its own words; the bar still holds every other page.
    }
  }

  void _choose(TaughtCohort cohort) {
    setState(() {
      _cohortId = cohort.id;
      _cohortLabel = cohort.reference;
    });
  }

  void _open(String key) {
    if (_paperKeys.contains(key) && _cohortId == null) {
      final only = _board?.cohorts ?? const [];
      if (only.length == 1) {
        _choose(only.first);
      } else {
        setState(() => _page = 'teaching');
        if (mounted) {
          say(context, only.isEmpty
              ? 'No class is handed to you yet, so there is nothing to write papers on.'
              : 'Pick the class you are working on, then press the paper again.');
        }
        return;
      }
    }
    setState(() => _page = key);
    _scaffold.currentState?.closeDrawer();
  }

  Future<void> _logout() async {
    await widget.api.logout();
    if (mounted) {
      Navigator.of(context).pushAndRemoveUntil(
        MaterialPageRoute(builder: (_) => LoginScreen(api: widget.api)),
        (_) => false,
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final current = _footer.firstWhere((p) => p.key == _page, orElse: () => _footer.first);
    final onPaper = _paperKeys.contains(_page);

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
                    for (final p in _footer)
                      ListTile(
                        selected: p.key == current.key,
                        selectedTileColor: lime,
                        selectedColor: onLime,
                        shape: const RoundedRectangleBorder(
                            borderRadius: BorderRadius.all(Radius.circular(12))),
                        minVerticalPadding: 6,
                        leading: Icon(p.icon, color: p.key == current.key ? onLime : muted),
                        title: Text(p.label,
                            style: TextStyle(
                                fontWeight: p.key == current.key ? FontWeight.w800 : FontWeight.w600,
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
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(current.label),
            if (onPaper && _cohortLabel.isNotEmpty)
              Text(_cohortLabel,
                  style: const TextStyle(fontSize: 11.5, color: Color(0xFFCDE6C6))),
          ],
        ),
        actions: [
          IconButton(
            tooltip: 'Refresh',
            icon: const Icon(Icons.refresh),
            onPressed: () {
              setState(() => _tick++);
              _loadDesk();
            },
          ),
        ],
      ),
      body: switch (_page) {
        'week' => TimeSheetScreen(key: ValueKey('week-$_tick'), api: widget.api),
        'leave' => LeaveScreen(api: widget.api, staff: true),
        'profile' => StaffProfileScreen(key: ValueKey('profile-$_tick'), api: widget.api),
        'teaching' => TeachingDeskScreen(
            key: ValueKey('teaching-$_tick'),
            api: widget.api,
            board: _board,
            selectedId: _cohortId,
            onLoaded: (loaded) => setState(() => _board = loaded),
            onSelect: (cohort) {
              _choose(cohort);
              setState(() => _page = 'register');
            },
          ),
        _ => paperBody(_page, api: widget.api, cohortId: _cohortId),
      },
      bottomNavigationBar: FooterNav(
        items: _footer,
        current: _page,
        onOpen: _open,
        onMore: () => _scaffold.currentState?.openDrawer(),
      ),
    ));
  }
}
