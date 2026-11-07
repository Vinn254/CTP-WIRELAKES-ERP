/// One trainee's record on the phone — the page the desk reads before it marks. The seat, the marks
/// weighed the way the Registrar set the unit, the track, the modules got through, the paperwork and
/// where certification stands. Every write runs the website's own track writer, so a record typed here
/// is the same record the office and the trainee see.
library;

import 'package:flutter/material.dart';
import '../../models/teaching_models.dart';
import '../../services/api_client.dart';
import '../../widgets/ui.dart';

/// Opens the record from a roll name on the register or a trainee on the marks sheet.
Future<void> openTraineeRecord(BuildContext context, ApiClient api, int enrollmentId) {
  return Navigator.of(context).push(MaterialPageRoute(
    builder: (_) => TraineeRecordScreen(api: api, enrollmentId: enrollmentId),
  ));
}

class TraineeRecordScreen extends StatefulWidget {
  final ApiClient api;
  final int enrollmentId;

  const TraineeRecordScreen({super.key, required this.api, required this.enrollmentId});

  @override
  State<TraineeRecordScreen> createState() => _TraineeRecordScreenState();
}

class _TraineeRecordScreenState extends State<TraineeRecordScreen> {
  TraineeBoard? _board;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final first = _board == null;
    try {
      final board = await widget.api.teachingTrainee(widget.enrollmentId);
      if (!mounted) return;
      setState(() { _board = board; _error = null; });
    } catch (e) {
      if (!mounted) return;
      if (first) {
        setState(() { _error = '$e'; });
      } else {
        showError(context, e);
      }
    }
  }

  Future<void> _run(Future<String> Function() action) async {
    try {
      final message = await action();
      if (mounted) say(context, message);
      await _load();
    } catch (e) {
      if (mounted) showError(context, e);
    }
  }

  Future<void> _addRecord() async {
    final board = _board;
    if (board == null) return;

    final answers = await showFormSheet(
      context,
      title: 'Add a record',
      subtitle: 'One line on ${board.seat.name}’s track — a session, a mark, an attendance day or a school report.',
      cta: board.seat.held ? 'Record and send to the office' : 'Record',
      fields: [
        FieldSpec.dropdown('kind', 'What happened', board.progressKinds),
        FieldSpec('entry_date', 'Date',
            kind: FieldKind.date, initial: _defaultDay(board), helper: _windowHint(board)),
        FieldSpec.dropdown('present', 'Came in? (attendance only)', const {
          '1': 'Yes — present',
          '0': 'No — absent',
        }, required: false),
        FieldSpec('score', 'Theory / practical / assessment score /100',
            kind: FieldKind.number, required: false),
        FieldSpec('hours', 'Hours covered in the session',
            kind: FieldKind.integer, required: false),
        FieldSpec('note', 'What they did',
            kind: FieldKind.multiline, maxLines: 2, required: false,
            helper: 'e.g. Wired the training board end to end'),
      ],
    );
    if (answers == null) return;

    final kind = answers['kind'] as String;
    await _run(() => widget.api.saveTraineeProgress(widget.enrollmentId, {
          'kind': kind,
          'entry_date': answers['entry_date'],
          if (kind == 'attendance' && answers['present'] != null) 'present': answers['present'],
          if ('${answers['score'] ?? ''}'.isNotEmpty) 'score': answers['score'],
          if ('${answers['hours'] ?? ''}'.isNotEmpty) 'hours': answers['hours'],
          if ('${answers['note'] ?? ''}'.trim().isNotEmpty) 'note': answers['note'],
        }));
  }

  Future<void> _markModule(TraineeModule module) async {
    final sure = await confirm(
      context,
      title: 'Mark ${module.title} as got through?',
      body: 'What you mark goes straight on the trainee’s record and the office sees it the same moment.',
      cta: 'Mark done',
    );
    if (sure != true || !mounted) return;
    await _run(() => widget.api.markTraineeModule(widget.enrollmentId, module.id));
  }

  Future<void> _unmarkModule(TraineeModule module) async {
    final sure = await confirm(
      context,
      title: 'Take the mark off ${module.title}?',
      body: 'The track falls back to where it was before this module was marked.',
      cta: 'Undo the mark',
    );
    if (sure != true || !mounted) return;
    await _run(() => widget.api.unmarkTraineeModule(widget.enrollmentId, module.id));
  }

  Future<void> _uploadDocument() async {
    final board = _board;
    if (board == null) return;

    final answers = await showFormSheet(
      context,
      title: 'Upload a document for ${board.seat.name}',
      subtitle: 'It must be dated inside ${board.period ?? 'the period the office set for this training'}.',
      cta: 'Upload to their record',
      fields: [
        FieldSpec('title', 'What it is',
            helper: 'e.g. Term 1 report — Nyanda Technical School'),
        FieldSpec.dropdown('kind', 'Type', board.documentKinds),
        FieldSpec('dated_on', 'Date it covers', kind: FieldKind.date, initial: _defaultDay(board)),
        FieldSpec.dropdown('training_module_id', 'Module it belongs to', {
          '': 'Not tied to one module',
          for (final u in board.units) '${u.id}': u.title,
        }, required: false),
        FieldSpec.photo('file', 'The file',
            required: true, photoHelper: 'A photo or scan of the paper — pdf, word, excel or a photo up to 8 MB.'),
        FieldSpec('note', 'What the office should take from it',
            kind: FieldKind.multiline, maxLines: 2, required: false),
      ],
    );
    if (answers == null) return;
    if (answers['file'] == null) {
      if (mounted) say(context, 'Take a photo of the paper or choose one from the gallery.');
      return;
    }

    await _run(() => widget.api.saveTraineeEvidence(widget.enrollmentId, {
          'title': answers['title'],
          'kind': answers['kind'],
          'dated_on': answers['dated_on'],
          if ('${answers['training_module_id'] ?? ''}'.isNotEmpty)
            'training_module_id': int.parse(answers['training_module_id'] as String),
          'file': answers['file'],
          if ('${answers['note'] ?? ''}'.trim().isNotEmpty) 'note': answers['note'],
        }));
  }

  Future<void> _openDocument(TraineeDocument paper) async {
    try {
      final file = await widget.api.traineeDocumentFile(widget.enrollmentId, paper.id);
      if (mounted) await openDocument(context, file);
    } catch (e) {
      if (mounted) showError(context, e);
    }
  }

  @override
  Widget build(BuildContext context) {
    final board = _board;

    return Scaffold(
      appBar: AppBar(title: Text(board == null ? 'Trainee record' : board.seat.name)),
      body: board == null
          ? (_error != null
              ? ErrorRetry(message: _error!, onRetry: _load)
              : const LoadingView())
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                padding: const EdgeInsets.fromLTRB(14, 12, 14, 28),
                children: [
                  _head(board),
                  if (!board.seat.held)
                    SectionCard(
                      title: 'This seat is ${board.seat.stateLabel.toLowerCase()}',
                      subtitle: 'The programme office confirms a seat before anything can be recorded on it. '
                          'Until then this page stays read-only.',
                      child: const SizedBox.shrink(),
                    ),
                  if (board.seat.attendance != null &&
                      board.seat.attendance! < board.seat.attendanceMin)
                    SectionCard(
                      title: 'Attendance is under the rule',
                      subtitle: 'A trainee under ${board.seat.attendanceMin}% cannot be certified, whatever the '
                          'scores say.',
                      child: Text('${board.seat.daysPresent} of ${board.seat.daysMarked} days marked were attended',
                          style: const TextStyle(fontSize: 13)),
                    ),
                  _marks(board),
                  _track(board),
                  if (board.seat.held) ...[_modules(board), _documents(board)],
                  if (board.cert != null) _certification(board),
                ],
              ),
            ),
    );
  }

  Widget _head(TraineeBoard board) {
    final seat = board.seat;

    return SectionCard(
      title: seat.name,
      subtitle: [
        seat.code,
        board.cohort?.reference,
        board.cohort?.course,
        board.period,
        board.cohort?.place,
      ].where((x) => x != null && x.isNotEmpty).join(' · '),
      trailing: Tag('${_num(seat.track)}% of the track', color: green),
      child: Wrap(
        spacing: 8,
        runSpacing: 8,
        children: [
          Tag(seat.stateLabel, color: seat.held ? green : warn),
          Tag(seat.stageLabel, color: muted),
          Tag(seat.attendance == null
              ? 'attendance not marked'
              : '${_num(seat.attendance)}% attendance', color: muted),
        ],
      ),
    );
  }

  Widget _marks(TraineeBoard board) {
    final plural = board.marks.length == 1 ? 'unit' : 'units';

    return SectionCard(
      title: 'Marks sheet (${board.marks.length} $plural)',
      subtitle: 'Every mark is out of 100 and weighed the way the Registrar set the unit.',
      trailing: board.canWrite
          ? TextButton.icon(
              onPressed: _addRecord,
              icon: const Icon(Icons.add, size: 18),
              label: const Text('Add a record'),
            )
          : null,
      child: board.marks.isEmpty
          ? const Text('This intake has no units registered on it yet, so there is nothing to mark.',
              style: TextStyle(color: muted, fontSize: 12.5, height: 1.4))
          : Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                for (final mark in board.marks) _markCard(mark),
              ],
            ),
    );
  }

  Widget _markCard(TraineeMark mark) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(mark.unit,
                    style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13.5, color: ink)),
              ),
              if (mark.term != null) Tag(mark.term!),
            ],
          ),
          if (mark.note != null)
            Padding(
              padding: const EdgeInsets.only(top: 3),
              child: Text(mark.note!, style: const TextStyle(color: muted, fontSize: 12)),
            ),
          const SizedBox(height: 6),
          for (final part in mark.parts)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 2.5),
              child: Row(
                children: [
                  Expanded(
                      child: Text(part.label,
                          style: const TextStyle(color: muted, fontSize: 12.5))),
                  Text(
                    part.score == null ? '—' : _num(part.score),
                    style: const TextStyle(fontSize: 12.5, fontWeight: FontWeight.w700),
                  ),
                  SizedBox(
                      width: 46,
                      child: Text(' ${part.weight}%',
                          style: const TextStyle(color: muted, fontSize: 11.5))),
                ],
              ),
            ),
          const Divider(height: 16, color: line),
          Row(
            children: [
              Expanded(
                child: Text(mark.marked ? '${_num(mark.pct)} ${mark.letter} · ${mark.remark}' : 'Not marked yet',
                    style: TextStyle(
                        fontSize: 12.5,
                        fontWeight: FontWeight.w800,
                        color: mark.marked ? (mark.passed ? green : danger) : muted)),
              ),
              Text('Pass ${_num(mark.passPct)}%',
                  style: const TextStyle(color: muted, fontSize: 11.5)),
            ],
          ),
          if (mark.marked && mark.onSheet != mark.pct)
            Padding(
              padding: const EdgeInsets.only(top: 2),
              child: Text('${_num(mark.onSheet)}% before attendance',
                  style: const TextStyle(color: muted, fontSize: 11.5)),
            ),
        ],
      ),
    );
  }

  Widget _track(TraineeBoard board) {
    final short = board.parts.where((p) => !p.done).map((p) => p.label).toList();

    return SectionCard(
      title: 'Their track',
      trailing: Tag('${_num(board.seat.track)}%', color: green),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          ClipRRect(
            borderRadius: BorderRadius.circular(8),
            child: LinearProgressIndicator(
              value: (board.seat.track.clamp(0, 100) / 100),
              minHeight: 9,
              backgroundColor: limeSoft,
              valueColor: const AlwaysStoppedAnimation(lime),
            ),
          ),
          const SizedBox(height: 12),
          for (final part in board.parts)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 4),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                      flex: 5,
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(part.label,
                              style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13)),
                          Text(part.detail,
                              style: const TextStyle(color: muted, fontSize: 12)),
                        ],
                      )),
                  SizedBox(
                    width: 66,
                    child: Text('${_num(part.got)} / ${part.weight}',
                        textAlign: TextAlign.right,
                        style: TextStyle(
                            fontSize: 12.5,
                            fontWeight: FontWeight.w800,
                            color: part.done ? green : warn)),
                  ),
                ],
              ),
            ),
          if (board.seat.track >= 100)
            Padding(
              padding: const EdgeInsets.only(top: 8),
              child: Text(
                board.cert == null
                    ? 'Track complete. They go into the certification queue for the office to verify and an '
                        'executive to sign.'
                    : 'Track complete. They are in the certification queue as '
                        '${board.certificateStatus} — an executive signs it and the code is issued.',
                style: const TextStyle(fontSize: 12.5, height: 1.4)),
            )
          else if (short.isNotEmpty)
            Padding(
              padding: const EdgeInsets.only(top: 8),
              child: Text('Still short of 100%: ${short.join(', ')}.',
                  style: const TextStyle(color: muted, fontSize: 12.5, height: 1.4)),
            ),
        ],
      ),
    );
  }

  Widget _modules(TraineeBoard board) {
    final marked = board.modules.where((m) => m.marked).length;

    return SectionCard(
      title: 'Modules under this training ($marked of ${board.modules.length} marked)',
      child: board.modules.isEmpty
          ? const Text('The office has not put any modules under this training — it is not compulsory to have '
              'them. Add them under Training and they appear here and on the trainee’s record straight away.',
              style: TextStyle(color: muted, fontSize: 12.5, height: 1.4))
          : Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                for (final module in board.modules)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Expanded(
                              child: Text('${module.position}. ${module.title}',
                                  style: const TextStyle(
                                      fontWeight: FontWeight.w700, fontSize: 13.5, color: ink)),
                            ),
                            if (module.marked)
                              const Tag('marked', color: green)
                            else
                              const Tag('not marked', color: warn),
                          ],
                        ),
                        if (module.hours != null)
                          Padding(
                            padding: const EdgeInsets.only(top: 2),
                            child: Text(module.hours!,
                                style: const TextStyle(color: muted, fontSize: 12)),
                          ),
                        if (module.markedOn != null)
                          Padding(
                            padding: const EdgeInsets.only(top: 2),
                            child: Text(
                                'marked ${module.markedOn}'
                                '${module.markedBy == null ? '' : ' · ${module.markedBy}'}'
                                '${module.markedNote == null ? '' : ' · “${module.markedNote}”'}',
                                style: const TextStyle(color: muted, fontSize: 12)),
                          ),
                        if (module.outcome != null)
                          Padding(
                            padding: const EdgeInsets.only(top: 2),
                            child: Text('Expected: ${module.outcome}',
                                style: const TextStyle(color: muted, fontSize: 12)),
                          ),
                        if (board.canWrite)
                          Padding(
                            padding: const EdgeInsets.only(top: 6),
                            child: module.marked
                                ? OutlinedButton(
                                    onPressed: () => _unmarkModule(module),
                                    child: const Text('Undo'),
                                  )
                                : FilledButton.icon(
                                    onPressed: () => _markModule(module),
                                    style: FilledButton.styleFrom(
                                        backgroundColor: green, foregroundColor: Colors.white),
                                    icon: const Icon(Icons.check, size: 18),
                                    label: const Text('Mark done'),
                                  ),
                          ),
                      ],
                    ),
                  ),
                if (marked < board.modules.length)
                  const Text('Every module has to be marked before this trainee reaches 100% and can be certified.',
                      style: TextStyle(color: muted, fontSize: 12.5, height: 1.4)),
              ],
            ),
    );
  }

  Widget _documents(TraineeBoard board) {
    return SectionCard(
      title: 'Documents on the record (${board.documents.length})',
      subtitle: 'Kept private and opened from this record.',
      trailing: board.canWrite
          ? TextButton.icon(
              onPressed: _uploadDocument,
              icon: const Icon(Icons.add, size: 18),
              label: const Text('Upload'),
            )
          : null,
      child: board.documents.isEmpty
          ? const Text('Nothing has been uploaded for this trainee yet.',
              style: TextStyle(color: muted, fontSize: 12.5))
          : Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                for (final paper in board.documents)
                  RecordRow(
                    title: paper.title,
                    subtitle: [
                      paper.kindLabel,
                      paper.dated,
                      paper.size,
                      paper.unit,
                    ].where((x) => x != null && x.isNotEmpty).join(' · '),
                    meta: paper.note,
                    trailing: paper.hasFile
                        ? IconButton(
                            tooltip: 'Open ${paper.title}',
                            icon: const Icon(Icons.description_outlined),
                            onPressed: () => _openDocument(paper),
                          )
                        : null,
                  ),
              ],
            ),
    );
  }

  Widget _certification(TraineeBoard board) {
    return SectionCard(
      title: 'Certification',
      subtitle: 'A trainer records what happened. The office verifies the track and a programme executive '
          'signs the certificate.',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            '${board.certificateCode} — ${board.certificateStatus}'
            '${board.certificateReason == null ? '' : ' · ${board.certificateReason}'}',
            style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13),
          ),
        ],
      ),
    );
  }
}

/// A record must fall inside the cohort period, so the form never opens on a date the guard refuses.
String _defaultDay(TraineeBoard board) {
  final today = DateTime.now().toIso8601String().substring(0, 10);
  final starts = board.startsOn;
  final ends = board.endsOn;
  if (starts != null && today.compareTo(starts) < 0) return starts;
  if (ends != null && today.compareTo(ends) > 0) return ends;
  return today;
}

String? _windowHint(TraineeBoard board) =>
    board.period == null ? null : 'Inside the cohort period only — ${board.period}.';

String _num(double? value) {
  if (value == null) return '—';
  return value == value.roundToDouble() ? value.toStringAsFixed(0) : '$value';
}
