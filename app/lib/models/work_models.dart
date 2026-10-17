/// The worker's side — My work orders (Module 7), Items to put right (Module 8) and
/// My repair jobs (Module 9).
library;

class OrderSummary {
  final int id;
  final String reference;
  final String? project;
  final String? projectReference;
  final String? hirer;
  final String status;
  final String statusLabel;
  final int percent;
  final String? startOn;
  final String? deadline;
  final String? county;
  final String? next;
  final bool canAccept;
  final bool canLog;
  final bool canComplete;

  OrderSummary({
    required this.id,
    required this.reference,
    required this.project,
    required this.projectReference,
    required this.hirer,
    required this.status,
    required this.statusLabel,
    required this.percent,
    required this.startOn,
    required this.deadline,
    required this.county,
    required this.next,
    required this.canAccept,
    required this.canLog,
    required this.canComplete,
  });

  factory OrderSummary.fromJson(Map<String, dynamic> j) => OrderSummary(
        id: _i(j['id']),
        reference: (j['reference'] ?? '') as String,
        project: j['project'] as String?,
        projectReference: j['project_reference'] as String?,
        hirer: j['hirer'] as String?,
        status: (j['status'] ?? '') as String,
        statusLabel: (j['status_label'] ?? j['status'] ?? '') as String,
        percent: _i(j['percent']),
        startOn: j['start_on'] as String?,
        deadline: j['deadline'] as String?,
        county: j['county'] as String?,
        next: j['next'] as String?,
        canAccept: j['can_accept'] == true,
        canLog: j['can_log'] == true,
        canComplete: j['can_complete'] == true,
      );
}

class WorkBoard {
  final bool isCtp;
  final Map<String, String> statuses;
  final List<OrderSummary> orders;
  final int waiting;
  final int working;
  final int questions;
  final int done;

  WorkBoard({
    required this.isCtp,
    required this.statuses,
    required this.orders,
    required this.waiting,
    required this.working,
    required this.questions,
    required this.done,
  });

  factory WorkBoard.fromJson(Map<String, dynamic> j) => WorkBoard(
        isCtp: j['is_ctp'] == true,
        statuses: _options(j['statuses']),
        orders: _parse(j['orders'], OrderSummary.fromJson),
        waiting: _i(j['waiting']),
        working: _i(j['working']),
        questions: _i(j['questions']),
        done: _i(j['done']),
      );
}

class Milestone {
  final int id;
  final String name;
  final int share;
  final String amount;
  final String? dueOn;
  final String status;
  final String statusLabel;
  final String? signedOn;
  final String? signedBy;
  final String? note;

  Milestone({
    required this.id,
    required this.name,
    required this.share,
    required this.amount,
    required this.dueOn,
    required this.status,
    required this.statusLabel,
    required this.signedOn,
    required this.signedBy,
    required this.note,
  });

  factory Milestone.fromJson(Map<String, dynamic> j) => Milestone(
        id: _i(j['id']),
        name: (j['name'] ?? '') as String,
        share: _i(j['share']),
        amount: (j['amount'] ?? '') as String,
        dueOn: j['due_on'] as String?,
        status: (j['status'] ?? '') as String,
        statusLabel: (j['status_label'] ?? j['status'] ?? '') as String,
        signedOn: j['signed_on'] as String?,
        signedBy: j['signed_by'] as String?,
        note: j['note'] as String?,
      );
}

class ProvidedMaterial {
  final String name;
  final String quantity;
  final String? unit;
  final String? issuedOn;
  final String value;

  ProvidedMaterial({
    required this.name,
    required this.quantity,
    required this.unit,
    required this.issuedOn,
    required this.value,
  });

  factory ProvidedMaterial.fromJson(Map<String, dynamic> j) => ProvidedMaterial(
        name: (j['name'] ?? '') as String,
        quantity: (j['quantity'] ?? '') as String,
        unit: j['unit'] as String?,
        issuedOn: j['issued_on'] as String?,
        value: (j['value'] ?? '') as String,
      );
}

class LogEntry {
  final int id;
  final String kind;
  final String? on;
  final int? percent;
  final String? note;
  final String? by;
  final bool hasPhoto;

  LogEntry({
    required this.id,
    required this.kind,
    required this.on,
    required this.percent,
    required this.note,
    required this.by,
    required this.hasPhoto,
  });

