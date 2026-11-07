/// The trainer's five papers over the API — the same desk the office and the E-Fundi portal fill in
/// web/resources/views/teaching. Each paper answers with the class, its roll and whatever that paper already
/// holds, so the phone draws the same register, marks grid, week, lesson plan and shelf the website shows.
library;

int _i(dynamic v) => v is int ? v : int.tryParse('$v') ?? 0;

String? _s(dynamic v) => v == null ? null : '$v';

double? _num(dynamic v) => v is num ? v.toDouble() : double.tryParse('$v');

Map<String, String> _labels(dynamic v) =>
    Map<String, String>.from((v as Map? ?? {}).map((k, value) => MapEntry('$k', '$value')));

List<Map<String, dynamic>> _rows(dynamic v) =>
    (v as List? ?? []).map((e) => Map<String, dynamic>.from(e as Map)).toList();

/// Where a paper stands with the Registrar — the four states every desk on the website reads.
class TeachingPaper {
  final int id;
  final String status;
  final String statusLabel;
  final bool editable;
  final String? reviewNote;
  final String? note;

  const TeachingPaper({
    required this.id,
    required this.status,
    required this.statusLabel,
    required this.editable,
    this.reviewNote,
    this.note,
  });

  static TeachingPaper from(Map<String, dynamic> j) => TeachingPaper(
        id: _i(j['id']),
        status: _s(j['status']) ?? 'draft',
        statusLabel: _s(j['status_label']) ?? 'Draft',
        editable: j['editable'] == true,
        reviewNote: _s(j['review_note']),
        note: _s(j['note']),
      );
}

/// The class the office handed to the trainer — the page the five papers sit under.
class TaughtCohort {
  final int id;
  final String reference;
  final String course;
  final String? programme;
  final String? venue;
  final String? place;
  final String? period;
  final String? term;
  final String status;
  final String statusLabel;
  final String? stage;
  final String? startsOn;
  final String? endsOn;
  final int seats;
  final int units;

  const TaughtCohort({
    required this.id,
    required this.reference,
    required this.course,
    required this.status,
    required this.statusLabel,
    required this.seats,
    required this.units,
    this.programme,
    this.venue,
    this.place,
    this.period,
    this.term,
    this.startsOn,
    this.endsOn,
    this.stage,
  });

  factory TaughtCohort.fromJson(Map<String, dynamic> j) => TaughtCohort(
        id: _i(j['id']),
        reference: _s(j['reference']) ?? '',
        course: _s(j['course']) ?? 'A class',
        programme: _s(j['programme']),
        venue: _s(j['venue']),
        place: _s(j['place']),
        period: _s(j['period']),
        term: _s(j['term']),
        status: _s(j['status']) ?? '',
        statusLabel: _s(j['status_label']) ?? '',
        stage: _s(j['stage']),
        startsOn: _s(j['starts_on']),
        endsOn: _s(j['ends_on']),
        seats: _i(j['seats']),
        units: _i(j['units']),
      );

  String get line =>
      [reference, period, place].where((x) => x != null && x.isNotEmpty).join(' · ');
}

/// A trainee on the roll — the names a register ticks and a marks grid runs down.
class RollSeat {
  final int id;
  final String name;
  final String? reference;

  const RollSeat({required this.id, required this.name, this.reference});

  factory RollSeat.fromJson(Map<String, dynamic> j) => RollSeat(
        id: _i(j['id']),
        name: _s(j['name']) ?? 'Trainee',
        reference: _s(j['reference']),
      );
}

/// A unit the intake teaches, with the term tag the Registrar put on it.
class TaughtUnit {
  final int id;
  final String title;
  final String? term;

  const TaughtUnit({required this.id, required this.title, this.term});

  factory TaughtUnit.fromJson(Map<String, dynamic> j) => TaughtUnit(
        id: _i(j['id']),
        title: _s(j['title']) ?? 'Unit',
        term: _s(j['term']),
      );
}

class WeekOption {
  final String label;
  final String dates;
  final String start;
  final String end;

  const WeekOption({required this.label, required this.dates, required this.start, required this.end});

  factory WeekOption.fromJson(Map<String, dynamic> j) => WeekOption(
        label: _s(j['label']) ?? '',
        dates: _s(j['dates']) ?? '',
        start: _s(j['start']) ?? '',
        end: _s(j['end']) ?? '',
      );
}

// ------------------------------------------------------------------ the desk

class TeachingDeskBoard {
  final String trainer;
  final bool office;

  /// An office account carries a staff file, so My profile opens beside the papers. An E-Fundi who trains does not.
  final bool staff;
  final List<TaughtCohort> cohorts;
  final String? note;

  const TeachingDeskBoard({
    required this.trainer,
    required this.office,
    this.staff = false,
    required this.cohorts,
    this.note,
  });

