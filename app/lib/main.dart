import 'package:flutter/material.dart';
import 'config/api_config.dart';
import 'services/api_client.dart';
import 'screens/home_screen.dart';

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
      ),
      home: HomeScreen(api: api),
    );
  }
}

/// Reusable brand widget.
class BrandHeader extends StatelessWidget {
  final String? subtitle;
  const BrandHeader({super.key, this.subtitle});

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Image.asset('assets/logo.png', height: 96),
        const SizedBox(height: 12),
        const Text(
          'CTP-WireLakes',
          style: TextStyle(
            fontWeight: FontWeight.w800,
            color: Color(0xFF0E6B3A),
            letterSpacing: 1.2,
          ),
        ),
        if (subtitle != null) ...[
          const SizedBox(height: 4),
          Text(
            subtitle!,
            style: const TextStyle(color: Color(0xFF355A40), fontSize: 12),
          ),
        ],
        const SizedBox(height: 8),
        Text(
          'API: ${ApiConfig.baseUrl}',
          style: const TextStyle(color: Color(0xFF6F8C7C), fontSize: 11),
        ),
      ],
    );
  }
}
