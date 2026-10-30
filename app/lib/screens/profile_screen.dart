import 'package:flutter/material.dart';
import '../models/member_models.dart';
import '../services/api_client.dart';
import '../widgets/ui.dart';

/// My profile — the record the office holds on this member, and the way they put their own
/// face up. The photo goes through the same attachPhoto() writer the web portal uses.
class ProfileScreen extends StatefulWidget {
  final ApiClient api;
  final Board board;
  final VoidCallback onLogout;
  const ProfileScreen({
    super.key,
    required this.api,
    required this.board,
    required this.onLogout,
  });

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  InlineFile? _photo;
  PickedPhoto? _picked;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _loadPhoto();
  }

  Future<void> _loadPhoto() async {
    try {
      final file = await widget.api.profilePhoto();
      if (mounted) setState(() => _photo = file);
    } catch (_) {
      // 404 simply means no picture on record yet — the initials stand in.
    }
  }

  Future<void> _upload() async {
    final picked = _picked;
    if (picked == null) return;
    setState(() => _busy = true);
    try {
      final message = await widget.api.uploadProfilePhoto(picked);
      if (mounted) {
        say(context, message);
        setState(() { _picked = null; _busy = false; });
        _loadPhoto();
      }
    } catch (e) {
      if (mounted) {
        setState(() => _busy = false);
        showError(context, e);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final m = widget.board.member;
    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 18, 16, 30),
      children: [
        Center(
          child: SizedBox(
            width: 108,
            height: 108,
            child: _photo != null && _photo!.hasFile
                ? ClipOval(
                    child: Image.memory(
                      base64Of(_photo!.dataUri!),
                      fit: BoxFit.cover,
                    ),
                  )
                : CircleAvatar(
                    backgroundColor: green,
                    foregroundColor: Colors.white,
                    child: Text(m.name.isNotEmpty ? m.name[0].toUpperCase() : '?',
                        style: const TextStyle(fontSize: 42, fontWeight: FontWeight.w800)),
                  ),
          ),
        ),
        const SizedBox(height: 14),
        Text(m.name,
            textAlign: TextAlign.center,
            style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: ink)),
        Text(m.standing,
            textAlign: TextAlign.center, style: const TextStyle(color: muted)),
        const SizedBox(height: 20),
        SectionCard(
          title: 'Your picture',
          subtitle: 'The programme office sees the same photo you put here.',
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              PhotoPicker(
                label: 'Profile photo',
                helper: 'jpg or png, up to 5 MB',
                photo: _picked,
                onPicked: (p) => setState(() => _picked = p),
              ),
              const SizedBox(height: 12),
              FilledButton.icon(
                onPressed: _picked == null || _busy ? null : _upload,
                icon: const Icon(Icons.upload_outlined, size: 18),
                label: Text(_busy ? 'Uploading…' : 'Put it on my profile'),
              ),
            ],
          ),
        ),
        SectionCard(
          title: 'On record',
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              LabeledValue(label: 'Member code', value: m.code ?? '—'),
              LabeledValue(label: 'Phone', value: m.phone ?? '—'),
              LabeledValue(label: 'Email', value: m.email ?? '—'),
              LabeledValue(label: 'County', value: m.county ?? '—'),
              LabeledValue(label: 'Record status', value: m.status),
              LabeledValue(label: 'Grade', value: m.level),
            ],
          ),
        ),
        const SizedBox(height: 8),
        OutlinedButton.icon(
          onPressed: widget.onLogout,
          icon: const Icon(Icons.logout),
          label: const Text('Log out'),
        ),
      ],
    );
  }
}