  factory TeachingDeskBoard.fromJson(Map<String, dynamic> j) => TeachingDeskBoard(
        trainer: _s(j['trainer']) ?? '',
        office: j['office'] == true,
        staff: j['staff'] == true,
        cohorts: _rows(j['cohorts']).map(TaughtCohort.fromJson).toList(),
        note: _s(j['note']),
      );
}

// ------------------------------------------------------------------ register

class RegisterWeekDay {
  final String date;
  final String day;
  final String label;

  const RegisterWeekDay({required this.date, required this.day, required this.label});

  factory RegisterWeekDay.fromJson(Map<String, dynamic> j) => RegisterWeekDay(
        date: _s(j['date']) ?? '',
        day: _s(j['day']) ?? '',
        label: _s(j['label']) ?? '',
      );

  bool get isTodayOrPast => date.compareTo(_today()) <= 0;
}

class RegisterWeek {
  final String start;
  final String label;
  final String month;
  final int weekNo;
  final List<RegisterWeekDay> days;
  final List<WeekOption> weeks;

  const RegisterWeek({
    required this.start,
    required this.label,
    required this.month,
    required this.weekNo,
    required this.days,
    required this.weeks,
  });

  factory RegisterWeek.fromJson(Map<String, dynamic> j) => RegisterWeek(
        start: _s(j['start']) ?? '',
        label: _s(j['label']) ?? '',
        month: _s(j['month']) ?? '',
        weekNo: _i(j['week_no']),
        days: _rows(j['days']).map(RegisterWeekDay.fromJson).toList(),
        weeks: _rows(j['weeks']).map(WeekOption.fromJson).toList(),
      );
}

/// A class on the attendance register: the timetable's own line, with the paper written for it or none.
class RegisterClass {
  final String date;
  final String day;
  final String? time;
  final double? hours;
  final String kind;
  final String kindLabel;
  final int? unitId;
  final String? unit;
  final String? room;
  final bool owed;
  final bool offTimetable;
  final DayRegister? session;

  const RegisterClass({
    required this.date,
    required this.day,
    required this.kind,
    required this.kindLabel,
    required this.owed,
    required this.offTimetable,
    this.time,
    this.hours,
    this.unitId,
    this.unit,
    this.room,
    this.session,
  });

  factory RegisterClass.fromJson(Map<String, dynamic> j) => RegisterClass(
        date: _s(j['date']) ?? '',
        day: _s(j['day']) ?? '',
        time: _s(j['time']),
        hours: _num(j['hours']),
        kind: _s(j['kind']) ?? 'class',
        kindLabel: _s(j['kind_label']) ?? 'Class',
        unitId: j['unit_id'] == null ? null : _i(j['unit_id']),
        unit: _s(j['unit']),
        room: _s(j['room']),
        owed: j['owed'] == true,
        offTimetable: j['off_timetable'] == true,
        session: j['session'] == null
            ? null
            : DayRegister.fromJson(Map<String, dynamic>.from(j['session'] as Map)),
      );

  String get head => [kindLabel, unit].where((x) => x != null && x.isNotEmpty).join(' — ');

  String get meta => [
        time ?? 'time not fixed',
        hours == null ? null : '$hours ${hours == 1 ? 'hour' : 'hours'}',
        room,
      ].where((x) => x != null && x.isNotEmpty).join(' · ');
}

/// One day's register — the ticks, the note and the sheet the class signed.
class DayRegister {
  final TeachingPaper paper;
  final String heldOn;
  final String dateLabel;
  final String kind;
  final String kindLabel;
  final int? unitId;
  final String unit;
  final String? topic;
  final double hours;
  final String hoursLabel;
  final int present;
  final bool hasSheet;
  final String? sheetName;
  final Map<int, bool> ticks;
  final Map<int, String> tickNotes;

  DayRegister({
    required this.paper,
    required this.heldOn,
    required this.dateLabel,
    required this.kind,
    required this.kindLabel,
    required this.unit,
    required this.hours,
    required this.hoursLabel,
    required this.present,
    required this.hasSheet,
    required this.ticks,
    required this.tickNotes,
    this.unitId,
    this.topic,
    this.sheetName,
  });

  factory DayRegister.fromJson(Map<String, dynamic> j) => DayRegister(
        paper: TeachingPaper.from(j),
        heldOn: _s(j['held_on']) ?? '',
        dateLabel: _s(j['date_label']) ?? '',
        kind: _s(j['kind']) ?? 'class',
        kindLabel: _s(j['kind_label']) ?? 'Class',
        unitId: j['unit_id'] == null ? null : _i(j['unit_id']),
        unit: _s(j['unit']) ?? '',
        topic: _s(j['topic']),
        hours: _num(j['hours']) ?? 0,
        hoursLabel: _s(j['hours_label']) ?? '',
        present: _i(j['present']),
        hasSheet: j['has_sheet'] == true,
        sheetName: _s(j['sheet_name']),
        ticks: (j['ticks'] as Map? ?? {})
            .map((k, v) => MapEntry(int.tryParse('$k') ?? 0, v == true || v == 1 || v == '1')),
        tickNotes: (j['tick_notes'] as Map? ?? {})
            .map((k, v) => MapEntry(int.tryParse('$k') ?? 0, '$v')),
      );

