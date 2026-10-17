/// My E-Fundi record, Invited to you and My training — the JSON that
/// /api/efundi, /api/invitations and /api/training return, one class per block.
library;

class EfundiSite {
  final String? venue;
  final String? county;
  final String? status;
  final String statusLabel;
  final List<String> trainings;

  EfundiSite({
    required this.venue,
    required this.county,
    required this.status,
    required this.statusLabel,
    required this.trainings,
  });

  factory EfundiSite.fromJson(Map<String, dynamic> j) => EfundiSite(
        venue: j['venue'] as String?,
        county: j['county'] as String?,
        status: j['status'] as String?,
        statusLabel: (j['status_label'] ?? j['status'] ?? '') as String,
        trainings: _strings(j['trainings']),
      );
}

class RecordFile {
  final String slot;
  final String label;
  RecordFile({required this.slot, required this.label});

  factory RecordFile.fromJson(Map<String, dynamic> j) =>
      RecordFile(slot: (j['slot'] ?? '') as String, label: (j['label'] ?? '') as String);
}

class EfundiRecord {
  final String level;
  final String tier;
  final String status;
  final String statusLabel;
  final String? place;
  final bool canTrain;
  final String? availability;
  final String availabilityLabel;
  final String? availabilityNote;
  final Map<String, String> availabilityOptions;
  final List<String> specialisations;
  final Map<String, String> specialisationOptions;
  final List<String> serviceCounties;
  final List<String> countyOptions;
  final List<EfundiSite> sites;
  final List<RecordFile> documents;

  EfundiRecord({
    required this.level,
    required this.tier,
    required this.status,
    required this.statusLabel,
    required this.place,
    required this.canTrain,
    required this.availability,
    required this.availabilityLabel,
    required this.availabilityNote,
    required this.availabilityOptions,
    required this.specialisations,
    required this.specialisationOptions,
    required this.serviceCounties,
    required this.countyOptions,
    required this.sites,
    required this.documents,
  });

  factory EfundiRecord.fromJson(Map<String, dynamic> j) => EfundiRecord(
        level: (j['level'] ?? '') as String,
        tier: (j['tier'] ?? '') as String,
        status: (j['status'] ?? '') as String,
        statusLabel: (j['status_label'] ?? j['status'] ?? '') as String,
        place: j['place'] as String?,
        canTrain: j['can_train'] == true,
        availability: j['availability'] as String?,
        availabilityLabel: (j['availability_label'] ?? 'Not set') as String,
        availabilityNote: j['availability_note'] as String?,
        availabilityOptions: _options(j['availability_options']),
        specialisations: _strings(j['specialisations']),
        specialisationOptions: _options(j['specialisation_options']),
        serviceCounties: _strings(j['service_counties']),
        countyOptions: _strings(j['county_options']),
        sites: (j['sites'] as List? ?? [])
            .map((e) => EfundiSite.fromJson(e as Map<String, dynamic>))
            .toList(),
        documents: (j['documents'] as List? ?? [])
            .map((e) => RecordFile.fromJson(e as Map<String, dynamic>))
            .toList(),
      );
}

class Invite {
  final int id;
  final String status;
  final String statusLabel;
  final String? projectTitle;
  final String? projectReference;
  final String? hirer;
  final int score;
  final List<String> matched;
  final int? round;
  final String? timeLabel;
  final bool canRespond;

  Invite({
    required this.id,
    required this.status,
    required this.statusLabel,
    required this.projectTitle,
    required this.projectReference,
    required this.hirer,
    required this.score,
    required this.matched,
    required this.round,
    required this.timeLabel,
    required this.canRespond,
  });

  factory Invite.fromJson(Map<String, dynamic> j) => Invite(
        id: _i(j['id']),
        status: (j['status'] ?? '') as String,
        statusLabel: (j['status_label'] ?? j['status'] ?? '') as String,
        projectTitle: j['project_title'] as String?,
        projectReference: j['project_reference'] as String?,
        hirer: j['hirer'] as String?,
        score: _i(j['score']),
        matched: _strings(j['matched']),
        round: j['round'] == null ? null : _i(j['round']),
        timeLabel: j['time_label'] as String?,
        canRespond: j['can_respond'] == true,
      );
}

class InvitationBoard {
  final int window;
  final List<Invite> invites;
  final Map<String, int> counts;

