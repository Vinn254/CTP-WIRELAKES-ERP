import 'package:flutter/material.dart';
import '../models/member_models.dart';
import '../models/portal_models.dart';
import '../services/api_client.dart';
import '../widgets/ui.dart';

/// My training — the CTP's own portal page: the seats they hold, the intakes open in and
/// outside their county, and the history of every intake they applied to or left.
class TrainingScreen extends StatefulWidget {
  final ApiClient api;
  final Board board;
  const TrainingScreen({super.key, required this.api, required this.board});

  @override
  State<TrainingScreen> createState() => _TrainingScreenState();
}

class _TrainingScreenState extends State<TrainingScreen> {
  TrainingBoard? _data;
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
      final t = await widget.api.training();
      if (mounted) {
        setState(() {
          _data = t;
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

  Future<void> _apply(CohortSeat c) async {
    final body = await showFormSheet(
      context,
      title: 'Apply for training',
      subtitle: 'Apply for ${c.reference} — ${c.course}? The programme office confirms the seat.',
      fields: [
        FieldSpec('note', 'Why you want this intake (optional)',
            kind: FieldKind.multiline,
            required: false,
            maxLines: 3,
            helper: 'e.g. I have the tools and can attend every day at the school.'),
      ],
      cta: 'Apply for a seat',
    );
    if (body == null) return;
    try {
      final msg = await widget.api.applyCohort(c.id, note: body['note'] as String?);
      if (mounted) say(context, msg);
      _load();
    } catch (e) {
      if (mounted) showError(context, e);
    }
  }

  Future<void> _withdraw(Seat s) async {
    try {
      final msg = await widget.api.withdrawSeat(s.id);
      if (mounted) say(context, msg);
      _load();
    } catch (e) {
      if (mounted) showError(context, e);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading && _data == null) return const LoadingView();
    if (_error != null && _data == null) {
      return ErrorRetry(message: _error!, onRetry: _load);
    }
    final t = _data!;

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(14),
        children: [
          _intro(t),
          if (t.mine.isNotEmpty) ...[
            _heading('Your training, term by term'),
            for (final s in t.mine) _seatCard(s),
          ] else
            _heading('Your training, term by term'),
          if (t.mine.isEmpty)
            const Card(
              child: Padding(
                padding: EdgeInsets.all(16),
                child: EmptyHint(
                  icon: Icons.school_outlined,
                  title: 'No seat yet',
                  body: 'You do not hold a seat right now. Apply for an open intake below — the '
                      'programme office confirms the seat.',
                ),
              ),
            ),
          _intakes('Trainings open in your area', t.nearby, t,
              'These are the intakes the programme is running in your county. Your job as a trainee '
              'is to bring the other CTPs in your area to them — they apply here and the programme '
              'office confirms the seats.'),
          if (t.elsewhere.isNotEmpty)
            _intakes('Open in other areas', t.elsewhere, t,
                'The programme will move you only if the office says so — apply if you can travel there.'),
          if (t.history.isNotEmpty) ...[
            _heading('Intakes you applied to or left'),
            SectionCard(
              title: 'Your history',
              subtitle: 'A declined or withdrawn application never blocks a seat — you can apply '
                  'again while the intake is open.',
              child: Column(
                children: [
                  for (final h in t.history)
                    RecordRow(
                      title: h.course ?? 'Cohort removed',
                      subtitle: [h.cohort, h.period].where((x) => x != null && x.isNotEmpty).join(' · '),
                      meta: h.stateLabel + (h.stageLabel.isEmpty ? '' : ' · ${h.stageLabel}'),
                      trailing: Tag(h.stateLabel,
                          color: h.state == 'approved' ? green : h.state == 'pending' ? Colors.orange.shade800 : muted),
                    ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _intro(TrainingBoard t) {
    return const SectionCard(
      title: 'This is your training record',
      subtitle: 'You apply for an intake that is open, the programme office confirms the seat, and every '
          'session your trainer marks — or the school or the office records on their behalf — lands here. '
          'Nobody changes what has been recorded without you seeing it.',
      child: SizedBox.shrink(),
    );
  }

  Widget _heading(String text) => Padding(
        padding: const EdgeInsets.only(top: 6, bottom: 10),
        child: Text(text,
            style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16.5, color: ink)),
      );

  Widget _bar(String label, int pct) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 5),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                  child: Text(label, style: const TextStyle(color: muted, fontSize: 12.5))),
              Text('$pct%',
                  style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 12.5)),
            ],
          ),
          const SizedBox(height: 4),
          ClipRRect(
            borderRadius: BorderRadius.circular(6),
            child: LinearProgressIndicator(
              value: (pct / 100).clamp(0.0, 1.0),
              minHeight: 7,
              color: green,
              backgroundColor: soft,
            ),
          ),
        ],
      ),
    );
  }

