import 'package:flutter/material.dart';

import '../services/api_client.dart';
import 'dashboard_shell.dart';
import 'login_screen.dart';
import 'trainer_shell.dart';

/// Cold-start router for the members-only app. A stored token is only trusted after the server
/// confirms what kind of account it belongs to: a member gets the portal, a trainer who works in the
/// office gets the teaching desk, and any other office account is sent back to the website.
class RoleGate extends StatefulWidget {
  final ApiClient api;
  const RoleGate({super.key, required this.api});

  @override
  State<RoleGate> createState() => _RoleGateState();
}

class _RoleGateState extends State<RoleGate> {
  bool _resolving = false;
  bool _trainerHome = false;
  String _name = '';
  String? _notice;

  @override
  void initState() {
    super.initState();
    if (widget.api.isAuthenticated) {
      if (widget.api.kind == 'staff' && !widget.api.trainerDesk) {
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
        if (me['trainer'] == true) {
          _trainerHome = true;
          _name = (me['user'] as Map?)?['name']?.toString() ?? '';
        } else {
          await widget.api.clearToken();
          if (mounted) _notice = 'Staff sign in on the website office, not in this app.';
        }
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
    if (widget.api.isAuthenticated && _trainerHome && widget.api.kind == 'staff') {
      return TrainerShell(api: widget.api, name: _name);
    }
    if (widget.api.isAuthenticated && widget.api.kind == 'member') {
      return DashboardShell(api: widget.api);
    }
    return LoginScreen(api: widget.api, notice: _notice);
  }
}
