import 'package:flutter/material.dart';
import '../config/api_config.dart';
import '../main.dart';
import '../services/api_client.dart';

class HomeScreen extends StatefulWidget {
  final ApiClient api;
  const HomeScreen({super.key, required this.api});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  String _status = 'Not yet contacted.';
  String? _userEmail;
  bool _busy = false;
  final _email = TextEditingController(text: 'vincent@ctp-wirelakes.test');
  final _password = TextEditingController(text: 'wirelakes-dev-2026');

  @override
  void initState() {
    super.initState();
    _userEmail = null;
    if (widget.api.isAuthenticated) {
      _whoAmI();
    }
  }

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _checkHealth() async {
    setState(() {
      _busy = true;
      _status = 'GET ${ApiConfig.url('/health')} ...';
    });
    try {
      final body = await widget.api.get('/health') as Map<String, dynamic>;
      final db = body['checks']?['database'] ?? '?';
      setState(() {
        _status =
            'Healthy. Laravel ${body['laravel']} on PHP ${body['php']} | DB: $db';
      });
    } catch (e) {
      setState(() => _status = 'Health error: $e');
    } finally {
      setState(() => _busy = false);
    }
  }

  Future<void> _login() async {
    setState(() {
      _busy = true;
      _status = 'POST ${ApiConfig.url('/auth/login')} ...';
    });
    try {
      final data = await widget.api.login(
        email: _email.text.trim(),
        password: _password.text,
      );
      final user = data['user'] as Map<String, dynamic>;
      setState(() {
        _userEmail = user['email'] as String?;
        _status = 'Logged in as ${user['name']}';
      });
      await _whoAmI();
    } catch (e) {
      setState(() => _status = 'Login error: $e');
    } finally {
      setState(() => _busy = false);
    }
  }

  Future<void> _whoAmI() async {
    setState(() => _status = 'GET ${ApiConfig.url('/me')} ...');
    try {
      final data = await widget.api.me();
      final user = data['user'] as Map<String, dynamic>;
      setState(() {
        _userEmail = user['email'] as String?;
        _status = 'Authenticated as ${user['name']} <${user['email']}>';
      });
    } catch (e) {
      setState(() => _status = 'Auth error: $e');
    }
  }

  Future<void> _logout() async {
    setState(() => _busy = true);
    await widget.api.logout();
    setState(() {
      _userEmail = null;
      _status = 'Logged out.';
      _busy = false;
    });
  }

  @override
  Widget build(BuildContext context) {
    final isAuthed = widget.api.isAuthenticated;
    return Scaffold(
      appBar: AppBar(
        title: const Text('CTP-WireLakes'),
        actions: [
          if (isAuthed)
            IconButton(
              tooltip: 'Log out',
              icon: const Icon(Icons.logout),
              onPressed: _busy ? null : _logout,
            ),
        ],
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const SizedBox(height: 16),
              const BrandHeader(subtitle: 'Construction & Technology Enterprise'),
              const SizedBox(height: 28),
              if (!isAuthed) ...[
                TextField(
                  controller: _email,
                  decoration: const InputDecoration(
                    labelText: 'Email',
                    border: OutlineInputBorder(),
                  ),
                  keyboardType: TextInputType.emailAddress,
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _password,
                  decoration: const InputDecoration(
                    labelText: 'Password',
                    border: OutlineInputBorder(),
                  ),
                  obscureText: true,
                ),
                const SizedBox(height: 16),
                ElevatedButton.icon(
                  onPressed: _busy ? null : _login,
                  icon: const Icon(Icons.login),
                  label: const Text('Log in'),
                ),
              ] else ...[
                Card(
                  child: ListTile(
                    leading: const Icon(Icons.person, color: Color(0xFF0E6B3A)),
                    title: Text(_userEmail ?? 'Authenticated'),
                    subtitle: const Text('Tap below to refresh /api/me'),
                    trailing: IconButton(
                      icon: const Icon(Icons.refresh),
                      onPressed: _busy ? null : _whoAmI,
                    ),
                  ),
                ),
              ],
              const SizedBox(height: 16),
              OutlinedButton.icon(
                onPressed: _busy ? null : _checkHealth,
                icon: const Icon(Icons.medical_services_outlined),
                label: const Text('Check /api/health'),
              ),
              const SizedBox(height: 24),
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: const Color(0xFFE7F3E3),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: const Color(0xFFB5D8A8)),
                ),
                child: Text(
                  _status,
                  style: const TextStyle(
                    color: Color(0xFF10331F),
                    fontFamily: 'monospace',
                    fontSize: 13,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