  bool get editable => paper.editable;
}

class RegisterBoard {
  final TaughtCohort cohort;
  final List<RollSeat> roll;
  final List<TaughtUnit> units;
  final CohortTimetable? programmeWeek;
  final RegisterWeek week;
  final List<RegisterClass> classes;
  final Map<String, String> kinds;
  final bool onTheWeek;

  const RegisterBoard({
    required this.cohort,
    required this.roll,
    required this.units,
    required this.week,
    required this.classes,
    required this.kinds,
    required this.onTheWeek,
    this.programmeWeek,
  });

  factory RegisterBoard.fromJson(Map<String, dynamic> j) => RegisterBoard(
        cohort: TaughtCohort.fromJson(Map<String, dynamic>.from(j['cohort'] as Map)),
        roll: _rows(j['roll']).map(RollSeat.fromJson).toList(),
        units: _rows(j['units']).map(TaughtUnit.fromJson).toList(),
        programmeWeek: j['programme_week'] == null
            ? null
            : CohortTimetable.fromJson(Map<String, dynamic>.from(j['programme_week'] as Map)),
        week: RegisterWeek.fromJson(Map<String, dynamic>.from(j['week'] as Map)),
        classes: _rows(j['classes']).map(RegisterClass.fromJson).toList(),
        kinds: _labels(j['kinds']),
        onTheWeek: j['on_the_week'] == true,
      );
}

// -------------------------------------------------------------------- marks

class MarkCell {
  final int enrollmentId;
  final String kind;
  final double? score;
  final String? note;

  const MarkCell({required this.enrollmentId, required this.kind, this.score, this.note});

  factory MarkCell.fromJson(Map<String, dynamic> j) => MarkCell(
        enrollmentId: _i(j['enrollment_id']),
        kind: _s(j['kind']) ?? '',
        score: _num(j['score']),
        note: _s(j['note']),
      );
}

class MarksSheet {
  final TeachingPaper paper;
  final int? unitId;
  final String unit;
  final String? coveredOn;
  final String dateLabel;
  final List<MarkCell> marks;
  final int written;

  MarksSheet({
    required this.paper,
    required this.unit,
    required this.dateLabel,
    required this.marks,
    required this.written,
    this.unitId,
    this.coveredOn,
  });

  factory MarksSheet.fromJson(Map<String, dynamic> j) => MarksSheet(
        paper: TeachingPaper.from(j),
        unitId: j['unit_id'] == null ? null : _i(j['unit_id']),
        unit: _s(j['unit']) ?? '',
        coveredOn: _s(j['covered_on']),
        dateLabel: _s(j['date_label']) ?? '',
        marks: _rows(j['marks']).map(MarkCell.fromJson).toList(),
        written: _i(j['written']),
      );

  double? score(int enrollmentId, String kind) {
    for (final cell in marks) {
      if (cell.enrollmentId == enrollmentId && cell.kind == kind) return cell.score;
    }
    return null;
  }

  String? noteFor(int enrollmentId, String kind) {
    for (final cell in marks) {
      if (cell.enrollmentId == enrollmentId && cell.kind == kind) return cell.note;
    }
    return null;
  }
}

/// The Registrar's own weights for the unit the grid is open on.
class UnitGrading {
  final Map<String, double> weights;
  final double passPct;
  final String? note;

  const UnitGrading({required this.weights, required this.passPct, this.note});

  factory UnitGrading.fromJson(Map<String, dynamic> j) => UnitGrading(
        weights: (j['weights'] as Map? ?? {})
            .map((k, v) => MapEntry('$k', v is num ? v.toDouble() : double.tryParse('$v') ?? 0)),
        passPct: _num(j['pass_pct']) ?? 0,
        note: _s(j['note']),
      );
}

class MarksBoard {
  final TaughtCohort cohort;
  final List<RollSeat> roll;
  final List<TaughtUnit> units;
  final CohortTimetable? programmeWeek;
  final List<MarksSheet> sheets;
  final MarksSheet? sheet;
  final Map<String, String> kinds;
  final double outOf;
  final UnitGrading? grading;
  final bool canCorrect;

  const MarksBoard({
    required this.cohort,
    required this.roll,
    required this.units,
    required this.sheets,
    required this.kinds,
    required this.outOf,
    required this.canCorrect,
    this.programmeWeek,
    this.sheet,
    this.grading,
  });

