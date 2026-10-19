import 'package:flutter/material.dart';
import '../models/field_models.dart';
import '../models/member_models.dart';
import '../services/api_client.dart';
import '../widgets/ui.dart';

/// Training CTPs — the senior fundi's teaching role: the sites they are approved at, the
/// cohorts handed to them and the trainee roll they mark. The office approves a trainer
/// site by site; the marks entered here move the trainee's own track.
class TrainerScreen extends StatefulWidget {
  final ApiClient api;
  final Board board;
  const TrainerScreen({super.key, required this.api, required this.board});

  @override
  State<TrainerScreen> createState() => _TrainerScreenState();
}

class _TrainerScreenState extends State<TrainerScreen> {
  TrainerBoard? _data;
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
      final b = await widget.api.trainer();
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

  /// Runs a write, shows the server's sentence unchanged, then reloads the board.
  Future<void> _run(Future<String> Function() action) async {
    try {
      final msg = await action();
      if (mounted) {
        say(context, msg);
      }
      _load();
    } catch (e) {
      if (mounted) showError(context, e);
    }
  }

  Future<void> _applySite(TrainerBoard b) async {
    final body = await showFormSheet(
      context,
      title: 'Apply to train at a site',
      subtitle: 'The office approves a trainer site by site — each site is its own application and '
          'its own approval. A cohort is only handed to a trainer the office approved at that venue.',
      fields: [
        FieldSpec.dropdown('venue_id', 'Site you would teach at',
            {for (final v in b.venues) '${v.id}': v.name}),
        FieldSpec.dropdown('county', 'County', {for (final c in b.counties) c: c}),
        FieldSpec('sub_county', 'Sub-county', required: false),
        FieldSpec('ward', 'Ward', required: false),
        FieldSpec.multichips('trainings', 'What you can teach',
            [for (final t in b.trainings) t.name],
            helper: 'Pick the trainings you can actually deliver — a cohort is only handed to a '
                'trainer listed for it.'),
        FieldSpec('weekly_hours', 'Hours you can give a week',
            kind: FieldKind.integer, required: false, helper: 'e.g. 15'),
        FieldSpec('rate_ksh', 'Rate KSH', kind: FieldKind.number, required: false,
            helper: 'The office holds the rate — leave blank unless told otherwise.'),
        FieldSpec('note', 'What you have actually built or fixed',
            kind: FieldKind.multiline, required: false, maxLines: 3,
            helper: 'Years on the job, the tools you have, sites you have worked.'),
      ],
      cta: 'Send to the programme office',
    );
    if (body == null) return;
    final chosen = (body['trainings'] as List).cast<String>();
    if (chosen.isEmpty) {
      if (mounted) say(context, 'Pick at least one training you can deliver.');
      return;
    }
    final idsByName = {for (final t in b.trainings) t.name: t.id};
    await _run(() => widget.api.applyTrainer({
          'venue_id': int.parse(body['venue_id'] as String),
          'county': body['county'],
          if ((body['sub_county'] as String?)?.isNotEmpty ?? false) 'sub_county': body['sub_county'],
          if ((body['ward'] as String?)?.isNotEmpty ?? false) 'ward': body['ward'],
          'trainings': [for (final n in chosen) idsByName[n]!],
          if ((body['weekly_hours'] as String?)?.isNotEmpty ?? false)
            'weekly_hours': int.tryParse(body['weekly_hours'] as String),
          if ((body['rate_ksh'] as String?)?.isNotEmpty ?? false)
            'rate_ksh': num.tryParse(body['rate_ksh'] as String),
          if ((body['note'] as String?)?.isNotEmpty ?? false) 'note': body['note'],
        }));
  }

  Future<void> _recordProgress(Trainee t, TrainerBoard b) async {
    final body = await showFormSheet(
      context,
      title: 'Record progress',
      subtitle: 'The mark you enter here is what moves ${t.ctp ?? 'this trainee'}’s track — '
          'the office sees the same line.',
      fields: [
        FieldSpec.dropdown('kind', 'What are you marking', b.kinds),
        FieldSpec('entry_date', 'Date of the session', kind: FieldKind.date),
        FieldSpec.dropdown('present', 'Present', {'1': 'Present', '0': 'Absent'}),
        FieldSpec('score', 'Score out of 100', kind: FieldKind.number, required: false),
        FieldSpec('hours', 'Hours', kind: FieldKind.integer, required: false),
        FieldSpec('note', 'Note', kind: FieldKind.multiline, required: false, maxLines: 3),
      ],
      cta: 'Save the mark',
    );
    if (body == null) return;
    await _run(() => widget.api.markProgress({
          'enrollment_id': t.id,
          ...body,
        }));
  }

