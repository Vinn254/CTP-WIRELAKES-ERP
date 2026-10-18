import 'package:flutter/material.dart';
import '../models/member_models.dart';
import '../services/api_client.dart';
import '../widgets/ui.dart';
import 'member_pages.dart';

/// Overview — the portal home board: who you are, what is waiting on you, and the way into
/// every page of the portal from one grid.
class OverviewScreen extends StatelessWidget {
  final ApiClient api;
  final Board board;
  final ValueChanged<String> onOpen;
  final Future<void> Function() onRefresh;
  const OverviewScreen({
    super.key,
    required this.api,
    required this.board,
    required this.onOpen,
    required this.onRefresh,
  });

  @override
  Widget build(BuildContext context) {
    final m = board.member;
    final c = board.counts;
    final pages = memberPages(m, c, canTrain: m.canTrain)
        .where((p) => p.key != 'overview' && p.key != 'profile')
        .toList();

    final waiting = <(String, int)>[
      if (m.isFundi) ('invitations', c.invites),
      ('work', c.workOrders),
      ('fixes', c.fixes),
      if (m.isFundi) ('tech', c.repairJobs),
      ('applications', c.applications),
    ].where((x) => x.$2 > 0).toList();

    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
        children: [
          Card(
            margin: EdgeInsets.zero,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
            color: green,
            child: Padding(
              padding: const EdgeInsets.all(18),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Hello, ${m.name.split(' ').first}',
                      style: const TextStyle(
                          color: Colors.white, fontSize: 20, fontWeight: FontWeight.w800)),
                  const SizedBox(height: 4),
                  Text('${m.typeLabel} · ${m.level}',
                      style: TextStyle(color: Colors.white.withValues(alpha: 0.85), fontSize: 13)),
                  if (m.code != null) ...[
                    const SizedBox(height: 10),
                    Text('Member code ${m.code}',
                        style: const TextStyle(
                            color: Colors.white, fontSize: 12, fontWeight: FontWeight.w700)),
                  ],
                  const SizedBox(height: 14),
                  Row(
                    children: [
                      _boardPill('${c.projects}', 'projects', 'projects'),
                      const SizedBox(width: 8),
                      _boardPill('${c.workOrders}', 'work orders', 'work'),
                      const SizedBox(width: 8),
                      _boardPill('${c.fixes}', 'to put right', 'fixes'),
                    ],
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 18),
          if (waiting.isNotEmpty) ...[
            const Text('Waiting on you',
                style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: ink)),
            const SizedBox(height: 8),
            Card(
              margin: EdgeInsets.zero,
              child: Column(
                children: [
                  for (final item in waiting)
                    ListTile(
                      dense: true,
                      leading: Badge.count(
                        count: item.$2,
                        backgroundColor: Colors.orange.shade700,
                        child: const Icon(Icons.notifications_outlined, color: green),
                      ),
                      title: Text(_waitingLabel(item.$1, m.isFundi)),
                      trailing: const Icon(Icons.chevron_right, color: muted),
                      onTap: () => onOpen(item.$1),
                    ),
                ],
              ),
            ),
            const SizedBox(height: 18),
          ],
          const Text('The portal',
              style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: ink)),
          const SizedBox(height: 8),
          GridView.count(
            crossAxisCount: 2,
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            childAspectRatio: 1.3,
            crossAxisSpacing: 12,
            mainAxisSpacing: 12,
            children: [
              for (final p in pages)
                Material(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(16),
                  child: InkWell(
                    borderRadius: BorderRadius.circular(16),
                    onTap: () => onOpen(p.key),
                    child: Container(
                      decoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(color: const Color(0xFFE3EDE5)),
                      ),
                      padding: const EdgeInsets.all(14),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Container(
                                width: 38,
                                height: 38,
                                decoration: BoxDecoration(
                                  color: green.withValues(alpha: 0.1),
                                  borderRadius: BorderRadius.circular(12),
                                ),
                                child: Icon(p.icon, size: 20, color: green),
                              ),
                              const Spacer(),
                              if (p.badge > 0)
                                Container(
                                  padding:
                                      const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                                  decoration: BoxDecoration(
                                    color: green,
                                    borderRadius: BorderRadius.circular(20),
                                  ),
                                  child: Text('${p.badge}',
                                      style: const TextStyle(
                                          color: Colors.white,
                                          fontSize: 11,
                                          fontWeight: FontWeight.w800)),
                                ),
                            ],
                          ),
                          const SizedBox(height: 12),
                          Text(p.label,
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                  fontWeight: FontWeight.w800, fontSize: 14, color: ink)),
                          const SizedBox(height: 4),
                          Expanded(
                            child: Text(p.hint,
                                maxLines: 3,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(
                                    fontSize: 11.5, height: 1.25, color: muted)),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _boardPill(String value, String label, String pageKey) => InkWell(
        onTap: () => onOpen(pageKey),
        borderRadius: BorderRadius.circular(12),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: 0.16),
            borderRadius: BorderRadius.circular(12),
          ),
          child: Row(
            children: [
              Text(value,
                  style: const TextStyle(
                      color: Colors.white, fontWeight: FontWeight.w800, fontSize: 16)),
              const SizedBox(width: 6),
              Text(label,
                  style: TextStyle(
                      color: Colors.white.withValues(alpha: 0.9), fontSize: 12)),
            ],
          ),
        ),
      );

  String _waitingLabel(String key, bool isFundi) {
    switch (key) {
      case 'invitations':
        return 'Projects the desk put to you — answer within 48 hours';
      case 'work':
        return isFundi ? 'Work orders to accept, log or finish' : 'Attachment work to keep moving';
      case 'fixes':
        return 'Items an inspector failed that still need your proof';
      case 'tech':
        return 'Repair jobs assigned to you';
      case 'applications':
        return 'Applications the office has yet to decide';
      default:
        return 'Something is waiting';
    }
  }
}