  factory LogEntry.fromJson(Map<String, dynamic> j) => LogEntry(
        id: _i(j['id']),
        kind: (j['kind'] ?? '') as String,
        on: j['on'] as String?,
        percent: j['percent'] == null ? null : _i(j['percent']),
        note: j['note'] as String?,
        by: j['by'] as String?,
        hasPhoto: j['has_photo'] == true,
      );
}

class Dispute {
  final int id;
  final String? issue;
  final String? raisedOn;
  final String? timeLabel;
  final bool awaitingWorker;
  final bool hasEvidence;
  final String? response;
  final String? respondedOn;
  final String? mediation;
  final String? outcome;
  final String? outcomeNote;
  final String? penalty;
  final String? decidedOn;

  Dispute({
    required this.id,
    required this.issue,
    required this.raisedOn,
    required this.timeLabel,
    required this.awaitingWorker,
    required this.hasEvidence,
    required this.response,
    required this.respondedOn,
    required this.mediation,
    required this.outcome,
    required this.outcomeNote,
    required this.penalty,
    required this.decidedOn,
  });

  factory Dispute.fromJson(Map<String, dynamic> j) => Dispute(
        id: _i(j['id']),
        issue: j['issue'] as String?,
        raisedOn: j['raised_on'] as String?,
        timeLabel: j['time_label'] as String?,
        awaitingWorker: j['awaiting_worker'] == true,
        hasEvidence: j['has_evidence'] == true,
        response: j['response'] as String?,
        respondedOn: j['responded_on'] as String?,
        mediation: j['mediation'] as String?,
        outcome: j['outcome'] as String?,
        outcomeNote: j['outcome_note'] as String?,
        penalty: j['penalty'] as String?,
        decidedOn: j['decided_on'] as String?,
      );
}

class PayoutPeek {
  final String reference;
  final String stageLabel;
  final String net;
  final String? source;

  PayoutPeek({
    required this.reference,
    required this.stageLabel,
    required this.net,
    required this.source,
  });

  factory PayoutPeek.fromJson(Map<String, dynamic> j) => PayoutPeek(
        reference: (j['reference'] ?? '') as String,
        stageLabel: (j['stage_label'] ?? j['stage'] ?? '') as String,
        net: (j['net'] ?? '') as String,
        source: j['source'] as String?,
      );
}

/// GET /api/work/{order} — the worker's own sheet for one order.
class OrderSheet {
  final OrderSummary order;
  final bool isCtp;
  final int window;
  final String? next;
  final int percent;
  final PayoutPeek? payout;
  final Dispute? dispute;
  final List<Milestone> milestones;
  final List<ProvidedMaterial> materials;
  final List<LogEntry> log;
  final List<Dispute> disputes;
  final String? scope;
  final String? deliverables;
  final String? schedule;
  final String? split;
  final String? agreed;
  final String? materialsNote;
  final String? materialsPlanned;
  final String? materialsIssued;
  final String? signature;
  final String? closureNote;
  final String? confirmedBy;
  final int milestonesOutstanding;

  OrderSheet({
    required this.order,
    required this.isCtp,
    required this.window,
    required this.next,
    required this.percent,
    required this.payout,
    required this.dispute,
    required this.milestones,
    required this.materials,
    required this.log,
    required this.disputes,
    required this.scope,
    required this.deliverables,
    required this.schedule,
    required this.split,
    required this.agreed,
    required this.materialsNote,
    required this.materialsPlanned,
    required this.materialsIssued,
    required this.signature,
    required this.closureNote,
    required this.confirmedBy,
    required this.milestonesOutstanding,
  });

