import 'package:flutter/material.dart';
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
  final _code = TextEditingController();
  bool _busy = false;
  bool _showPw = false;
  String? _error;
  String? _notice;

  /// Set while the app is waiting for the code that was emailed to this address.
  String? _challenge;
  int _minutes = 10;

  bool get _awaitingCode => _challenge != null;

  @override
  void initState() {
    super.initState();
    _error = widget.notice;
  }

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    _code.dispose();
    super.dispose();
  }

  Future<void> _login() async {
    setState(() {
      _busy = true;
      _error = null;
      _notice = null;
    });
    try {
      final data = await widget.api.login(
        email: _email.text.trim(),
        password: _password.text,
      );
      if (data['otp_required'] == true) {
        // Management: the password alone opens nothing. The emailed code decides.
        if (mounted) {
          setState(() {
            _busy = false;
            _challenge = data['challenge'] as String;
            _minutes = data['minutes'] as int? ?? 10;
          });
        }
        return;
      }
      await _finish();
    } on ApiException catch (e) {
      if (mounted) {
        setState(() {
          _busy = false;
          _error = e.statusCode == 401 ? 'Wrong email or password.' : e.message;
        });
      }
    } catch (_) {
      if (mounted) setState(() { _busy = false; _error = 'Cannot reach the server. Is it running?'; });
    }
  }

  Future<void> _confirmCode() async {
    setState(() {
      _busy = true;
      _error = null;
      _notice = null;
    });
    try {
      await widget.api.verifyOtp(challenge: _challenge!, code: _code.text.trim());
      await _finish();
    } on ApiException catch (e) {
      if (mounted) setState(() { _busy = false; _error = e.message; });
    } catch (_) {
      if (mounted) setState(() { _busy = false; _error = 'Cannot reach the server. Is it running?'; });
    }
  }

  Future<void> _resend() async {
    setState(() { _error = null; _notice = null; });
    try {
      final next = await widget.api.resendOtp(_challenge!);
      if (mounted) {
        setState(() {
          _challenge = next;
          _code.clear();
          _notice = 'A new code is on its way to $_email.';
        });
      }
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } catch (_) {
      if (mounted) setState(() => _error = 'Cannot reach the server. Is it running?');
    }
  }

  /// The token is in hand: check it is a member account, then open the portal.
  Future<void> _finish() async {
    final me = await widget.api.resolveAccount();
    final isStaff = me['staff'] == true;
    final isMember = me['member_type'] != null;
    if (isStaff || !isMember) {
      await widget.api.clearToken();
      if (mounted) {
        setState(() {
          _busy = false;
          _challenge = null;
          _error = isStaff
              ? 'Staff sign in on the website office, not in this app.'
              : 'This account has no member portal yet.';
        });
      }
      return;
    }
    if (mounted) {
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(builder: (_) => DashboardShell(api: widget.api)),
      );
    }
  }

  Future<void> _forgotPassword() async {
    final address = TextEditingController(text: _email.text.trim());
    final sent = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (sheetContext) => Padding(
        padding: EdgeInsets.fromLTRB(20, 22, 20, 20 + MediaQuery.of(sheetContext).viewInsets.bottom),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Text('Forgotten your password?',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: ink)),
            const SizedBox(height: 6),
            const Text(
              'We email a link that opens the change-password screen. It stops working on its own after '
              '56 minutes, it can only be used once, and one address is sent three a day at most.',
              style: TextStyle(fontSize: 12.5, color: muted, height: 1.4),
            ),
            const SizedBox(height: 16),
            TextField(
              controller: address,
              keyboardType: TextInputType.emailAddress,
              decoration: _field('Email', Icons.alternate_email_outlined),
            ),
            const SizedBox(height: 16),
            FilledButton(
              onPressed: () => Navigator.of(sheetContext).pop(true),
              child: const Text('Send me the link'),
            ),
          ],
        ),
      ),
    );

    if (sent != true) return;
    try {
      final message = await widget.api.forgotPassword(address.text.trim());
      if (mounted) setState(() { _notice = message; _error = null; });
    } catch (_) {
      if (mounted) setState(() => _error = 'Cannot reach the server. Is it running?');
    } finally {
      address.dispose();
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
                  child: _awaitingCode ? _codeStep() : _credentials(),
                ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(24, 24, 24, 30),
              child: Column(
                children: [
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

  Widget _credentials() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const Text('Welcome back',
            style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: ink)),
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
            icon: Icon(_showPw ? Icons.visibility_off_outlined : Icons.visibility_outlined,
                size: 20, color: muted),
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
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
          ),
          icon: _busy
              ? const SizedBox(height: 18, width: 18,
                  child: CircularProgressIndicator(color: onLime, strokeWidth: 2))
              : const Icon(Icons.login),
          label: Text(_busy ? 'Signing in…' : 'Log in',
              style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w800)),
        ),
        Align(
          alignment: Alignment.center,
          child: TextButton(
            onPressed: _busy ? null : _forgotPassword,
            child: const Text('Forgotten your password?'),
          ),
        ),
        const Text(
          'The office asks for a code from your email every time you sign in. Anyone left idle for '
          '5 minutes is signed out.',
          textAlign: TextAlign.center,
          style: TextStyle(fontSize: 11.5, color: muted, height: 1.4),
        ),
        ..._banners(),
      ],
    );
  }

  Widget _codeStep() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const Text('Confirm it is you',
            style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: ink)),
        const SizedBox(height: 4),
        Text('We emailed a code to ${_email.text.trim()}. Type it here to open the office. '
            'It works for $_minutes minutes and dies after 5 wrong tries.',
            style: const TextStyle(fontSize: 12.5, color: muted, height: 1.35)),
        const SizedBox(height: 20),
        TextField(
          controller: _code,
          keyboardType: TextInputType.number,
          autofocus: true,
          maxLength: 10,
          onSubmitted: (_) => _busy ? null : _confirmCode(),
          decoration: _field('Code from your email', Icons.pin_outlined),
        ),
        const SizedBox(height: 12),
        FilledButton.icon(
          onPressed: _busy ? null : _confirmCode,
          style: FilledButton.styleFrom(
            backgroundColor: lime,
            foregroundColor: onLime,
            minimumSize: const Size.fromHeight(50),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
          ),
          icon: _busy
              ? const SizedBox(height: 18, width: 18,
                  child: CircularProgressIndicator(color: onLime, strokeWidth: 2))
              : const Icon(Icons.verified_outlined),
          label: Text(_busy ? 'Checking…' : 'Confirm and sign in',
              style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w800)),
        ),
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            TextButton(
              onPressed: _busy ? null : _resend,
              child: const Text('Send another code'),
            ),
            TextButton(
              onPressed: _busy
                  ? null
                  : () => setState(() {
                        _challenge = null;
                        _code.clear();
                        _error = null;
                        _notice = null;
                      }),
              child: const Text('Use my password again'),
            ),
          ],
        ),
        ..._banners(),
      ],
    );
  }

  List<Widget> _banners() {
    return [
      if (_notice != null) _banner(_notice!, Icons.mark_email_unread_outlined, green),
      if (_error != null) _banner(_error!, Icons.error_outline, danger),
    ];
  }

  Widget _banner(String text, IconData icon, Color colour) {
    return Padding(
      padding: const EdgeInsets.only(top: 14),
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: colour.withValues(alpha: 0.07),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: colour.withValues(alpha: 0.35)),
        ),
        child: Row(
          children: [
            Icon(icon, size: 18, color: colour),
            const SizedBox(width: 8),
            Expanded(
              child: Text(text,
                  style: TextStyle(color: colour, fontSize: 12.5, height: 1.3)),
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
