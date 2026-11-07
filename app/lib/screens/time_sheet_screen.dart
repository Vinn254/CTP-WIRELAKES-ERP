/// My time log — the ruled week the trainer's office account or E-Fundi record signs in web
/// resources/views/partials/week-day.blade.php. Monday to Friday is written day by day, then the whole week is
/// signed and sent to Human Resources from the phone.
library;

import 'package:flutter/material.dart';
import '../models/teaching_models.dart';
import '../services/api_client.dart';
import '../widgets/ui.dart';
import '../widgets/week_picker.dart';

class TimeSheetScreen extends StatefulWidget {
  final ApiClient api;
  const TimeSheetScreen({super.key, required this.api});

  @override
  State<TimeSheetScreen> createState() => _TimeSheetScreenState();
}

class _TimeSheetScreenState extends State<TimeSheetScreen> {
  TimeSheetBoard? _board;
  bool _loading = false;
  String? _error;
  String? _month;
  int _week = 1;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load({String? month, int? week}) async {
    final first = _board == null;
    if (first) setState(() { _loading = true; _error = null; });
    try {
      final board = await widget.api.timeSheet(month: month ?? _month, week: week ?? _week);
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

  Future<void> _fillDay(SheetDay day) async {
    final board = _board;
    if (board == null) return;
    final entry = day.entry;
    final many = board.defaults.departments.length > 1;
    final programmes = {
      '': 'Not one of the programmes',
      for (final t in board.programmes) '${t.id}': t.title,
    };
    final cohorts = {
      '': 'No single cohort',
      for (final c in board.cohorts) '${c.id}': c.label,
    };

    final answers = await showFormSheet(
      context,
      title: '${entry == null ? 'Write' : 'Correct'} ${day.day} ${prettyDay(day.date)}',
      subtitle: 'An hour for lunch comes off the two clock times, and the hours and units count themselves.',
      cta: entry == null ? 'Save as draft' : 'Save the correction',
      fields: [
        FieldSpec('time_in', 'Time in',
            required: false, initial: entry?.timeIn, helper: 'Type it as 08:00'),
        FieldSpec('time_out', 'Time out',
            required: false, initial: entry?.timeOut, helper: 'Type it as 16:00'),
        FieldSpec('task', 'Tasks accomplished',
            kind: FieldKind.multiline, maxLines: 3, initial: entry?.task,
            helper: 'What you actually did that day'),
        if (many)
          FieldSpec.dropdown('department', 'Department',
              {for (final d in board.defaults.departments) d: d},
              required: false,
              initial: entry?.department ?? board.defaults.department ?? board.defaults.departments.first,
              helper: 'Only the desks your account sits at.'),
        if (board.programmes.isNotEmpty)
          FieldSpec.dropdown('training_id', 'Programme', programmes,
              required: false, initial: '${entry?.trainingId ?? ''}',
              helper: 'Only the programmes this account is licensed for.'),
        if (board.cohorts.isNotEmpty)
          FieldSpec.dropdown('cohort_id', 'Cohort', cohorts,
              required: false, initial: '${entry?.cohortId ?? ''}'),
      ],
    );
    if (answers == null || !mounted) return;
    if ('${answers['task'] ?? ''}'.trim().isEmpty) {
      showError(context, 'Tasks accomplished is needed for the day.');
      return;
    }

    await _send({
      'log_date': day.date,
      'month': board.week.month,
      'week': board.week.weekNo,
      'action': 'draft',
      for (final key in ['time_in', 'time_out', 'task', 'department', 'training_id', 'cohort_id'])
        if (answers[key] != null && '${answers[key]}'.isNotEmpty) key: answers[key],
    });
  }

  Future<void> _send(Map<String, dynamic> body) async {
    try {
      final message = body.containsKey('log_date')
          ? await widget.api.saveTimeSheetDay(body)
          : await widget.api.saveTimeSheet(body);
      await _load(month: _month, week: _week);
      if (mounted) say(context, message);
    } catch (e) {
      if (mounted) showError(context, e);
    }
  }

  Future<void> _submit(TimeSheetBoard board) async {
    final sure = await confirm(
      context,
      title: 'Sign the week and send it?',
      body: 'The week of ${board.week.label} closes for writing until Human Resources answers it.',
      cta: 'Sign and send',
    );
    if (sure != true || !mounted) return;
    await _send({'month': board.week.month, 'week': board.week.weekNo, 'action': 'submit'});
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
        padding: const EdgeInsets.fromLTRB(14, 14, 14, 28),
        children: [
          Row(
            children: [
              Expanded(
                child: Text('My time log',
                    style: const TextStyle(fontSize: 19, fontWeight: FontWeight.w800, color: ink)),
              ),
              Tag(board.week.statusLabel,
                  color: board.week.status == 'approved'
                      ? green
                      : board.week.status == 'submitted'
                          ? warn
                          : muted),
            ],
          ),
          const Padding(
            padding: EdgeInsets.only(top: 4, bottom: 12),
            child: Text('The week you worked, signed and sent to Human Resources. A day already on the sheet is '
                'corrected, never added a second time.',
                style: TextStyle(color: muted, fontSize: 12.5, height: 1.4)),
          ),
          SectionCard(
            title: 'Week of the month',
            subtitle: '${board.week.label} · ${_asNum(board.week.hours)} hours · ${board.week.units} units',
            child: WeekPicker(
              month: board.week.month,
              weekNo: board.week.weekNo,
              years: board.years,
              weeks: [
                for (final w in board.weeks)
                  WeekOption(label: w.label, dates: w.dates, start: '', end: '')
              ],
              onMonth: (m) => _load(month: m, week: 1),
              onWeek: (w) => _load(week: w),
            ),
          ),
          if (!board.canEdit)
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: Text('This week is already with the office — it is answered there, never rewritten here.',
                  style: const TextStyle(color: danger, fontSize: 12.5)),
            ),
          for (final day in board.days) _day(day, board),
          if (board.canEdit)
            FilledButton.icon(
              onPressed: () => _submit(board),
              style: FilledButton.styleFrom(
                  backgroundColor: green, foregroundColor: Colors.white,
                  minimumSize: const Size.fromHeight(48)),
              icon: const Icon(Icons.draw_outlined),
              label: const Text('Sign the week and send it'),
            ),
        ],
      ),
    );
  }

  Widget _day(SheetDay day, TimeSheetBoard board) {
    final entry = day.entry;
    final future = (day.date ?? '')
            .compareTo('${DateTime.now().year.toString().padLeft(4, '0')}-'
                '${DateTime.now().month.toString().padLeft(2, '0')}-'
                '${DateTime.now().day.toString().padLeft(2, '0')}') >
        0;

    return SectionCard(
      title: '${day.day} ${prettyDay(day.date)}',
      subtitle: entry == null
          ? (future ? 'A day that has not come yet' : 'Nothing written')
          : '${entry.clock} · ${entry.hours == null ? '—' : _asNum(entry.hours)} hours · '
              '${entry.units} units',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (entry?.task != null)
            Text(entry!.task!, style: const TextStyle(fontSize: 13, height: 1.4)),
          if (entry != null && (entry.department != null || entry.approvalNote != null))
            Padding(
              padding: const EdgeInsets.only(top: 4),
              child: Text([
                if (entry.department != null) entry.department!,
                if (entry.approvalNote != null) 'The office: ${entry.approvalNote}',
              ].join(' · '), style: const TextStyle(color: muted, fontSize: 12)),
            ),
          if (board.canEdit && !future)
            Padding(
              padding: const EdgeInsets.only(top: 8),
              child: OutlinedButton.icon(
                onPressed: () => _fillDay(day),
                icon: const Icon(Icons.edit_outlined, size: 18),
                label: Text(entry == null ? 'Write the day' : 'Correct the day'),
              ),
            ),
        ],
      ),
    );
  }

  String _asNum(num? v) {
    final n = v ?? 0;
    return n == n.roundToDouble() ? '${n.toInt()}' : '$n';
  }
}