  Future<void> _addDocument(Trainee t, TrainerBoard b) async {
    final body = await showFormSheet(
      context,
      title: 'Add a document',
      subtitle: 'Uploads sit on the trainee’s own record — the office reads the same file.',
      fields: [
        FieldSpec('title', 'Title'),
        FieldSpec.dropdown('kind', 'What it is', b.documentKinds),
        FieldSpec('dated_on', 'Dated on', kind: FieldKind.date),
        FieldSpec.dropdown(
            'training_module_id', 'Module it belongs to (optional)',
            {for (final m in t.modules) '${m.id}': m.title},
            required: false),
        FieldSpec.photo('file', 'The paper itself',
            photoHelper: 'a photo or scan of the paper, jpg/png, up to 8 MB'),
        FieldSpec('note', 'Note', kind: FieldKind.multiline, required: false, maxLines: 3),
      ],
      cta: 'Upload',
    );
    if (body == null) return;
    final moduleId = body['training_module_id'] as String?;
    await _run(() => widget.api.storeTraineeDocument(t.id, {
          ...body,
          'training_module_id':
              (moduleId == null || moduleId.isEmpty) ? null : int.parse(moduleId),
        }));
  }

  @override
  Widget build(BuildContext context) {
    if (_loading && _data == null) return const LoadingView();
    if (_error != null && _data == null) {
      return ErrorRetry(message: _error!, onRetry: _load);
    }
    final b = _data!;

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(14),
        children: [
          const SectionCard(
            title: 'A senior fundi can train CTPs and be paid for it',
            subtitle: 'Your E-Fundi record stays yours — this page is only the teaching role: the '
                'site you teach at, which of the programme’s trainings you take, the hours you give '
                'and the trainees you mark. The office approves a trainer site by site, so an '
                'approval at one venue is not an approval at another. Training never takes away '
                'your right to apply for open projects.',
            child: SizedBox.shrink(),
          ),
          if (!b.canTrain)
            const SectionCard(
              title: 'Training CTPs',
              subtitle: 'Only a senior fundi trains CTPs — a junior fundi, an intern or an '
                  'attachment applicant works on the job under a senior, and sits the training '
                  'rather than teaching it. When the office moves you up to senior fundi, this '
                  'page opens for a site application.',
              child: SizedBox.shrink(),
            ),
          if (b.sites.isEmpty)
            Card(
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(16),
                side: cardBorder,
              ),
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  children: [
                    const EmptyHint(
                      icon: Icons.school_outlined,
                      title: 'No site approved for you yet',
                      body: 'You are not on the trainer list yet. Pick a site and send it in. The '
                          'office approves a trainer for that site, and only then can a cohort '
                          'booked there be handed to you.',
                    ),
                    if (b.canTrain)
                      FilledButton(
                        onPressed: () => _applySite(b),
                        child: const Text('Ask to train at a site'),
                      ),
                  ],
                ),
              ),
            )
          else
            for (final s in b.sites) _siteCard(s, b),
          if (b.sites.isNotEmpty && b.canTrain)
            Padding(
              padding: const EdgeInsets.only(top: 4),
              child: OutlinedButton.icon(
                onPressed: () => _applySite(b),
                icon: const Icon(Icons.add_location_alt_outlined, size: 18),
                label: const Text('Apply to train at another site'),
              ),
            ),
        ],
      ),
    );
  }

  Widget _siteCard(TrainerSite s, TrainerBoard b) {
    return SectionCard(
      title: s.venue ?? 'A site',
      subtitle: s.place,
      trailing: Tag(s.statusLabel,
          color: s.status == 'approved'
              ? green
              : s.status == 'applied'
                  ? warn
                  : muted),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (s.trainings.isNotEmpty)
            Wrap(spacing: 6, runSpacing: 6, children: [for (final t in s.trainings) Tag(t)]),
          if (s.status == 'applied')
            const Padding(
              padding: EdgeInsets.only(top: 8),
              child: Text('Waiting on the programme office to approve this site. You can mark '
                  'trainees once it is approved.',
                  style: TextStyle(color: muted, fontSize: 12.5)),
            ),
          for (final c in s.cohorts) _cohortCard(c, b),
          if (s.cohorts.isEmpty && s.status == 'approved')
            const Padding(
              padding: EdgeInsets.only(top: 8),
              child: Text('No cohort handed to you yet. The programme office posts a cohort and '
                  'names its trainer — until then your record sits here ready.',
                  style: TextStyle(color: muted, fontSize: 12.5)),
            ),
        ],
      ),
    );
  }

  Widget _cohortCard(TrainerCohort c, TrainerBoard b) {
    return Container(
      margin: const EdgeInsets.only(top: 12),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: soft.withValues(alpha: 0.4),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: green.withValues(alpha: 0.2)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(c.course,
                    style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14, color: ink)),
              ),
              Tag(c.status.replaceAll('_', ' ')),
            ],
          ),
          Padding(
            padding: const EdgeInsets.only(top: 3, bottom: 6),
            child: Text(
                [c.reference, c.period, c.dates]
                    .where((x) => x != null && x.isNotEmpty)
                    .join(' · '),
                style: const TextStyle(color: muted, fontSize: 12.5)),
          ),
          const Text('Your students — open one to record their performance',
              style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: ink)),
          const SizedBox(height: 2),
          Text(
              'Attendance under 80% keeps a trainee out of certification, so mark the days.',
              style: TextStyle(color: muted, fontSize: 12)),
          if (c.trainees.isEmpty)
            const Padding(
              padding: EdgeInsets.only(top: 6),
              child: Text('Nobody is on this roll yet.', style: TextStyle(color: muted, fontSize: 13)),
            )
          else
            for (final t in c.trainees) _traineeTile(t, b),
        ],
      ),
    );
  }

  Widget _traineeTile(Trainee t, TrainerBoard b) {
    return Theme(
      data: Theme.of(context).copyWith(dividerColor: Colors.transparent),
      child: ExpansionTile(
        tilePadding: EdgeInsets.zero,
        childrenPadding: const EdgeInsets.only(bottom: 8),
        title: Text(t.ctp ?? 'Trainee ${t.ctpCode ?? t.id}',
            style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13.5)),
        subtitle: Text(
            [
              if (t.ctpCode != null && t.ctpCode!.isNotEmpty) t.ctpCode!,
              t.stageLabel,
              '${t.modulesMarked}/${t.modulesTotal} modules marked'
            ].join(' · '),
            style: const TextStyle(color: muted, fontSize: 12)),
        trailing: Tag(t.stateLabel, color: t.state == 'approved' ? green : muted),
        children: [
          _pct('Your track to 100%', t.trackPct),
          _pct('Attendance', t.attendancePct),
          const Padding(
            padding: EdgeInsets.only(top: 10, bottom: 4),
            child: Align(
              alignment: Alignment.centerLeft,
              child: Text('Modules',
                  style: TextStyle(fontWeight: FontWeight.w800, fontSize: 13, color: ink)),
            ),
          ),
          if (t.modules.isEmpty)
            const Text('No modules on this training yet.',
                style: TextStyle(color: muted, fontSize: 12.5))
          else
            for (final m in t.modules)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 2),
                child: Row(
                  children: [
                    Icon(m.marked ? Icons.check_circle : Icons.circle_outlined,
                        size: 18, color: m.marked ? green : muted),
                    const SizedBox(width: 8),
                    Expanded(child: Text(m.title, style: const TextStyle(fontSize: 13))),
                    m.marked
                        ? TextButton(
                            onPressed: () => _run(() => widget.api.unmarkModule(t.id, m.id)),
                            child: const Text('Undo'))
                        : TextButton(
                            onPressed: () => _run(() => widget.api.markModule(t.id, m.id, {})),
                            child: const Text('Mark')),
                  ],
                ),
              ),
          if (t.entries.isNotEmpty) ...[
            const Padding(
              padding: EdgeInsets.only(top: 10, bottom: 2),
              child: Align(
                alignment: Alignment.centerLeft,
                child: Text('Sessions marked',
                    style: TextStyle(fontWeight: FontWeight.w800, fontSize: 13, color: ink)),
              ),
            ),
            for (final e in t.entries)
              RecordRow(
                title: e.kind.replaceAll('_', ' '),
                subtitle: e.on,
                meta: [
                  if (e.score != null) 'Score ${e.score}/100',
                  if (e.present != null) (e.present! ? 'Present' : 'Absent'),
                  if (e.note != null && e.note!.isNotEmpty) e.note!,
                ].join(' · '),
              ),
          ],
          if (t.documents.isNotEmpty) ...[
            const Padding(
              padding: EdgeInsets.only(top: 6, bottom: 2),
              child: Align(
                alignment: Alignment.centerLeft,
                child: Text('Documents',
                    style: TextStyle(fontWeight: FontWeight.w800, fontSize: 13, color: ink)),
              ),
            ),
            for (final d in t.documents)
              RecordRow(
                title: '${d['title'] ?? ''}',
                subtitle: '${d['kind'] ?? ''}',
                meta: d['on']?.toString(),
              ),
          ],
          Padding(
            padding: const EdgeInsets.only(top: 8),
            child: Row(
              children: [
                Expanded(
                  child: FilledButton(
                    onPressed: () => _recordProgress(t, b),
                    child: const Text('Record progress'),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: OutlinedButton(
                    onPressed: () => _addDocument(t, b),
                    child: const Text('Add document'),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _pct(String label, int pct) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                  child: Text(label, style: const TextStyle(color: muted, fontSize: 12))),
              Text('$pct%',
                  style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 12)),
            ],
          ),
          const SizedBox(height: 3),
          ClipRRect(
            borderRadius: BorderRadius.circular(6),
            child: LinearProgressIndicator(
              value: (pct / 100).clamp(0.0, 1.0),
              minHeight: 6,
              color: green,
              backgroundColor: Colors.white,
            ),
          ),
        ],
      ),
    );
  }
}
