import 'package:flutter/material.dart';
import '../models/account_models.dart';
import '../models/member_models.dart';
import '../services/api_client.dart';
import '../widgets/ui.dart';

/// My leave — the days an E-Fundi has left of each type, and an application for Human Resources
/// to answer. A CTP trainee holds no post, so the page never opens for one, exactly as on the portal.
class LeaveScreen extends StatefulWidget {
  final ApiClient api;
  final Board board;
  const LeaveScreen({super.key, required this.api, required this.board});

  @override
  State<LeaveScreen> createState() => _LeaveScreenState();
}

class _LeaveScreenState extends State<LeaveScreen> {
  LeaveBoard? _data;
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
      final b = await widget.api.leave();
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

  Future<void> _book() async {
    final b = _data!;
    final body = await showFormSheet(
      context,
      title: 'Book leave',
      subtitle: 'Only the days you have left are booked. Human Resources answers every application.',
      cta: 'Send to Human Resources',
      fields: [
        FieldSpec.dropdown('type', 'Leave type',
            {for (final e in b.balances) e.type: '${e.name} — ${_days(e.left)} left'}),
        FieldSpec('starts_on', 'First day',
            kind: FieldKind.date, futureDate: true),
        FieldSpec('ends_on', 'Last day',
            kind: FieldKind.date, futureDate: true,
            helper: 'Days are counted from the first to the last, both included.'),
        FieldSpec('cover_for', 'Who covers your work', required: false,
            helper: 'A colleague, or nothing booked yet'),
        FieldSpec('phone', 'Reachable on', required: false, helper: '07********'),
        FieldSpec('reason', 'Reason',
            kind: FieldKind.multiline, required: false, maxLines: 2,
            helper: 'Anything the desk should know'),
      ],
    );
    if (body == null) return;
    await _post(() => widget.api.applyLeave(body));
  }

  Future<void> _withdraw(LeaveRow row) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('Withdraw application?'),
        content: Text('Withdraw the application for ${row.period}?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Keep it')),
          FilledButton(onPressed: () => Navigator.pop(context, true),
              child: const Text('Withdraw')),
        ],
      ),
    );
    if (ok != true) return;
    await _post(() => widget.api.cancelLeave(row.id));
  }

  Future<void> _post(Future<String> Function() send) async {
    try {
      final message = await send();
      if (!mounted) return;
      say(context, message);
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
    final b = _data!;
    final waiting = b.history.where((h) => h.status == 'pending').length;

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(14),
        children: [
          SectionCard(
            title: 'Your leave',
            subtitle: 'The days you have left of each type, and an application for Human Resources '
                'to answer. Annual leave builds by 1.75 days for every month worked; the other '
                'types are given in full for the year.',
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    _stat('Annual left', _days(b.balanceOf('annual')?.left ?? 0)),
                    _stat('Sick left', _days(b.balanceOf('sick')?.left ?? 0)),
                    _stat('Waiting on the desk', '$waiting'),
                    _stat('Working since', b.joined),
                  ],
                ),
                const SizedBox(height: 12),
                Align(
                  alignment: Alignment.centerLeft,
                  child: FilledButton.icon(
                    onPressed: _book,
                    icon: const Icon(Icons.add, size: 18),
                    label: const Text('Book leave'),
                  ),
                ),
              ],
            ),
          ),
          SectionCard(
            title: 'What you have left',
            subtitle: 'Nothing here is kept on a second register — the days fall out of the '
                'applications on this account, so a week Human Resources declines comes back.',
            child: Column(
              children: [
                for (final t in b.balances) _balance(t),
              ],
            ),
          ),
          SectionCard(
            title: 'Your applications',
            subtitle: b.history.isEmpty
                ? 'No leave has been booked from this account.'
                : 'A withdrawn or declined week stays on the register with the note that went with '
                    'it — nothing is deleted.',
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                for (final row in b.history) _history(row),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _stat(String label, String value) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
        decoration: BoxDecoration(color: soft, borderRadius: BorderRadius.circular(16)),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(value,
                style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14, color: greenDark)),
            Text(label, style: const TextStyle(fontSize: 11, color: muted)),
          ],
        ),
      );

  Widget _balance(LeaveBalance t) {
    final spent = t.left <= 0;
    return RecordRow(
      title: t.name,
      subtitle: '${_days(t.left)} left · ${_days(t.taken)} taken · ${_days(t.pending)} pending '
          '· ${_days(t.entitled)} entitled',
      meta: [
        if (t.note.isNotEmpty) t.note,
        if (t.accrualLine.isNotEmpty)
          t.accrualLine
        else
          '${_days(t.entitled)} days a year',
      ].join(' · '),
      trailing: Tag(spent ? 'All used' : '${_days(t.left)} left',
          color: spent ? muted : green),
    );
  }

  Widget _history(LeaveRow row) {
    final decided = [
      row.decisionNote,
      row.decidedBy == null ? null : '${row.decidedBy}${row.decidedAt == null ? '' : ' · ${row.decidedAt}'}',
    ].where((x) => x != null && x.isNotEmpty).join(' — ');

    return RecordRow(
      title: '${row.typeLabel} — ${row.days} '
          '${double.tryParse(row.days) == 1 ? 'day' : 'days'}',
      subtitle: row.period,
      meta: [
        row.appliedOn == null ? null : 'Applied ${row.appliedOn}',
        row.coverFor == null ? null : 'Cover: ${row.coverFor}',
        row.reason,
        decided.isEmpty ? null : decided,
      ].where((x) => x != null && x.toString().isNotEmpty).join(' · '),
      trailing: Wrap(
        spacing: 6,
        runSpacing: 6,
        crossAxisAlignment: WrapCrossAlignment.center,
        children: [
          Tag(row.statusLabel,
              color: row.approved
                  ? green
                  : row.rejected
                      ? danger
                      : row.status == 'cancelled'
                          ? muted
                          : warn),
          if (row.canCancel)
            TextButton(onPressed: () => _withdraw(row), child: const Text('Withdraw')),
        ],
      ),
    );
  }
}

/// A number a person counts with: 5, 5.5, 1.75 — never 5.5000000001, the way the web prints it.
String _days(double value) {
  final rounded = (value * 100).round() / 100;
  var text = rounded.toStringAsFixed(2);
  if (text.endsWith('00')) {
    text = text.substring(0, text.length - 3);
  } else if (text.endsWith('0')) {
    text = text.substring(0, text.length - 1);
  }
  return text;
}
