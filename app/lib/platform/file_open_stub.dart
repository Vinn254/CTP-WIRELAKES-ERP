import 'dart:js_interop';
import 'dart:typed_data';

import 'package:web/web.dart' as web;

/// A browser has no app folder to write into, so the bytes go out as a download the page itself starts —
/// the same file the phone would have opened. Returns null when it went, or the words to show the member.
Future<String?> saveAndOpen(Uint8List bytes, String name) async {
  final url = web.URL.createObjectURL(web.Blob([bytes.toJS].toJS));
  final anchor = web.document.createElement('a') as web.HTMLAnchorElement
    ..href = url
    ..download = name
    ..style.display = 'none';
  web.document.body!.appendChild(anchor);
  anchor.click();
  anchor.remove();
  web.URL.revokeObjectURL(url);
  return null;
}
