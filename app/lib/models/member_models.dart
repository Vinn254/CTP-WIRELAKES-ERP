/// Plain data classes mirroring the JSON the member API returns.
/// They are deliberately dumb — parsing lives here so the screens stay readable.
library;

class Member {
  final String type; // 'ctp' | 'fundi'
  final int id;
  final String? code;
  final String name;
  final String? phone;
  final String? email;
  final String? county;
  final String status;
  final String level;

  /// True once the office has moved this CTP into a fundi pool.
  final bool moved;
  final bool canTrain;

  Member({
    required this.type,
    required this.id,
    required this.code,
    required this.name,
    required this.phone,
    required this.email,
    required this.county,
    required this.status,
    required this.level,
    this.moved = false,
    this.canTrain = false,
  });

  bool get isFundi => type == 'fundi';
  String get typeLabel => isFundi ? 'E-Fundi' : 'CTP';

  /// What this person reads as. The office moving a CTP into a fundi pool changes the grade the app shows from
  /// then on — the API sends the pool in [level], so the nav never keeps saying "CTP trainee" (§8.2).
  String get gradeLabel => level.isNotEmpty ? level : (isFundi ? 'E-Fundi' : 'CTP trainee');

  /// The one line under a member's name: a moved CTP shows only their new grade, not "CTP · …".
  String get standing => moved ? gradeLabel : '$typeLabel · $level';

  /// The nav group heading, same wording the web sidebar uses.
  String get navGrade => (isFundi || moved) ? gradeLabel : 'CTP trainee';

  factory Member.fromJson(Map<String, dynamic> j) => Member(
        type: (j['type'] ?? 'ctp') as String,
        id: (j['id'] as num).toInt(),
        code: j['code'] as String?,
        name: (j['name'] ?? '') as String,
        phone: j['phone'] as String?,
        email: j['email'] as String?,
        county: j['county'] as String?,
        status: (j['status'] ?? '') as String,
        level: (j['level'] ?? '') as String,
        moved: j['moved'] == true,
        canTrain: j['can_train'] == true,
      );
}

class Counts {
  final int projects;
  final int applications;
  final int communities;
  final int invites;
  final int workOrders;
  final int fixes;
  final int repairJobs;

  Counts({
    required this.projects,
    required this.applications,
    required this.communities,
    required this.invites,
    required this.workOrders,
    required this.fixes,
    required this.repairJobs,
  });

  factory Counts.fromJson(Map<String, dynamic> j) => Counts(
        projects: _i(j['projects']),
        applications: _i(j['applications']),
        communities: _i(j['communities']),
        invites: _i(j['invites']),
        workOrders: _i(j['work_orders']),
        fixes: _i(j['fixes']),
        repairJobs: _i(j['repair_jobs']),
      );
}

class Board {
  final Member member;
  final Counts counts;
  Board({required this.member, required this.counts});

  factory Board.fromJson(Map<String, dynamic> j) => Board(
        member: Member.fromJson(j['member'] as Map<String, dynamic>),
        counts: Counts.fromJson(j['counts'] as Map<String, dynamic>),
      );
}

class ProjectSummary {
  final int id;
  final String? reference;
  final String title;
  final String category;
  final String categoryLabel;
  final String? trade;
  final String? county;
  final String? ward;
  final String? budget;
  final String? deadline;
  final String status;
  final bool canApply;

  ProjectSummary({
    required this.id,
    required this.reference,
    required this.title,
    required this.category,
    required this.categoryLabel,
    required this.trade,
    required this.county,
    required this.ward,
    required this.budget,
    required this.deadline,
    required this.status,
    required this.canApply,
  });

  factory ProjectSummary.fromJson(Map<String, dynamic> j) => ProjectSummary(
        id: _i(j['id']),
        reference: j['reference'] as String?,
        title: (j['title'] ?? '') as String,
        category: (j['category'] ?? '') as String,
        categoryLabel: (j['category_label'] ?? j['category'] ?? '') as String,
        trade: j['trade'] as String?,
        county: j['county'] as String?,
        ward: j['ward'] as String?,
        budget: (j['budget']?.toString()),
        deadline: j['deadline'] as String?,
        status: (j['status'] ?? '') as String,
        canApply: j['can_apply'] == true,
      );
}

class ProjectDetail extends ProjectSummary {
  final String? description;
  final String? materials;
  final String? duration;
  final String? hirer;

  ProjectDetail({
    required super.id,
    required super.reference,
    required super.title,
    required super.category,
    required super.categoryLabel,
    required super.trade,
    required super.county,
    required super.ward,
    required super.budget,
    required super.deadline,
    required super.status,
    required super.canApply,
    required this.description,
    required this.materials,
    required this.duration,
    required this.hirer,
  });

  factory ProjectDetail.fromJson(Map<String, dynamic> j) => ProjectDetail(
        id: _i(j['id']),
        reference: j['reference'] as String?,
        title: (j['title'] ?? '') as String,
        category: (j['category'] ?? '') as String,
        categoryLabel: (j['category_label'] ?? j['category'] ?? '') as String,
        trade: j['trade'] as String?,
        county: j['county'] as String?,
        ward: j['ward'] as String?,
        budget: j['budget']?.toString(),
        deadline: j['deadline'] as String?,
        status: (j['status'] ?? '') as String,
        canApply: j['can_apply'] == true,
        description: j['description'] as String?,
        materials: j['materials'] as String?,
        duration: j['duration'] as String?,
        hirer: j['hirer'] as String?,
      );
}

class ProjectView {
  final ProjectDetail project;
  final Application? application; // this member's existing application, if any
  ProjectView({required this.project, required this.application});

  factory ProjectView.fromJson(Map<String, dynamic> j) => ProjectView(
        project: ProjectDetail.fromJson(j['project'] as Map<String, dynamic>),
        application: j['application'] == null
            ? null
            : Application.fromJson(j['application'] as Map<String, dynamic>),
      );
}

class Application {
  final int id;
  final int projectId;
  final String? projectTitle;
  final String? projectReference;
  final String? status;
  final String? statusLabel;
  final String? note;
  final String? appliedOn;
  final bool canWithdraw;

  Application({
    required this.id,
    required this.projectId,
    required this.projectTitle,
    required this.projectReference,
    required this.status,
    required this.statusLabel,
    required this.note,
    required this.appliedOn,
    required this.canWithdraw,
  });

  factory Application.fromJson(Map<String, dynamic> j) => Application(
        id: _i(j['id']),
        projectId: _i(j['project_id']),
        projectTitle: j['project_title'] as String?,
        projectReference: j['project_reference'] as String?,
        status: j['status'] as String?,
        statusLabel: j['status_label'] as String?,
        note: j['note'] as String?,
        appliedOn: j['applied_on'] as String?,
        canWithdraw: j['can_withdraw'] == true,
      );
}

int _i(dynamic v) => v is int ? v : (v is num ? v.toInt() : int.tryParse('$v') ?? 0);
