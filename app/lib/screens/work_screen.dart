import 'package:flutter/material.dart';
import '../models/member_models.dart';
import '../models/work_models.dart';
import '../services/api_client.dart';
import '../widgets/ui.dart';
import 'work_detail_screen.dart';

/// My work orders / My attachment work — the same page and the same sentences as
/// web/resources/views/portal/work.blade.php.
class WorkScreen extends StatefulWidget {
  final ApiClient api;
  final Board board;
  const WorkScreen({super.key, required this.api, required this.board});

  @override
  State<WorkScreen> createState() => _WorkScreenState();
}

class _WorkScreenState extends State<WorkScreen> {
  WorkBoard? _data;
  bool _loading = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final first = _data == null;
    if (first) setState(() { _loading = true; _error = null; });
    try {
      final data = await widget.api.work();
      if (mounted) setState(() { _data = data; _loading = false; _error = null; });
    } catch (e) {
      if (!mounted) return;
      if (first) {
        setState(() { _error = '$e'; _loading = false; });
      } else {
        showError(context, e);
      }
    }
  }

  Future<void> _open(OrderSummary order) async {
    await Navigator.of(context).push(MaterialPageRoute(
        builder: (_) => WorkDetailScreen(api: widget.api, orderId: order.id)));
    if (mounted) _load();
  }

  @override
  Widget build(BuildContext context) {
    if (_loading && _data == null) return const LoadingView();
    if (_error != null && _data == null) {
      return ErrorRetry(message: _error!, onRetry: _load);
    }

    final data = _data!;
    final isFundi = widget.board.member.isFundi;

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(14, 14, 14, 28),
        children: [
          Text(isFundi ? 'My work orders' : 'My attachment work',
              style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: ink)),
          const SizedBox(height: 8),
          const Text(
              'When the programme gives you a project, it becomes a work order in your name (§10.2). '
              'This is where you sign it, see what the store is giving you, log your days on site, ask for '
              'the completion to be inspected, and answer a hirer who is not happy. Nothing here is typed by '
              'you except your own work — the office records the site visits, the milestones and the money.',
              style: TextStyle(color: muted, fontSize: 13, height: 1.45)),
          const SizedBox(height: 14),
          _Stats(
            tiles: <(String, int)>[
              ('Waiting for your signature', data.waiting),
              ('Working now', data.working),
              ('Questions to answer', data.questions),
              ('Finished or stopped', data.done),
            ],
          ),
          const SizedBox(height: 6),
          if (data.orders.isEmpty)
            const Card(
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.all(Radius.circular(16))),
              child: EmptyHint(
                icon: Icons.assignment_outlined,
                title: 'No work order yet',
                body: 'A work order is generated the moment the programme awards you a project. Two things '
                    'bring one to you: an active record on the E-Fundi register, and the matching desk putting '
                    'a project to you — answer it on Invited to you within the 48 hours.',
              ),
            )
          else
            for (final order in data.orders) _OrderCard(order: order, onOpen: () => _open(order)),
          const SizedBox(height: 8),
          const SectionCard(
            title: 'How a job runs, and who does each part',
            child: _Steps(items: [
              ('You accept and sign the order', '§10.4 step 1 — with the day you start'),
              ('The store issues your materials',
                  '§10.4 step 2 — what they hand over is deducted from your payout'),
              ('You log each day', '§10.4 step 3 — text, photos and the percentage'),
              ('A supervisor signs stages off',
                  '§10.4 step 4 — the split on your order decides the stages'),
              ('You request completion', '§10.4 step 5 — with the final photos'),
              ('The hirer inspects',
                  '§10.4 steps 6–7 — the office records what they say; if they raise an issue you get 48 '
                  'hours to answer'),
              ('Finance pays you',
                  '§10.4 steps 8–12 — the payout appears on My earnings with its own ID'),
            ]),
          ),
        ],
      ),
    );
  }
}

