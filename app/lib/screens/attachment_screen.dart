import 'package:flutter/material.dart';
import '../models/attachment_models.dart';
import '../models/member_models.dart';
import '../services/api_client.dart';
import '../widgets/ui.dart';

/// My attachment — the placed trainee's own page, the same screen and the same sentences as
/// web/resources/views/portal/attachment.blade.php. The office gives a CTP who has finished the course a site;
/// here they log the days they sat there and send the week to the Academic Registrar. An attachment is training,
/// not a job, so nothing on this page mentions a payout.
class AttachmentScreen extends StatefulWidget {
  final ApiClient api;
  final Board board;
  const AttachmentScreen({super.key, required this.api, required this.board});

  @override
  State<AttachmentScreen> createState() => _AttachmentScreenState();
}

class _AttachmentScreenState extends State<AttachmentScreen> {
  AttachmentSheet? _data;
  bool _loading = false;
  String? _error;

  /// The month and week of the month the sheet is opened on; both come back on every load.
  String? _month;
  int _week = 1;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load({String? month, int? week}) async {
    final first = _data == null;
    if (first) setState(() { _loading = true; _error = null; });
    try {
      final data = await widget.api.attachment(
          month: month ?? _month, week: week ?? _week);
      if (!mounted) return;
      setState(() {
        _data = data;
        _month = data.week.month;
        _week = data.week.weekNo;
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

  /// One day written, or the whole week sent. The backend keeps a day left blank as it stands, so a
  /// single row can be filled on its own without touching the rest of the week.
  Future<void> _post(Map<String, dynamic> body) async {
    try {
      final message = await widget.api.saveAttachment(body);
      await _load();
      if (mounted) say(context, message);
    } catch (e) {
      if (mounted) showError(context, e);
    }
  }

  Future<void> _fillDay(AttachmentSheet sheet, AttachmentDay day) async {
    final answers = await showFormSheet(
      context,
      title: '${day.day} ${_pretty(day.date)}',
      subtitle: 'The hours count themselves from the two clock times — an hour off for lunch, '
          'eight hours a day at most.',
      cta: 'Keep the day',
      fields: [
        FieldSpec('time_in', 'Time you started',
            required: false, initial: day.timeIn, helper: 'Type it as 08:00'),
        FieldSpec('time_out', 'Time you finished',
            required: false, initial: day.timeOut, helper: 'Type it as 16:00'),
        FieldSpec('task', 'What you did that day',
            kind: FieldKind.multiline, maxLines: 3, initial: day.task),
        FieldSpec('initials', "Supervisor's initials",
            required: false, initial: day.initials, helper: 'Whoever sat with you at the site'),
      ],
    );
    if (answers == null || !mounted) return;

    await _post({
      'month': sheet.week.month,
      'week': sheet.week.weekNo,
      'action': 'draft',
      'days': {day.date: answers},
    });
  }

  Future<void> _note(AttachmentSheet sheet) async {
    final answers = await showFormSheet(
      context,
      title: 'Anything to say with the week',
      subtitle: 'It travels to the Registrar with the days you logged.',
      cta: 'Keep the note',
      fields: [
        FieldSpec('note', 'Note on the week',
            kind: FieldKind.multiline, maxLines: 4, required: false, initial: sheet.week.note),
      ],
    );
    if (answers == null || !mounted) return;

    await _post({
      'month': sheet.week.month,
      'week': sheet.week.weekNo,
      'action': 'draft',
      'note': answers['note'],
    });
  }

  Future<void> _submit(AttachmentSheet sheet) async {
    final sure = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('Send the week to the Registrar?'),
        content: Text('The week of ${sheet.week.label} is locked until they answer it.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Keep filling it')),
          TextButton(onPressed: () => Navigator.pop(context, true), child: const Text('Send it')),
        ],
      ),
    );
    if (sure != true || !mounted) return;

    await _post({'month': sheet.week.month, 'week': sheet.week.weekNo, 'action': 'submit'});
  }

