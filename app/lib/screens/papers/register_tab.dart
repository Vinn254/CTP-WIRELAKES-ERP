/// The attendance register on the phone — the same week of classes the web desk builds from the
/// timetable, with the register the trainer ticks for each class and the paper that class signed.
library;

import 'package:flutter/material.dart';
import '../../models/teaching_models.dart';
import '../../services/api_client.dart';
import '../../widgets/ui.dart';
import '../../widgets/week_picker.dart';

class RegisterTab extends StatefulWidget {
  final ApiClient api;
  final int cohortId;
  const RegisterTab({super.key, required this.api, required this.cohortId});

  @override
  State<RegisterTab> createState() => _RegisterTabState();
}

class _RegisterTabState extends State<RegisterTab> {
  RegisterBoard? _board;
  String? _month;
  int? _week;
  bool _loading = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load({String? month, int? week}) async {
    final first = _board == null;
    if (first) setState(() { _loading = true; _error = null; });
    try {
      final board = await widget.api.teachingRegister(widget.cohortId,
          month: month ?? _month, week: week ?? _week);
      if (!mounted) return;
      setState(() {
        _board = board;
        _month = board.week.month;
        _week = board.week.weekNo;
        _loading = false;
        _error = null;
      });
    } catch (e) {
      if (!mounted) return;
      if (first) {
        setState(() { _error = '$e'; _loading = false; });
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

  Future<void> _write(RegisterClass row) async {
    final body = await showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      builder: (_) => _DayForm(board: _board!, row: row),
    );
    if (body == null) return;
    await _run(() => widget.api.saveRegister(widget.cohortId, body));
  }

  Future<void> _forward(RegisterClass row) async {
    final sure = await confirm(
      context,
      title: 'Send this register to the Registrar?',
      body: 'It stops here until they answer it — a register with the Registrar is corrected by them, not here.',
      cta: 'Send it',
    );
    if (sure != true || row.session == null || !mounted) return;
    await _run(() => widget.api.forwardRegister(widget.cohortId, row.session!.paper.id));
  }

  Future<void> _sheet(RegisterClass row) async {
    if (row.session == null) return;
    try {
      final file = await widget.api.registerSheet(widget.cohortId, row.session!.paper.id);
      if (mounted) await openDocument(context, file);
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

    final written = board.classes.where((c) => c.session != null).length;

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(14, 12, 14, 28),
        children: [
          SectionCard(
            title: board.week.label,
            subtitle: '$written of ${board.classes.length} registers written',
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                WeekPicker(
                  month: board.week.month,
                  weekNo: board.week.weekNo,
                  weeks: board.week.weeks,
                  years: _years(),
                  onMonth: (m) => _load(month: m, week: 1),
                  onWeek: (w) => _load(week: w),
                ),
                Padding(
                  padding: const EdgeInsets.only(top: 10),
                  child: Text(
                    board.onTheWeek
                        ? 'The week’s classes are the timetable’s, not a list from memory — a day that has passed '
                            'with no register shows as owed.'
                        : 'No timetable sets this week yet, so each class is typed in here. The week the Registrar '
                            'laid out for the programme is read on the Timetable paper.',
                    style: const TextStyle(color: muted, fontSize: 12.5, height: 1.4),
                  ),
                ),
              ],
            ),
          ),
          if (board.classes.isEmpty)
            const EmptyHint(
              icon: Icons.how_to_reg_outlined,
              title: 'No class on this week',
              body: 'Pick another week, or put the week on the Timetable paper so the register knows what to expect.',
            )
          else
            for (final row in board.classes) _card(row),
          Padding(
            padding: const EdgeInsets.only(top: 6),
            child: OutlinedButton.icon(
              onPressed: () => _write(RegisterClass(
                  date: _today(),
                  day: _today(),
                  kind: 'class',
                  kindLabel: 'Class',
                  owed: false,
                  offTimetable: true)),
              icon: const Icon(Icons.add, size: 18),
              label: const Text('A class the timetable does not carry'),
            ),
          ),
        ],
      ),
    );
  }

  Widget _card(RegisterClass row) {
    final session = row.session;

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
                  child: Text(row.day,
                      style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14, color: ink)),
                ),
                if (session != null)
                  Tag(session.paper.statusLabel,
                      color: session.paper.status == 'submitted'
                          ? warn
                          : session.editable
                              ? muted
                              : green)
                else if (row.owed)
                  const Tag('Register owed', color: danger)
                else
                  const Tag('Not yet', color: muted),
              ],
            ),
            Padding(
              padding: const EdgeInsets.only(top: 3),
              child: Text([row.head, row.meta].where((x) => x.isNotEmpty).join(' · '),
                  style: const TextStyle(color: muted, fontSize: 12.5)),
            ),
            if (row.offTimetable)
              Padding(
                padding: const EdgeInsets.only(top: 3),
                child: Text('Off the timetable — this day is not on the week the office laid out.',
                    style: const TextStyle(color: warn, fontSize: 12)),
              ),
            if (session != null) ...[
              Padding(
                padding: const EdgeInsets.only(top: 6),
                child: Text(
                    '${session.present} of ${_board!.roll.length} present'
                    '${session.topic == null ? '' : ' · ${session.topic}'}'
                    '${session.paper.editable ? '' : ' · ${session.paper.statusLabel}'}',
                    style: const TextStyle(fontSize: 13)),
              ),
              if (!session.paper.editable && session.paper.reviewNote != null)
                Padding(
                  padding: const EdgeInsets.only(top: 4),
                  child: Text('The Registrar: ${session.paper.reviewNote}',
                      style: const TextStyle(color: danger, fontSize: 12.5)),
                ),
            ],
            Padding(
              padding: const EdgeInsets.only(top: 10),
              child: Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  if (session == null || session.paper.editable)
                    FilledButton.icon(
                      onPressed: () => _write(row),
                      style: FilledButton.styleFrom(
                          backgroundColor: green, foregroundColor: Colors.white,
                          textStyle: const TextStyle(fontSize: 13)),
                      icon: const Icon(Icons.edit_outlined, size: 18),
                      label: Text(session == null ? 'Write the register' : 'Correct the register'),
                    ),
                  if (session != null && session.paper.editable)
                    OutlinedButton.icon(
                      onPressed: () => _forward(row),
                      icon: const Icon(Icons.send_outlined, size: 18),
                      label: const Text('Send to the Registrar'),
                    ),
                  if (session != null && session.hasSheet)
                    OutlinedButton.icon(
                      onPressed: () => _sheet(row),
                      icon: const Icon(Icons.description_outlined, size: 18),
                      label: Text(session.sheetName ?? 'Signed sheet'),
                    ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  /// The years a week can be opened on: this one back to the programme’s first term.
  List<int> _years() {
    final now = DateTime.now().year;
    return [for (var y = 2025; y <= now + 1; y++) y];
  }
}

/// One class written: what was taught, who sat it, and the paper they signed.
class _DayForm extends StatefulWidget {
  final RegisterBoard board;
  final RegisterClass row;

  const _DayForm({required this.board, required this.row});

  @override
  State<_DayForm> createState() => _DayFormState();
}

class _DayFormState extends State<_DayForm> {
  late String _date = widget.row.session?.heldOn ?? widget.row.date;
  late String _kind = widget.row.session?.kind ?? widget.row.kind;
  late String? _unit = '${widget.row.session?.unitId ?? widget.row.unitId ?? ''}';
  late double? _hours = widget.row.session?.hours ?? widget.row.hours;
  final _topic = TextEditingController();
  final _note = TextEditingController();
  final _absentNotes = <int, TextEditingController>{};
  late final Map<int, bool> _ticks = {
    for (final seat in widget.board.roll)
      seat.id: widget.row.session?.ticks[seat.id] ?? false,
  };
  PickedPhoto? _sheet;

  @override
  void initState() {
    super.initState();
    _topic.text = widget.row.session?.topic ?? '';
    _note.text = widget.row.session?.paper.note ?? '';
    for (final seat in widget.board.roll) {
      _absentNotes[seat.id] =
          TextEditingController(text: widget.row.session?.tickNotes[seat.id] ?? '');
    }
  }

  @override
  void dispose() {
    _topic.dispose();
    _note.dispose();
    for (final c in _absentNotes.values) {
      c.dispose();
    }
    super.dispose();
  }

  Map<String, dynamic> _body({required bool forward}) => {
        'held_on': _date,
        'kind': _kind,
        if (_unit != null && _unit!.isNotEmpty) 'training_module_id': int.parse(_unit!),
        'topic': _topic.text.trim(),
        if (_hours != null) 'hours': _hours,
        'note': _note.text.trim(),
        if (_sheet != null) 'sheet': _sheet,
        'ticks': {for (final e in _ticks.entries) '${e.key}': e.value},
        'tick_note': {
          for (final e in _ticks.entries)
            if (!e.value && (_absentNotes[e.key]?.text.trim().isNotEmpty ?? false))
              '${e.key}': _absentNotes[e.key]!.text.trim(),
        },
        if (forward) 'forward': true,
      };

  @override
  Widget build(BuildContext context) {
    final board = widget.board;
    // A class the timetable carries sits for exactly the length the timetable set, so the hours are read-only.
    final fixedHours = widget.row.hours != null;
    final units = {'': 'No unit — a class day', for (final u in board.units) '${u.id}': u.title};

    return SafeArea(
      child: Padding(
        padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(18, 14, 18, 22),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(widget.row.session == null ? 'The register for ${widget.row.day}' : 'Correct the register',
                  style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w800, color: ink)),
              const SizedBox(height: 4),
              const Text('Tick who sat the class and the count follows the roll. A register dated the day the class '
                  'happened — never a day that has not come yet.',
                  style: TextStyle(color: muted, fontSize: 12.5, height: 1.4)),
              const SizedBox(height: 14),
              _label('Day of the class'),
              OutlinedButton.icon(
                onPressed: () async {
                  final picked = await showDatePicker(
                    context: context,
                    initialDate: DateTime.tryParse(_date) ?? DateTime.now(),
                    firstDate: DateTime(2025),
                    lastDate: DateTime.now(),
                  );
                  if (picked == null) return;
                  setState(() => _date =
                      '${picked.year.toString().padLeft(4, '0')}-${picked.month.toString().padLeft(2, '0')}-${picked.day.toString().padLeft(2, '0')}');
                },
                icon: const Icon(Icons.calendar_today_outlined, size: 18),
                label: Text(prettyDay(_date)),
              ),
              const SizedBox(height: 12),
              _label('Class'),
              DropdownButtonFormField<String>(
                initialValue: board.kinds.containsKey(_kind) ? _kind : board.kinds.keys.first,
                isExpanded: true,
                items: [
                  for (final e in board.kinds.entries)
                    DropdownMenuItem(value: e.key, child: Text(e.value)),
                ],
                onChanged: (v) => setState(() => _kind = v ?? _kind),
              ),
              const SizedBox(height: 12),
              _label('Unit taught'),
              DropdownButtonFormField<String>(
                initialValue: units.containsKey(_unit) ? _unit : '',
                isExpanded: true,
                items: [
                  for (final e in units.entries)
                    DropdownMenuItem(value: e.key,
                        child: Text(e.value, overflow: TextOverflow.ellipsis)),
                ],
                onChanged: (v) => setState(() => _unit = v ?? ''),
              ),
              const SizedBox(height: 12),
              _label('What was covered'),
              TextField(
                controller: _topic,
                maxLength: 190,
                decoration: const InputDecoration(isDense: true, counterText: ''),
              ),
              const SizedBox(height: 10),
              if (fixedHours)
                _label('Hours — the timetable sets this class at ${widget.row.hours} '
                    '${widget.row.hours == 1 ? 'hour' : 'hours'}')
              else ...[
                _label('Hours sat'),
                TextField(
                  keyboardType: const TextInputType.numberWithOptions(decimal: true),
                  onChanged: (v) => _hours = double.tryParse(v.trim()),
                  decoration: const InputDecoration(isDense: true, hintText: 'e.g. 2'),
                ),
                const SizedBox(height: 10),
              ],
              _label('Note on the class'),
              TextField(
                controller: _note,
                maxLines: 2,
                decoration: const InputDecoration(isDense: true),
              ),
              const SizedBox(height: 16),
              const Text('The roll',
                  style: TextStyle(fontWeight: FontWeight.w800, fontSize: 14, color: ink)),
              const SizedBox(height: 4),
              for (final seat in board.roll) _rollRow(seat),
              const SizedBox(height: 14),
              PhotoPicker(
                label: 'The sheet the class signed',
                helper: 'A photo or scan of the signed register — pdf and jpg on the web desk, a picture here.',
                photo: _sheet,
                onPicked: (p) => setState(() => _sheet = p),
              ),
              const SizedBox(height: 18),
              FilledButton.icon(
                onPressed: () => Navigator.pop(context, _body(forward: false)),
                style: FilledButton.styleFrom(
                    backgroundColor: green, foregroundColor: Colors.white,
                    minimumSize: const Size.fromHeight(46)),
                icon: const Icon(Icons.save_outlined),
                label: const Text('Keep the register'),
              ),
              const SizedBox(height: 8),
              FilledButton.icon(
                onPressed: () => Navigator.pop(context, _body(forward: true)),
                style: FilledButton.styleFrom(
                    backgroundColor: Colors.white, foregroundColor: greenDark,
                    minimumSize: const Size.fromHeight(46),
                    side: const BorderSide(color: green)),
                icon: const Icon(Icons.send_outlined),
                label: const Text('Keep and send to the Registrar'),
              ),
              TextButton(
                onPressed: () => Navigator.pop(context),
                child: const Text('Cancel'),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _rollRow(RollSeat seat) {
    final present = _ticks[seat.id] ?? false;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        CheckboxListTile(
          value: present,
          onChanged: (v) => setState(() => _ticks[seat.id] = v ?? false),
          contentPadding: EdgeInsets.zero,
          dense: true,
          controlAffinity: ListTileControlAffinity.leading,
          title: Text(seat.name, style: const TextStyle(fontSize: 13.5)),
          subtitle: seat.reference == null
              ? null
              : Text(seat.reference!, style: const TextStyle(fontSize: 12, color: muted)),
          activeColor: green,
        ),
        if (!present)
          Padding(
            padding: const EdgeInsets.only(left: 12, bottom: 4),
            child: TextField(
              controller: _absentNotes[seat.id],
              decoration: const InputDecoration(
                  isDense: true,
                  hintText: 'Why they were not there (optional)',
                  counterText: ''),
            ),
          ),
      ],
    );
  }

  Widget _label(String text) => Padding(
        padding: const EdgeInsets.only(bottom: 6),
        child: Text(text,
            style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: ink)),
      );
}

String _today() {
  final now = DateTime.now();
  return '${now.year.toString().padLeft(4, '0')}-${now.month.toString().padLeft(2, '0')}-${now.day.toString().padLeft(2, '0')}';
}
