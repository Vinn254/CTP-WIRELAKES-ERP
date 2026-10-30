/// My class, My library, My invoices and My leave — the JSON the four member endpoints return,
/// one class per block. Parsing lives here so the screens stay readable.
library;

/// One thing the grade on an E-Fundi record opens, or the one it keeps shut.
class Capability {
  final String key;
  final String title;
  final String blurb;

  Capability({required this.key, required this.title, required this.blurb});

  factory Capability.fromJson(Map<String, dynamic> j) => Capability(
        key: (j['key'] ?? '') as String,
        title: (j['title'] ?? '') as String,
        blurb: (j['blurb'] ?? '') as String,
      );
}

List<T> _list<T>(dynamic raw, T Function(Map<String, dynamic>) parse) =>
    (raw as List? ?? []).map((e) => parse(e as Map<String, dynamic>)).toList();

// ---- My class --------------------------------------------------------------

class ClassSeat {
  final int cohortId;
  final String? reference;
  final String? course;
  final String? place;
  final String? period;
  final String? dates;

  ClassSeat({
    required this.cohortId,
    required this.reference,
    required this.course,
    required this.place,
    required this.period,
    required this.dates,
  });

  factory ClassSeat.fromJson(Map<String, dynamic> j) => ClassSeat(
        cohortId: _i(j['cohort_id']),
        reference: j['reference'] as String?,
        course: j['course'] as String?,
        place: j['place'] as String?,
        period: j['period'] as String?,
        dates: j['dates'] as String?,
      );

  String get label => '$reference — $course';
}

class SeatOption {
  final int cohortId;
  final String label;
  SeatOption({required this.cohortId, required this.label});

  factory SeatOption.fromJson(Map<String, dynamic> j) =>
      SeatOption(cohortId: _i(j['cohort_id']), label: (j['label'] ?? '') as String);
}

class SlotRow {
  final String time;
  final String classLabel;
  final String? unit;
  final String? room;

  SlotRow({
    required this.time,
    required this.classLabel,
    required this.unit,
    required this.room,
  });

  factory SlotRow.fromJson(Map<String, dynamic> j) => SlotRow(
        time: (j['time'] ?? '') as String,
        classLabel: (j['class'] ?? '') as String,
        unit: j['unit'] as String?,
        room: j['room'] as String?,
      );
}

class WeekDay {
  final String label;
  final List<SlotRow> slots;

  WeekDay({required this.label, required this.slots});

  factory WeekDay.fromJson(Map<String, dynamic> j) => WeekDay(
        label: (j['label'] ?? '') as String,
        slots: _list(j['slots'], SlotRow.fromJson),
      );
}

class UnitMark {
  final String kind;
  final String label;
  final String score;

  UnitMark({required this.kind, required this.label, required this.score});

  factory UnitMark.fromJson(Map<String, dynamic> j) => UnitMark(
        kind: (j['kind'] ?? '') as String,
        label: (j['label'] ?? j['kind'] ?? '') as String,
        score: '${j['score'] ?? ''}',
      );
}

class UnitRow {
  final int position;
  final String title;
  final String? coveredOn;
  final List<UnitMark> marks;

  UnitRow({
    required this.position,
    required this.title,
    required this.coveredOn,
    required this.marks,
  });

  factory UnitRow.fromJson(Map<String, dynamic> j) => UnitRow(
        position: _i(j['position']),
        title: (j['title'] ?? '') as String,
        coveredOn: j['covered_on'] as String?,
        marks: _list(j['marks'], UnitMark.fromJson),
      );
}

class MaterialRow {
  final int id;
  final String title;
  final String kindLabel;
  final String? unit;
  final String? lesson;
  final String? givenOn;
  final String? size;
  final String? givenBy;
  final String? note;
  final bool hasFile;

  MaterialRow({
    required this.id,
    required this.title,
    required this.kindLabel,
    required this.unit,
    required this.lesson,
    required this.givenOn,
    required this.size,
    required this.givenBy,
    required this.note,
    required this.hasFile,
  });

