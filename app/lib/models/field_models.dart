/// The field side of the portal — My communities, My mobilisation, E-Waste collection,
/// Purchase request, My earnings and Training CTPs.
library;

class NeedRow {
  final int id;
  final String title;
  final String type;
  final String typeLabel;
  final String? detail;
  final String statusLabel;
  final String? community;
  final bool hasPhoto;
  final String? on;

  NeedRow({
    required this.id,
    required this.title,
    required this.type,
    required this.typeLabel,
    required this.detail,
    required this.statusLabel,
    required this.community,
    required this.hasPhoto,
    required this.on,
  });

  factory NeedRow.fromJson(Map<String, dynamic> j) => NeedRow(
        id: _i(j['id']),
        title: (j['title'] ?? '') as String,
        type: (j['type'] ?? '') as String,
        typeLabel: (j['type_label'] ?? j['type'] ?? '') as String,
        detail: j['detail'] as String?,
        statusLabel: (j['status_label'] ?? j['status'] ?? '') as String,
        community: j['community'] as String?,
        hasPhoto: j['has_photo'] == true,
        on: j['on'] as String?,
      );
}

class BeneficiaryRow {
  final int id;
  final String name;
  final String typeLabel;
  final String? community;
  final String? project;
  final String? on;

  BeneficiaryRow({
    required this.id,
    required this.name,
    required this.typeLabel,
    required this.community,
    required this.project,
    required this.on,
  });

  factory BeneficiaryRow.fromJson(Map<String, dynamic> j) => BeneficiaryRow(
        id: _i(j['id']),
        name: (j['name'] ?? '') as String,
        typeLabel: (j['type_label'] ?? j['type'] ?? '') as String,
        community: j['community'] as String?,
        project: j['project'] as String?,
        on: j['on'] as String?,
      );
}

class CommunityRow {
  final int id;
  final String name;
  final String? code;
  final String? location;
  final String status;
  final String statusLabel;
  final String role;
  final int ctps;
  final int? population;
  final int? households;
  final List<NeedRow> needs;
  final List<BeneficiaryRow> beneficiaries;

  CommunityRow({
    required this.id,
    required this.name,
    required this.code,
    required this.location,
    required this.status,
    required this.statusLabel,
    required this.role,
    required this.ctps,
    required this.population,
    required this.households,
    required this.needs,
    required this.beneficiaries,
  });

  factory CommunityRow.fromJson(Map<String, dynamic> j) => CommunityRow(
        id: _i(j['id']),
        name: (j['name'] ?? '') as String,
        code: j['code'] as String?,
        location: j['location'] as String?,
        status: (j['status'] ?? '') as String,
        statusLabel: (j['status_label'] ?? j['status'] ?? '') as String,
        role: (j['role'] ?? 'Mobilizer') as String,
        ctps: _i(j['ctps']),
        population: j['population'] == null ? null : _i(j['population']),
        households: j['households'] == null ? null : _i(j['households']),
        needs: _parse(j['needs'], NeedRow.fromJson),
        beneficiaries: _parse(j['beneficiaries'], BeneficiaryRow.fromJson),
      );

  bool get confirmed => status == 'active';
}

class CommunityBoard {
  final Map<String, String> needTypes;
  final Map<String, String> beneficiaryTypes;
  final List<String> counties;
  final List<CommunityRow> communities;
  final List<NeedRow> recent;

  CommunityBoard({
    required this.needTypes,
    required this.beneficiaryTypes,
    required this.counties,
    required this.communities,
    required this.recent,
  });

  factory CommunityBoard.fromJson(Map<String, dynamic> j) => CommunityBoard(
        needTypes: _options(j['need_types']),
        beneficiaryTypes: _options(j['beneficiary_types']),
        counties: _strings(j['counties']),
        communities: _parse(j['communities'], CommunityRow.fromJson),
        recent: _parse(j['recent'], NeedRow.fromJson),
      );

