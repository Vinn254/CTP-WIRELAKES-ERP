import 'package:flutter/material.dart';
import 'services/api_client.dart';
import 'screens/role_gate.dart';

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
      theme: ThemeData(
        useMaterial3: true,
        colorScheme: ColorScheme.fromSeed(
          seedColor: const Color(0xFF0E6B3A),
          brightness: Brightness.light,
        ),
        appBarTheme: const AppBarTheme(
          backgroundColor: Color(0xFF0E6B3A),
          foregroundColor: Colors.white,
          elevation: 0,
        ),
        elevatedButtonTheme: ElevatedButtonThemeData(
          style: ElevatedButton.styleFrom(
            backgroundColor: const Color(0xFF0E6B3A),
            foregroundColor: Colors.white,
            minimumSize: const Size.fromHeight(48),
          ),
        ),
        filledButtonTheme: FilledButtonThemeData(
          style: FilledButton.styleFrom(
            backgroundColor: const Color(0xFF0E6B3A),
            foregroundColor: Colors.white,
          ),
        ),
      ),
      // A stored token only means "try the portal" — RoleGate asks the server
      // who it belongs to and opens the member portal or the staff office.
      home: RoleGate(api: api),
    );
  }
}
