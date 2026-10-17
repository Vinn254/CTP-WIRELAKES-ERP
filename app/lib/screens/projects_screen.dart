import 'package:flutter/material.dart';
import '../models/member_models.dart';
import '../services/api_client.dart';
import '../widgets/ui.dart';
import 'project_detail_screen.dart';

/// My projects — every posting the portal shows this member. A CTP reads them all and applies
/// only to an attachment; the level gate is the API's, not the screen's.
class ProjectsScreen extends StatefulWidget {
  final ApiClient api;
  final Board board;
  const ProjectsScreen({super.key, required this.api, required this.board});

  @override
  State<ProjectsScreen> createState() => _ProjectsScreenState();
}

class _ProjectsScreenState extends State<ProjectsScreen> {
  List<ProjectSummary>? _projects;
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
      final p = await widget.api.projects();
      if (mounted) setState(() { _projects = p; _loading = false; });
    } catch (e) {
      if (mounted) setState(() { _error = '$e'; _loading = false; });
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading && _projects == null) return const LoadingView();
    if (_error != null && _projects == null) {
      return ErrorRetry(message: _error!, onRetry: _load);
    }

    final list = _projects!;
    return RefreshIndicator(
      onRefresh: _load,
      child: list.isEmpty
          ? ListView(children: const [
              SizedBox(height: 80),
              EmptyHint(
                  icon: Icons.work_off,
                  title: 'No projects right now',
                  body: 'New postings appear here when the office publishes them.'),
            ])
          : ListView.separated(
              padding: const EdgeInsets.all(14),
              itemCount: list.length,
              separatorBuilder: (_, _) => const SizedBox(height: 10),
              itemBuilder: (_, i) {
                final p = list[i];
                return Card(
                  margin: EdgeInsets.zero,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                  child: InkWell(
                    borderRadius: BorderRadius.circular(16),
                    onTap: () async {
                      await Navigator.of(context).push(MaterialPageRoute(
                          builder: (_) =>
                              ProjectDetailScreen(api: widget.api, projectId: p.id)));
                      _load();
                    },
                    child: Padding(
                      padding: const EdgeInsets.all(15),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Expanded(child: Tag(p.categoryLabel)),
                              if (p.canApply) const Tag('Apply', color: green),
                              if (p.deadline != null) const SizedBox(width: 6),
                            ],
                          ),
                          const SizedBox(height: 9),
                          Text(p.title,
                              style: const TextStyle(
                                  fontWeight: FontWeight.w800, fontSize: 15.5, color: ink)),
                          const SizedBox(height: 4),
                          Text(
                              [p.county, p.ward, p.trade]
                                  .where((x) => x != null && x.isNotEmpty)
                                  .join(' · '),
                              style: const TextStyle(color: muted, fontSize: 13)),
                          if (p.budget != null) ...[
                            const SizedBox(height: 6),
                            Text('Budget KSh ${p.budget}',
                                style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700)),
                          ],
                        ],
                      ),
                    ),
                  ),
                );
              },
            ),
    );
  }
}