  /// Only the areas this CTP can log against — the same list the web form offers.
  List<CommunityRow> get loggable => communities;
}

class Mobilisation {
  final String? code;
  final String? link;
  final String term;
  final int thisTerm;
  final int lifetime;
  final int next;
  final int percent;
  final String? reward;
  final String? hint;
  final List<Map<String, dynamic>> brought;

  Mobilisation({
    required this.code,
    required this.link,
    required this.term,
    required this.thisTerm,
    required this.lifetime,
    required this.next,
    required this.percent,
    required this.reward,
    required this.hint,
    required this.brought,
  });

  factory Mobilisation.fromJson(Map<String, dynamic> j) => Mobilisation(
        code: j['code'] as String?,
        link: j['link'] as String?,
        term: (j['term'] ?? '') as String,
        thisTerm: _i(j['this_term']),
        lifetime: _i(j['lifetime']),
        next: _i(j['next']),
        percent: _i(j['percent']),
        reward: j['reward'] as String?,
        hint: j['hint'] as String?,
        brought: (j['brought'] as List? ?? [])
            .map((e) => Map<String, dynamic>.from(e as Map))
            .toList(),
      );
}

class CollectionLine {
  final int id;
  final String reference;
  final String item;
  final int quantity;
  final String condition;
  final String status;
  final String statusLabel;
  final String? collectedOn;
  final String? place;
  final String? community;
  final String? weight;
  final String? collector;
  final String? note;
  final String? forwardNote;
  final bool hasPhoto;
  final bool canForward;

  CollectionLine({
    required this.id,
    required this.reference,
    required this.item,
    required this.quantity,
    required this.condition,
    required this.status,
    required this.statusLabel,
    required this.collectedOn,
    required this.place,
    required this.community,
    required this.weight,
    required this.collector,
    required this.note,
    required this.forwardNote,
    required this.hasPhoto,
    required this.canForward,
  });

  factory CollectionLine.fromJson(Map<String, dynamic> j) => CollectionLine(
        id: _i(j['id']),
        reference: (j['reference'] ?? '') as String,
        item: (j['item'] ?? '') as String,
        quantity: _i(j['quantity']),
        condition: (j['condition'] ?? '') as String,
        status: (j['status'] ?? '') as String,
        statusLabel: (j['status_label'] ?? j['status'] ?? '') as String,
        collectedOn: j['collected_on'] as String?,
        place: j['place'] as String?,
        community: j['community'] as String?,
        weight: j['weight'] as String?,
        collector: j['collector'] as String?,
        note: j['note'] as String?,
        forwardNote: j['forward_note'] as String?,
        hasPhoto: j['has_photo'] == true,
        canForward: j['can_forward'] == true,
      );
}

class PlaceOption {
  final int id;
  final String name;
  PlaceOption({required this.id, required this.name});
  factory PlaceOption.fromJson(Map<String, dynamic> j) => PlaceOption(
      id: _i(j['id']),
      name: '${j['name'] ?? j['title'] ?? j['full_name'] ?? ''}');
}

class EwasteBoard {
  final bool isCtp;
  final Map<String, String> itemTypes;
  final Map<String, String> conditions;
  final Map<String, String> statuses;
  final List<String> counties;
  final List<PlaceOption> communities;
  final List<CollectionLine> mine;
  final List<CollectionLine> waiting;
  final Map<String, String> totals;

  EwasteBoard({
    required this.isCtp,
    required this.itemTypes,
    required this.conditions,
    required this.statuses,
    required this.counties,
    required this.communities,
    required this.mine,
    required this.waiting,
    required this.totals,
  });

  factory EwasteBoard.fromJson(Map<String, dynamic> j) {
    final t = (j['totals'] as Map? ?? {}).map((k, v) => MapEntry('$k', '$v'));
    return EwasteBoard(
      isCtp: j['is_ctp'] == true,
      itemTypes: _options(j['item_types']),
      conditions: _options(j['conditions']),
      statuses: _options(j['statuses']),
      counties: _strings(j['counties']),
      communities: _parse(j['communities'], PlaceOption.fromJson),
      mine: _parse(j['mine'], CollectionLine.fromJson),
      waiting: _parse(j['waiting'], CollectionLine.fromJson),
      totals: t,
    );
  }

