import 'package:flutter/material.dart';
import '../models/member_models.dart';
import '../services/api_client.dart';
import '../widgets/ui.dart';
import 'applications_screen.dart';
import 'communities_screen.dart';
import 'earnings_screen.dart';
import 'efundi_screen.dart';
import 'ewaste_screen.dart';
import 'fixes_screen.dart';
import 'invitations_screen.dart';
import 'login_screen.dart';
import 'member_pages.dart';
import 'mobilisation_screen.dart';
import 'overview_screen.dart';
import 'procurement_screen.dart';
import 'profile_screen.dart';
import 'projects_screen.dart';
import 'tech_jobs_screen.dart';
import 'trainer_screen.dart';
import 'training_screen.dart';
import 'work_screen.dart';

/// The member's app. One drawer with the portal's own sidebar list, and the page it opens —
/// so a CTP and an E-Fundi each get exactly the screens the website gives them.
class DashboardShell extends StatefulWidget {
  final ApiClient api;
  const DashboardShell({super.key, required this.api});

  @override
  State<DashboardShell> createState() => _DashboardShellState();
}

class _DashboardShellState extends State<DashboardShell> {
  final _scaffold = GlobalKey<ScaffoldState>();
  Board? _board;
  bool _loading = false;
  String? _error;
  String _page = 'overview';

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() { _loading = true; _error = null; });
    try {
      final board = await widget.api.board();
      if (mounted) setState(() { _board = board; _loading = false; });
    } catch (e) {
      if (mounted) setState(() { _error = '$e'; _loading = false; });
    }
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

  void _open(String key) {
    setState(() => _page = key);
    _scaffold.currentState?.closeDrawer();
  }

  @override
  Widget build(BuildContext context) {
    if (_loading && _board == null) return const Scaffold(body: LoadingView());
    if (_error != null && _board == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('CTP-WireLakes')),
        body: ErrorRetry(message: _error!, onRetry: _load),
      );
    }

    final board = _board!;
    final pages = memberPages(board.member, board.counts, canTrain: board.member.canTrain);
    final current = pages.firstWhere((p) => p.key == _page,
        orElse: () => pages.first);

    return Scaffold(
      key: _scaffold,
      drawer: _Drawer(
        board: board,
        pages: pages,
        current: _page,
        onOpen: _open,
        onLogout: _logout,
      ),
      appBar: AppBar(
        title: Text(current.label),
        actions: [
          IconButton(
            tooltip: 'Refresh',
            icon: const Icon(Icons.refresh),
            onPressed: _load,
          ),
        ],
      ),
      body: _body(board, current.key),
    );
  }

  Widget _body(Board board, String key) {
    final api = widget.api;
    switch (key) {
      case 'overview':
        return OverviewScreen(api: api, board: board, onOpen: _open, onRefresh: _load);
      case 'training':
        return TrainingScreen(api: api, board: board);
      case 'mobilisation':
        return MobilisationScreen(api: api, board: board);
      case 'communities':
        return CommunitiesScreen(api: api, board: board);
      case 'efundi':
        return EfundiScreen(api: api, board: board);
      case 'invitations':
        return InvitationsScreen(api: api, board: board);
      case 'trainer':
        return TrainerScreen(api: api, board: board);
      case 'ewaste':
        return EwasteScreen(api: api, board: board);
      case 'work':
        return WorkScreen(api: api, board: board);
      case 'fixes':
        return FixesScreen(api: api, board: board);
      case 'tech':
        return TechJobsScreen(api: api, board: board);
      case 'procurement':
        return ProcurementScreen(api: api, board: board);
      case 'earnings':
        return EarningsScreen(api: api, board: board);
      case 'projects':
        return ProjectsScreen(api: api, board: board);
      case 'applications':
        return ApplicationsScreen(api: api, board: board);
      case 'profile':
        return ProfileScreen(api: api, board: board, onLogout: _logout);
      default:
        return OverviewScreen(api: api, board: board, onOpen: _open, onRefresh: _load);
    }
  }
}

class _Drawer extends StatelessWidget {
  final Board board;
  final List<MemberPage> pages;
  final String current;
  final ValueChanged<String> onOpen;
  final VoidCallback onLogout;
  const _Drawer({
    required this.board,
    required this.pages,
    required this.current,
    required this.onOpen,
    required this.onLogout,
  });

  @override
  Widget build(BuildContext context) {
    final m = board.member;
    return Drawer(
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
                    child: Text(m.name.isNotEmpty ? m.name[0].toUpperCase() : '?',
                        style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 18)),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(m.name,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                                fontWeight: FontWeight.w800, fontSize: 16, color: Colors.white)),
                        Text('${m.navGrade}${m.code != null ? ' · ${m.code}' : ''}',
                            style: TextStyle(
                                color: Colors.white.withValues(alpha: 0.85), fontSize: 12)),
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
                  Padding(
                    padding: const EdgeInsets.fromLTRB(18, 10, 18, 6),
                    child: Text('My pages',
                        style: const TextStyle(
                            color: muted, fontSize: 11, fontWeight: FontWeight.w800,
                            letterSpacing: 0.8)),
                  ),
                  for (final p in pages)
                    if (p.key != 'profile')
                      _item(p, p.key == current, onOpen),
                  Padding(
                    padding: const EdgeInsets.fromLTRB(18, 16, 18, 6),
                    child: Text('Account',
                        style: const TextStyle(
                            color: muted, fontSize: 11, fontWeight: FontWeight.w800,
                            letterSpacing: 0.8)),
                  ),
                  _item(
                      pages.firstWhere((p) => p.key == 'profile',
                          orElse: () => const MemberPage(
                              'profile', 'My profile', Icons.person_outline)),
                      current == 'profile',
                      onOpen),
                  ListTile(
                    leading: const Icon(Icons.logout, color: muted),
                    title: const Text('Log out', style: TextStyle(color: muted)),
                    onTap: onLogout,
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

Widget _item(MemberPage p, bool selected, ValueChanged<String> onOpen) {
  return ListTile(
    selected: selected,
    // The web sidebar marks the page you are on with the bright lime block, so the drawer does the same.
    selectedTileColor: lime,
    selectedColor: onLime,
    shape: const RoundedRectangleBorder(borderRadius: BorderRadius.all(Radius.circular(12))),
    minVerticalPadding: 6,
    leading: Icon(p.icon, color: selected ? onLime : muted),
    title: Text(p.label,
        style: TextStyle(
            fontWeight: selected ? FontWeight.w800 : FontWeight.w600,
            color: selected ? onLime : ink)),
    trailing: p.badge > 0
        ? Container(
            padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 3),
            decoration: BoxDecoration(
              color: selected ? onLime : lime,
              borderRadius: BorderRadius.circular(20),
            ),
            child: Text('${p.badge}',
                style: TextStyle(
                    color: selected ? lime : onLime,
                    fontSize: 11,
                    fontWeight: FontWeight.w800)),
          )
        : null,
    onTap: () => onOpen(p.key),
  );
}
