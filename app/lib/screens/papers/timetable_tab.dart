/// The class week the trainer keeps: the cohort's own timetable, written whole, with the
/// Registrar's programme week read beside it.
library;

import 'package:flutter/material.dart';
import '../../models/teaching_models.dart';
import '../../services/api_client.dart';
import '../../widgets/ui.dart';

class TimetableTab extends StatefulWidget {
  final ApiClient api;
  final int cohortId;
  const TimetableTab({super.key, required this.api, required this.cohortId});

  @override
  State<TimetableTab> createState() => _TimetableTabState();
}

class _TimetableTabState extends State<TimetableTab> {
  TimetableBoard? _board;
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
      final board = await widget.api.teachingTimetable(widget.cohortId);
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

  Future<void> _write() async {
    final board = _board;
    if (board == null) return;
    await Navigator.of(context).push(MaterialPageRoute(
      builder: (_) => TimetableEditorScreen(api: widget.api, cohortId: widget.cohortId, board: board),
    ));
    await _load();
  }

  Future<void> _forward(CohortTimetable paper) async {
    final sure = await confirm(
      context,
      title: 'Send the week to the Registrar?',
      body: 'The register for each class is built from this week, so it stops here until the Registrar answers it.',
      cta: 'Send the week',
    );
    if (sure != true || !mounted) return;
    try {
      final message = await widget.api.forwardTimetable(widget.cohortId, paper.paper.id);
      if (mounted) say(context, message);
      await _load();
    } catch (e) {
      if (mounted) showError(context, e);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading && _board == null) return const LoadingView();
    if (_error != null && _board == null) return ErrorRetry(message: _error!, onRetry: _load);
    final board = _board;
    if (board == null) return const LoadingView();
    final paper = board.paper;

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(14, 12, 14, 28),
        children: [
          SectionCard(
            title: 'This class’s week',
            subtitle: paper == null
                ? 'No week written yet'
                : '${paper.slots.length} ${paper.slots.length == 1 ? 'class' : 'classes'} · '
                    '${paper.paper.statusLabel}',
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('The week you teach is what the attendance register is built from, so a class that '
                    'is not here still shows on the register as one you recorded off the timetable.',
                    style: TextStyle(color: muted, fontSize: 12.5, height: 1.4)),
                const SizedBox(height: 10),
                if (paper != null) _days(paper.slots),
                if (paper != null && !paper.paper.editable)
                  Padding(
                    padding: const EdgeInsets.only(top: 8),
                    child: Text('This week is ${paper.paper.statusLabel.toLowerCase()} — the Registrar answers it.',
                        style: const TextStyle(color: danger, fontSize: 12.5)),
                  )
                else
                  Padding(
                    padding: const EdgeInsets.only(top: 10),
                    child: Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        FilledButton.icon(
                          onPressed: _write,
                          style: FilledButton.styleFrom(
                              backgroundColor: green, foregroundColor: Colors.white),
                          icon: const Icon(Icons.view_day_outlined, size: 18),
                          label: Text(paper == null ? 'Write the week' : 'Correct the week'),
                        ),
                        if (paper != null && paper.paper.editable)
                          OutlinedButton.icon(
                            onPressed: () => _forward(paper),
                            icon: const Icon(Icons.send_outlined, size: 18),
                            label: const Text('Send the week to the Registrar'),
                          ),
                      ],
                    ),
                  ),
              ],
            ),
          ),
          if (board.programmeWeek != null)
            SectionCard(
              title: 'The Registrar’s week for the programme',
              subtitle: '${board.programmeWeek!.slots.length} classes · '
                  '${board.programmeWeek!.paper.statusLabel}',
              child: _days(board.programmeWeek!.slots),
            )
          else
            const SectionCard(
              title: 'The Registrar’s week for the programme',
              subtitle: 'The Registrar has not laid out a week for this programme yet, so the register takes '
                  'the week you write here.',
              child: SizedBox.shrink(),
            ),
        ],
      ),
    );
  }

  /// Monday to Saturday, each day carrying its classes — the same order the web table runs.
  Widget _days(List<TimetableSlot> slots) {
    final byDay = <String, List<TimetableSlot>>{};
    for (final slot in slots) {
      byDay.putIfAbsent(slot.day ?? '', () => []).add(slot);
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        for (final entry in byDay.entries) ...[
          Padding(
            padding: const EdgeInsets.only(top: 8, bottom: 4),
            child: Text(entry.key.replaceAll('_', ' '),
                style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13, color: ink)),
          ),
          for (final slot in entry.value)
            Padding(
              padding: const EdgeInsets.only(bottom: 6),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  SizedBox(
                    width: 110,
                    child: Text(slot.time.isEmpty ? '—' : slot.time,
                        style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700)),
                  ),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text([slot.kindLabel, slot.unit]
                            .where((x) => x != null && x.isNotEmpty)
                            .join(' — '), style: const TextStyle(fontSize: 13)),
                        if ([slot.room, slot.lecturer].any((x) => x != null && x.isNotEmpty))
                          Text([
                            if (slot.hoursLabel.isNotEmpty) slot.hoursLabel,
                            if (slot.room != null && slot.room!.isNotEmpty) slot.room,
                            if (slot.lecturer != null && slot.lecturer!.isNotEmpty) slot.lecturer,
                          ].join(' · '), style: const TextStyle(color: muted, fontSize: 12)),
                      ],
                    ),
                  ),
                ],
              ),
            ),
        ],
      ],
    );
  }
}