  int get waitingCount => int.tryParse(totals['waiting'] ?? '0') ?? 0;
}

class PurchaseLine {
  final int id;
  final String reference;
  final String itemName;
  final String quantity;
  final String budget;
  final String charge;
  final String cost;
  final String status;
  final String statusLabel;
  final int step;
  final bool closed;
  final String? neededBy;
  final String? decision;
  final String? justification;
  final String? raisedOn;

  PurchaseLine({
    required this.id,
    required this.reference,
    required this.itemName,
    required this.quantity,
    required this.budget,
    required this.charge,
    required this.cost,
    required this.status,
    required this.statusLabel,
    required this.step,
    required this.closed,
    required this.neededBy,
    required this.decision,
    required this.justification,
    required this.raisedOn,
  });

  factory PurchaseLine.fromJson(Map<String, dynamic> j) => PurchaseLine(
        id: _i(j['id']),
        reference: (j['reference'] ?? '') as String,
        itemName: (j['item_name'] ?? '') as String,
        quantity: (j['quantity'] ?? '') as String,
        budget: (j['budget'] ?? '') as String,
        charge: (j['charge'] ?? '') as String,
        cost: (j['cost'] ?? '') as String,
        status: (j['status'] ?? '') as String,
        statusLabel: (j['status_label'] ?? j['status'] ?? '') as String,
        step: _i(j['step']),
        closed: j['closed'] == true,
        neededBy: j['needed_by'] as String?,
        decision: j['decision'] as String?,
        justification: j['justification'] as String?,
        raisedOn: j['raised_on'] as String?,
      );
}

class ProcurementBoard {
  final bool isCtp;
  final Map<String, String> units;
  final Map<String, String> categories;
  final Map<String, String> statuses;
  final List<String> path;
  final List<PurchaseLine> mine;
  final List<PlaceOption> awarded;
  final Map<String, int> totals;

  ProcurementBoard({
    required this.isCtp,
    required this.units,
    required this.categories,
    required this.statuses,
    required this.path,
    required this.mine,
    required this.awarded,
    required this.totals,
  });

  factory ProcurementBoard.fromJson(Map<String, dynamic> j) {
    final steps = (j['path'] as List? ?? [])
        .map((e) => '${(e as Map)['action'] ?? ''}')
        .toList();
    return ProcurementBoard(
      isCtp: j['is_ctp'] == true,
      units: _options(j['units']),
      categories: _options(j['categories']),
      statuses: _options(j['statuses']),
      path: steps,
      mine: _parse(j['mine'], PurchaseLine.fromJson),
      awarded: _parse(j['awarded'], PlaceOption.fromJson),
      totals: (j['totals'] as Map? ?? {}).map((k, v) => MapEntry('$k', _i(v))),
    );
  }
}

class PayoutLine {
  final int id;
  final String reference;
  final String stream;
  final String streamLabel;
  final String stage;
  final String stageLabel;
  final String? source;
  final String gross;
  final String net;
  final String? calculation;
  final String? method;
  final String? releasedOn;
  final int step;
  final List<String> path;

  PayoutLine({
    required this.id,
    required this.reference,
    required this.stream,
    required this.streamLabel,
    required this.stage,
    required this.stageLabel,
    required this.source,
    required this.gross,
    required this.net,
    required this.calculation,
    required this.method,
    required this.releasedOn,
    required this.step,
    required this.path,
  });

