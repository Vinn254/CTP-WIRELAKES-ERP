/// API base URL configuration for the CTP-WireLakes app.
///
/// Resolve order (first match wins):
///   1. `--dart-define=API_BASE_URL=https://api.ctp-wirelakes.test`
///      Set this on the build/run command for production or LAN testing.
///   2. Platform default:
///        - Android emulator -> http://10.0.2.2:8000  (loopback to host PC)
///        - iOS simulator    -> http://127.0.0.1:8000 (loopback to host Mac)
///        - Windows desktop  -> http://127.0.0.1:8000
///   3. Hard-coded localhost fallback.
///
/// To run against a phone on your Wi-Fi:
///   flutter run --dart-define=API_BASE_URL=http://192.168.1.42:8000
library;

import 'package:flutter/foundation.dart' show TargetPlatform, defaultTargetPlatform;

class ApiConfig {
  ApiConfig._();

  /// Reads --dart-define if present.
  static const String _fromDefine = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: '',
  );

  /// Sensible default per platform.
  static String get _platformDefault {
    // Only an Android emulator reaches its own computer through 10.0.2.2; a real device has no backend on it,
    // so it is built with --dart-define and never lands here.
    return defaultTargetPlatform == TargetPlatform.android
        ? 'http://10.0.2.2:8000'
        : 'http://127.0.0.1:8000';
  }

  /// The base URL without a trailing slash, e.g. "http://10.0.2.2:8000".
  static String get baseUrl {
    final raw = _fromDefine.isNotEmpty ? _fromDefine : _platformDefault;
    return raw.replaceAll(RegExp(r'/+$'), '');
  }

  /// API prefix. Laravel routes in routes/api.php are mounted at /api.
  static const String apiPrefix = '/api';

  /// Full URL for a path, e.g. ApiConfig.url('/health') -> http://...:8000/api/health
  static String url(String path) {
    final p = path.startsWith('/') ? path : '/$path';
    if (p.startsWith(apiPrefix)) return '$baseUrl$p';
    return '$baseUrl$apiPrefix$p';
  }
}