  @override
  Widget build(BuildContext context) {
    if (_loading && _data == null) return const LoadingView();
    if (_error != null && _data == null) return ErrorRetry(message: _error!, onRetry: _load);

    final sheet = _data;
    if (sheet == null) return const LoadingView();

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(14, 14, 14, 28),
        children: [
          const Text('My attachment',
              style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: ink)),
          const SizedBox(height: 8),
          if (sheet.placement == null)
            const Padding(
              padding: EdgeInsets.only(top: 24),
              child: EmptyHint(
                icon: Icons.badge_outlined,
                title: 'No site yet',
                body: 'The office places a trainee at an attachment site once the course is finished and the '
                    'attachment you applied for is approved. Until then there is nothing to log here — keep to '
                    'My training and My applications.',
              ),
            )
          else
            ..._placed(sheet),
        ],
      ),
    );
  }

  List<Widget> _placed(AttachmentSheet sheet) {
    final p = sheet.placement!;
    final week = sheet.week;

    return [
      const SizedBox(height: 14),
      SectionCard(
        title: 'My placement',
        subtitle: p.reference,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            LabeledValue(label: 'Site', value: p.site ?? 'The site on the order'),
            if (p.place != null) LabeledValue(label: 'Where', value: p.place!, strong: false),
            LabeledValue(
                label: 'With',
                value: p.organisation ?? 'the organisation the office placed you with',
                strong: false),
            LabeledValue(label: 'Day placed', value: _pretty(p.placedOn) , strong: false),
            LabeledValue(
                label: 'Running until',
                value: _pretty(p.until),
                strong: false),
            LabeledValue(label: 'What you are doing there', value: p.scope ?? '—', strong: false),
            const Padding(
              padding: EdgeInsets.only(top: 10),
              child: Text(
                  'An attachment is training, not a job — it is not paid. What you hand in is this week: the days '
                  'you sat at the site and what you did, which the Academic Registrar approves.',
                  style: TextStyle(color: muted, fontSize: 12.5, height: 1.4)),
            ),
          ],
        ),
      ),
      SectionCard(
        title: week.label,
        subtitle: '${week.written} of 5 days written · ${week.hours.toStringAsFixed(2)} hours · '
            '${week.statusLabel}',
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _pickers(sheet),
            if (week.status == 'returned' && week.reviewNote != null)
              Padding(
                padding: const EdgeInsets.only(top: 10),
                child: Text('Sent back by the Registrar: ${week.reviewNote}',
                    style: const TextStyle(color: danger, fontSize: 13, height: 1.4)),
              ),
          ],
        ),
      ),
      SectionCard(
        title: 'The week you sat at the site',
        subtitle: week.canEdit
            ? 'Fill each day you were at the site. A day you leave out is simply not on the week.'
            : 'This week is with the Registrar — it is answered there, never rewritten here.',
        child: Column(
          children: [
            for (final day in sheet.days) _row(sheet, day),
            if (week.canEdit)
              Padding(
                padding: const EdgeInsets.only(top: 6),
                child: Align(
                  alignment: Alignment.centerLeft,
                  child: TextButton.icon(
                    onPressed: () => _note(sheet),
                    icon: const Icon(Icons.edit_note_outlined, color: green, size: 20),
                    label: const Text('Anything to say with the week',
                        style: TextStyle(color: green)),
                  ),
                ),
              ),
          ],
        ),
      ),
      if (week.canEdit)
        Padding(
          padding: const EdgeInsets.only(bottom: 14),
          child: FilledButton.icon(
            onPressed: () => _submit(sheet),
            style: FilledButton.styleFrom(
                backgroundColor: green, foregroundColor: Colors.white,
                minimumSize: const Size.fromHeight(48)),
            icon: const Icon(Icons.send_outlined),
            label: const Text('Submit the week to the Registrar'),
          ),
        ),
      SectionCard(
        title: 'Every week you have handed in',
        child: sheet.history.isEmpty
            ? const Text('Nothing sent yet. The weeks appear here as you hand them in.',
                style: TextStyle(color: muted, fontSize: 13))
            : Column(
                children: [
                  for (final h in sheet.history)
                    RecordRow(
                      title: h.label,
                      subtitle: '${h.days} ${h.days == 1 ? 'day' : 'days'} · '
                          '${h.hours.toStringAsFixed(2)} hours',
                      meta: sheet.statuses[h.status] ?? h.status,
                      onTap: () => _load(month: h.month, week: h.weekNo),
                    ),
                ],
              ),
      ),
    ];
  }

  Widget _row(AttachmentSheet sheet, AttachmentDay day) {
    final canEdit = sheet.week.canEdit && day.past;

    return RecordRow(
      title: '${day.day} ${_pretty(day.date)}',
      subtitle: day.written
          ? '${day.clock} · ${day.task}'
          : day.past
              ? 'Nothing logged yet'
              : 'A day that has not come yet',
      meta: day.hours == null
          ? null
          : "${day.hours!.toStringAsFixed(2)} hours · initials ${day.initials ?? '—'}",
      trailing: canEdit
          ? const Icon(Icons.edit_outlined, size: 18, color: green)
          : (day.written ? const Tag('On file', color: muted) : null),
      onTap: canEdit ? () => _fillDay(sheet, day) : null,
    );
  }

  /// Month, year and week of the month — the same three picks the web page's filter bar carries.
  Widget _pickers(AttachmentSheet sheet) {
    final month = int.tryParse(sheet.week.month.split('-').last) ?? DateTime.now().month;
    final year = int.tryParse(sheet.week.month.split('-').first) ?? DateTime.now().year;

    return Wrap(
      spacing: 10,
      runSpacing: 10,
      crossAxisAlignment: WrapCrossAlignment.end,
      children: [
        _pick('Month', {
          for (var m = 1; m <= 12; m++) m.toString(): _monthNames[m - 1]
        }, month.toString(), (v) {
          if (v == null) return;
          _load(month: '$year-${int.parse(v).toString().padLeft(2, '0')}', week: 1);
        }),
        _pick('Year', {
          for (final y in sheet.years) '$y': '$y'
        }, '$year', (v) {
          if (v == null) return;
          _load(month: '$v-${month.toString().padLeft(2, '0')}', week: 1);
        }),
        _pick('Week of the month', {
          for (final w in sheet.weeks) '${w.no}': '${w.label} (${w.dates})'
        }, '${sheet.week.weekNo}', (v) {
          if (v == null) return;
          _load(week: int.parse(v));
        }),
      ],
    );
  }

  Widget _pick(String label, Map<String, String> options, String value,
      ValueChanged<String?> onChanged) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label, style: const TextStyle(color: muted, fontSize: 12)),
        const SizedBox(height: 4),
        ConstrainedBox(
          constraints: const BoxConstraints(minWidth: 150),
          child: DropdownButtonHideUnderline(
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 10),
              decoration: BoxDecoration(
                  border: Border.all(color: line, width: 1.5),
                  borderRadius: BorderRadius.circular(10)),
              child: DropdownButton<String>(
                value: options.containsKey(value) ? value : options.keys.first,
                isExpanded: true,
                items: [
                  for (final e in options.entries)
                    DropdownMenuItem(value: e.key, child: Text(e.value,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(fontSize: 13))),
                ],
                onChanged: onChanged,
              ),
            ),
          ),
        ),
      ],
    );
  }
}

const _monthNames = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December',
];

/// The dates the web page shows as `02 Nov 2026`, read off the ISO day the API sends.
String _pretty(String? iso) {
  if (iso == null || iso.length < 10) return iso ?? '—';
  final d = DateTime.tryParse(iso);
  if (d == null) return iso;
  return '${d.day.toString().padLeft(2, '0')} ${_monthNames[d.month - 1]} ${d.year}';
}
