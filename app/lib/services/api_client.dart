import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import '../config/api_config.dart';
import '../models/field_models.dart';
import '../models/member_models.dart';
import '../models/portal_models.dart';
import '../models/work_models.dart';

class ApiException implements Exception {
  final int statusCode;
  final String message;
  final dynamic body;
  ApiException(this.statusCode, this.message, [this.body]);
  @override
  String toString() => 'ApiException($statusCode): $message';
}

/// A picture the member chose on the phone. It travels as base64 in the JSON body
/// under `<input>_b64`, which the backend re-attaches as a real upload and hands to
/// the same storePhoto()/storeProof() writers the website uses.
class PickedPhoto {
  final String base64;
  final String name;
  const PickedPhoto({required this.base64, required this.name});
}

/// A private file the API returns inline as a data URI (photos are never public URLs).
class InlineFile {
  final String? name;
  final String? dataUri;
  const InlineFile({this.name, this.dataUri});
  const InlineFile.none() : name = null, dataUri = null;
  bool get hasFile => dataUri != null && dataUri!.isNotEmpty;

  factory InlineFile.fromJson(Map<String, dynamic> j) =>
      InlineFile(name: j['name'] as String?, dataUri: j['file'] as String?);
}

/// Thin wrapper around the Laravel API with a Sanctum bearer token.
class ApiClient {
  static const _tokenKey = 'ctp_sanctum_token';
  static const _kindKey = 'ctp_account_kind';

  String? _token;
  String? get token => _token;
  bool get isAuthenticated => _token != null && _token!.isNotEmpty;

  /// How long the phone may sit untouched before the app signs itself out. The server answers
  /// with it on /me, so the app and the website always expire on the same window.
  int idleMinutes = 5;

  /// Which kind of account owns the current token: 'member' or 'staff'. Drives
  /// which home screen the app opens — the native portal or the web office.
  String? _kind;
  String? get kind => _kind;
  bool get isStaff => _kind == 'staff';

  /// Load a previously stored token (and its account kind) from local storage.
  Future<void> loadToken() async {
    final prefs = await SharedPreferences.getInstance();
    _token = prefs.getString(_tokenKey);
    _kind = prefs.getString(_kindKey);
  }