  factory MarksBoard.fromJson(Map<String, dynamic> j) => MarksBoard(
        cohort: TaughtCohort.fromJson(Map<String, dynamic>.from(j['cohort'] as Map)),
        roll: _rows(j['roll']).map(RollSeat.fromJson).toList(),
        units: _rows(j['units']).map(TaughtUnit.fromJson).toList(),
        programmeWeek: j['programme_week'] == null
            ? null
            : CohortTimetable.fromJson(Map<String, dynamic>.from(j['programme_week'] as Map)),
        sheets: _rows(j['sheets']).map(MarksSheet.fromJson).toList(),
        sheet: j['sheet'] == null
            ? null
            : MarksSheet.fromJson(Map<String, dynamic>.from(j['sheet'] as Map)),
        kinds: _labels(j['kinds']),
        outOf: _num(j['out_of']) ?? 100,
        grading: j['grading'] == null
            ? null
            : UnitGrading.fromJson(Map<String, dynamic>.from(j['grading'] as Map)),
        canCorrect: j['can_correct'] == true,
      );
}

// ----------------------------------------------------------------- timetable

class TimetableSlot {
  final int? id;
  final String? day;
  final String? startsAt;
  final String? endsAt;
  final String time;
  final double? hours;
  final String hoursLabel;
  final String? kind;
  final String? kindLabel;
  final int? unitId;
  final String? unit;
  final String? room;
  final String? lecturer;

  const TimetableSlot({
    this.id,
    this.day,
    this.startsAt,
    this.endsAt,
    this.time = '',
    this.hours,
    this.hoursLabel = '',
    this.kind,
    this.kindLabel,
    this.unitId,
    this.unit,
    this.room,
    this.lecturer,
  });

  factory TimetableSlot.fromJson(Map<String, dynamic> j) => TimetableSlot(
        id: j['id'] == null ? null : _i(j['id']),
        day: _s(j['day']),
        startsAt: _s(j['starts_at']),
        endsAt: _s(j['ends_at']),
        time: _s(j['time']) ?? '',
        hours: _num(j['hours']),
        hoursLabel: _s(j['hours_label']) ?? '',
        kind: _s(j['kind']),
        kindLabel: _s(j['kind_label']),
        unitId: j['unit_id'] == null ? null : _i(j['unit_id']),
        unit: _s(j['unit']),
        room: _s(j['room']),
        lecturer: _s(j['lecturer']),
      );

  /// A blank line the trainer has not filled yet — the backend drops it off the week.
  bool get isBlank =>
      (day == null || day!.isEmpty) && (startsAt == null || startsAt!.isEmpty);

  Map<String, dynamic> toBody() => {
        'day': day,
        'starts_at': startsAt,
        'ends_at': endsAt,
        'kind': kind,
        'training_module_id': unitId,
        'room': room,
      };
}

class CohortTimetable {
  final TeachingPaper paper;
  final bool own;
  final List<TimetableSlot> slots;

  CohortTimetable({required this.paper, required this.own, required this.slots});

  factory CohortTimetable.fromJson(Map<String, dynamic> j) => CohortTimetable(
        paper: TeachingPaper.from(j),
        own: j['own'] == true,
        slots: _rows(j['slots']).map(TimetableSlot.fromJson).toList(),
      );
}

class TimetableBoard {
  final TaughtCohort cohort;
  final List<TaughtUnit> units;
  final CohortTimetable? programmeWeek;
  final CohortTimetable? paper;
  final Map<String, String> days;
  final Map<String, String> kinds;

  const TimetableBoard({
    required this.cohort,
    required this.units,
    required this.days,
    required this.kinds,
    this.programmeWeek,
    this.paper,
  });

  factory TimetableBoard.fromJson(Map<String, dynamic> j) => TimetableBoard(
        cohort: TaughtCohort.fromJson(Map<String, dynamic>.from(j['cohort'] as Map)),
        units: _rows(j['units']).map(TaughtUnit.fromJson).toList(),
        programmeWeek: j['programme_week'] == null
            ? null
            : CohortTimetable.fromJson(Map<String, dynamic>.from(j['programme_week'] as Map)),
        paper: j['paper'] == null
            ? null
            : CohortTimetable.fromJson(Map<String, dynamic>.from(j['paper'] as Map)),
        days: _labels(j['days']),
        kinds: _labels(j['kinds']),
      );
}

// -------------------------------------------------------------- lesson plan

class PlanMaterial {
  final int id;
  final String title;
  final String kindLabel;

  const PlanMaterial({required this.id, required this.title, required this.kindLabel});

  factory PlanMaterial.fromJson(Map<String, dynamic> j) => PlanMaterial(
        id: _i(j['id']),
        title: _s(j['title']) ?? '',
        kindLabel: _s(j['kind_label']) ?? '',
      );
}