  factory PayoutLine.fromJson(Map<String, dynamic> j) => PayoutLine(
        id: _i(j['id']),
        reference: (j['reference'] ?? '') as String,
        stream: (j['stream'] ?? '') as String,
        streamLabel: (j['stream_label'] ?? j['stream'] ?? '') as String,
        stage: (j['stage'] ?? '') as String,
        stageLabel: (j['stage_label'] ?? j['stage'] ?? '') as String,
        source: j['source'] as String?,
        gross: (j['gross'] ?? '') as String,
        net: (j['net'] ?? '') as String,
        calculation: j['calculation'] as String?,
        method: j['method'] as String?,
        releasedOn: j['released_on'] as String?,
        step: _i(j['step']),
        path: _strings(j['path']),
      );

  bool get released => stage == 'released';
  bool get rejected => stage == 'rejected';
}

class LedgerRow {
  final int id;
  final String? date;
  final String label;
  final String kind;
  final String direction;
  final String amount;

  LedgerRow({
    required this.id,
    required this.date,
    required this.label,
    required this.kind,
    required this.direction,
    required this.amount,
  });

  factory LedgerRow.fromJson(Map<String, dynamic> j) => LedgerRow(
        id: _i(j['id']),
        date: j['date'] as String?,
        label: (j['label'] ?? '') as String,
        kind: (j['kind'] ?? '') as String,
        direction: (j['direction'] ?? 'in') as String,
        amount: (j['amount'] ?? '') as String,
      );

  bool get isIncome => direction == 'in';
}

class EarningsBoard {
  final bool isTrainer;
  final List<PayoutLine> payouts;
  final List<LedgerRow> ledger;
  final Map<String, String> totals;

  EarningsBoard({
    required this.isTrainer,
    required this.payouts,
    required this.ledger,
    required this.totals,
  });

  factory EarningsBoard.fromJson(Map<String, dynamic> j) => EarningsBoard(
        isTrainer: j['is_trainer'] == true,
        payouts: _parse(j['payouts'], PayoutLine.fromJson),
        ledger: _parse(j['ledger'], LedgerRow.fromJson),
        totals: (j['totals'] as Map? ?? {}).map((k, v) => MapEntry('$k', '$v')),
      );
}

// ---- Training CTPs (the senior fundi's roll) ------------------------------

class TraineeModule {
  final int id;
  final String title;
  final bool marked;
  TraineeModule({required this.id, required this.title, required this.marked});

  factory TraineeModule.fromJson(Map<String, dynamic> j) => TraineeModule(
        id: _i(j['id']),
        title: (j['title'] ?? '') as String,
        marked: j['marked'] == true,
      );
}

class TraineeEntry {
  final int id;
  final String kind;
  final String? on;
  final String? note;
  final int? score;
  final bool? present;

  TraineeEntry({
    required this.id,
    required this.kind,
    required this.on,
    required this.note,
    required this.score,
    required this.present,
  });

  factory TraineeEntry.fromJson(Map<String, dynamic> j) => TraineeEntry(
        id: _i(j['id']),
        kind: (j['kind'] ?? '') as String,
        on: j['on'] as String?,
        note: j['note'] as String?,
        score: j['score'] == null ? null : _i(j['score']),
        present: j['present'] as bool?,
      );
}

class Trainee {
  final int id;
  final String? ctp;
  final String? ctpCode;
  final String state;
  final String stateLabel;
  final String stage;
  final String stageLabel;
  final int trackPct;
  final int attendancePct;
  final int modulesMarked;
  final int modulesTotal;
  final List<TraineeModule> modules;
  final List<Map<String, dynamic>> documents;
  final List<TraineeEntry> entries;

  Trainee({
    required this.id,
    required this.ctp,
    required this.ctpCode,
    required this.state,
    required this.stateLabel,
    required this.stage,
    required this.stageLabel,
    required this.trackPct,
    required this.attendancePct,
    required this.modulesMarked,
    required this.modulesTotal,
    required this.modules,
    required this.documents,
    required this.entries,
  });