/// The week written whole: every class on one screen, because saving the week replaces it — a class
/// left off here is off the timetable, the same rule the web page runs on.
class TimetableEditorScreen extends StatefulWidget {
  final ApiClient api;
  final int cohortId;
  final TimetableBoard board;

  const TimetableEditorScreen({
    super.key,
    required this.api,
    required this.cohortId,
    required this.board,
  });

  @override
  State<TimetableEditorScreen> createState() => _TimetableEditorScreenState();
}

class _TimetableEditorScreenState extends State<TimetableEditorScreen> {
  final _rows = <_SlotDraft>[];
  final _note = TextEditingController();
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    final slots = widget.board.paper?.slots ?? const [];
    if (slots.isEmpty) {
      _rows.add(_SlotDraft());
    } else {
      _rows.addAll(slots.map(_SlotDraft.from));
    }
    _note.text = widget.board.paper?.paper.note ?? '';
  }

  @override
  void dispose() {
    for (final row in _rows) {
      row.dispose();
    }
    _note.dispose();
    super.dispose();
  }

  Future<void> _save({required bool forward}) async {
    setState(() => _busy = true);
    final slots = <Map<String, dynamic>>[];
    for (final row in _rows) {
      if (row.isBlank) continue;
      slots.add(row.body(widget.board));
    }

    try {
      final message = await widget.api.saveTimetable(widget.cohortId, {
        'slots': slots,
        if (_note.text.trim().isNotEmpty) 'note': _note.text.trim(),
        if (forward) 'forward': true,
      });
      if (!mounted) return;
      setState(() => _busy = false);
      say(context, message);
      if (forward) Navigator.pop(context);
    } catch (e) {
      if (!mounted) return;
      setState(() => _busy = false);
      showError(context, e);
    }
  }

  @override
  Widget build(BuildContext context) {
    final board = widget.board;

    return Scaffold(
      appBar: AppBar(title: const Text('The class week')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(14, 12, 14, 28),
        children: [
          const Text('A class needs a day, a start time and an end time — the length counts itself from the two. '
              'Saving writes the week again, so a row you remove is off the timetable.',
              style: TextStyle(color: muted, fontSize: 12.5, height: 1.4)),
          const SizedBox(height: 12),
          for (var i = 0; i < _rows.length; i++) _row(board, i),
          OutlinedButton.icon(
            onPressed: () => setState(() => _rows.add(_SlotDraft())),
            icon: const Icon(Icons.add, size: 18),
            label: const Text('Add a class'),
          ),
          const SizedBox(height: 14),
          SectionCard(
            title: 'Note on the week',
            child: TextField(
              controller: _note,
              maxLines: 2,
              decoration: const InputDecoration(isDense: true),
            ),
          ),
          FilledButton.icon(
            onPressed: _busy ? null : () => _save(forward: false),
            style: FilledButton.styleFrom(
                backgroundColor: green, foregroundColor: Colors.white,
                minimumSize: const Size.fromHeight(48)),
            icon: const Icon(Icons.save_outlined),
            label: const Text('Keep the week'),
          ),
          const SizedBox(height: 8),
          OutlinedButton.icon(
            onPressed: _busy ? null : () => _save(forward: true),
            icon: const Icon(Icons.send_outlined, color: green),
            label: const Text('Keep and send to the Registrar', style: TextStyle(color: greenDark)),
          ),
        ],
      ),
    );
  }

  Widget _row(TimetableBoard board, int index) {
    final row = _rows[index];

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(14, 12, 8, 12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text('Class ${index + 1}',
                      style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13.5, color: ink)),
                ),
                IconButton(
                  tooltip: 'Take this class off the week',
                  onPressed: _rows.length == 1
                      ? null
                      : () => setState(() => _rows.removeAt(index)),
                  icon: const Icon(Icons.delete_outline, size: 20, color: danger),
                ),
              ],
            ),
            _pair(
              DropdownButtonFormField<String>(
                initialValue: row.day,
                decoration: const InputDecoration(labelText: 'Day', isDense: true),
                items: [
                  for (final entry in board.days.entries)
                    DropdownMenuItem(value: entry.key, child: Text(entry.value)),
                ],
                onChanged: (v) => setState(() => row.day = v),
              ),
              DropdownButtonFormField<String>(
                initialValue: row.kind,
                decoration: const InputDecoration(labelText: 'Class', isDense: true),
                items: [
                  for (final entry in board.kinds.entries)
                    DropdownMenuItem(value: entry.key, child: Text(entry.value)),
                ],
                onChanged: (v) => setState(() => row.kind = v),
              ),
            ),
            const SizedBox(height: 10),
            _pair(
              TextField(
                controller: row.starts,
                keyboardType: TextInputType.datetime,
                decoration: const InputDecoration(labelText: 'Starts', isDense: true, hintText: '09:00'),
              ),
              TextField(
                controller: row.ends,
                keyboardType: TextInputType.datetime,
                decoration: const InputDecoration(labelText: 'Ends', isDense: true, hintText: '11:00'),
              ),
            ),
            const SizedBox(height: 10),
            _pair(
              DropdownButtonFormField<String>(
                initialValue: row.unit,
                decoration: const InputDecoration(labelText: 'Unit', isDense: true),
                items: [
                  DropdownMenuItem(value: '', child: Text('No unit', style: TextStyle(fontSize: 14))),
                  for (final unit in board.units)
                    DropdownMenuItem(
                        value: '${unit.id}',
                        child: Text(unit.title, overflow: TextOverflow.ellipsis)),
                ],
                onChanged: (v) => setState(() => row.unit = v ?? ''),
              ),
              TextField(
                controller: row.room,
                decoration: const InputDecoration(labelText: 'Room', isDense: true),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _pair(Widget a, Widget b) => Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(child: a),
          const SizedBox(width: 10),
          Expanded(child: b),
        ],
      );
}

