/// The unit marks sheet on the phone: the sheets already written for the class, and the grid the
/// trainer fills — one row per trainee, a box for each part the Registrar weights.
library;

import 'package:flutter/material.dart';
import '../../models/teaching_models.dart';
import '../../services/api_client.dart';
import '../../widgets/ui.dart';
import '../../widgets/week_picker.dart';
import 'trainee_screen.dart';

class MarksTab extends StatefulWidget {
  final ApiClient api;
  final int cohortId;
  const MarksTab({super.key, required this.api, required this.cohortId});

  @override
  State<MarksTab> createState() => _MarksTabState();
}

class _MarksTabState extends State<MarksTab> {
  MarksBoard? _board;
  bool _loading = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final first = _board == null;
    if (first) setState(() { _loading = true; _error = null; });
    try {
      final board = await widget.api.teachingMarks(widget.cohortId);
      if (!mounted) return;
      setState(() { _board = board; _loading = false; _error = null; });
    } catch (e) {
      if (!mounted) return;
      if (first) {
        setState(() { _error = '$e'; _loading = false; });
      } else {
        showError(context, e);
      }
    }
  }

  Future<void> _forward(MarksSheet sheet) async {
    final sure = await confirm(
      context,
      title: 'Send these marks to the Registrar?',
      body: 'A sheet with the Registrar is counted into the trainees’ records there — marks already '
          'handed in are corrected by them, not here.',
      cta: 'Send them',
    );
    if (sure != true || !mounted) return;
    try {
      final message = await widget.api.forwardMarks(widget.cohortId, sheet.paper.id);
      if (mounted) say(context, message);
      await _load();
    } catch (e) {
      if (mounted) showError(context, e);
    }
  }

  Future<void> _open({int? unitId, String? coveredOn}) async {
    final board = _board;
    if (board == null) return;

    final answers = await showFormSheet(
      context,
      title: 'A sheet for one unit',
      subtitle: 'A sheet is one unit taken on one day. The marks you put on it are counted under the '
          'weights the Registrar set for that unit.',
      cta: 'Open the sheet',
      fields: [
        FieldSpec.dropdown('training_module_id', 'Unit',
            {for (final u in board.units) '${u.id}': u.title},
            initial: unitId == null ? null : '$unitId'),
        FieldSpec('covered_on', 'Day the unit was taken',
            kind: FieldKind.date,
            initial: coveredOn ?? DateTime.now().toIso8601String().substring(0, 10)),
      ],
    );
    if (answers == null || !mounted) return;

    await Navigator.of(context).push(MaterialPageRoute(
      builder: (_) => MarksSheetScreen(
        api: widget.api,
        cohortId: widget.cohortId,
        units: board.units,
        kinds: board.kinds,
        outOf: board.outOf,
        canCorrect: board.canCorrect,
        unitId: int.parse(answers['training_module_id'] as String),
        coveredOn: answers['covered_on'] as String,
      ),
    ));
    await _load();
  }

  @override
  Widget build(BuildContext context) {
    if (_loading && _board == null) return const LoadingView();
    if (_error != null && _board == null) return ErrorRetry(message: _error!, onRetry: _load);
    final board = _board;
    if (board == null) return const LoadingView();

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(14, 12, 14, 28),
        children: [
          SectionCard(
            title: 'Marks sheets on ${board.cohort.reference}',
            subtitle: '${board.sheets.length} ${board.sheets.length == 1 ? 'sheet' : 'sheets'} · '
                '${board.roll.length} trainees on the roll',
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('Each part is marked out of ${_asNum(board.outOf)} — theory, practical, assignment '
                    'and the class test. The Registrar sets what each part is worth on the unit.',
                    style: const TextStyle(color: muted, fontSize: 12.5, height: 1.4)),
                const SizedBox(height: 10),
                FilledButton.icon(
                  onPressed: () => _open(),
                  style: FilledButton.styleFrom(
                      backgroundColor: green, foregroundColor: Colors.white),
                  icon: const Icon(Icons.rate_review_outlined, size: 18),
                  label: Text(board.sheets.isEmpty ? 'Open a sheet to write' : 'A sheet for another unit'),
                ),
              ],
            ),
          ),
          if (board.sheets.isEmpty)
            const EmptyHint(
              icon: Icons.fact_check_outlined,
              title: 'No marks sheet yet',
              body: 'Open a sheet for the unit you took, put the marks in, and send it to the Registrar.',
            )
          else
            for (final sheet in board.sheets) _card(sheet, board),
        ],
      ),
    );
  }

  Widget _card(MarksSheet sheet, MarksBoard board) {
    final status = sheet.paper.status;

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(sheet.unit,
                      style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14, color: ink)),
                ),
                Tag(sheet.paper.statusLabel,
                    color: status == 'submitted'
                        ? warn
                        : sheet.paper.editable
                            ? muted
                            : green),
              ],
            ),
            Padding(
              padding: const EdgeInsets.only(top: 3),
              child: Text('${prettyDay(sheet.coveredOn)} · ${sheet.written} '
                  '${sheet.written == 1 ? 'mark' : 'marks'} written',
                  style: const TextStyle(color: muted, fontSize: 12.5)),
            ),
            if (!sheet.paper.editable && sheet.paper.reviewNote != null)
              Padding(
                padding: const EdgeInsets.only(top: 4),
                child: Text('The Registrar: ${sheet.paper.reviewNote}',
                    style: const TextStyle(color: danger, fontSize: 12.5)),
              ),
            Padding(
              padding: const EdgeInsets.only(top: 10),
              child: Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  if (sheet.paper.editable || board.canCorrect)
                    FilledButton.icon(
                      onPressed: () => _open(unitId: sheet.unitId, coveredOn: sheet.coveredOn),
                      style: FilledButton.styleFrom(
                          backgroundColor: green, foregroundColor: Colors.white,
                          textStyle: const TextStyle(fontSize: 13)),
                      icon: const Icon(Icons.edit_outlined, size: 18),
                      label: Text(sheet.paper.editable ? 'Write the marks' : 'Correct the marks'),
                    ),
                  if (sheet.paper.editable)
                    OutlinedButton.icon(
                      onPressed: () => _forward(sheet),
                      icon: const Icon(Icons.send_outlined, size: 18),
                      label: const Text('Send to the Registrar'),
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

/// The grid itself: one card per trainee, a box for every part, and the Registrar's weights read
/// beside them so the trainer knows what each part is worth before sending it.
class MarksSheetScreen extends StatefulWidget {
  final ApiClient api;
  final int cohortId;
  final List<TaughtUnit> units;
  final Map<String, String> kinds;
  final double outOf;
  final bool canCorrect;
  final int unitId;
  final String coveredOn;

  const MarksSheetScreen({
    super.key,
    required this.api,
    required this.cohortId,
    required this.units,
    required this.kinds,
    required this.outOf,
    required this.canCorrect,
    required this.unitId,
    required this.coveredOn,
  });

  @override
  State<MarksSheetScreen> createState() => _MarksSheetScreenState();
}

class _MarksSheetScreenState extends State<MarksSheetScreen> {
  MarksBoard? _board;
  MarksSheet? _sheet;
  final _boxes = <String, TextEditingController>{};
  final _note = TextEditingController();
  bool _loading = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    for (final c in _boxes.values) {
      c.dispose();
    }
    _note.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() { _loading = true; _error = null; });
    try {
      final board = await widget.api.teachingMarks(widget.cohortId,
          unitId: widget.unitId, coveredOn: widget.coveredOn);
      if (!mounted) return;
      final sheet = board.sheet ??
          MarksSheet(
              paper: const TeachingPaper(
                  id: 0, status: 'draft', statusLabel: 'Draft', editable: true),
              unit: board.units.firstWhere((u) => u.id == widget.unitId,
                      orElse: () => TaughtUnit(id: widget.unitId, title: 'The unit'))
                  .title,
              dateLabel: widget.coveredOn,
              marks: const [],
              written: 0);
      setState(() {
        _board = board;
        _sheet = sheet;
        _note.text = sheet.paper.note ?? '';
        for (final seat in board.roll) {
          for (final kind in board.kinds.keys) {
            final key = '${seat.id}|$kind';
            _boxes[key] ??= TextEditingController(
                text: _shown(sheet.score(seat.id, kind)));
          }
        }
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() { _error = '$e'; _loading = false; });
    }
  }

  Future<void> _save({required bool forward}) async {
    if (_loading) return;
    setState(() => _loading = true);
    final marks = <String, dynamic>{};
    for (final seat in _board!.roll) {
      final row = <String, dynamic>{};
      for (final kind in _board!.kinds.keys) {
        final text = _boxes['${seat.id}|$kind']?.text.trim() ?? '';
        if (text.isNotEmpty) row[kind] = text;
      }
      if (row.isNotEmpty) marks['${seat.id}'] = row;
    }

    try {
      final message = await widget.api.saveMarks(widget.cohortId, {
        'training_module_id': widget.unitId,
        'covered_on': widget.coveredOn,
        if (_note.text.trim().isNotEmpty) 'note': _note.text.trim(),
        'marks': marks,
        if (forward) 'forward': true,
      });
      if (!mounted) return;
      setState(() => _loading = false);
      say(context, message);
      if (forward) Navigator.pop(context);
    } catch (e) {
      if (!mounted) return;
      setState(() => _loading = false);
      showError(context, e);
    }
  }

  @override
  Widget build(BuildContext context) {
    final board = _board;
    final sheet = _sheet;

    return Scaffold(
      appBar: AppBar(title: Text(sheet?.unit ?? 'Marks sheet')),
      body: board == null || sheet == null
          ? (_error != null
              ? ErrorRetry(message: _error!, onRetry: _load)
              : const LoadingView())
          : ListView(
              padding: const EdgeInsets.fromLTRB(14, 12, 14, 28),
              children: [
                SectionCard(
                  title: prettyDay(widget.coveredOn),
                  subtitle: '${sheet.written} ${sheet.written == 1 ? 'mark' : 'marks'} on file · '
                      '${sheet.paper.statusLabel}',
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('Every part is out of ${_asNum(board.outOf)}. '
                          'A box you leave empty is left as it stands.',
                          style: const TextStyle(color: muted, fontSize: 12.5, height: 1.4)),
                      if (board.grading != null) ...[
                        const SizedBox(height: 10),
                        Wrap(
                          spacing: 8,
                          runSpacing: 8,
                          children: [
                            for (final entry in board.grading!.weights.entries)
                              Tag('${_label(board.kinds, entry.key)} ${entry.value}%'),
                            Tag('Pass ${_asNum(board.grading!.passPct)}%', color: green),
                          ],
                        ),
                        if (board.grading!.note != null)
                          Padding(
                            padding: const EdgeInsets.only(top: 8),
                            child: Text(board.grading!.note!,
                                style: const TextStyle(fontSize: 12.5, height: 1.4)),
                          ),
                      ],
                      if (!sheet.paper.editable && !board.canCorrect)
                        Padding(
                          padding: const EdgeInsets.only(top: 8),
                          child: Text('This sheet is ${sheet.paper.statusLabel.toLowerCase()} — the Registrar '
                              'answers it, not the trainer.',
                              style: const TextStyle(color: danger, fontSize: 12.5)),
                        ),
                    ],
                  ),
                ),
                if (board.roll.isEmpty)
                  const EmptyHint(
                    icon: Icons.group_outlined,
                    title: 'Nobody is on the roll',
                    body: 'The office seats a trainee on the cohort before marks can be written.',
                  )
                else
                  for (final seat in board.roll)
                    SectionCard(
                      title: seat.name,
                      subtitle: seat.reference,
                      trailing: TextButton(
                        onPressed: () => openTraineeRecord(context, widget.api, seat.id),
                        child: const Text('Record'),
                      ),
                      child: Column(
                        children: [
                          for (final kind in board.kinds.keys)
                            Padding(
                              padding: const EdgeInsets.only(bottom: 8),
                              child: Row(
                                children: [
                                  Expanded(
                                    child: Text(board.kinds[kind]!,
                                        style: const TextStyle(fontSize: 13)),
                                  ),
                                  SizedBox(
                                    width: 92,
                                    child: TextField(
                                      controller: _boxes['${seat.id}|$kind'],
                                      enabled: sheet.paper.editable || board.canCorrect,
                                      keyboardType: const TextInputType.numberWithOptions(decimal: true),
                                      textAlign: TextAlign.center,
                                      decoration: InputDecoration(
                                          isDense: true,
                                          suffixText: '/ ${_asNum(board.outOf)}'),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                        ],
                      ),
                    ),
                SectionCard(
                  title: 'Note on the sheet',
                  child: TextField(
                    controller: _note,
                    maxLines: 2,
                    enabled: sheet.paper.editable || board.canCorrect,
                    decoration: const InputDecoration(isDense: true),
                  ),
                ),
                if (sheet.paper.editable || board.canCorrect) ...[
                  FilledButton.icon(
                    onPressed: () => _save(forward: false),
                    style: FilledButton.styleFrom(
                        backgroundColor: green, foregroundColor: Colors.white,
                        minimumSize: const Size.fromHeight(48)),
                    icon: const Icon(Icons.save_outlined),
                    label: const Text('Keep the marks'),
                  ),
                  const SizedBox(height: 8),
                  OutlinedButton.icon(
                    onPressed: () => _save(forward: true),
                    icon: const Icon(Icons.send_outlined, color: green),
                    label: const Text('Keep and send to the Registrar',
                        style: TextStyle(color: greenDark)),
                  ),
                ],
              ],
            ),
    );
  }
}

String _asNum(double value) =>
    value == value.roundToDouble() ? value.toStringAsFixed(0) : '$value';

/// An empty box means "leave it as it stands", so a null score opens as nothing typed.
String _shown(double? value) => value == null ? '' : _asNum(value);

String _label(Map<String, String> kinds, String key) => kinds[key] ?? key;
