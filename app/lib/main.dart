import 'package:flutter/material.dart';
import 'services/api_client.dart';
import 'screens/role_gate.dart';
import 'widgets/ui.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  final api = ApiClient();
  await api.loadToken();
  runApp(CtpWireLakesApp(api: api));
}

class CtpWireLakesApp extends StatelessWidget {
  final ApiClient api;
  const CtpWireLakesApp({super.key, required this.api});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'CTP-WireLakes',
      debugShowCheckedModeBanner: false,
      theme: _brand(),
      // A stored token only means "try the portal" — RoleGate asks the server
      // who it belongs to and opens the member portal or the staff office.
      home: RoleGate(api: api),
    );
  }

  /// One theme for the whole app, mirroring the website's sheets: white cards on a hairline rule, the brand
  /// green on every action, and the bright lime for what is selected, focused or waiting.
  ThemeData _brand() {
    final scheme = ColorScheme.fromSeed(
      seedColor: green,
      brightness: Brightness.light,
    ).copyWith(
      primary: green,
      onPrimary: Colors.white,
      secondary: lime,
      onSecondary: onLime,
      surface: Colors.white,
      onSurface: ink,
      error: danger,
      outline: line,
    );

    const cardRadius = BorderRadius.all(Radius.circular(14));

    OutlineInputBorder border(Color color, [double width = 1.5]) =>
        OutlineInputBorder(
          borderRadius: cardRadius,
          borderSide: BorderSide(color: color, width: width),
        );

    return ThemeData(
      useMaterial3: true,
      colorScheme: scheme,
      scaffoldBackgroundColor: page,
      appBarTheme: const AppBarTheme(
        backgroundColor: green,
        foregroundColor: Colors.white,
        elevation: 0,
        scrolledUnderElevation: 0,
        centerTitle: false,
        titleTextStyle: TextStyle(
            color: Colors.white, fontSize: 17, fontWeight: FontWeight.w800),
        iconTheme: IconThemeData(color: Colors.white),
      ),
      cardTheme: CardThemeData(
        color: Colors.white,
        surfaceTintColor: Colors.transparent,
        // The web card is flat with a hairline rule; the lift is for tap targets only.
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(14),
          side: const BorderSide(color: line, width: 1.5),
        ),
      ),
      dividerTheme: const DividerThemeData(color: line, thickness: 1, space: 1),
      listTileTheme: const ListTileThemeData(
        iconColor: muted,
        textColor: ink,
        contentPadding: EdgeInsets.symmetric(horizontal: 16, vertical: 2),
      ),
      navigationDrawerTheme: const NavigationDrawerThemeData(
        backgroundColor: Colors.white,
        indicatorColor: limeSoft,
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: green,
          foregroundColor: Colors.white,
          // Height only — a stretched Column makes a button fill the row, while a button that sits
          // beside a title or in a dialog box keeps to its own words.
          minimumSize: const Size(0, 48),
          padding: const EdgeInsets.symmetric(horizontal: 20),
          textStyle: const TextStyle(fontSize: 14.5, fontWeight: FontWeight.w800),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        ),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: green,
          foregroundColor: Colors.white,
          minimumSize: const Size(0, 48),
          padding: const EdgeInsets.symmetric(horizontal: 20),
          textStyle: const TextStyle(fontSize: 14.5, fontWeight: FontWeight.w800),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: green,
          minimumSize: const Size(0, 46),
          padding: const EdgeInsets.symmetric(horizontal: 18),
          side: const BorderSide(color: line, width: 1.5),
          textStyle: const TextStyle(fontSize: 14, fontWeight: FontWeight.w700),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: green,
          textStyle: const TextStyle(fontSize: 13.5, fontWeight: FontWeight.w700),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: Colors.white,
        isDense: true,
        contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
        hintStyle: const TextStyle(color: muted, fontSize: 13),
        labelStyle: const TextStyle(color: muted, fontWeight: FontWeight.w700),
        floatingLabelStyle: const TextStyle(color: green, fontWeight: FontWeight.w800),
        enabledBorder: border(line),
        border: border(line),
        focusedBorder: border(lime, 2),
        errorBorder: border(danger),
        focusedErrorBorder: border(danger, 2),
      ),
      chipTheme: ChipThemeData(
        backgroundColor: Colors.white,
        side: const BorderSide(color: line),
        selectedColor: limeSoft,
        labelStyle: const TextStyle(color: ink, fontSize: 13, fontWeight: FontWeight.w600),
        secondaryLabelStyle:
            const TextStyle(color: greenDark, fontSize: 13, fontWeight: FontWeight.w800),
        shape: const StadiumBorder(),
      ),
      snackBarTheme: SnackBarThemeData(
        backgroundColor: greenDark,
        contentTextStyle: const TextStyle(color: Colors.white, fontSize: 13.5),
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      ),
      progressIndicatorTheme: const ProgressIndicatorThemeData(color: green),
      bottomSheetTheme: const BottomSheetThemeData(
        backgroundColor: Colors.white,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.vertical(top: Radius.circular(22))),
      ),
      dialogTheme: DialogThemeData(
        backgroundColor: Colors.white,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
      ),
    );
  }
}