  factory MaterialRow.fromJson(Map<String, dynamic> j) => MaterialRow(
        id: _i(j['id']),
        title: (j['title'] ?? '') as String,
        kindLabel: (j['kind_label'] ?? '') as String,
        unit: j['unit'] as String?,
        lesson: j['lesson'] as String?,
        givenOn: j['given_on'] as String?,
        size: j['size'] as String?,
        givenBy: j['given_by'] as String?,
        note: j['note'] as String?,
        hasFile: j['has_file'] == true,
      );

  /// The one line under a handout: what it is, which unit, when it was given.
  String get summary => [
        kindLabel,
        unit,
        lesson != null ? 'Lesson $lesson' : null,
        givenOn,
        size,
        givenBy != null ? 'given by $givenBy' : null,
      ].where((x) => x != null && x.toString().isNotEmpty).join(' · ');
}

class ClassDetail {
  final int id;
  final String? reference;
  final String? course;
  final String? place;
  final String? period;
  final String? dates;
  final String? trainer;
  final double trackPct;

  ClassDetail({
    required this.id,
    required this.reference,
    required this.course,
    required this.place,
    required this.period,
    required this.dates,
    required this.trainer,
    required this.trackPct,
  });

  factory ClassDetail.fromJson(Map<String, dynamic> j) => ClassDetail(
        id: _i(j['id']),
        reference: j['reference'] as String?,
        course: j['course'] as String?,
        place: j['place'] as String?,
        period: j['period'] as String?,
        dates: j['dates'] as String?,
        trainer: j['trainer'] as String?,
        trackPct: (j['track_pct'] as num?)?.toDouble() ?? 0,
      );
}

class ClassBoard {
  final ClassDetail info;
  final String? timetableOn;
  final List<WeekDay> week;
  final List<UnitRow> units;
  final List<MaterialRow> materials;
  final List<SeatOption> seats;

  ClassBoard({
    required this.info,
    required this.timetableOn,
    required this.week,
    required this.units,
    required this.materials,
    required this.seats,
  });

  factory ClassBoard.fromJson(Map<String, dynamic> j) => ClassBoard(
        info: ClassDetail.fromJson(j['class'] as Map<String, dynamic>),
        timetableOn: j['timetable_on'] as String?,
        week: _list(j['week'], WeekDay.fromJson),
        units: _list(j['units'], UnitRow.fromJson),
        materials: _list(j['materials'], MaterialRow.fromJson),
        seats: _list(j['seats'], SeatOption.fromJson),
      );
}

// ---- My library ------------------------------------------------------------

class LibraryRow {
  final int id;
  final String title;
  final String kind;
  final String kindLabel;
  final String course;
  final String? note;
  final String postedOn;
  final String? size;
  final bool hasFile;

  LibraryRow({
    required this.id,
    required this.title,
    required this.kind,
    required this.kindLabel,
    required this.course,
    required this.note,
    required this.postedOn,
    required this.size,
    required this.hasFile,
  });

  factory LibraryRow.fromJson(Map<String, dynamic> j) => LibraryRow(
        id: _i(j['id']),
        title: (j['title'] ?? '') as String,
        kind: (j['kind'] ?? '') as String,
        kindLabel: (j['kind_label'] ?? '') as String,
        course: (j['course'] ?? 'Every course') as String,
        note: j['note'] as String?,
        postedOn: (j['posted_on'] ?? '') as String,
        size: j['size'] as String?,
        hasFile: j['has_file'] == true,
      );
}

class LibraryBoard {
  final List<LibraryRow> items;
  final Map<String, String> courses;
  final String? course;
  final String? kind;
  final Map<String, String> kinds;

  LibraryBoard({
    required this.items,
    required this.courses,
    required this.course,
    required this.kind,
    required this.kinds,
  });

  factory LibraryBoard.fromJson(Map<String, dynamic> j) {
    final courses = <String, String>{'general': 'Reading for every course'};
    for (final c in (j['courses'] as List? ?? [])) {
      courses['${(c as Map)['id']}'] = '${c['name']}';
    }

    return LibraryBoard(
      items: _list(j['items'], LibraryRow.fromJson),
      courses: courses,
      course: j['course'] == null ? null : '${j['course']}',
      kind: j['kind'] as String?,
      kinds: (j['kinds'] as Map? ?? {}).map((k, v) => MapEntry('$k', '$v')),
    );
  }