class Lesson {
  final int id;
  final int position;
  final int? unitId;
  final String? unit;
  final String? plannedOn;
  final String dateLabel;
  final String? startsAt;
  final String? endsAt;
  final String time;
  final String topic;
  final String? summary;
  final String? objectives;
  final String? activities;
  final String? resources;
  final String? assessmentNote;
  final List<PlanMaterial> materials;

  Lesson({
    required this.id,
    required this.position,
    required this.dateLabel,
    required this.time,
    required this.topic,
    required this.materials,
    this.unitId,
    this.unit,
    this.plannedOn,
    this.startsAt,
    this.endsAt,
    this.summary,
    this.objectives,
    this.activities,
    this.resources,
    this.assessmentNote,
  });

  factory Lesson.fromJson(Map<String, dynamic> j) => Lesson(
        id: _i(j['id']),
        position: _i(j['position']),
        unitId: j['unit_id'] == null ? null : _i(j['unit_id']),
        unit: _s(j['unit']),
        plannedOn: _s(j['planned_on']),
        dateLabel: _s(j['date_label']) ?? '',
        startsAt: _s(j['starts_at']),
        endsAt: _s(j['ends_at']),
        time: _s(j['time']) ?? '',
        topic: _s(j['topic']) ?? '',
        summary: _s(j['summary']),
        objectives: _s(j['objectives']),
        activities: _s(j['activities']),
        resources: _s(j['resources']),
        assessmentNote: _s(j['assessment_note']),
        materials: _rows(j['materials']).map(PlanMaterial.fromJson).toList(),
      );
}

class LessonPlanInfo {
  final TeachingPaper paper;
  final int lessons;

  LessonPlanInfo({required this.paper, required this.lessons});

  factory LessonPlanInfo.fromJson(Map<String, dynamic> j) => LessonPlanInfo(
        paper: TeachingPaper.from(j),
        lessons: _i(j['lessons']),
      );
}

class LessonsBoard {
  final TaughtCohort cohort;
  final List<RollSeat> roll;
  final List<TaughtUnit> units;
  final CohortTimetable? programmeWeek;
  final LessonPlanInfo? plan;
  final List<Lesson> lines;
  final bool canWrite;
  final String? planNote;
  final Map<String, String> materialKinds;

  const LessonsBoard({
    required this.cohort,
    required this.roll,
    required this.units,
    required this.lines,
    required this.canWrite,
    required this.materialKinds,
    this.programmeWeek,
    this.plan,
    this.planNote,
  });

  factory LessonsBoard.fromJson(Map<String, dynamic> j) => LessonsBoard(
        cohort: TaughtCohort.fromJson(Map<String, dynamic>.from(j['cohort'] as Map)),
        roll: _rows(j['roll']).map(RollSeat.fromJson).toList(),
        units: _rows(j['units']).map(TaughtUnit.fromJson).toList(),
        programmeWeek: j['programme_week'] == null
            ? null
            : CohortTimetable.fromJson(Map<String, dynamic>.from(j['programme_week'] as Map)),
        plan: j['plan'] == null
            ? null
            : LessonPlanInfo.fromJson(Map<String, dynamic>.from(j['plan'] as Map)),
        lines: _rows(j['lines']).map(Lesson.fromJson).toList(),
        canWrite: j['can_write'] == true,
        planNote: _s(j['plan_note']),
        materialKinds: _labels(j['material_kinds']),
      );
}

// ------------------------------------------------------------------ material

class Handout {
  final int id;
  final String title;
  final String kind;
  final String kindLabel;
  final int? unitId;
  final String? unit;
  final int? lessonId;
  final String? lesson;
  final String? givenOn;
  final String dateLabel;
  final String? note;
  final String? fileName;
  final String sizeLabel;

  const Handout({
    required this.id,
    required this.title,
    required this.kind,
    required this.kindLabel,
    required this.dateLabel,
    required this.sizeLabel,
    this.unitId,
    this.unit,
    this.lessonId,
    this.lesson,
    this.givenOn,
    this.note,
    this.fileName,
  });

  factory Handout.fromJson(Map<String, dynamic> j) => Handout(
        id: _i(j['id']),
        title: _s(j['title']) ?? '',
        kind: _s(j['kind']) ?? '',
        kindLabel: _s(j['kind_label']) ?? '',
        unitId: j['unit_id'] == null ? null : _i(j['unit_id']),
        unit: _s(j['unit']),
        lessonId: j['lesson_id'] == null ? null : _i(j['lesson_id']),
        lesson: _s(j['lesson']),
        givenOn: _s(j['given_on']),
        dateLabel: _s(j['date_label']) ?? '',
        note: _s(j['note']),
        fileName: _s(j['file_name']),
        sizeLabel: _s(j['size_label']) ?? '',
      );
}

