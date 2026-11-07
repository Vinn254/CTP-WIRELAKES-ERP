/// Handing bytes to whatever reads that file type. The phone writes the file into the app's own folder and lets
/// Android open it; a browser has no folder to write into, so it says so rather than failing silently.
library;

export 'file_open_stub.dart' if (dart.library.io) 'file_open_io.dart';