  /// The detail payload rides on the same `order` object as the summary fields.
  factory OrderSheet.fromJson(Map<String, dynamic> j) {
    final raw = Map<String, dynamic>.from((j['order'] ?? {}) as Map);

    return OrderSheet(
      order: OrderSummary.fromJson(raw),
      isCtp: j['is_ctp'] == true,
      window: _i(j['window']),
      next: j['next'] as String?,
      percent: _i(j['percent']),
      payout: j['payout'] == null
          ? null
          : PayoutPeek.fromJson(Map<String, dynamic>.from(j['payout'] as Map)),
      dispute: j['dispute'] == null
          ? null
          : Dispute.fromJson(Map<String, dynamic>.from(j['dispute'] as Map)),
      milestones: _parse(j['milestones'], Milestone.fromJson),
      materials: _parse(j['materials'], ProvidedMaterial.fromJson),
      log: _parse(j['log'], LogEntry.fromJson),
      disputes: _parse(j['disputes'], Dispute.fromJson),
      scope: raw['scope'] as String?,
      deliverables: raw['deliverables'] as String?,
      schedule: raw['schedule'] as String?,
      split: raw['split'] as String?,
      agreed: raw['agreed'] as String?,
      materialsNote: raw['materials_note'] as String?,
      materialsPlanned: raw['materials_planned'] as String?,
      materialsIssued: raw['materials_issued'] as String?,
      signature: raw['signature'] as String?,
      closureNote: raw['closure_note'] as String?,
      confirmedBy: raw['confirmed_by'] as String?,
      milestonesOutstanding: _i(raw['milestones_outstanding']),
    );
  }
}

class FixItem {
  final int id;
  final String item;
  final String result;
  final String? note;
  final String? project;
  final String? order;
  final String? inspectedOn;
  final String? proofNote;
  final String? proofOn;
  final bool hasProof;
  final String? recheckedOn;
  final bool cleared;
  final bool canProve;

  FixItem({
    required this.id,
    required this.item,
    required this.result,
    required this.note,
    required this.project,
    required this.order,
    required this.inspectedOn,
    required this.proofNote,
    required this.proofOn,
    required this.hasProof,
    required this.recheckedOn,
    required this.cleared,
    required this.canProve,
  });

  factory FixItem.fromJson(Map<String, dynamic> j) => FixItem(
        id: _i(j['id']),
        item: (j['item'] ?? '') as String,
        result: (j['result'] ?? '') as String,
        note: j['note'] as String?,
        project: j['project'] as String?,
        order: j['order'] as String?,
        inspectedOn: j['inspected_on'] as String?,
        proofNote: j['proof_note'] as String?,
        proofOn: j['proof_on'] as String?,
        hasProof: j['has_proof'] == true,
        recheckedOn: j['rechecked_on'] as String?,
        cleared: j['cleared'] == true,
        canProve: j['can_prove'] == true,
      );
}

class FixBoard {
  final List<FixItem> fixes;
  final List<FixItem> cleared;
  FixBoard({required this.fixes, required this.cleared});

  factory FixBoard.fromJson(Map<String, dynamic> j) => FixBoard(
        fixes: _parse(j['fixes'], FixItem.fromJson),
        cleared: _parse(j['cleared'], FixItem.fromJson),
      );
}

class TechJob {
  final int id;
  final String reference;
  final String title;
  final String? fault;
  final String priority;
  final String status;
  final String statusLabel;
  final String? asset;
  final String? resolution;
  final String? resolvedOn;
  final bool canResolve;

  TechJob({
    required this.id,
    required this.reference,
    required this.title,
    required this.fault,
    required this.priority,
    required this.status,
    required this.statusLabel,
    required this.asset,
    required this.resolution,
    required this.resolvedOn,
    required this.canResolve,
  });

  factory TechJob.fromJson(Map<String, dynamic> j) => TechJob(
        id: _i(j['id']),
        reference: (j['reference'] ?? '') as String,
        title: (j['title'] ?? '') as String,
        fault: j['fault'] as String?,
        priority: (j['priority'] ?? '') as String,
        status: (j['status'] ?? '') as String,
        statusLabel: (j['status_label'] ?? j['status'] ?? '') as String,
        asset: j['asset'] as String?,
        resolution: j['resolution'] as String?,
        resolvedOn: j['resolved_on'] as String?,
        canResolve: j['can_resolve'] == true,
      );
}

List<T> _parse<T>(dynamic raw, T Function(Map<String, dynamic>) fromJson) =>
    (raw as List? ?? []).map((e) => fromJson(Map<String, dynamic>.from(e as Map))).toList();

Map<String, String> _options(dynamic raw) =>
    (raw as Map? ?? {}).map((k, v) => MapEntry('$k', '$v'));

int _i(dynamic v) => v is int ? v : (v is num ? v.toInt() : int.tryParse('$v') ?? 0);