class _OrderCard extends StatelessWidget {
  final OrderSummary order;
  final VoidCallback onOpen;
  const _OrderCard({required this.order, required this.onOpen});

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 14),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: onOpen,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 14, 12, 14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Text('${order.reference} — ${order.project ?? 'your site'}',
                        style: const TextStyle(
                            fontWeight: FontWeight.w800, fontSize: 15, color: ink)),
                  ),
                  const SizedBox(width: 8),
                  Tag(order.statusLabel, color: _pill(order.status)),
                ],
              ),
              const SizedBox(height: 8),
              LabeledValue(
                  label: 'Hirer',
                  value: order.hirer ?? 'the hirer named on the project',
                  strong: false),
              LabeledValue(
                  label: 'Dates',
                  value: '${_day(order.startOn) ?? 'not confirmed'} → ${_day(order.deadline) ?? 'no deadline'}'),
              LabeledValue(label: 'Progress', value: '${order.percent}%'),
              ClipRRect(
                borderRadius: BorderRadius.circular(8),
                child: LinearProgressIndicator(
                  value: order.percent / 100,
                  minHeight: 7,
                  backgroundColor: soft,
                  valueColor: const AlwaysStoppedAnimation(green),
                ),
              ),
              if ((order.next ?? '').isNotEmpty) ...[
                const SizedBox(height: 10),
                Container(
                  padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
                  decoration: BoxDecoration(
                    color: soft,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Icon(Icons.hourglass_empty_outlined, size: 16, color: green),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(order.next!,
                            style: const TextStyle(fontSize: 12.5, color: muted, height: 1.4)),
                      ),
                    ],
                  ),
                ),
              ],
              Align(
                alignment: Alignment.centerRight,
                child: TextButton.icon(
                  onPressed: onOpen,
                  icon: const Icon(Icons.arrow_forward, size: 16),
                  label: const Text('Open the order'),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Stats extends StatelessWidget {
  final List<(String, int)> tiles;
  const _Stats({required this.tiles});

  @override
  Widget build(BuildContext context) {
    return GridView.count(
      crossAxisCount: 2,
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      mainAxisSpacing: 10,
      crossAxisSpacing: 10,
      childAspectRatio: 2.4,
      children: [
        for (final t in tiles)
          Container(
            padding: const EdgeInsets.all(11),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: soft),
            ),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('${t.$2}',
                    style: const TextStyle(
                        fontSize: 21, fontWeight: FontWeight.w800, color: green)),
                Text(t.$1,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(fontSize: 11.5, color: muted, height: 1.25)),
              ],
            ),
          ),
      ],
    );
  }
}

class _Steps extends StatelessWidget {
  final List<(String, String)> items;
  const _Steps({required this.items});

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        for (var n = 0; n < items.length; n++)
          Padding(
            padding: const EdgeInsets.only(bottom: 10),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 24,
                  height: 24,
                  alignment: Alignment.center,
                  decoration: const BoxDecoration(color: soft, shape: BoxShape.circle),
                  child: Text('${n + 1}',
                      style: const TextStyle(
                          fontWeight: FontWeight.w800, fontSize: 12, color: green)),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(items[n].$1,
                          style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13.5)),
                      Padding(
                        padding: const EdgeInsets.only(top: 2),
                        child: Text(items[n].$2,
                            style: const TextStyle(color: muted, fontSize: 12, height: 1.35)),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
      ],
    );
  }
}

Color _pill(String status) => switch (status) {
      'issued' || 'accepted' => Colors.amber.shade800,
      'in_progress' || 'completion_submitted' => green,
      'confirmed' || 'closed' => const Color(0xFF0E6B3A),
      'disputed' || 'cancelled' => Colors.red.shade700,
      _ => muted,
    };

const _months = [
  'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
  'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec',
];

String? _day(String? iso) {
  final d = DateTime.tryParse(iso ?? '');
  if (d == null) return (iso ?? '').isEmpty ? null : iso;
  return '${d.day.toString().padLeft(2, '0')} ${_months[d.month - 1]} ${d.year}';
}
