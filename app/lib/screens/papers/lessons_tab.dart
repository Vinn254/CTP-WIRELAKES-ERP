/// The plan the trainer writes for the class ahead: every lesson on one paper, added, rewritten or
/// struck off, then sent to the Registrar as a whole.
library;

import 'package:flutter/material.dart';
import '../../models/teaching_models.dart';
import '../../services/api_client.dart';
import '../../widgets/ui.dart';
import '../../widgets/week_picker.dart';

class LessonsTab extends StatefulWidget {
  final ApiClient api;
  final int cohortId;
  const LessonsTab({super.key, required this.api, required this.cohortId});

  @override
  State<LessonsTab> createState() => _LessonsTabState();
}

class _LessonsTabState extends State<LessonsTab> {
  LessonsBoard? _board;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final first = _board == null;
    if (first) setState(() => _error = null);
    try {
      final board = await widget.api.teachingLessons(widget.cohortId);
      if (!mounted) return;
      setState(() { _board = board; _error = null; });
    } catch (e) {
      if (!mounted) return;
      if (first) {
        setState(() => _error = '$e');
      } else {
        showError(context, e);
      }
    }
  }

  Future<void> _write({Lesson? line}) async {
    final board = _board;
    if (board == null) return;
    await Navigator.of(context).push(MaterialPageRoute(
      builder: (_) => LessonEditorScreen(
          api: widget.api, cohortId: widget.cohortId, board: board, line: line),
    ));
    await _load();
  }

  Future<void> _remove(Lesson line) async {
    final sure = await confirm(
      context,
      title: 'Take lesson ${line.position} off the plan?',
      body: 'The lessons behind it move up, so the plan still runs one, two, three to the last lesson.',
      cta: 'Take it off',
    );
    if (sure != true || !mounted) return;
    try {
      final message = await widget.api.removeLesson(widget.cohortId, line.id);
      if (mounted) say(context, message);
      await _load();
    } catch (e) {
      if (mounted) showError(context, e);
    }
  }

  Future<void> _forward(LessonPlanInfo plan) async {
    final sure = await confirm(
      context,
      title: 'Send the plan to the Registrar?',
      body: 'The plan closes for writing while the Registrar answers it. A plan sent back opens again.',
      cta: 'Send the plan',
    );
    if (sure != true || !mounted) return;
    try {
      final message = await widget.api.forwardLesson(widget.cohortId, plan.paper.id);
      if (mounted) say(context, message);
      await _load();
    } catch (e) {
      if (mounted) showError(context, e);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_error != null && _board == null) return ErrorRetry(message: _error!, onRetry: _load);
    final board = _board;
    if (board == null) return const LoadingView();
    final plan = board.plan;

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(14, 12, 14, 28),
        children: [
          SectionCard(
            title: 'The lesson plan',
            subtitle: plan == null
                ? 'No plan written yet'
                : '${board.lines.length} ${board.lines.length == 1 ? 'lesson' : 'lessons'} · '
                    '${plan.paper.statusLabel}',
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (board.planNote != null)
                  Text(board.planNote!,
                      style: const TextStyle(color: muted, fontSize: 12.5, height: 1.4))
                else
                  const Text('The plan is the class you are going to teach, lesson by lesson. It stays open for '
                      'writing until you send it to the Registrar.',
                      style: TextStyle(color: muted, fontSize: 12.5, height: 1.4)),
                const SizedBox(height: 10),
                if (plan != null && !plan.paper.editable && plan.paper.reviewNote != null)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 8),
                    child: Text('The Registrar: ${plan.paper.reviewNote}',
                        style: const TextStyle(color: danger, fontSize: 12.5)),
                  ),
                if (board.canWrite)
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      FilledButton.icon(
                        onPressed: _write,
                        style: FilledButton.styleFrom(
                            backgroundColor: green, foregroundColor: Colors.white),
                        icon: const Icon(Icons.add, size: 18),
                        label: const Text('Add a lesson'),
                      ),
                      if (plan != null && board.lines.isNotEmpty)
                        OutlinedButton.icon(
                          onPressed: () => _forward(plan),
                          icon: const Icon(Icons.send_outlined, size: 18),
                          label: const Text('Send the plan to the Registrar'),
                        ),
                    ],
                  ),
              ],
            ),
          ),
          if (board.lines.isEmpty)
            const EmptyHint(
              icon: Icons.menu_book_outlined,
              title: 'The plan is empty',
              body: 'Write the first lesson and the class has a shape the Registrar can answer.',
            )
          else
            for (final line in board.lines) _card(line, board.canWrite),
        ],
      ),
    );
  }

  Widget _card(Lesson line, bool canWrite) {
    final detail = [
      if (line.objectives != null && line.objectives!.isNotEmpty)
        'Objectives: ${line.objectives}',
      if (line.activities != null && line.activities!.isNotEmpty)
        'Activities: ${line.activities}',
      if (line.resources != null && line.resources!.isNotEmpty)
        'Resources: ${line.resources}',
      if (line.assessmentNote != null && line.assessmentNote!.isNotEmpty)
        'Assessment: ${line.assessmentNote}',
    ];

    return SectionCard(
      title: 'Lesson ${line.position} — ${line.topic}',
      subtitle: '${line.dateLabel}${line.time.isEmpty ? '' : ' · ${line.time}'}'
          '${line.unit == null ? '' : ' · ${line.unit}'}',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(line.summary ?? '', style: const TextStyle(fontSize: 13, height: 1.4)),
          for (final row in detail)
            Padding(
              padding: const EdgeInsets.only(top: 6),
              child: Text(row, style: const TextStyle(color: muted, fontSize: 12.5, height: 1.4)),
            ),
          if (line.materials.isNotEmpty)
            Padding(
              padding: const EdgeInsets.only(top: 8),
              child: Wrap(
                spacing: 6,
                runSpacing: 6,
                children: [
                  for (final paper in line.materials)
                    Tag('${paper.kindLabel}: ${paper.title}'),
                ],
              ),
            ),
          if (canWrite)
            Padding(
              padding: const EdgeInsets.only(top: 10),
              child: Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  OutlinedButton.icon(
                    onPressed: () => _write(line: line),
                    icon: const Icon(Icons.edit_outlined, size: 18),
                    label: const Text('Rewrite the lesson'),
                  ),
                  TextButton.icon(
                    onPressed: () => _remove(line),
                    style: TextButton.styleFrom(foregroundColor: danger),
                    icon: const Icon(Icons.delete_outline, size: 18),
                    label: const Text('Take off the plan'),
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}

/// One lesson on the plan. A lesson opened for editing is overwritten, never put beside itself.
class LessonEditorScreen extends StatefulWidget {
  final ApiClient api;
  final int cohortId;
  final LessonsBoard board;
  final Lesson? line;

  const LessonEditorScreen({
    super.key,
    required this.api,
    required this.cohortId,
    required this.board,
    this.line,
  });

  @override
  State<LessonEditorScreen> createState() => _LessonEditorScreenState();
}

class _LessonEditorScreenState extends State<LessonEditorScreen> {
  late String _day = widget.line?.plannedOn ?? _today();
  late String _unit = '${widget.line?.unitId ?? ''}';
  late final _starts = TextEditingController(text: widget.line?.startsAt ?? '');
  late final _ends = TextEditingController(text: widget.line?.endsAt ?? '');
  late final _topic = TextEditingController(text: widget.line?.topic ?? '');
  late final _summary = TextEditingController(text: widget.line?.summary ?? '');
  late final _objectives = TextEditingController(text: widget.line?.objectives ?? '');
  late final _activities = TextEditingController(text: widget.line?.activities ?? '');
  late final _resources = TextEditingController(text: widget.line?.resources ?? '');
  late final _assessment = TextEditingController(text: widget.line?.assessmentNote ?? '');
  bool _busy = false;

  static String _today() {
    final now = DateTime.now();
    return '${now.year.toString().padLeft(4, '0')}-${now.month.toString().padLeft(2, '0')}-${now.day.toString().padLeft(2, '0')}';
  }

  @override
  void dispose() {
    for (final c in [
      _starts, _ends, _topic, _summary, _objectives, _activities, _resources, _assessment
    ]) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _save() async {
    if (_topic.text.trim().isEmpty || _summary.text.trim().isEmpty) {
      if (mounted) {
        showError(context, 'A lesson needs what it teaches and a summary of the content.');
      }
      return;
    }
    setState(() => _busy = true);
    try {
      final message = await widget.api.saveLesson(
        widget.cohortId,
        {
          if (_unit.isNotEmpty) 'training_module_id': int.parse(_unit),
          'planned_on': _day,
          if (_starts.text.trim().isNotEmpty) 'starts_at': _starts.text.trim(),
          if (_ends.text.trim().isNotEmpty) 'ends_at': _ends.text.trim(),
          'topic': _topic.text.trim(),
          'summary': _summary.text.trim(),
          if (_objectives.text.trim().isNotEmpty) 'objectives': _objectives.text.trim(),
          if (_activities.text.trim().isNotEmpty) 'activities': _activities.text.trim(),
          if (_resources.text.trim().isNotEmpty) 'resources': _resources.text.trim(),
          if (_assessment.text.trim().isNotEmpty)
            'assessment_note': _assessment.text.trim(),
        },
        lineId: widget.line?.id,
      );
      if (!mounted) return;
      say(context, message);
      Navigator.pop(context);
    } catch (e) {
      if (!mounted) return;
      setState(() => _busy = false);
      showError(context, e);
    }
  }

  @override
  Widget build(BuildContext context) {
    final units = <String, String>{
      '': 'No unit — a general lesson',
      for (final u in widget.board.units) '${u.id}': u.title,
    };
    // The plan is written for the teaching ahead, so its days run to the end of the cohort's period.
    final first = DateTime.tryParse(widget.board.cohort.startsOn ?? '') ?? DateTime(2025);
    final last = DateTime.tryParse(widget.board.cohort.endsOn ?? '') ??
        DateTime(DateTime.now().year + 1, 12, 31);

    return Scaffold(
      appBar: AppBar(
          title: Text(widget.line == null ? 'A lesson on the plan' : 'Rewrite lesson ${widget.line!.position}')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(14, 12, 14, 28),
        children: [
          SectionCard(
            title: 'When it sits',
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                OutlinedButton.icon(
                  onPressed: () async {
                    final start = DateTime.tryParse(_day) ?? first;
                  final picked = await showDatePicker(
                    context: context,
                    initialDate: start.isBefore(first)
                        ? first
                        : start.isAfter(last)
                            ? last
                            : start,
                    firstDate: first,
                    lastDate: last,
                  );
                    if (picked == null) return;
                    setState(() => _day =
                        '${picked.year.toString().padLeft(4, '0')}-${picked.month.toString().padLeft(2, '0')}-${picked.day.toString().padLeft(2, '0')}');
                  },
                  icon: const Icon(Icons.calendar_today_outlined, size: 18),
                  label: Text(prettyDay(_day)),
                ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(
                      child: TextField(
                        controller: _starts,
                        keyboardType: TextInputType.datetime,
                        decoration: const InputDecoration(
                            labelText: 'Starts', isDense: true, hintText: '09:00'),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: TextField(
                        controller: _ends,
                        keyboardType: TextInputType.datetime,
                        decoration: const InputDecoration(
                            labelText: 'Ends', isDense: true, hintText: '11:00'),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                DropdownButtonFormField<String>(
                  initialValue: units.containsKey(_unit) ? _unit : '',
                  isExpanded: true,
                  decoration: const InputDecoration(labelText: 'Unit', isDense: true),
                  items: [
                    for (final e in units.entries)
                      DropdownMenuItem(value: e.key,
                          child: Text(e.value, overflow: TextOverflow.ellipsis)),
                  ],
                  onChanged: (v) => setState(() => _unit = v ?? ''),
                ),
              ],
            ),
          ),
          SectionCard(
            title: 'What the lesson covers',
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _label('Topic *'),
                TextField(
                  controller: _topic,
                  maxLength: 190,
                  decoration: const InputDecoration(isDense: true, counterText: ''),
                ),
                const SizedBox(height: 12),
                _label('Summary and content *'),
                TextField(
                  controller: _summary,
                  maxLines: 5,
                  decoration: const InputDecoration(isDense: true),
                ),
                const SizedBox(height: 12),
                _label('Objectives'),
                TextField(controller: _objectives, maxLines: 3,
                    decoration: const InputDecoration(isDense: true)),
                const SizedBox(height: 12),
                _label('Activities'),
                TextField(controller: _activities, maxLines: 3,
                    decoration: const InputDecoration(isDense: true)),
                const SizedBox(height: 12),
                _label('Resources'),
                TextField(controller: _resources, maxLines: 3,
                    decoration: const InputDecoration(isDense: true)),
                const SizedBox(height: 12),
                _label('How the class is assessed'),
                TextField(controller: _assessment, maxLines: 3,
                    decoration: const InputDecoration(isDense: true)),
              ],
            ),
          ),
          FilledButton.icon(
            onPressed: _busy ? null : _save,
            style: FilledButton.styleFrom(
                backgroundColor: green, foregroundColor: Colors.white,
                minimumSize: const Size.fromHeight(48)),
            icon: const Icon(Icons.save_outlined),
            label: Text(widget.line == null ? 'Put it on the plan' : 'Keep the lesson'),
          ),
        ],
      ),
    );
  }

  Widget _label(String text) => Padding(
        padding: const EdgeInsets.only(bottom: 6),
        child: Text(text, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13)),
      );
}
