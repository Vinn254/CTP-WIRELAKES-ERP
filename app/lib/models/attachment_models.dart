/// My attachment over the API — the same sheet the web portal page draws: the site the office placed
/// the trainee at, the week they sat there, and the weeks already handed to the Registrar.
library;

class AttachmentPlacement {
  final String reference;
  final String? site;
  final String? place;
  final String? organisation;
  final String? placedOn;
  final String? until;
  final String? scope;

  const AttachmentPlacement({
    required this.reference,
    this.site,
    this.place,
    this.organisation,
    this.placedOn,
    this.until,
    this.scope,
  });

  factory AttachmentPlacement.fromJson(Map<String, dynamic> j) => AttachmentPlacement(
        reference: j['reference']?.toString() ?? '',
        site: j['site']?.toString(),
        place: j['place']?.toString(),
        organisation: j['with']?.toString(),
        placedOn: j['placed_on']?.toString(),
        until: j['until']?.toString(),
        scope: j['scope']?.toString(),
      );
}

class AttachmentWeek {
  final String start;
  final String label;
  final String month;
  final int weekNo;
  final String status;
  final String statusLabel;
  final double hours;
  final int written;
  final bool canEdit;
  final String? note;
  final String? reviewNote;

  const AttachmentWeek({
    required this.start,
    required this.label,
    required this.month,
    required this.weekNo,
    required this.status,
    required this.statusLabel,
    required this.hours,
    required this.written,
    required this.canEdit,
    this.note,
    this.reviewNote,
  });

  factory AttachmentWeek.fromJson(Map<String, dynamic> j) => AttachmentWeek(
        start: j['start']?.toString() ?? '',
        label: j['label']?.toString() ?? '',
        month: j['month']?.toString() ?? '',
        weekNo: j['week_no'] is num ? j['week_no'] as int : int.tryParse('${j['week_no']}') ?? 1,
        status: j['status']?.toString() ?? 'draft',
        statusLabel: j['status_label']?.toString() ?? '',
        hours: j['hours'] is num ? (j['hours'] as num).toDouble() : double.tryParse('${j['hours']}') ?? 0,
        written: j['written'] is num ? j['written'] as int : int.tryParse('${j['written']}') ?? 0,
        canEdit: j['can_edit'] == true,
        note: j['note']?.toString(),
        reviewNote: j['review_note']?.toString(),
      );
}

class AttachmentDay {
  final String date;
  final String day;
  final bool past;
  final String? timeIn;
  final String? timeOut;
  final String? task;
  final String? initials;
  final double? hours;

  const AttachmentDay({
    required this.date,
    required this.day,
    required this.past,
    this.timeIn,
    this.timeOut,
    this.task,
    this.initials,
    this.hours,
  });

  factory AttachmentDay.fromJson(Map<String, dynamic> j) => AttachmentDay(
        date: j['date']?.toString() ?? '',
        day: j['day']?.toString() ?? '',
        past: j['past'] == true,
        timeIn: j['time_in']?.toString(),
        timeOut: j['time_out']?.toString(),
        task: j['task']?.toString(),
        initials: j['initials']?.toString(),
        hours: j['hours'] is num ? (j['hours'] as num).toDouble() : null,
      );

  bool get written => task != null || timeIn != null || timeOut != null || initials != null;

  String get clock =>
      (timeIn == null && timeOut == null) ? '—' : '${timeIn ?? '—'} – ${timeOut ?? '—'}';
}

class AttachmentWeekOption {
  final int no;
  final String label;
  final String dates;

  const AttachmentWeekOption({required this.no, required this.label, required this.dates});

  factory AttachmentWeekOption.fromJson(Map<String, dynamic> j) => AttachmentWeekOption(
        no: j['no'] is num ? j['no'] as int : int.tryParse('${j['no']}') ?? 1,
        label: j['label']?.toString() ?? '',
        dates: j['dates']?.toString() ?? '',
      );
}

class AttachmentHistory {
  final int id;
  final String label;
  final String month;
  final int weekNo;
  final int days;
  final double hours;
  final String status;

  const AttachmentHistory({
    required this.id,
    required this.label,
    required this.month,
    required this.weekNo,
    required this.days,
    required this.hours,
    required this.status,
  });

  factory AttachmentHistory.fromJson(Map<String, dynamic> j) => AttachmentHistory(
        id: j['id'] is num ? j['id'] as int : int.tryParse('${j['id']}') ?? 0,
        label: j['label']?.toString() ?? '',
        month: j['month']?.toString() ?? '',
        weekNo: j['week_no'] is num ? j['week_no'] as int : int.tryParse('${j['week_no']}') ?? 1,
        days: j['days'] is num ? j['days'] as int : int.tryParse('${j['days']}') ?? 0,
        hours: j['hours'] is num ? (j['hours'] as num).toDouble() : 0,
        status: j['status']?.toString() ?? 'draft',
      );
}

class AttachmentSheet {
  final AttachmentPlacement? placement;
  final AttachmentWeek week;
  final List<AttachmentWeekOption> weeks;
  final List<AttachmentDay> days;
  final List<AttachmentHistory> history;
  final List<int> years;
  final Map<String, String> statuses;

  const AttachmentSheet({
    this.placement,
    required this.week,
    required this.weeks,
    required this.days,
    required this.history,
    required this.years,
    required this.statuses,
  });

  factory AttachmentSheet.fromJson(Map<String, dynamic> j) => AttachmentSheet(
        placement: j['placement'] == null
            ? null
            : AttachmentPlacement.fromJson(Map<String, dynamic>.from(j['placement'] as Map)),
        week: AttachmentWeek.fromJson(Map<String, dynamic>.from(j['week'] as Map)),
        weeks: (j['weeks'] as List? ?? [])
            .map((e) => AttachmentWeekOption.fromJson(Map<String, dynamic>.from(e as Map)))
            .toList(),
        days: (j['days'] as List? ?? [])
            .map((e) => AttachmentDay.fromJson(Map<String, dynamic>.from(e as Map)))
            .toList(),
        history: (j['history'] as List? ?? [])
            .map((e) => AttachmentHistory.fromJson(Map<String, dynamic>.from(e as Map)))
            .toList(),
        years: (j['years'] as List? ?? []).map((e) => e is int ? e : int.tryParse('$e') ?? 0).toList(),
        statuses: Map<String, String>.from(
            (j['statuses'] as Map? ?? {}).map((k, v) => MapEntry('$k', '$v'))),
      );
}
