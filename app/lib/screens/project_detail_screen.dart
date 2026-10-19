import 'package:flutter/material.dart';
import '../models/member_models.dart';
import '../services/api_client.dart';
import '../widgets/ui.dart';

class ProjectDetailScreen extends StatefulWidget {
  final ApiClient api;
  final int projectId;
  const ProjectDetailScreen({super.key, required this.api, required this.projectId});

  @override
  State<ProjectDetailScreen> createState() => _ProjectDetailScreenState();
}

class _ProjectDetailScreenState extends State<ProjectDetailScreen> {
  ProjectView? _view;
  bool _loading = false;
  String? _error;
  bool _submitting = false;
  final _note = TextEditingController();

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _note.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() { _loading = true; _error = null; });
    try {
      final v = await widget.api.projectView(widget.projectId);
      if (mounted) setState(() { _view = v; _loading = false; });
    } catch (e) {
      if (mounted) setState(() { _error = '$e'; _loading = false; });
    }
  }

  Future<void> _apply() async {
    setState(() => _submitting = true);
    try {
      await widget.api.apply(widget.projectId, note: _note.text.trim());
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Applied — the office will review it.')));
        _load();
      }
    } on ApiException catch (e) {
      if (mounted) {
        setState(() => _submitting = false);
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
      }
    } catch (e) {
      if (mounted) {
        setState(() => _submitting = false);
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Could not apply: $e')));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final p = _view?.project;
    return Scaffold(
      appBar: AppBar(title: Text(p?.title ?? 'Project')),
      body: _buildBody(),
    );
  }

  Widget _buildBody() {
    if (_loading && _view == null) return const Center(child: CircularProgressIndicator());
    if (_error != null && _view == null) {
      return Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text(_error!, textAlign: TextAlign.center),
            const SizedBox(height: 12),
            FilledButton(onPressed: _load, child: const Text('Try again')),
          ],
        ),
      );
    }
    final v = _view!;
    final p = v.project;
    final applied = v.application != null;

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Wrap(
          spacing: 8,
          children: [
            _tag(p.categoryLabel),
            if (p.status == 'open') _tag('Open', color: green),
            if (p.deadline != null) _tag('Deadline ${p.deadline}'),
          ],
        ),
        const SizedBox(height: 12),
        Text(p.title, style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w800)),
        const SizedBox(height: 6),
        Text([p.county, p.ward, p.trade].where((x) => x != null && x.isNotEmpty).join(' · '),
            style: const TextStyle(color: muted)),
        const Divider(height: 32),
        if (p.hirer != null) _field('Posted by', p.hirer!),
        _field('Reference', p.reference ?? '—'),
        if (p.budget != null) _field('Budget', 'KSh ${p.budget}'),
        if (p.duration != null) _field('Duration', p.duration!),
        const SizedBox(height: 8),
        if (p.description != null && p.description!.isNotEmpty) ...[
          const Text('Description', style: TextStyle(fontWeight: FontWeight.w700)),
          const SizedBox(height: 4),
          Text(p.description!),
          const SizedBox(height: 12),
        ],
        if (p.materials != null && p.materials!.isNotEmpty) ...[
          const Text('Materials provided', style: TextStyle(fontWeight: FontWeight.w700)),
          const SizedBox(height: 4),
          Text(p.materials!),
          const SizedBox(height: 12),
        ],
        const SizedBox(height: 8),
        if (applied)
          _AppliedBanner(application: v.application!)
        else if (p.canApply)
          _ApplyCard(
            controller: _note,
            busy: _submitting,
            onSubmit: _apply,
          )
        else
          const Card(
            child: ListTile(
              leading: Icon(Icons.lock_outline, color: muted),
              title: Text('Not open for your level'),
              subtitle: Text('This project is for a different member category.'),
            ),
          ),
      ],
    );
  }
}

class _ApplyCard extends StatelessWidget {
  final TextEditingController controller;
  final bool busy;
  final Future<void> Function() onSubmit;
  const _ApplyCard({required this.controller, required this.busy, required this.onSubmit});

  @override
  Widget build(BuildContext context) {
    return Card(
      color: soft,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Text('Apply for this project',
                style: TextStyle(fontWeight: FontWeight.w700, color: ink)),
            const SizedBox(height: 8),
            TextField(
              controller: controller,
              maxLines: 3,
              decoration: const InputDecoration(
                labelText: 'A short note (optional)',
                border: OutlineInputBorder(),
                fillColor: Colors.white,
                filled: true,
              ),
            ),
            const SizedBox(height: 12),
            FilledButton.icon(
              onPressed: busy ? null : onSubmit,
              icon: busy
                  ? const SizedBox(height: 16, width: 16,
                      child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                  : const Icon(Icons.send),
              label: Text(busy ? 'Sending…' : 'Apply'),
            ),
          ],
        ),
      ),
    );
  }
}

class _AppliedBanner extends StatelessWidget {
  final Application application;
  const _AppliedBanner({required this.application});

  @override
  Widget build(BuildContext context) {
    return Card(
      child: ListTile(
        leading: const Icon(Icons.check_circle, color: green),
        title: const Text('You have applied'),
        subtitle: Text('Status: ${application.statusLabel ?? application.status ?? 'pending review'}'),
      ),
    );
  }
}

Widget _field(String label, String value) => Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(width: 120, child: Text(label, style: const TextStyle(color: muted))),
          Expanded(child: Text(value, style: const TextStyle(fontWeight: FontWeight.w600))),
        ],
      ),
    );

Widget _tag(String text, {Color color = muted}) => Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: color.withValues(alpha: 0.4)),
      ),
      child: Text(text, style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.w600)),
    );