class MaterialsBoard {
  final TaughtCohort cohort;
  final List<RollSeat> roll;
  final List<TaughtUnit> units;
  final CohortTimetable? programmeWeek;
  final List<Handout> papers;
  final List<Lesson> lessons;
  final Map<String, String> kinds;

  const MaterialsBoard({
    required this.cohort,
    required this.units,
    required this.papers,
    required this.lessons,
    required this.kinds,
    this.roll = const [],
    this.programmeWeek,
  });

  factory MaterialsBoard.fromJson(Map<String, dynamic> j) => MaterialsBoard(
        cohort: TaughtCohort.fromJson(Map<String, dynamic>.from(j['cohort'] as Map)),
        roll: _rows(j['roll']).map(RollSeat.fromJson).toList(),
        units: _rows(j['units']).map(TaughtUnit.fromJson).toList(),
        programmeWeek: j['programme_week'] == null
            ? null
            : CohortTimetable.fromJson(Map<String, dynamic>.from(j['programme_week'] as Map)),
        papers: _rows(j['papers']).map(Handout.fromJson).toList(),
        lessons: _rows(j['lessons']).map(Lesson.fromJson).toList(),
        kinds: _labels(j['kinds']),
      );
}

// ---------------------------------------------------------------- time sheet

class SheetWorker {
  final String name;
  final String initials;

  const SheetWorker({required this.name, required this.initials});

  factory SheetWorker.fromJson(Map<String, dynamic> j) => SheetWorker(
        name: _s(j['name']) ?? '',
        initials: _s(j['initials']) ?? '',
      );
}

class WorkWeek {
  final String start;
  final String label;
  final String month;
  final int weekNo;
  final String status;
  final String statusLabel;
  final double hours;
  final int units;

  const WorkWeek({
    required this.start,
    required this.label,
    required this.month,
    required this.weekNo,
    required this.status,
    required this.statusLabel,
    required this.hours,
    required this.units,
  });

  factory WorkWeek.fromJson(Map<String, dynamic> j) => WorkWeek(
        start: _s(j['start']) ?? '',
        label: _s(j['label']) ?? '',
        month: _s(j['month']) ?? '',
        weekNo: _i(j['week_no']),
        status: _s(j['status']) ?? 'draft',
        statusLabel: _s(j['status_label']) ?? 'Draft',
        hours: _num(j['hours']) ?? 0,
        units: _i(j['units']),
      );
}

class SheetWeekOption {
  final int no;
  final String label;
  final String dates;

  const SheetWeekOption({required this.no, required this.label, required this.dates});

  factory SheetWeekOption.fromJson(Map<String, dynamic> j) => SheetWeekOption(
        no: _i(j['no']),
        label: _s(j['label']) ?? '',
        dates: _s(j['dates']) ?? '',
      );
}

class SheetDay {
  final String day;
  final String? date;
  final String label;
  final DayLog? entry;

  const SheetDay({required this.day, required this.label, this.date, this.entry});

  factory SheetDay.fromJson(Map<String, dynamic> j) => SheetDay(
        day: _s(j['day']) ?? '',
        date: _s(j['date']),
        label: _s(j['label']) ?? '',
        entry: j['entry'] == null
            ? null
            : DayLog.fromJson(Map<String, dynamic>.from(j['entry'] as Map)),
      );

  bool get written => entry != null;
}

class DayLog {
  final int id;
  final String date;
  final String? timeIn;
  final String? timeOut;
  final String? task;
  final double? hours;
  final int units;
  final String? department;
  final int? trainingId;
  final int? cohortId;
  final String status;
  final String statusLabel;
  final String? approvalNote;

  const DayLog({
    required this.id,
    required this.date,
    required this.status,
    required this.statusLabel,
    required this.units,
    this.timeIn,
    this.timeOut,
    this.task,
    this.hours,
    this.department,
    this.trainingId,
    this.cohortId,
    this.approvalNote,
  });

  factory DayLog.fromJson(Map<String, dynamic> j) => DayLog(
        id: _i(j['id']),
        date: _s(j['date']) ?? '',
        timeIn: _s(j['time_in']),
        timeOut: _s(j['time_out']),
        task: _s(j['task']),
        hours: _num(j['hours']),
        units: _i(j['units']),
        department: _s(j['department']),
        trainingId: j['training_id'] == null ? null : _i(j['training_id']),
        cohortId: j['cohort_id'] == null ? null : _i(j['cohort_id']),
        status: _s(j['status']) ?? 'draft',
        statusLabel: _s(j['status_label']) ?? 'Draft',
        approvalNote: _s(j['approval_note']),
      );

  String get clock =>
      (timeIn == null && timeOut == null) ? '—' : '${timeIn ?? '—'} – ${timeOut ?? '—'}';
}

