import 'package:flutter/material.dart';
import '../models/member_models.dart';
import '../services/api_client.dart';
import '../widgets/ui.dart';
import 'project_detail_screen.dart';

/// My applications — what this member put forward and how the office answered.
class ApplicationsScreen extends StatefulWidget {
  final ApiClient api;
  final Board board;
  const ApplicationsScreen({super.key, required this.api, required this.board});

  @override
  State<ApplicationsScreen> createState() => _ApplicationsScreenState();
}

class _ApplicationsScreenState extends State<ApplicationsScreen> {
  List<Application>? _apps;
  bool _loading = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() { _loading = true; _error = null; });
    try {
      final a = await widget.api.applications();
      if (mounted) setState(() { _apps = a; _loading = false; });
    } catch (e) {
      if (mounted) setState(() { _error = '$e'; _loading = false; });
    }
  }

  Future<void> _withdraw(Application a) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('Withdraw application?'),
        content: Text('Stop being considered for “${a.projectTitle ?? 'this project'}”?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Keep it')),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Withdraw')),
        ],
      ),
    );
    if (ok != true) return;
    try {
      await widget.api.withdraw(a.id);
      if (mounted) {
        say(context, 'Withdrawn — the office can see your answer.');
        _load();
      }
    } catch (e) {
      if (mounted) showError(context, e);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading && _apps == null) return const LoadingView();
    if (_error != null && _apps == null) {
      return ErrorRetry(message: _error!, onRetry: _load);
    }

    final list = _apps!;
    return RefreshIndicator(
      onRefresh: _load,
      child: list.isEmpty
          ? ListView(children: const [
              SizedBox(height: 80),
              EmptyHint(
                  icon: Icons.how_to_reg_outlined,
                  title: 'You have not applied yet',
                  body: 'Open a project and apply to see it listed here.'),
            ])
          : ListView.separated(
              padding: const EdgeInsets.all(14),
              itemCount: list.length,
              separatorBuilder: (_, _) => const SizedBox(height: 10),
              itemBuilder: (_, i) {
                final a = list[i];
                return Card(
                  margin: EdgeInsets.zero,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(16),
                    side: cardBorder,
                  ),
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(15, 13, 12, 10),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Expanded(
                              child: Text(a.projectTitle ?? 'Project #${a.projectId}',
                                  style: const TextStyle(
                                      fontWeight: FontWeight.w800, fontSize: 15, color: ink)),
                            ),
                            Tag(a.statusLabel ?? a.status ?? 'Pending',
                                color: a.status == 'approved'
                                    ? green
                                    : a.status == 'rejected'
                                        ? danger
                                        : muted),
                          ],
                        ),
                        if (a.appliedOn != null)
                          Padding(
                            padding: const EdgeInsets.only(top: 4),
                            child: Text('Applied ${a.appliedOn}',
                                style: const TextStyle(color: muted, fontSize: 12.5)),
                          ),
                        if (a.note != null && a.note!.isNotEmpty)
                          Padding(
                            padding: const EdgeInsets.only(top: 6),
                            child: Text(a.note!, style: const TextStyle(fontSize: 13)),
                          ),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.end,
                          children: [
                            if (a.projectId > 0)
                              TextButton(
                                  onPressed: () async {
                                    await Navigator.of(context).push(MaterialPageRoute(
                                        builder: (_) => ProjectDetailScreen(
                                            api: widget.api, projectId: a.projectId)));
                                    _load();
                                  },
                                  child: const Text('Open project')),
                            if (a.canWithdraw)
                              TextButton(
                                  onPressed: () => _withdraw(a),
                                  child: Text('Withdraw',
                                      style: TextStyle(color: danger))),
                          ],
                        ),
                      ],
                    ),
                  ),
                );
              },
            ),
    );
  }
}