  /// Titles grouped the way the Registrar filed them — one block per programme.
  Map<String, List<LibraryRow>> get byCourse {
    final blocks = <String, List<LibraryRow>>{};
    for (final item in items) {
      (blocks[item.course] ??= <LibraryRow>[]).add(item);
    }
    final keys = blocks.keys.toList()..sort();
    return {for (final k in keys) k: blocks[k]!};
  }
}

// ---- My invoices -----------------------------------------------------------

class PaymentRow {
  final String on;
  final String amount;
  final String method;
  final String reference;

  PaymentRow({
    required this.on,
    required this.amount,
    required this.method,
    required this.reference,
  });

  factory PaymentRow.fromJson(Map<String, dynamic> j) => PaymentRow(
        on: (j['on'] ?? '') as String,
        amount: (j['amount'] ?? '') as String,
        method: (j['method'] ?? '') as String,
        reference: (j['reference'] ?? '—') as String,
      );
}

class InvoiceRow {
  final int id;
  final String reference;
  final String title;
  final String details;
  final String? issuedOn;
  final String dueOn;
  final bool overdue;
  final String status;
  final String statusLabel;
  final String amount;
  final String paid;
  final String balance;
  final String? note;
  final String? withdrawnBy;
  final String? cancelReason;
  final List<PaymentRow> payments;

  InvoiceRow({
    required this.id,
    required this.reference,
    required this.title,
    required this.details,
    required this.issuedOn,
    required this.dueOn,
    required this.overdue,
    required this.status,
    required this.statusLabel,
    required this.amount,
    required this.paid,
    required this.balance,
    required this.note,
    required this.withdrawnBy,
    required this.cancelReason,
    required this.payments,
  });

  factory InvoiceRow.fromJson(Map<String, dynamic> j) => InvoiceRow(
        id: _i(j['id']),
        reference: (j['reference'] ?? '') as String,
        title: (j['title'] ?? '') as String,
        details: (j['details'] ?? j['title'] ?? '') as String,
        issuedOn: j['issued_on'] as String?,
        dueOn: (j['due_on'] ?? 'on receipt') as String,
        overdue: j['overdue'] == true,
        status: (j['status'] ?? '') as String,
        statusLabel: (j['status_label'] ?? '') as String,
        amount: (j['amount'] ?? '') as String,
        paid: (j['paid'] ?? '') as String,
        balance: (j['balance'] ?? '') as String,
        note: j['note'] as String?,
        withdrawnBy: j['withdrawn_by'] as String?,
        cancelReason: j['cancel_reason'] as String?,
        payments: _list(j['payments'], PaymentRow.fromJson),
      );

  bool get cancelled => status == 'cancelled';
  bool get settled => status == 'settled';
}

class InvoiceBoard {
  final String paybillNumber;
  final String paybillAccount;
  final Map<String, String> totals;
  final List<InvoiceRow> invoices;

  InvoiceBoard({
    required this.paybillNumber,
    required this.paybillAccount,
    required this.totals,
    required this.invoices,
  });

  factory InvoiceBoard.fromJson(Map<String, dynamic> j) => InvoiceBoard(
        paybillNumber: '${(j['paybill'] as Map?)?['number'] ?? ''}',
        paybillAccount: '${(j['paybill'] as Map?)?['account'] ?? ''}',
        totals: (j['totals'] as Map? ?? {}).map((k, v) => MapEntry('$k', '$v')),
        invoices: _list(j['invoices'], InvoiceRow.fromJson),
      );

  int get openCount => int.tryParse(totals['open'] ?? '0') ?? 0;
}

// ---- My leave --------------------------------------------------------------

class LeaveBalance {
  final String type;
  final String name;
  final String note;
  final double entitled;
  final double taken;
  final double pending;
  final double left;
  final double? accrual;
  final int? months;