class SheetDefaults {
  final String? initials;
  final List<String> departments;
  final String? department;

  const SheetDefaults({this.initials, this.departments = const [], this.department});

  factory SheetDefaults.fromJson(Map<String, dynamic> j) => SheetDefaults(
        initials: _s(j['initials']),
        departments: (j['departments'] as List? ?? []).map((e) => '$e').toList(),
        department: _s(j['department']),
      );
}

class TimeSheetBoard {
  final SheetWorker worker;
  final bool canEdit;
  final WorkWeek week;
  final List<SheetWeekOption> weeks;
  final List<SheetDay> days;
  final SheetDefaults defaults;
  final List<TaughtUnit> programmes;
  final List<({int id, String label})> cohorts;
  final List<int> years;
  final Map<String, String> statuses;

  const TimeSheetBoard({
    required this.worker,
    required this.canEdit,
    required this.week,
    required this.weeks,
    required this.days,
    required this.defaults,
    required this.programmes,
    required this.cohorts,
    required this.years,
    required this.statuses,
  });

  factory TimeSheetBoard.fromJson(Map<String, dynamic> j) => TimeSheetBoard(
        worker: SheetWorker.fromJson(Map<String, dynamic>.from(j['worker'] as Map)),
        canEdit: j['can_edit'] == true,
        week: WorkWeek.fromJson(Map<String, dynamic>.from(j['week'] as Map)),
        weeks: _rows(j['weeks']).map(SheetWeekOption.fromJson).toList(),
        days: _rows(j['days']).map(SheetDay.fromJson).toList(),
        defaults: SheetDefaults.fromJson(Map<String, dynamic>.from(j['defaults'] as Map)),
        programmes: _rows(j['programmes'])
            .map((e) => TaughtUnit(id: _i(e['id']), title: _s(e['name']) ?? ''))
            .toList(),
        cohorts: _rows(j['cohorts'])
            .map((e) => (id: _i(e['id']), label: _s(e['label']) ?? ''))
            .toList(),
        years: (j['years'] as List? ?? []).map(_i).toList(),
        statuses: _labels(j['statuses']),
      );
}

/// One trainee's record — the page the desk reads before it marks. Everything on it is counted by the class's own
/// sheet, so a phone never shows a trainee a different total from the register and marks papers.
class TraineeSeat {
  final int id;
  final String name;
  final String? code;
  final String stateLabel;
  final String stageLabel;
  final bool held;
  final double? attendance;
  final int attendanceMin;
  final double track;
  final int daysMarked;
  final int daysPresent;

  const TraineeSeat({
    required this.id,
    required this.name,
    this.code,
    required this.stateLabel,
    required this.stageLabel,
    required this.held,
    this.attendance,
    required this.attendanceMin,
    required this.track,
    required this.daysMarked,
    required this.daysPresent,
  });

  factory TraineeSeat.fromJson(Map<String, dynamic> j) => TraineeSeat(
        id: _i(j['id']),
        name: _s(j['name']) ?? 'Trainee',
        code: _s(j['code']),
        stateLabel: _s(j['state_label']) ?? '',
        stageLabel: _s(j['stage_label']) ?? '',
        held: j['held'] == true,
        attendance: _num(j['attendance']),
        attendanceMin: _i(j['attendance_min']),
        track: _num(j['track']) ?? 0,
        daysMarked: _i(j['days_marked']),
        daysPresent: _i(j['days_present']),
      );
}

class MarkPartScore {
  final String label;
  final double? score;
  final int weight;

  const MarkPartScore({required this.label, this.score, required this.weight});

  factory MarkPartScore.fromJson(Map<String, dynamic> j) => MarkPartScore(
        label: _s(j['label']) ?? '',
        score: _num(j['score']),
        weight: _i(j['weight']),
      );
}

class TraineeMark {
  final String unit;
  final String? term;
  final String? note;
  final List<MarkPartScore> parts;
  final double pct;

  /// What the marks sheets alone come to, before attendance is weighed in.
  final double onSheet;
  final String letter;
  final bool marked;
  final bool passed;
  final double passPct;
  final String remark;

  const TraineeMark({
    required this.unit,
    this.term,
    this.note,
    required this.parts,
    required this.pct,
    required this.onSheet,
    required this.letter,
    required this.marked,
    required this.passed,
    required this.passPct,
    required this.remark,
  });

  factory TraineeMark.fromJson(Map<String, dynamic> j) => TraineeMark(
        unit: _s(j['unit']) ?? 'Unit',
        term: _s(j['term']),
        note: _s(j['note']),
        parts: _rows(j['parts']).map(MarkPartScore.fromJson).toList(),
        pct: _num(j['pct']) ?? 0,
        onSheet: _num(j['on_sheet']) ?? 0,
        letter: _s(j['letter']) ?? '',
        marked: j['marked'] == true,
        passed: j['passed'] == true,
        passPct: _num(j['pass_pct']) ?? 0,
        remark: _s(j['remark']) ?? '',
      );
}