  factory Trainee.fromJson(Map<String, dynamic> j) => Trainee(
        id: _i(j['id']),
        ctp: j['ctp'] as String?,
        ctpCode: j['ctp_code'] as String?,
        state: (j['state'] ?? '') as String,
        stateLabel: (j['state_label'] ?? j['state'] ?? '') as String,
        stage: (j['stage'] ?? '') as String,
        stageLabel: (j['stage_label'] ?? j['stage'] ?? '') as String,
        trackPct: _i(j['track_pct']),
        attendancePct: _i(j['attendance_pct']),
        modulesMarked: _i(j['modules_marked']),
        modulesTotal: _i(j['modules_total']),
        modules: _parse(j['modules'], TraineeModule.fromJson),
        documents: (j['documents'] as List? ?? [])
            .map((e) => Map<String, dynamic>.from(e as Map))
            .toList(),
        entries: _parse(j['entries'], TraineeEntry.fromJson),
      );
}

class TrainerCohort {
  final int id;
  final String reference;
  final String course;
  final String? period;
  final String? dates;
  final String status;
  final List<Trainee> trainees;

  TrainerCohort({
    required this.id,
    required this.reference,
    required this.course,
    required this.period,
    required this.dates,
    required this.status,
    required this.trainees,
  });

  factory TrainerCohort.fromJson(Map<String, dynamic> j) => TrainerCohort(
        id: _i(j['id']),
        reference: (j['reference'] ?? '') as String,
        course: (j['course'] ?? '') as String,
        period: j['period'] as String?,
        dates: j['dates'] as String?,
        status: (j['status'] ?? '') as String,
        trainees: _parse(j['trainees'], Trainee.fromJson),
      );
}

class TrainerSite {
  final int id;
  final String? venue;
  final String? place;
  final String status;
  final String statusLabel;
  final List<String> trainings;
  final List<TrainerCohort> cohorts;

  TrainerSite({
    required this.id,
    required this.venue,
    required this.place,
    required this.status,
    required this.statusLabel,
    required this.trainings,
    required this.cohorts,
  });

  factory TrainerSite.fromJson(Map<String, dynamic> j) => TrainerSite(
        id: _i(j['id']),
        venue: j['venue'] as String?,
        place: j['place'] as String?,
        status: (j['status'] ?? '') as String,
        statusLabel: (j['status_label'] ?? j['status'] ?? '') as String,
        trainings: _strings(j['trainings']),
        cohorts: _parse(j['cohorts'], TrainerCohort.fromJson),
      );
}

class TrainerBoard {
  final bool canTrain;
  final Map<String, String> kinds;
  final Map<String, String> documentKinds;
  final List<PlaceOption> trainings;
  final List<PlaceOption> venues;
  final List<String> counties;
  final List<TrainerSite> sites;

  TrainerBoard({
    required this.canTrain,
    required this.kinds,
    required this.documentKinds,
    required this.trainings,
    required this.venues,
    required this.counties,
    required this.sites,
  });

  factory TrainerBoard.fromJson(Map<String, dynamic> j) => TrainerBoard(
        canTrain: j['can_train'] == true,
        kinds: _options(j['kinds']),
        documentKinds: _options(j['document_kinds']),
        trainings: _parse(j['trainings'], PlaceOption.fromJson),
        venues: _parse(j['venues'], PlaceOption.fromJson),
        counties: _strings(j['counties']),
        sites: _parse(j['sites'], TrainerSite.fromJson),
      );
}

List<T> _parse<T>(dynamic raw, T Function(Map<String, dynamic>) fromJson) =>
    (raw as List? ?? []).map((e) => fromJson(Map<String, dynamic>.from(e as Map))).toList();

Map<String, String> _options(dynamic raw) {
  if (raw is List) return {for (final v in raw) '$v': '$v'};
  return (raw as Map? ?? {}).map((k, v) => MapEntry('$k', '$v'));
}

List<String> _strings(dynamic raw) => (raw as List? ?? []).map((e) => '$e').toList();

int _i(dynamic v) => v is int ? v : (v is num ? v.toInt() : int.tryParse('$v') ?? 0);
