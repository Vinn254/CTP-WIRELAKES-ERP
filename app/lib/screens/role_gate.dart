import 'package:flutter/material.dart';

import '../services/api_client.dart';
import 'dashboard_shell.dart';
import 'login_screen.dart';

/// Cold-start router for the members-only app. A stored token is only trusted
/// after the server confirms it belongs to a member (CTP or E-Fundi); a staff
/// token is dropped — staff use the website office, not this app.
class RoleGate extends StatefulWidget {
  final ApiClient api;
  const RoleGate({super.key, required this.api});

  @override
  State<RoleGate> createState() => _RoleGateState();
}

class _RoleGateState extends State<RoleGate> {
  bool _resolving = false;
  String? _notice;

  @override
  void initState() {
    super.initState();
    if (widget.api.isAuthenticated) {
      if (widget.api.kind == 'staff') {
        widget.api.clearToken();
        _notice = 'Staff sign in on the website office, not in this app.';
      } else if (widget.api.kind != 'member') {
        _resolve();
      }
    }
  }

  Future<void> _resolve() async {
    setState(() => _resolving = true);
    try {
      final me = await widget.api.resolveAccount();
      if (me['staff'] == true) {
        await widget.api.clearToken();
        if (mounted) _notice = 'Staff sign in on the website office, not in this app.';
      }
    } on ApiException {
      await widget.api.clearToken();
    } catch (_) {
      if (mounted) _notice = 'Cannot reach the server. Check your connection.';
    } finally {
      if (mounted) setState(() => _resolving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_resolving) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }
    if (widget.api.isAuthenticated && widget.api.kind == 'member') {
      return DashboardShell(api: widget.api);
    }
    return LoginScreen(api: widget.api, notice: _notice);
  }
}
