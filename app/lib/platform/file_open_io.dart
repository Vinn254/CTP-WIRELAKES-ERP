import 'dart:io';
import 'dart:typed_data';

import 'package:open_file/open_file.dart';
import 'package:path_provider/path_provider.dart';

/// Writes the document into the app's own folder and hands it to whichever app on the phone reads that type.
/// Returns null when the file went out happily, or the words to show the member when it did not.
Future<String?> saveAndOpen(Uint8List bytes, String name) async {
  final directory = await getApplicationDocumentsDirectory();
  final path = '${directory.path}/$name';
  await File(path).writeAsBytes(bytes);
  final result = await OpenFile.open(path);
  if (result.type == ResultType.done) return null;
  return result.message.isNotEmpty ? result.message : 'No app on this phone can open it.';
}
