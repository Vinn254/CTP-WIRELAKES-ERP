import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import '../config/api_config.dart';

class ApiException implements Exception {
  final int statusCode;
  final String message;
  final dynamic body;
  ApiException(this.statusCode, this.message, [this.body]);
  @override
  String toString() => 'ApiException($statusCode): $message';
}

/// Thin wrapper around the Laravel API with a Sanctum bearer token.
class ApiClient {
  static const _tokenKey = 'ctp_sanctum_token';

  String? _token;
  String? get token => _token;
  bool get isAuthenticated => _token != null && _token!.isNotEmpty;

  /// Load a previously stored token (if any) from local storage.
  Future<void> loadToken() async {
    final prefs = await SharedPreferences.getInstance();
    _token = prefs.getString(_tokenKey);
  }

  Future<void> _saveToken(String token) async {
    _token = token;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_tokenKey, token);
  }

  Future<void> clearToken() async {
    _token = null;
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_tokenKey);
  }

  Map<String, String> _headers({bool json = true}) {
    return {
      if (json) 'Content-Type': 'application/json',
      'Accept': 'application/json',
      if (_token != null) 'Authorization': 'Bearer $_token',
    };
  }

  Future<dynamic> get(String path) async {
    final res = await http
        .get(Uri.parse(ApiConfig.url(path)), headers: _headers(json: false))
        .timeout(const Duration(seconds: 10));
    return _parse(res);
  }

  Future<dynamic> post(String path, Map<String, dynamic> body) async {
    final res = await http
        .post(Uri.parse(ApiConfig.url(path)),
            headers: _headers(), body: jsonEncode(body))
        .timeout(const Duration(seconds: 10));
    return _parse(res);
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
    await _saveToken(data['token'] as String);
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
    await _saveToken(data['token'] as String);
    return data;
  }

  Future<Map<String, dynamic>> me() async {
    final data = await get('/me') as Map<String, dynamic>;
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
}