  Future<void> _saveToken(String token) async {
    _token = token;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_tokenKey, token);
  }

  Future<void> saveKind(String kind) async {
    _kind = kind;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_kindKey, kind);
  }

  Future<void> clearToken() async {
    _token = null;
    _kind = null;
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_tokenKey);
    await prefs.remove(_kindKey);
  }

  Map<String, String> _headers({bool json = true}) {
    return {
      if (json) 'Content-Type': 'application/json',
      'Accept': 'application/json',
      if (_token != null) 'Authorization': 'Bearer $_token',
    };
  }

  /// Generous on purpose: `php artisan serve` answers one request at a time, so a screen that opens with
  /// several calls waits behind them. A short limit turned a merely slow office into "server unreachable".
  static const _wait = Duration(seconds: 45);

  Future<dynamic> get(String path) async {
    final res = await http
        .get(Uri.parse(ApiConfig.url(path)), headers: _headers(json: false))
        .timeout(_wait);
    return _parse(res);
  }

  Future<dynamic> post(String path, Map<String, dynamic> body) async {
    final res = await http
        .post(Uri.parse(ApiConfig.url(path)),
            headers: _headers(), body: jsonEncode(_flatten(body)))
        .timeout(_wait);
    return _parse(res);
  }

  Future<dynamic> delete(String path) async {
    final res = await http
        .delete(Uri.parse(ApiConfig.url(path)), headers: _headers())
        .timeout(_wait);
    return _parse(res);
  }

  /// A chosen picture goes out as `<input>_b64` + `<input>_name`; empty answers are
  /// left out so the backend's own validators decide what is required.
  Map<String, dynamic> _flatten(Map<String, dynamic> body) {
    final out = <String, dynamic>{};
    body.forEach((key, value) {
      if (value == null) return;
      if (value is PickedPhoto) {
        out['${key}_b64'] = value.base64;
        out['${key}_name'] = value.name;
        return;
      }
      if (value is String && value.isEmpty) return;
      if (value is List && value.isEmpty) return;
      out[key] = value;
    });
    return out;
  }

  dynamic _parse(http.Response res) {
    final code = res.statusCode;
    final body = res.body.isEmpty ? null : jsonDecode(res.body);
    if (code >= 200 && code < 300) return body;
    final msg = body is Map && body['message'] != null
        ? body['message'].toString()
        : 'HTTP $code';
    throw ApiException(code, msg, body);
  }

  // ---- High-level auth helpers -----------------------------------------
  //
  // Signing up, and signing in as management, answers with a challenge instead of a token: the person
  // has to type back the code that was emailed to them first. Only /auth/otp/verify hands over a token.

  Future<Map<String, dynamic>> register({
    required String name,
    required String email,
    required String password,
  }) async {
    final data = await post('/auth/register', {
      'name': name,
      'email': email,
      'password': password,
    }) as Map<String, dynamic>;
    if (data['token'] != null) await _saveToken(data['token'] as String);
    return data;
  }

  Future<Map<String, dynamic>> login({
    required String email,
    required String password,
  }) async {
    final data = await post('/auth/login', {
      'email': email,
      'password': password,
    }) as Map<String, dynamic>;
    if (data['token'] != null) await _saveToken(data['token'] as String);
    return data;
  }

  /// The code from the email, against the challenge the last call returned.
  Future<Map<String, dynamic>> verifyOtp({
    required String challenge,
    required String code,
  }) async {
    final data = await post('/auth/otp/verify', {
      'challenge': challenge,
      'code': code,
    }) as Map<String, dynamic>;
    await _saveToken(data['token'] as String);
    return data;
  }

  Future<String> resendOtp(String challenge) async {
    final data = await post('/auth/otp/resend', {'challenge': challenge});
    return (data as Map)['challenge']?.toString() ?? challenge;
  }

  Future<String> forgotPassword(String email) async {
    final data = await post('/auth/forgot-password', {'email': email});
    return (data as Map)['message'].toString();
  }

  Future<String> resetPassword({
    required String token,
    required String password,
  }) async {
    final data = await post('/auth/reset-password', {
      'token': token,
      'password': password,
      'password_confirmation': password,
    });
    return (data as Map)['message'].toString();
  }

  Future<Map<String, dynamic>> me() async {
    final data = await get('/me') as Map<String, dynamic>;
    return data;
  }

  /// Ask the server who this token belongs to and remember the account kind so
  /// the app opens the right home on the next cold start.
  Future<Map<String, dynamic>> resolveAccount() async {
    final data = await me();
    if (data['idle_minutes'] is int) idleMinutes = data['idle_minutes'] as int;
    if (data['staff'] == true) {
      await saveKind('staff');
    } else if (data['member_type'] != null) {
      await saveKind('member');
    }
    return data;
  }

  Future<void> logout() async {
    try {
      await post('/auth/logout', {});
    } catch (_) {
      // even if the server call fails, drop the local token
    }
    await clearToken();
  }

  // ---- Member portal endpoints -----------------------------------------

  Future<Board> board() async {
    final data = await get('/me/board') as Map<String, dynamic>;
    return Board.fromJson(data);
  }

  Future<List<ProjectSummary>> projects() async {
    final data = await get('/projects') as Map<String, dynamic>;
    return (data['projects'] as List)
        .map((e) => ProjectSummary.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<ProjectView> projectView(int id) async {
    final data = await get('/projects/$id') as Map<String, dynamic>;
    return ProjectView.fromJson(data);
  }

  Future<Application> apply(int projectId, {String? note}) async {
    final data = await post('/projects/$projectId/apply', {
      if (note != null && note.isNotEmpty) 'note': note,
    }) as Map<String, dynamic>;
    return Application.fromJson(data['application'] as Map<String, dynamic>);
  }

  Future<List<Application>> applications() async {
    final data = await get('/applications') as Map<String, dynamic>;
    return (data['applications'] as List)
        .map((e) => Application.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<void> withdraw(int applicationId) async {
    await post('/applications/$applicationId/withdraw', {});
  }

  // ---- My E-Fundi record -------------------------------------------------

  Future<EfundiRecord> efundi() async =>
      EfundiRecord.fromJson(await get('/efundi') as Map<String, dynamic>);

  Future<String> saveAvailability(String availability, {String? note}) async {
    final data = await post('/efundi/availability',
        {'availability': availability, 'availability_note': note});
    return (data as Map)['message'].toString();
  }

  Future<String> saveSkills(List<String> specialisations, List<String> counties) async {
    final data = await post('/efundi/skills', {
      'specialisations': specialisations,
      'service_counties': counties,
    });
    return (data as Map)['message'].toString();
  }

  Future<InlineFile> efundiFile(String slot) async {
    final data = await get('/efundi/file/$slot') as Map<String, dynamic>;
    return InlineFile.fromJson(data);
  }

  // ---- Invited to you ----------------------------------------------------

  Future<InvitationBoard> invitations() async =>
      InvitationBoard.fromJson(await get('/invitations') as Map<String, dynamic>);

  Future<String> respondInvite(int matchId, String answer, {String? note}) async {
    final data = await post('/invitations/$matchId/respond',
        {'answer': answer, 'note': note});
    return (data as Map)['message'].toString();
  }

  // ---- My training (CTP) -------------------------------------------------

  Future<TrainingBoard> training() async =>
      TrainingBoard.fromJson(await get('/training') as Map<String, dynamic>);

  Future<String> applyCohort(int cohortId, {String? note}) async {
    final data = await post('/training/apply', {'cohort_id': cohortId, 'note': note});
    return (data as Map)['message'].toString();
  }

  Future<String> withdrawSeat(int enrollmentId) async {
    final data = await post('/training/$enrollmentId/withdraw', {});
    return (data as Map)['message'].toString();
  }

  // ---- My communities & mobilisation (CTP) --------------------------------

  Future<CommunityBoard> communities() async =>
      CommunityBoard.fromJson(await get('/communities') as Map<String, dynamic>);

  Future<String> storeCommunity(Map<String, dynamic> place) async {
    final data = await post('/communities', place);
    return (data as Map)['message'].toString();
  }

  Future<String> storeNeed(Map<String, dynamic> need) async {
    final data = await post('/communities/need', need);
    return (data as Map)['message'].toString();
  }

  Future<String> storeBeneficiary(Map<String, dynamic> beneficiary) async {
    final data = await post('/communities/beneficiary', beneficiary);
    return (data as Map)['message'].toString();
  }

  Future<Mobilisation> mobilisation() async =>
      Mobilisation.fromJson(await get('/mobilisation') as Map<String, dynamic>);

  // ---- E-Waste collection -------------------------------------------------

  Future<EwasteBoard> ewaste() async =>
      EwasteBoard.fromJson(await get('/ewaste') as Map<String, dynamic>);

  Future<String> storeEwaste(Map<String, dynamic> line) async {
    final data = await post('/ewaste', line);
    return (data as Map)['message'].toString();
  }

  Future<String> forwardEwaste(int collectionId, String condition, {String? note}) async {
    final data = await post('/ewaste/$collectionId/forward',
        {'condition': condition, 'note': note});
    return (data as Map)['message'].toString();
  }

  Future<InlineFile> ewastePhoto(int collectionId) async =>
      InlineFile.fromJson(await get('/ewaste/$collectionId/photo') as Map<String, dynamic>);

  // ---- Purchase request ---------------------------------------------------

  Future<ProcurementBoard> procurement() async =>
      ProcurementBoard.fromJson(await get('/procurement') as Map<String, dynamic>);

  Future<String> storeProcurement(Map<String, dynamic> request) async {
    final data = await post('/procurement', request);
    return (data as Map)['message'].toString();
  }

  // ---- My earnings --------------------------------------------------------

  Future<EarningsBoard> earnings() async =>
      EarningsBoard.fromJson(await get('/earnings') as Map<String, dynamic>);

  // ---- Training CTPs (senior E-Fundi) -------------------------------------

  Future<TrainerBoard> trainer() async =>
      TrainerBoard.fromJson(await get('/trainer') as Map<String, dynamic>);

  Future<String> applyTrainer(Map<String, dynamic> application) async {
    final data = await post('/trainer/apply', application);
    return (data as Map)['message'].toString();
  }

  Future<String> markProgress(Map<String, dynamic> entry) async {
    final data = await post('/trainer/progress', entry);
    return (data as Map)['message'].toString();
  }

  Future<String> markModule(int enrollmentId, int moduleId, Map<String, dynamic> mark) async {
    final data = await post('/trainer/$enrollmentId/modules/$moduleId', mark);
    return (data as Map)['message'].toString();
  }

  Future<String> unmarkModule(int enrollmentId, int moduleId) async {
    final data = await delete('/trainer/$enrollmentId/modules/$moduleId');
    return (data as Map)['message'].toString();
  }

  Future<String> storeTraineeDocument(int enrollmentId, Map<String, dynamic> document) async {
    final data = await post('/trainer/$enrollmentId/documents', document);
    return (data as Map)['message'].toString();
  }

  // ---- My work orders -----------------------------------------------------

  Future<WorkBoard> work() async =>
      WorkBoard.fromJson(await get('/work') as Map<String, dynamic>);

  Future<OrderSheet> workSheet(int orderId) async =>
      OrderSheet.fromJson(await get('/work/$orderId') as Map<String, dynamic>);

  Future<String> acceptOrder(int orderId, Map<String, dynamic> signature) async {
    final data = await post('/work/$orderId/accept', signature);
    return (data as Map)['message'].toString();
  }

  Future<String> logWorkDay(int orderId, Map<String, dynamic> entry) async {
    final data = await post('/work/$orderId/log', entry);
    return (data as Map)['message'].toString();
  }

  Future<String> requestCompletion(int orderId, Map<String, dynamic> finalPhotos) async {
    final data = await post('/work/$orderId/complete', finalPhotos);
    return (data as Map)['message'].toString();
  }

  Future<String> answerDispute(int orderId, int disputeId, String response) async {
    final data = await post('/work/$orderId/dispute/$disputeId/answer',
        {'response': response});
    return (data as Map)['message'].toString();
  }

  Future<InlineFile> workUpdatePhoto(int orderId, int updateId) async => InlineFile.fromJson(
      await get('/work/$orderId/update/$updateId/photo') as Map<String, dynamic>);

  Future<InlineFile> disputeEvidence(int orderId, int disputeId) async => InlineFile.fromJson(
      await get('/work/$orderId/dispute/$disputeId/evidence') as Map<String, dynamic>);

  // ---- Items to put right -------------------------------------------------

  Future<FixBoard> fixes() async =>
      FixBoard.fromJson(await get('/fixes') as Map<String, dynamic>);

  Future<String> proveFix(int itemId, Map<String, dynamic> proof) async {
    final data = await post('/fixes/$itemId/proof', proof);
    return (data as Map)['message'].toString();
  }

  Future<InlineFile> fixPhoto(int itemId) async =>
      InlineFile.fromJson(await get('/fixes/$itemId/photo') as Map<String, dynamic>);

  // ---- My repair jobs -----------------------------------------------------

  Future<List<TechJob>> techJobs() async {
    final data = await get('/tech') as Map<String, dynamic>;
    return (data['jobs'] as List? ?? [])
        .map((e) => TechJob.fromJson(Map<String, dynamic>.from(e as Map)))
        .toList();
  }

  Future<String> resolveJob(int ticketId, String resolution) async {
    final data = await post('/tech/$ticketId/resolve', {'resolution': resolution});
    return (data as Map)['message'].toString();
  }

  // ---- Profile photo ------------------------------------------------------

  Future<String> uploadProfilePhoto(PickedPhoto photo) async {
    final data = await post('/me/photo', {'passport_photo': photo});
    return (data as Map)['message'].toString();
  }

  Future<InlineFile> profilePhoto() async =>
      InlineFile.fromJson(await get('/me/photo') as Map<String, dynamic>);
}
