import 'package:flutter/material.dart';
import '../models/field_models.dart';
import '../models/member_models.dart';
import '../services/api_client.dart';
import '../widgets/ui.dart';

/// My earnings — the fundi's two income streams kept apart on purpose (§14.6), read-only.
/// Nothing here moves money: staff prepare a payout and a programme executive pays it out.
class EarningsScreen extends StatefulWidget {
  final ApiClient api;
  final Board board;
  const EarningsScreen({super.key, required this.api, required this.board});

  @override
  State<EarningsScreen> createState() => _EarningsScreenState();
}

class _EarningsScreenState extends State<EarningsScreen> {
  EarningsBoard? _data;
  bool _loading = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final b = await widget.api.earnings();
      if (mounted) {
        setState(() {
          _data = b;
          _loading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _error = e is ApiException ? e.message : '$e';
          _loading = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading && _data == null) return const LoadingView();
    if (_error != null && _data == null) {
      return ErrorRetry(message: _error!, onRetry: _load);
    }
    final b = _data!;
    final project = b.payouts.where((p) => p.stream == 'project').toList();
    final training = b.payouts.where((p) => p.stream != 'project').toList();

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(14),
        children: [
          SectionCard(
            title: 'Two kinds of money, kept apart (§14.6)',
            subtitle: 'Project income is your share of a job: the amount agreed for the work, minus '
                'materials the programme supplied, the service commission, the mobilization credit '
                'and any deduction — and it is only released once the hirer has paid the '
                'organisation. Training income is what you are paid for running a cohort as a '
                'trainer. Every payout carries its own ID, which is what a payment is traced by.',
            child: _totals(b),
          ),
          const SectionCard(
            title: 'How a payout reaches you',
            subtitle: 'Nothing here is released by a member — staff prepare it and a programme '
                'executive pays it out. If a payout is stuck, the reason is written on the row.',
            child: SizedBox.shrink(),
          ),
          if (!b.isTrainer)
            const Padding(
              padding: EdgeInsets.only(bottom: 12),
              child: Text('You are not registered as a trainer yet, so no training payout can come '
                  'to your name. A senior fundi trains CTPs — ask the programme office about the '
                  'next intake.',
                  style: TextStyle(color: muted, fontSize: 12.5)),
            ),
          if (b.payouts.isEmpty)
            const Card(
              child: Padding(
                padding: EdgeInsets.all(16),
                child: EmptyHint(
                  icon: Icons.payments_outlined,
                  title: 'No payout is open in your name yet',
                  body: 'One is opened by the office when a project you were awarded is completed, '
                      'or when a cohort you trained reaches its report.',
                ),
              ),
            )
          else ...[
            _streamHeading('Project income', project.length),
            if (project.isEmpty)
              _noneLine('No project payout yet — it opens when a project you were awarded is completed.')
            else
              for (final p in project) _payoutCard(p),
            _streamHeading('Training income', training.length),
            if (training.isEmpty)
              _noneLine('No training payout yet — it opens when a cohort you trained reaches its report.')
            else
              for (final p in training) _payoutCard(p),
          ],
          if (b.ledger.isNotEmpty)
            SectionCard(
              title: 'The same money on the programme register',
              subtitle: 'Every payout posts its own line when the Finance Officer releases it — that '
                  'is the programme’s record of what it paid you, and it should always agree with '
                  'the cards above.',
              child: Column(
                children: [
                  for (final e in b.ledger)
                    RecordRow(
                      title: e.label,
                      subtitle: e.date,
                      meta: e.kind.replaceAll('_', ' '),
                      trailing: Text(
                        e.amount,
                        style: TextStyle(
                          fontWeight: FontWeight.w800,
                          fontSize: 13.5,
                          color: e.isIncome ? green : Colors.red.shade700,
                        ),
                      ),
                    ),
                ],
              ),
            ),
          const Padding(
            padding: EdgeInsets.only(top: 4),
            child: Text('If a figure here is wrong — an agreed amount, a material charge, a '
                'certified count that should be yours — the correction is made by the programme '
                'office on the same payout row, never by deleting it. Ask them to reopen it with '
                'the evidence.',
                style: TextStyle(color: muted, fontSize: 12)),
          ),
        ],
      ),
    );
  }

  Widget _totals(EarningsBoard b) {
    final items = [
      ('Project income paid', b.totals['project'] ?? '—'),
      ('Training income paid', b.totals['training'] ?? '—'),
      ('Calculated, not yet released', b.totals['waiting'] ?? '—'),
    ];
    return Wrap(
      spacing: 8,
      runSpacing: 8,
      children: [
        for (final it in items)
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            decoration: BoxDecoration(
              color: soft,
              borderRadius: BorderRadius.circular(16),
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(it.$2,
                    style: const TextStyle(
                        fontWeight: FontWeight.w800, fontSize: 14, color: green)),
                Text(it.$1, style: const TextStyle(fontSize: 11, color: muted)),
              ],
            ),
          ),
      ],
    );
  }

  Widget _streamHeading(String label, int n) => Padding(
        padding: const EdgeInsets.only(top: 6, bottom: 10),
        child: Text('$label ($n)',
            style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16.5, color: ink)),
      );

  Widget _noneLine(String text) => Card(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Text(text, style: const TextStyle(color: muted, fontSize: 13)),
        ),
      );

  Widget _payoutCard(PayoutLine p) {
    return SectionCard(
      title: p.reference,
      subtitle: [p.streamLabel, p.source].where((x) => x != null && x.isNotEmpty).join(' — '),
      trailing: Tag(p.stageLabel,
          color: p.released ? green : p.rejected ? Colors.red.shade700 : Colors.orange.shade800),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          LabeledValue(
              label: p.stream == 'training' ? 'Payable' : 'Net to you', value: p.net),
          LabeledValue(label: 'Gross', value: p.gross, strong: false),
          if (p.calculation != null && p.calculation!.isNotEmpty)
            LabeledValue(label: 'Calculated as', value: p.calculation!, strong: false),
          if (p.method != null && p.method!.isNotEmpty)
            LabeledValue(label: 'Method', value: p.method!, strong: false),
          if (p.releasedOn != null && p.releasedOn!.isNotEmpty)
            LabeledValue(label: 'Released', value: p.releasedOn!),
          const SizedBox(height: 6),
          for (var i = 0; i < p.path.length; i++)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 2),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(
                    i < p.step ? Icons.check_circle : Icons.circle_outlined,
                    size: 16,
                    color: i < p.step ? green : Colors.grey,
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      p.path[i],
                      style: TextStyle(
                        fontSize: 12.5,
                        color: i < p.step ? ink : Colors.grey.shade600,
                        fontWeight: i == p.step ? FontWeight.w700 : FontWeight.w400,
                      ),
                    ),
                  ),
                  if (i == p.step) Tag('You are here', color: Colors.orange.shade800),
                ],
              ),
            ),
        ],
      ),
    );
  }
}