class TrackPart {
  final String label;
  final double got;
  final int weight;
  final String detail;
  final bool done;

  const TrackPart({
    required this.label,
    required this.got,
    required this.weight,
    required this.detail,
    required this.done,
  });

  factory TrackPart.fromJson(Map<String, dynamic> j) => TrackPart(
        label: _s(j['label']) ?? '',
        got: _num(j['got']) ?? 0,
        weight: _i(j['weight']),
        detail: _s(j['detail']) ?? '',
        done: j['done'] == true,
      );
}

class TraineeModule {
  final int id;
  final int position;
  final String title;
  final String? hours;
  final String? outcome;
  final String? markedOn;
  final String? markedBy;
  final String? markedNote;

  const TraineeModule({
    required this.id,
    required this.position,
    required this.title,
    this.hours,
    this.outcome,
    this.markedOn,
    this.markedBy,
    this.markedNote,
  });

  bool get marked => markedOn != null;

  factory TraineeModule.fromJson(Map<String, dynamic> j) => TraineeModule(
        id: _i(j['id']),
        position: _i(j['position']),
        title: _s(j['title']) ?? 'Module',
        hours: _s(j['hours']),
        outcome: _s(j['outcome']),
        markedOn: _s(j['marked_on']),
        markedBy: _s(j['marked_by']),
        markedNote: _s(j['marked_note']),
      );
}

class TraineeDocument {
  final int id;
  final String title;
  final String kindLabel;
  final String? dated;
  final String? size;
  final String? unit;
  final String? note;
  final bool hasFile;

  const TraineeDocument({
    required this.id,
    required this.title,
    required this.kindLabel,
    this.dated,
    this.size,
    this.unit,
    this.note,
    required this.hasFile,
  });

  factory TraineeDocument.fromJson(Map<String, dynamic> j) => TraineeDocument(
        id: _i(j['id']),
        title: _s(j['title']) ?? 'Document',
        kindLabel: _s(j['kind_label']) ?? '',
        dated: _s(j['dated']),
        size: _s(j['size']),
        unit: _s(j['unit']),
        note: _s(j['note']),
        hasFile: j['has_file'] == true,
      );
}

class TraineeBoard {
  final TraineeSeat seat;
  final TaughtCohort? cohort;
  final List<TraineeMark> marks;
  final List<TrackPart> parts;
  final List<TraineeModule> modules;
  final List<TraineeDocument> documents;
  final Map<String, dynamic>? cert;
  final List<TaughtUnit> units;
  final Map<String, String> progressKinds;
  final Map<String, String> documentKinds;
  final String? period;
  final String? startsOn;
  final String? endsOn;
  final bool canWrite;

  const TraineeBoard({
    required this.seat,
    required this.marks,
    required this.parts,
    required this.modules,
    required this.documents,
    required this.units,
    required this.progressKinds,
    required this.documentKinds,
    required this.canWrite,
    this.cohort,
    this.cert,
    this.period,
    this.startsOn,
    this.endsOn,
  });

  factory TraineeBoard.fromJson(Map<String, dynamic> j) => TraineeBoard(
        seat: TraineeSeat.fromJson(Map<String, dynamic>.from(j['seat'] as Map)),
        cohort: j['cohort'] == null
            ? null
            : TaughtCohort.fromJson(Map<String, dynamic>.from(j['cohort'] as Map)),
        marks: _rows(j['marks']).map(TraineeMark.fromJson).toList(),
        parts: _rows(j['parts']).map(TrackPart.fromJson).toList(),
        modules: _rows(j['modules']).map(TraineeModule.fromJson).toList(),
        documents: _rows(j['documents']).map(TraineeDocument.fromJson).toList(),
        cert: j['cert'] == null ? null : Map<String, dynamic>.from(j['cert'] as Map),
        units: _rows(j['units']).map(TaughtUnit.fromJson).toList(),
        progressKinds: _labels(j['progress_kinds']),
        documentKinds: _labels(j['document_kinds']),
        period: _s(j['period']),
        startsOn: _s(j['starts_on']),
        endsOn: _s(j['ends_on']),
        canWrite: j['can_write'] == true,
      );

  String get certificateCode => _s(cert?['code']) ?? 'no code yet';
  String get certificateStatus => _s(cert?['status_label']) ?? '';
  String? get certificateReason => _s(cert?['reason']);
}

String _today() {
  final now = DateTime.now();
  return '${now.year.toString().padLeft(4, '0')}-${now.month.toString().padLeft(2, '0')}-${now.day.toString().padLeft(2, '0')}';
}