  LeaveBalance({
    required this.type,
    required this.name,
    required this.note,
    required this.entitled,
    required this.taken,
    required this.pending,
    required this.left,
    required this.accrual,
    required this.months,
  });

  factory LeaveBalance.fromJson(Map<String, dynamic> j) => LeaveBalance(
        type: (j['type'] ?? '') as String,
        name: (j['name'] ?? '') as String,
        note: (j['note'] ?? '') as String,
        entitled: (j['entitled'] as num?)?.toDouble() ?? 0,
        taken: (j['taken'] as num?)?.toDouble() ?? 0,
        pending: (j['pending'] as num?)?.toDouble() ?? 0,
        left: (j['left'] as num?)?.toDouble() ?? 0,
        accrual: (j['accrual'] as num?)?.toDouble(),
        months: j['months'] == null ? null : _i(j['months']),
      );

  /// Annual leave is the one type that builds up, so the tile says where the days came from.
  String get accrualLine => accrual == null
      ? ''
      : 'Builds at $accrual days a month — ${months ?? 0} '
          '${(months ?? 0) == 1 ? 'month' : 'months'} worked this year.';
}

class LeaveRow {
  final int id;
  final String type;
  final String typeLabel;
  final String startsOn;
  final String endsOn;
  final String period;
  final String? appliedOn;
  final String? decidedAt;
  final String days;
  final String status;
  final String statusLabel;
  final String? coverFor;
  final String? reason;
  final String? decisionNote;
  final String? decidedBy;
  final bool canCancel;

  LeaveRow({
    required this.id,
    required this.type,
    required this.typeLabel,
    required this.startsOn,
    required this.endsOn,
    required this.period,
    required this.appliedOn,
    required this.decidedAt,
    required this.days,
    required this.status,
    required this.statusLabel,
    required this.coverFor,
    required this.reason,
    required this.decisionNote,
    required this.decidedBy,
    required this.canCancel,
  });

  factory LeaveRow.fromJson(Map<String, dynamic> j) => LeaveRow(
        id: _i(j['id']),
        type: (j['type'] ?? '') as String,
        typeLabel: (j['type_label'] ?? '') as String,
        startsOn: (j['starts_on'] ?? '') as String,
        endsOn: (j['ends_on'] ?? '') as String,
        period: (j['period'] ?? '') as String,
        appliedOn: j['applied_on'] as String?,
        decidedAt: j['decided_at'] as String?,
        days: '${j['days'] ?? ''}',
        status: (j['status'] ?? '') as String,
        statusLabel: (j['status_label'] ?? '') as String,
        coverFor: j['cover_for'] as String?,
        reason: j['reason'] as String?,
        decisionNote: j['decision_note'] as String?,
        decidedBy: j['decided_by'] as String?,
        canCancel: j['can_cancel'] == true,
      );

  bool get approved => status == 'approved';
  bool get rejected => status == 'rejected';
}

class LeaveBoard {
  final List<LeaveBalance> balances;
  final Map<String, Map<String, String>> types;
  final List<LeaveRow> history;
  final String joined;
  final int months;

  LeaveBoard({
    required this.balances,
    required this.types,
    required this.history,
    required this.joined,
    required this.months,
  });

  factory LeaveBoard.fromJson(Map<String, dynamic> j) => LeaveBoard(
        balances: _list(j['balances'], LeaveBalance.fromJson),
        types: (j['types'] as Map? ?? {}).map((k, v) => MapEntry(
            '$k',
            (v as Map).map((tk, tv) => MapEntry('$tk', '$tv')))),
        history: _list(j['history'], LeaveRow.fromJson),
        joined: (j['joined'] ?? '') as String,
        months: _i(j['months']),
      );

  LeaveBalance? balanceOf(String type) {
    for (final b in balances) {
      if (b.type == type) return b;
    }
    return null;
  }

  /// The type list a booking form is built from: key → "Annual Leave — Rest days…".
  Map<String, String> get typeChoices =>
      {for (final e in types.entries) e.key: e.value['name'] ?? e.key};
}

int _i(dynamic v) => v is int ? v : (v is num ? v.toInt() : int.tryParse('$v') ?? 0);
