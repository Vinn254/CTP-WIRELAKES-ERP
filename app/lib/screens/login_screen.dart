import 'package:flutter/material.dart';
import '../config/api_config.dart';
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
  bool _showPw = false;
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
      backgroundColor: page,
      body: SafeArea(
        child: ListView(
          padding: EdgeInsets.zero,
          children: [
            _brand(),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20),
              child: Card(
                margin: EdgeInsets.zero,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(20),
                  side: cardBorder,
                ),
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
                        obscureText: !_showPw,
                        autofillHints: const [AutofillHints.password],
                        onSubmitted: (_) => _busy ? null : _login(),
                        decoration: _field('Password', Icons.lock_outline, suffix: IconButton(
                          onPressed: () => setState(() => _showPw = !_showPw),
                          tooltip: _showPw ? 'Hide the password' : 'Show the password',
                          icon: Icon(
                              _showPw ? Icons.visibility_off_outlined : Icons.visibility_outlined,
                              size: 20,
                              color: muted),
                        )),
                      ),
                      const SizedBox(height: 20),
                      FilledButton.icon(
                        onPressed: _busy ? null : _login,
                        // The website's own primary button: bright lime with deep green lettering.
                        style: FilledButton.styleFrom(
                          backgroundColor: lime,
                          foregroundColor: onLime,
                          minimumSize: const Size.fromHeight(50),
                          shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(14)),
                        ),
                        icon: _busy
                            ? const SizedBox(
                                height: 18,
                                width: 18,
                                child: CircularProgressIndicator(
                                    color: onLime, strokeWidth: 2),
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
                            color: danger.withValues(alpha: 0.07),
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(color: danger.withValues(alpha: 0.35)),
                          ),
                          child: Row(
                            children: [
                              const Icon(Icons.error_outline, size: 18, color: danger),
                              const SizedBox(width: 8),
                              Expanded(
                                child: Text(_error!,
                                    style: const TextStyle(
                                        color: danger, fontSize: 12.5, height: 1.3)),
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
            Padding(
              padding: const EdgeInsets.fromLTRB(24, 24, 24, 30),
              child: Column(
                children: [
                  Text('Backend: ${ApiConfig.baseUrl}',
                      textAlign: TextAlign.center,
                      style: const TextStyle(color: muted, fontSize: 11)),
                  const SizedBox(height: 6),
                  const Text('CTP-WireLakes Construction and Technology Enterprise',
                      textAlign: TextAlign.center,
                      style: TextStyle(color: muted, fontSize: 11)),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  /// The brand block that sits directly above the form: the logo on its own white tile, centred,
  /// with the programme name under it. No colour band behind it — the page stays the pale web page.
  Widget _brand() {
    return Padding(
      padding: const EdgeInsets.fromLTRB(24, 44, 24, 26),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(18),
              border: Border.all(color: line),
            ),
            child: Image.asset('assets/logo.png', height: 64),
          ),
          const SizedBox(height: 16),
          const Text('CTP-WireLakes',
              textAlign: TextAlign.center,
              style: TextStyle(
                  color: green, fontSize: 24, fontWeight: FontWeight.w800, letterSpacing: 0.4)),
          const SizedBox(height: 2),
          const Text('Community Training Programme · Member portal',
              textAlign: TextAlign.center,
              style: TextStyle(color: muted, fontSize: 12.5)),
        ],
      ),
    );
  }

  InputDecoration _field(String label, IconData icon, {Widget? suffix}) => InputDecoration(
        labelText: label,
        prefixIcon: Icon(icon, size: 20, color: muted),
        suffixIcon: suffix,
      );
}
