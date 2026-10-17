import 'package:flutter/material.dart';
import '../config/api_config.dart';
import '../main.dart';
import '../services/api_client.dart';
import '../widgets/ui.dart';
import 'dashboard_shell.dart';

class LoginScreen extends StatefulWidget {
  final ApiClient api;
  final String? notice;
  const LoginScreen({super.key, required this.api, this.notice});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _email = TextEditingController();
  final _password = TextEditingController();
  bool _busy = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _error = widget.notice;
  }

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _login() async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await widget.api.login(
        email: _email.text.trim(),
        password: _password.text,
      );
      // Confirm it is a member account. Staff belong on the website office.
      final me = await widget.api.resolveAccount();
      final isStaff = me['staff'] == true;
      final isMember = me['member_type'] != null;
      if (isStaff || !isMember) {
        await widget.api.clearToken();
        if (mounted) {
          setState(() {
            _busy = false;
            _error = isStaff
                ? 'Staff sign in on the website office, not in this app.'
                : 'This account has no member portal yet.';
          });
        }
        return;
      }
      if (mounted) {
        Navigator.of(context).pushReplacement(
          MaterialPageRoute(
            builder: (_) => DashboardShell(
              api: widget.api,
            ),
          ),
        );
      }
    } on ApiException catch (e) {
      if (mounted) {
        setState(() {
          _busy = false;
          _error = e.statusCode == 401 ? 'Wrong email or password.' : e.message;
        });
      }
    } catch (e) {
      if (mounted) setState(() { _busy = false; _error = 'Cannot reach the server. Is it running?'; });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF2F7F3),
      body: SafeArea(
        child: ListView(
          padding: EdgeInsets.zero,
          children: [
            _header(),
            Transform.translate(
              offset: const Offset(0, -34),
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 20),
                child: Card(
                  margin: EdgeInsets.zero,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
                  elevation: 2,
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(20, 22, 20, 20),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        const Text('Welcome back',
                            style: TextStyle(
                                fontSize: 20, fontWeight: FontWeight.w800, color: ink)),
                        const SizedBox(height: 4),
                        const Text('Sign in with the same email and password you registered with.',
                            style: TextStyle(fontSize: 12.5, color: muted, height: 1.35)),
                        const SizedBox(height: 20),
                        TextField(
                          controller: _email,
                          keyboardType: TextInputType.emailAddress,
                          textInputAction: TextInputAction.next,
                          autofillHints: const [AutofillHints.email],
                          decoration: _field('Email', Icons.alternate_email_outlined),
                        ),
                        const SizedBox(height: 12),
                        TextField(
                          controller: _password,
                          obscureText: true,
                          autofillHints: const [AutofillHints.password],
                          onSubmitted: (_) => _busy ? null : _login(),
                          decoration: _field('Password', Icons.lock_outline),
                        ),
                        const SizedBox(height: 20),
                        FilledButton.icon(
                          onPressed: _busy ? null : _login,
                          style: FilledButton.styleFrom(
                            minimumSize: const Size.fromHeight(50),
                            shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(14)),
                          ),
                          icon: _busy
                              ? const SizedBox(
                                  height: 18,
                                  width: 18,
                                  child: CircularProgressIndicator(
                                      color: Colors.white, strokeWidth: 2),
                                )
                              : const Icon(Icons.login),
                          label: Text(_busy ? 'Signing in…' : 'Log in',
                              style: const TextStyle(
                                  fontSize: 15, fontWeight: FontWeight.w800)),
                        ),
                        if (_error != null) ...[
                          const SizedBox(height: 14),
                          Container(
                            padding: const EdgeInsets.all(12),
                            decoration: BoxDecoration(
                              color: const Color(0xFFFDECEC),
                              borderRadius: BorderRadius.circular(12),
                            ),
                            child: Row(
                              children: [
                                const Icon(Icons.error_outline, size: 18, color: Color(0xFFB3261E)),
                                const SizedBox(width: 8),
                                Expanded(
                                  child: Text(_error!,
                                      style: const TextStyle(
                                          color: Color(0xFF7A1712), fontSize: 12.5, height: 1.3)),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 24),
              child: Column(
                children: [
                  Text('Backend: ${ApiConfig.baseUrl}',
                      textAlign: TextAlign.center,
                      style: const TextStyle(color: Color(0xFF6F8C7C), fontSize: 11)),
                  const SizedBox(height: 6),
                  const Text('CTP-WireLakes Construction and Technology Enterprise',
                      textAlign: TextAlign.center,
                      style: TextStyle(color: Color(0xFF6F8C7C), fontSize: 11)),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _header() {
    return Container(
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [Color(0xFF0E6B3A), Color(0xFF0A4F2B)],
        ),
        borderRadius: BorderRadius.only(bottomLeft: Radius.circular(34), bottomRight: Radius.circular(34)),
      ),
      padding: const EdgeInsets.fromLTRB(24, 34, 24, 56),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(18),
            ),
            child: Image.asset('assets/logo.png', height: 58),
          ),
          const SizedBox(height: 16),
          const Text('CTP-WireLakes',
              style: TextStyle(
                  color: Colors.white, fontSize: 24, fontWeight: FontWeight.w800, letterSpacing: 0.4)),
          const SizedBox(height: 2),
          Text('Community Training Programme · Member portal',
              style: TextStyle(color: Colors.white.withValues(alpha: 0.85), fontSize: 12.5)),
        ],
      ),
    );
  }

  InputDecoration _field(String label, IconData icon) => InputDecoration(
        labelText: label,
        prefixIcon: Icon(icon, size: 20),
        filled: true,
        fillColor: Colors.white,
        contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: Color(0xFFD8E5DB)),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: Color(0xFFD8E5DB)),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: green, width: 1.6),
        ),
      );
}
