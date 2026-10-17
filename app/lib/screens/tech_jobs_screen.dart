import 'package:flutter/material.dart';
import '../models/member_models.dart';
import '../models/work_models.dart';
import '../services/api_client.dart';
import '../widgets/ui.dart';

/// My repair jobs — the technology tickets assigned to this E-Fundi, mirroring
/// web/resources/views/portal/tech-jobs.blade.php. The worker records the fix; only the
/// office confirms and closes the ticket.
class TechJobsScreen extends StatefulWidget {
  final ApiClient api;
  final Board board;
  const TechJobsScreen({super.key, required this.api, required this.board});

  @override
  State<TechJobsScreen> createState() => _TechJobsScreenState();
}

class _TechJobsScreenState extends State<TechJobsScreen> {
  List<TechJob>? _jobs;
  bool _loading = false;
  bool _busy = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final first = _jobs == null;
    if (first) setState(() { _loading = true; _error = null; });
    try {
      final jobs = await widget.api.techJobs();
      if (mounted) setState(() { _jobs = jobs; _loading = false; _error = null; });
    } catch (e) {
      if (!mounted) return;
      if (first) {
        setState(() { _error = '$e'; _loading = false; });
      } else {
        showError(context, e);
      }
    }
  }

  Future<void> _resolve(TechJob job) async {
    final body = await showFormSheet(
      context,
      title: 'Record the fix',
      subtitle: 'Write what you did to fix it — the ticket closes on that record, not a word (§12.7). '
          'The asset returns to service when the fix is recorded.',
      fields: [
        FieldSpec('resolution', 'What you did to fix it',
            kind: FieldKind.multiline,
            maxLines: 4,
            initial: job.resolution,
            helper: 'Exactly the repair, so the office can confirm it'),
      ],
      cta: 'Send the fix',
    );
    final text = body == null ? null : (body['resolution'] ?? '') as String;
    if (text == null || text.isEmpty || !mounted) return;

    setState(() => _busy = true);
    try {
      final message = await widget.api.resolveJob(job.id, text);
      if (!mounted) return;
      setState(() => _busy = false);
      say(context, message);
      await _load();
    } catch (e) {
      if (mounted) {
        setState(() => _busy = false);
        showError(context, e);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading && _jobs == null) return const LoadingView();
    if (_error != null && _jobs == null) {
      return ErrorRetry(message: _error!, onRetry: _load);
    }

    final jobs = _jobs!;
    final waiting = jobs.where((j) => j.status == 'assigned' || j.status == 'in_progress').length;
    final recorded = jobs.where((j) => j.status == 'resolved').length;

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(14, 14, 14, 28),
        children: [
          const Text('My repair jobs',
              style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: ink)),
          const SizedBox(height: 8),
          const Text(
              'When the office opens a fault on a device and assigns it to you, it comes here (§12.7). '
              'You answer it with what you did to fix it. The office then confirms the fix and closes the '
              'ticket — only they can close it, and while it is open the asset stays flagged on the register.',
              style: TextStyle(color: muted, fontSize: 13, height: 1.45)),
          const SizedBox(height: 14),
          _Stats(tiles: <(String, int)>[
            ('Waiting on you', waiting),
            ('Recorded, awaiting confirm', recorded),
          ]),
          const SizedBox(height: 10),
          if (jobs.isEmpty)
            const Card(
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.all(Radius.circular(16))),
              child: EmptyHint(
                icon: Icons.handyman_outlined,
                title: 'No repair job is assigned to you',
                body: 'A technology fault appears here only when the office assigns the ticket to your E-Fundi '
                    'record. Keep your trade and availability up to date, and the office can send the right job '
                    'to you.',
              ),
            )
          else
            for (final job in jobs)
              _JobCard(job: job, busy: _busy, onResolve: () => _resolve(job)),
          const SizedBox(height: 8),
          const SectionCard(
            title: 'How a repair ticket runs (§12.7)',
            child: _Steps(items: [
              ('The office opens the fault', 'on the device or licence it sits on'),
              ('They assign it to you', 'the ticket appears here'),
              ('You record the fix', 'what you did, so it can be checked'),
              ('The office confirms and closes',
                  'only they close a job — your entry marks it fixed, not closed'),
            ]),
          ),
          const Padding(
            padding: EdgeInsets.only(top: 4),
            child: Text(
                'Work on programme projects stays on My work orders — this screen is only for technology '
                'faults assigned to you.',
                style: TextStyle(color: muted, fontSize: 12, height: 1.4)),
          ),
        ],
      ),
    );
  }
}

class _JobCard extends StatelessWidget {
  final TechJob job;
  final bool busy;
  final VoidCallback onResolve;
  const _JobCard({required this.job, required this.busy, required this.onResolve});

  @override
  Widget build(BuildContext context) {
    final resolved = job.status == 'resolved';

    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      padding: const EdgeInsets.fromLTRB(16, 14, 14, 14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border(
          left: BorderSide(color: resolved ? muted : Colors.red.shade700, width: 4),
          top: BorderSide(color: Colors.grey.shade200),
          right: BorderSide(color: Colors.grey.shade200),
          bottom: BorderSide(color: Colors.grey.shade200),
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Text(job.title,
                    style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14.5, color: ink)),
              ),
              Tag(job.priority,
                  color: job.priority.toLowerCase().contains('urgent')
                      ? Colors.red.shade700
                      : Colors.amber.shade800),
            ],
          ),
          Padding(
            padding: const EdgeInsets.only(top: 5),
            child: Text(
              [
                job.asset ?? 'an asset',
                'ticket ${job.reference}',
                job.statusLabel,
              ].join(' · '),
              style: const TextStyle(color: muted, fontSize: 12),
            ),
          ),
          const SizedBox(height: 10),
          Text.rich(
            TextSpan(
              text: 'The fault: ',
              style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: ink),
              children: [
                TextSpan(
                  text: job.fault ?? 'not written',
                  style: const TextStyle(fontWeight: FontWeight.w400, color: muted),
                ),
              ],
            ),
          ),
          const SizedBox(height: 8),
          if (resolved)
            Text(
              'You recorded the fix — "${job.resolution ?? ''}" on ${_day(job.resolvedOn) ?? 'the day'}. '
              'Waiting for the office to confirm and close it.',
              style: const TextStyle(color: muted, fontSize: 12.5, height: 1.4),
            )
          else if (job.canResolve)
            FilledButton.icon(
              onPressed: busy ? null : onResolve,
              icon: const Icon(Icons.build_outlined, size: 18),
              label: const Text('Record the fix'),
            )
          else
            Text(job.statusLabel, style: const TextStyle(color: muted, fontSize: 12.5)),
        ],
      ),
    );
  }
}

class _Stats extends StatelessWidget {
  final List<(String, int)> tiles;
  const _Stats({required this.tiles});

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        for (final t in tiles)
          Expanded(
            child: Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: soft),
              ),
              child: Column(
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

const _months = [
  'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
  'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec',
];

String? _day(String? iso) {
  final d = DateTime.tryParse(iso ?? '');
  if (d == null) return (iso ?? '').isEmpty ? null : iso;
  return '${d.day.toString().padLeft(2, '0')} ${_months[d.month - 1]} ${d.year}';
}
