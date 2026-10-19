import 'package:flutter/material.dart';
import '../models/work_models.dart';
import '../services/api_client.dart';
import '../widgets/ui.dart';

/// One work order as the worker's own sheet — the same cards, in the same order, with the
/// portal's own sentences from web/resources/views/portal/workorder.blade.php.
class WorkDetailScreen extends StatefulWidget {
  final ApiClient api;
  final int orderId;
  const WorkDetailScreen({super.key, required this.api, required this.orderId});

  @override
  State<WorkDetailScreen> createState() => _WorkDetailScreenState();
}

class _WorkDetailScreenState extends State<WorkDetailScreen> {
  OrderSheet? _sheet;
  bool _loading = false;
  bool _busy = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final first = _sheet == null;
    if (first) setState(() { _loading = true; _error = null; });
    try {
      final sheet = await widget.api.workSheet(widget.orderId);
      if (mounted) setState(() { _sheet = sheet; _loading = false; _error = null; });
    } catch (e) {
      if (!mounted) return;
      if (first) {
        setState(() { _error = '$e'; _loading = false; });
      } else {
        showError(context, e);
      }
    }
  }

  Future<void> _write(Future<String> Function() call) async {
    setState(() => _busy = true);
    try {
      final message = await call();
      if (!mounted) return;
      setState(() => _busy = false);
      say(context, message);
      await _load();
    } catch (e) {
      if (mounted) {
        setState(() => _busy = false);
        showError(context, e);
      }
    }
  }

  Future<void> _openSheet(
    String title, {
    String? subtitle,
    required List<FieldSpec> fields,
    required String cta,
    required Future<String> Function(Map<String, dynamic> body) send,
  }) async {
    final body = await showFormSheet(context, title: title, subtitle: subtitle, fields: fields, cta: cta);
    if (body == null || !mounted) return;
    await _write(() => send(body));
  }

  void _accept(OrderSheet sheet) => _openSheet(
        'Accept the work order',
        subtitle: 'Accepting is your signature on the scope, the deliverables and the schedule above '
            '(§10.4 step 1). Confirm the day you are actually starting — the deadline and every stage '
            'date are measured from it.',
        fields: [
          FieldSpec('signature', 'Type your full name as your signature',
              helper: 'Minimally three letters — the order is signed when you accept it.'),
          FieldSpec('start_on', 'I am starting on',
              kind: FieldKind.date, initial: sheet.order.startOn),
          FieldSpec('accept_note', 'Anything the office should know',
              kind: FieldKind.multiline,
              maxLines: 2,
              required: false,
              helper: 'Who is with you on the job, what the site needs first'),
        ],
        cta: 'Accept and sign the order',
        send: (body) => widget.api.acceptOrder(widget.orderId, body),
      );

  void _logDay(OrderSheet sheet) => _openSheet(
        'Log a day',
        subtitle: 'Say what was done — a percentage with no words behind it is not a progress report '
            '(§10.4 step 3).',
        fields: [
          FieldSpec('entry_date', 'Which day', kind: FieldKind.date),
          FieldSpec('percent', 'Percentage complete',
              kind: FieldKind.integer,
              required: false,
              initial: '${sheet.percent}',
              helper: 'the log keeps the highest figure you report'),
          FieldSpec('note', 'What you did that day',
              kind: FieldKind.multiline, maxLines: 3, helper: 'Where you worked, what was finished, what is holding you up'),
          FieldSpec.photo('photo', 'Photo of the work', photoHelper: 'A jpg or png of the work, up to 8 MB.'),
        ],
        cta: 'Add to the log',
        send: (body) => widget.api.logWorkDay(widget.orderId, {'kind': 'progress', ...body}),
      );

  void _requestCompletion(OrderSheet sheet) => _openSheet(
        'Request completion',
        subtitle: 'Write what was completed. The hirer reads this when they inspect (§10.4 step 6).',
        fields: [
          FieldSpec('note', 'What was completed',
              kind: FieldKind.multiline,
              maxLines: 3,
              helper: 'Each deliverable and how it was finished, and anything left for the hirer to know'),
          FieldSpec.photo('photo', 'Final photo', photoHelper: 'The last picture of the finished work, up to 8 MB.'),
        ],
        cta: 'Request the completion',
        send: (body) => widget.api.requestCompletion(widget.orderId, body),
      );

  void _answerDispute(Dispute dispute) => _openSheet(
        'Answer the dispute',
        subtitle: 'Say your side of it — the Programme Manager reads both statements before mediating (§10.6).',
        fields: [
          FieldSpec('response', 'Your side of it',
              kind: FieldKind.multiline,
              maxLines: 4,
              helper: 'What happened, what you can still fix, and when'),
        ],
        cta: 'Send my answer',
        send: (body) => widget.api.answerDispute(
            widget.orderId, dispute.id, (body['response'] ?? '') as String),
      );

  Future<void> _showFile(Future<InlineFile> Function() read) async {
    try {
      final file = await read();
      if (mounted) await showPicture(context, file);
    } catch (e) {
      if (mounted) showError(context, e);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(_sheet?.order.reference ?? 'Work order'),
        actions: [
          IconButton(onPressed: _load, icon: const Icon(Icons.refresh)),
        ],
      ),
      body: _body(),
    );
  }

  Widget _body() {
    if (_loading && _sheet == null) return const LoadingView();
    if (_error != null && _sheet == null) {
      return ErrorRetry(message: _error!, onRetry: _load);
    }
    final sheet = _sheet!;

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(14, 14, 14, 28),
        children: [
          _intro(sheet),
          _signedUpFor(sheet),
          if (sheet.payout != null) _payoutCard(sheet.payout!),
          _stagesCard(sheet),
          _materialsCard(sheet),
          _logCard(sheet),
          if (sheet.dispute != null) _disputeCard(sheet, sheet.dispute!),
          _historyCard(sheet),
          _finishCard(sheet),
          _rulesCard(),
        ],
      ),
    );
  }

  Widget _intro(OrderSheet sheet) {
    final order = sheet.order;
    final site = [
      if ((order.county ?? '').isNotEmpty) order.county,
      if ((order.hirer ?? '').isNotEmpty) 'for ${order.hirer}',
    ].join(' · ');

    return Card(
      margin: const EdgeInsets.only(bottom: 14),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: cardBorder,
      ),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 14, 16, 16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Text(
                      '${order.reference} — ${order.project ?? 'your site'}',
                      style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16, color: ink)),
                ),
                Tag(order.statusLabel, color: _pill(order.status)),
              ],
            ),
            if (site.isNotEmpty)
              Padding(
                padding: const EdgeInsets.only(top: 5),
                child: Text(site, style: const TextStyle(color: muted, fontSize: 12.5)),
              ),
            Padding(
              padding: const EdgeInsets.only(top: 3),
              child: Text(
                '${_day(order.startOn) ?? 'start not confirmed'} → ${_day(order.deadline) ?? 'no deadline'}',
                style: const TextStyle(color: muted, fontSize: 12.5),
              ),
            ),
            const SizedBox(height: 12),
            ClipRRect(
              borderRadius: BorderRadius.circular(8),
              child: LinearProgressIndicator(
                value: sheet.percent / 100,
                minHeight: 8,
                backgroundColor: soft,
                valueColor: const AlwaysStoppedAnimation(green),
              ),
            ),
            const SizedBox(height: 5),
            Text('${sheet.percent}% logged',
                style: const TextStyle(fontSize: 12, color: muted, fontWeight: FontWeight.w700)),
            if ((sheet.next ?? '').isNotEmpty)
              Padding(
                padding: const EdgeInsets.only(top: 10),
                child: Text.rich(
                  TextSpan(
                    text: 'Where it stands: ',
                    style: const TextStyle(fontWeight: FontWeight.w800, color: ink, fontSize: 13),
                    children: [TextSpan(text: sheet.next, style: const TextStyle(fontWeight: FontWeight.w400, color: muted))],
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget _signedUpFor(OrderSheet sheet) {
    final order = sheet.order;

    return SectionCard(
      title: 'What you signed up for',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          LabeledValue(label: 'Scope of work', value: sheet.scope ?? 'not written', strong: false),
          LabeledValue(label: 'Deliverables', value: sheet.deliverables ?? 'not written', strong: false),
          LabeledValue(label: 'Agreed for the job', value: sheet.agreed ?? 'not stated'),
          _hint([
            if ((sheet.schedule ?? '').isNotEmpty) sheet.schedule,
            if ((sheet.split ?? '').isNotEmpty) 'released on ${sheet.split}',
          ].join(' — ')),
          LabeledValue(label: 'Materials', value: sheet.materialsNote ?? 'the project lists none', strong: false),
          if ((sheet.signature ?? '').isNotEmpty) ...[
            const SizedBox(height: 4),
            LabeledValue(label: 'You accepted', value: sheet.signature!),
            _hint('Your signature is on this order — the day it was signed is kept by the office.'),
          ],
          if (order.canAccept) ...[
            const SizedBox(height: 12),
            FilledButton.icon(
              onPressed: _busy ? null : () => _accept(sheet),
              icon: const Icon(Icons.draw_outlined, size: 18),
              label: const Text('Accept and sign the order'),
            ),
          ],
        ],
      ),
    );
  }

  Widget _payoutCard(PayoutPeek payout) => SectionCard(
        title: 'The payout on this order',
        subtitle: 'Finance calculates and releases it — the signature on a stage is not the payment.',
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            LabeledValue(label: 'Payout', value: payout.reference),
            LabeledValue(label: 'Stage', value: payout.stageLabel),
            LabeledValue(label: 'Net to you', value: payout.net),
            if ((payout.source ?? '').isNotEmpty)
              LabeledValue(label: 'Source', value: payout.source!, strong: false),
          ],
        ),
      );

  Widget _stagesCard(OrderSheet sheet) {
    final outstanding = sheet.milestonesOutstanding;

    return SectionCard(
      title: 'Stages and sign-offs (§10.4 step 4)',
      subtitle: outstanding > 0 ? '$outstanding stage(s) still waiting on a supervisor\'s visit' : null,
      child: sheet.milestones.isEmpty
          ? Text(
              'This order is on the ${(sheet.schedule ?? 'simple').toLowerCase()} schedule, so it has no '
              'stage to be signed off — the money is settled on completion.',
              style: const TextStyle(color: muted, fontSize: 13))
          : Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                for (final m in sheet.milestones)
                  RecordRow(
                    title: m.name,
                    subtitle: '${m.share}% · ${m.amount}',
                    meta: [
                      if (m.dueOn != null) 'Due ${_day(m.dueOn)}',
                      m.statusLabel,
                      if (m.signedOn != null) 'signed ${_day(m.signedOn)}',
                      if ((m.signedBy ?? '').isNotEmpty) m.signedBy,
                      if ((m.note ?? '').isNotEmpty) m.note,
                    ].where((x) => (x ?? '').toString().isNotEmpty).join(' · '),
                    trailing: m.status == 'signed'
                        ? const Icon(Icons.check_circle_outline, color: green, size: 20)
                        : null,
                  ),
                _hint('A signed stage is money the programme has agreed you earned; it is still released by '
                    'Finance after the hirer pays (§14.6), not on the signature alone.'),
              ],
            ),
    );
  }

  Widget _materialsCard(OrderSheet sheet) => SectionCard(
        title: 'Materials the programme is giving you (§10.3)',
        subtitle: 'The storekeeper hands these against your order. What is actually issued is deducted from '
            'your payout — anything not handed over is not deducted, so check the dates on this table.',
        child: sheet.materials.isEmpty
            ? const Text('No material line is listed for this project — ask the office if you were told to expect some.',
                style: TextStyle(color: muted, fontSize: 13))
            : Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  for (final m in sheet.materials)
                    RecordRow(
                      title: m.name,
                      subtitle: '${m.quantity}${(m.unit ?? '').isNotEmpty ? ' ${m.unit}' : ''}',
                      meta: '${m.value} · ${m.issuedOn != null ? 'issued ${_day(m.issuedOn)}' : 'at the store, on assignment'}',
                    ),
                  _hint('Issued so far ${sheet.materialsIssued ?? 'KSh 0'} of the '
                      '${sheet.materialsPlanned ?? 'planned total'} planned.'),
                ],
              ),
      );

  Widget _logCard(OrderSheet sheet) {
    final order = sheet.order;

    return SectionCard(
      title: 'Your log (§10.4 step 3)',
      subtitle: order.canLog
          ? 'Keep your days on the log so the supervisor can see the sequence.'
          : "The log is open while the order is signed and on site."
              "${order.status == 'issued' ? ' Accept the order first.' : ''}",
      trailing: order.canLog
          ? FilledButton(
              onPressed: _busy ? null : () => _logDay(sheet),
              child: const Text('Log a day'),
            )
          : null,
      child: sheet.log.isEmpty
          ? const Padding(
              padding: EdgeInsets.symmetric(vertical: 8),
              child: Text('Nothing logged yet.', style: TextStyle(color: muted, fontSize: 13)),
            )
          : Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                for (final u in sheet.log)
                  RecordRow(
                    title: u.kind,
                    subtitle: u.note ?? '',
                    meta: [
                      if (u.on != null) _day(u.on),
                      if (u.percent != null) '${u.percent}%',
                      u.by,
                    ].where((x) => (x ?? '').toString().isNotEmpty).join(' · '),
                    trailing: u.hasPhoto
                        ? IconButton(
                            tooltip: 'Zoom this photo',
                            icon: const Icon(Icons.photo_camera_outlined, color: green, size: 20),
                            onPressed: () => _showFile(
                                () => widget.api.workUpdatePhoto(widget.orderId, u.id)),
                          )
                        : null,
                  ),
              ],
            ),
    );
  }

  Widget _disputeCard(OrderSheet sheet, Dispute dispute) {
    final canAnswer = dispute.awaitingWorker && !sheet.isCtp;

    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border(
          left: BorderSide(color: danger, width: 4),
          top: BorderSide(color: line),
          right: BorderSide(color: line),
          bottom: BorderSide(color: line),
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text("The hirer's issue — answer it within ${sheet.window} hours",
              style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: ink)),
          const SizedBox(height: 10),
          Text(dispute.issue ?? 'The issue was not written down.', style: const TextStyle(fontSize: 13.5)),
          _hint('Recorded ${_day(dispute.raisedOn) ?? 'on the day of the visit'}'),
          if (dispute.hasEvidence)
            Align(
              alignment: Alignment.centerLeft,
              child: TextButton.icon(
                onPressed: () => _showFile(
                    () => widget.api.disputeEvidence(widget.orderId, dispute.id)),
                icon: const Icon(Icons.attachment_outlined, size: 18),
                label: const Text('open their file'),
              ),
            ),
          if (canAnswer) ...[
            if ((dispute.timeLabel ?? '').isNotEmpty)
              Text(dispute.timeLabel!,
                  style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: ink)),
            const SizedBox(height: 8),
            FilledButton(
              onPressed: _busy ? null : () => _answerDispute(dispute),
              child: const Text('Send my answer'),
            ),
          ] else if ((dispute.response ?? '').isNotEmpty) ...[
            LabeledValue(label: 'Your answer', value: dispute.response!, strong: false),
            _hint('Sent ${_day(dispute.respondedOn) ?? 'on the day'}'),
            LabeledValue(
                label: 'Mediation',
                value: (dispute.mediation ?? '').isEmpty ? 'not yet' : dispute.mediation!,
                strong: false),
            if ((dispute.outcome ?? '').isNotEmpty) ...[
              LabeledValue(label: 'Outcome', value: dispute.outcome!),
              if ((dispute.penalty ?? '').isNotEmpty) LabeledValue(label: 'Deduction', value: dispute.penalty!),
              if ((dispute.outcomeNote ?? '').isNotEmpty)
                LabeledValue(label: 'What they wrote', value: dispute.outcomeNote!, strong: false),
              _hint('Decided ${_day(dispute.decidedOn) ?? 'by the office'}'),
            ],
            _hint('The Programme Manager mediates and a Director decides. The outcome — rework, partial or '
                'full payment, refund, or a deduction — is written on this order and carried onto your '
                'payout (§10.6).'),
          ] else if (sheet.isCtp)
            const Text('An attachment order has no answer box for a trainee; the office settles it with the '
                'hirer and notes the outcome on your record.',
                style: TextStyle(color: muted, fontSize: 13)),
        ],
      ),
    );
  }

  Widget _historyCard(OrderSheet sheet) => SectionCard(
        title: 'Every issue raised on this order',
        child: sheet.disputes.isEmpty
            ? const Text('No issue has been raised on this order.',
                style: TextStyle(color: muted, fontSize: 13))
            : Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  for (final d in sheet.disputes)
                    RecordRow(
                      title: d.issue ?? 'An issue on the work',
                      subtitle: [
                        if ((d.response ?? '').isNotEmpty) 'Your answer: ${d.response}',
                        if ((d.mediation ?? '').isNotEmpty) 'Mediation: ${d.mediation}',
                      ].join('\n'),
                      meta: [
                        'Raised ${_day(d.raisedOn) ?? '—'}',
                        if (d.outcome != null) d.outcome,
                        if (d.penalty != null) d.penalty,
                        if (d.decidedOn != null) 'decided ${_day(d.decidedOn)}',
                      ].where((x) => (x ?? '').toString().isNotEmpty).join(' · '),
                      trailing: d.hasEvidence
                          ? IconButton(
                              tooltip: 'The hirer\'s file',
                              icon: const Icon(Icons.description_outlined, color: green, size: 20),
                              onPressed: () =>
                                  _showFile(() => widget.api.disputeEvidence(widget.orderId, d.id)),
                            )
                          : null,
                    ),
                ],
              ),
      );

  Widget _finishCard(OrderSheet sheet) {
    final order = sheet.order;

    return SectionCard(
      title: 'Finish the job (§10.4 step 5)',
      subtitle: order.canComplete
          ? "When everything on the deliverables list is done, request the completion with the final photos. "
              "The office then arranges the hirer's inspection — they either confirm the work, which starts "
              "your payout, or raise an issue, which comes back to you here."
          : null,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (order.canComplete)
            FilledButton.icon(
              onPressed: _busy ? null : () => _requestCompletion(sheet),
              icon: const Icon(Icons.task_alt_outlined, size: 18),
              label: const Text('Request the completion'),
            )
          else if (sheet.closureNote != null && sheet.closureNote!.isNotEmpty)
            Text(sheet.closureNote!, style: const TextStyle(color: muted, fontSize: 13))
          else
            Text(_waitingWords(sheet), style: const TextStyle(color: muted, fontSize: 13)),
          if ((sheet.confirmedBy ?? '').isNotEmpty) ...[
            const SizedBox(height: 10),
            LabeledValue(label: 'Confirmed by the hirer', value: sheet.confirmedBy!),
          ],
        ],
      ),
    );
  }

  String _waitingWords(OrderSheet sheet) {
    final status = sheet.order.status;
    if (status == 'completion_submitted') {
      return 'You asked for the completion to be inspected; the office is arranging the hirer\'s visit.';
    }
    if (status == 'confirmed') {
      return 'The hirer confirmed the work. The payout is Finance\'s to calculate and release.';
    }
    if (status == 'issued') return 'Accept the order first — the log and the completion request open after your signature.';
    if (status == 'closed') return 'This order is closed.';
    if (status == 'cancelled') return 'The order was stopped.';
    return 'The completion request opens once you have accepted the order and are on site.';
  }

  Widget _rulesCard() => SectionCard(
        title: 'The rules on this order',
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: const [
            _Rule(
                'The agreed amount is what the project was posted with; your payout is that figure minus the '
                'materials actually issued, the service commission and any credit decided against the job (§14.6).'),
            _Rule('Money reaches you only after the hirer pays the organisation, and it is released by a '
                'programme executive.'),
            _Rule('A day you do not log is a day the supervisor cannot sign off — the log is what your payment '
                'is checked against.'),
            _Rule('Being awarded a job puts you on the E-Fundi record as on a job; set yourself free again there '
                'when the order closes.'),
          ],
        ),
      );

  Widget _hint(String text) => Padding(
        padding: const EdgeInsets.only(top: 6),
        child: Text(text, style: const TextStyle(color: muted, fontSize: 12, height: 1.4)),
      );
}

class _Rule extends StatelessWidget {
  final String text;
  const _Rule(this.text);

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(bottom: 8),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Padding(
              padding: EdgeInsets.only(right: 8, top: 3),
              child: Icon(Icons.circle, size: 6, color: green),
            ),
            Expanded(
                child: Text(text, style: const TextStyle(fontSize: 13, color: muted, height: 1.4))),
          ],
        ),
      );
}

Color _pill(String status) => switch (status) {
      'issued' || 'accepted' => warn,
      'in_progress' || 'completion_submitted' => green,
      'confirmed' || 'closed' => greenDark,
      'disputed' || 'cancelled' => danger,
      _ => muted,
    };

final _months = const [
  'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
  'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec',
];

String? _day(String? iso) {
  final d = DateTime.tryParse(iso ?? '');
  if (d == null) return (iso ?? '').isEmpty ? null : iso;
  return '${d.day.toString().padLeft(2, '0')} ${_months[d.month - 1]} ${d.year}';
}