  InvitationBoard({required this.window, required this.invites, required this.counts});

  factory InvitationBoard.fromJson(Map<String, dynamic> j) => InvitationBoard(
        window: _i(j['window']),
        invites: (j['invites'] as List? ?? [])
            .map((e) => Invite.fromJson(e as Map<String, dynamic>))
            .toList(),
        counts: (j['counts'] as Map? ?? {}).map((k, v) => MapEntry('$k', _i(v))),
      );

  int get outstanding => counts['outstanding'] ?? 0;
}

class CohortSeat {
  final int id;
  final String reference;
  final String course;
  final String? place;
  final String? county;
  final String? period;
  final String? dates;
  final int? seatsLeft;
  final bool applied;
  final bool isOpen;

  CohortSeat({
    required this.id,
    required this.reference,
    required this.course,
    required this.place,
    required this.county,
    required this.period,
    required this.dates,
    required this.seatsLeft,
    required this.applied,
    required this.isOpen,
  });

  factory CohortSeat.fromJson(Map<String, dynamic> j) => CohortSeat(
        id: _i(j['id']),
        reference: (j['reference'] ?? '') as String,
        course: (j['course'] ?? '') as String,
        place: j['place'] as String?,
        county: j['county'] as String?,
        period: j['period'] as String?,
        dates: j['dates'] as String?,
        seatsLeft: j['seats_left'] == null ? null : _i(j['seats_left']),
        applied: j['applied'] == true,
        isOpen: j['is_open'] == true,
      );
}

/// One of the CTP's seats on a cohort, as the portal's training screen shows it.
class Seat {
  final int id;
  final String? cohort;
  final String? course;
  final String? period;
  final String state;
  final String stateLabel;
  final String stage;
  final String stageLabel;
  final int trackPct;
  final int attendancePct;
  final bool canWithdraw;

  Seat({
    required this.id,
    required this.cohort,
    required this.course,
    required this.period,
    required this.state,
    required this.stateLabel,
    required this.stage,
    required this.stageLabel,
    required this.trackPct,
    required this.attendancePct,
    required this.canWithdraw,
  });

  factory Seat.fromJson(Map<String, dynamic> j) => Seat(
        id: _i(j['id']),
        cohort: j['cohort'] as String?,
        course: j['course'] as String?,
        period: j['period'] as String?,
        state: (j['state'] ?? '') as String,
        stateLabel: (j['state_label'] ?? j['state'] ?? '') as String,
        stage: (j['stage'] ?? '') as String,
        stageLabel: (j['stage_label'] ?? j['stage'] ?? '') as String,
        trackPct: _i(j['track_pct']),
        attendancePct: _i(j['attendance_pct']),
        canWithdraw: j['can_withdraw'] == true,
      );
}

class TrainingBoard {
  final String status;
  final bool canApply;
  final List<CohortSeat> nearby;
  final List<CohortSeat> elsewhere;
  final List<Seat> mine;
  final List<Seat> history;

  TrainingBoard({
    required this.status,
    required this.canApply,
    required this.nearby,
    required this.elsewhere,
    required this.mine,
    required this.history,
  });

  factory TrainingBoard.fromJson(Map<String, dynamic> j) => TrainingBoard(
        status: (j['status'] ?? '') as String,
        canApply: j['can_apply'] == true,
        nearby: _seats(j['nearby'], CohortSeat.fromJson),
        elsewhere: _seats(j['elsewhere'], CohortSeat.fromJson),
        mine: _seats(j['mine'], Seat.fromJson),
        history: _seats(j['history'], Seat.fromJson),
      );
}

List<T> _seats<T>(dynamic raw, T Function(Map<String, dynamic>) parse) =>
    (raw as List? ?? []).map((e) => parse(e as Map<String, dynamic>)).toList();

/// A choice list arrives either as a keyed map (value → label) or as a plain list
/// of values, depending on the model constant behind it. Both become value → label.
Map<String, String> _options(dynamic raw) {
  if (raw is List) return {for (final v in raw) '$v': '$v'};
  return (raw as Map? ?? {}).map((k, v) => MapEntry('$k', '$v'));
}

List<String> _strings(dynamic raw) =>
    (raw as List? ?? []).map((e) => '$e').toList();

int _i(dynamic v) => v is int ? v : (v is num ? v.toInt() : int.tryParse('$v') ?? 0);