class _SlotDraft {
  String? day;
  String? kind;
  String unit;
  final TextEditingController starts;
  final TextEditingController ends;
  final TextEditingController room;

  _SlotDraft()
      : unit = '',
        starts = TextEditingController(),
        ends = TextEditingController(),
        room = TextEditingController();

  _SlotDraft.from(TimetableSlot slot)
      : day = slot.day,
        kind = slot.kind,
        unit = slot.unitId == null ? '' : '${slot.unitId}',
        starts = TextEditingController(text: slot.startsAt ?? ''),
        ends = TextEditingController(text: slot.endsAt ?? ''),
        room = TextEditingController(text: slot.room ?? '');

  bool get isBlank =>
      (day == null || day!.isEmpty) &&
      starts.text.trim().isEmpty &&
      ends.text.trim().isEmpty;

  Map<String, dynamic> body(TimetableBoard board) => {
        'day': day ?? board.days.keys.first,
        'kind': kind ?? board.kinds.keys.first,
        'starts_at': starts.text.trim(),
        'ends_at': ends.text.trim(),
        if (unit.isNotEmpty) 'training_module_id': int.parse(unit),
        if (room.text.trim().isNotEmpty) 'room': room.text.trim(),
      };

  void dispose() {
    starts.dispose();
    ends.dispose();
    room.dispose();
  }
}