  Widget _seatCard(Seat s) {
    return SectionCard(
      title: s.course ?? 'Training',
      subtitle: [s.cohort, s.period].where((x) => x != null && x.isNotEmpty).join(' · '),
      trailing: Tag(s.stateLabel, color: s.state == 'approved' ? green : Colors.orange.shade800),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.only(bottom: 6),
            child: Wrap(spacing: 6, children: [Tag(s.stageLabel)]),
          ),
          _bar('Your track to 100%', s.trackPct),
          _bar('Attendance', s.attendancePct),
          if (s.canWithdraw)
            Padding(
              padding: const EdgeInsets.only(top: 8),
              child: Align(
                alignment: Alignment.centerLeft,
                child: OutlinedButton(
                  onPressed: () => _withdraw(s),
                  child: const Text('Withdraw from this intake'),
                ),
              ),
            )
          else if (s.stage == 'completed' || s.stage == 'failed')
            const Padding(
              padding: EdgeInsets.only(top: 8),
              child: Text('This run is closed — the record and any certificate stay on your file.',
                  style: TextStyle(color: muted, fontSize: 12.5)),
            ),
        ],
      ),
    );
  }

  Widget _intakes(String title, List<CohortSeat> list, TrainingBoard t, String hint) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _heading('$title (${list.length})'),
        if (!t.canApply)
          SectionCard(
            title: 'Intakes open once your CTP ID is active',
            subtitle: 'Your status is ${t.status}. Training seats are held by active, graduated or '
                'deployed CTPs — finish the steps below and the open intakes become applyable.',
            child: const SizedBox.shrink(),
          )
        else if (list.isEmpty)
          const Card(
            child: Padding(
              padding: EdgeInsets.all(14),
              child: Text('No intake is open right now. The office posts cohorts under '
                  'Training & Certification — at a partner school or in a community it works with.',
                  style: TextStyle(color: muted, fontSize: 13)),
            ),
          )
        else
          for (final c in list) _intakeCard(c, t),
      ],
    );
  }

  Widget _intakeCard(CohortSeat c, TrainingBoard t) {
    return SectionCard(
      title: c.course,
      subtitle: [c.dates, c.place].where((x) => x != null && x.isNotEmpty).join(' · '),
      trailing: Tag(c.reference),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Wrap(
            spacing: 6,
            runSpacing: 6,
            children: [
              if (c.period != null && c.period!.isNotEmpty) Tag(c.period!),
              if (c.seatsLeft != null)
                Tag('${c.seatsLeft} seats left', color: c.seatsLeft! > 0 ? green : Colors.red.shade700),
              if (c.applied) const Tag('Applied', color: green),
            ],
          ),
          if (c.applied)
            const Padding(
              padding: EdgeInsets.only(top: 8),
              child: Text('You have already put yourself forward for this intake — it is in '
                  '“Your training” above.',
                  style: TextStyle(color: muted, fontSize: 12.5)),
            )
          else if (t.canApply && c.isOpen)
            Padding(
              padding: const EdgeInsets.only(top: 10),
              child: FilledButton(
                onPressed: () => _apply(c),
                child: const Text('Apply for a seat'),
              ),
            )
          else if (t.canApply)
            const Padding(
              padding: EdgeInsets.only(top: 8),
              child: Text('This intake is closed for applications.',
                  style: TextStyle(color: muted, fontSize: 12.5)),
            ),
        ],
      ),
    );
  }
}
