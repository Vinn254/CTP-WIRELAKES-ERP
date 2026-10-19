import 'package:flutter/material.dart';
import '../models/member_models.dart';
import '../models/portal_models.dart';
import '../services/api_client.dart';
import '../widgets/ui.dart';

/// Invited to you — projects the matching desk put to this fundi, the answer window on the
/// clock, and the accept/decline that the web portal offers. Silence is recorded as no answer.
class InvitationsScreen extends StatefulWidget {
  final ApiClient api;
  final Board board;
  const InvitationsScreen({super.key, required this.api, required this.board});

  @override
  State<InvitationsScreen> createState() => _InvitationsScreenState();
}

class _InvitationsScreenState extends State<InvitationsScreen> {
  InvitationBoard? _data;
  bool _loading = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final b = await widget.api.invitations();
      if (mounted) {
        setState(() {
          _data = b;
          _loading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _error = e is ApiException ? e.message : '$e';
          _loading = false;
        });
      }
    }
  }

  Future<void> _accept(Invite m) async {
    try {
      final msg = await widget.api.respondInvite(m.id, 'accept');
      if (mounted) say(context, msg);
      _load();
    } catch (e) {
      if (mounted) showError(context, e);
    }
  }

  Future<void> _decline(Invite m) async {
    final body = await showFormSheet(
      context,
      title: 'Decline this project',
      subtitle: 'Turn down ${m.projectReference ?? 'this project'}?',
      fields: [
        FieldSpec('note', 'Why, so the office can put the next one elsewhere',
            required: false, helper: 'e.g. I am on another job until the end of the month.'),
      ],
      cta: 'I cannot take it',
    );
    if (body == null) return;
    try {
      final msg = await widget.api.respondInvite(m.id, 'decline', note: body['note'] as String?);
      if (mounted) say(context, msg);
      _load();
    } catch (e) {
      if (mounted) showError(context, e);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading && _data == null) return const LoadingView();
    if (_error != null && _data == null) {
      return ErrorRetry(message: _error!, onRetry: _load);
    }
    final b = _data!;

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(14),
        children: [
          SectionCard(
            title: 'The ${b.window}-hour window',
            subtitle: 'The programme office puts a posted project to the E-Fundis whose grade, skills, '
                'county and availability fit it (§9.4). When it reaches you, you have ${b.window} '
                'hours to take it or turn it down — the clock starts when the office sends the ask, '
                'not when you read it. Taking it is not the award: the office assigns the project. '
                'Silence is recorded as no answer, and the project goes to someone else.',
            child: _counts(b),
          ),
          if (b.invites.isEmpty)
            const Card(
              child: Padding(
                padding: EdgeInsets.all(16),
                child: EmptyHint(
                  icon: Icons.mail_outline,
                  title: 'Nothing has been put to you yet',
                  body: 'The desk searches the register as projects are posted, so the things that '
                      'bring a project to you are your record being active, your availability set to '
                      'available under My E-Fundi record, and the skills and counties you keep there '
                      'matching the work.',
                ),
              ),
            )
          else
            for (final m in b.invites) _inviteCard(m),
        ],
      ),
    );
  }

  Widget _counts(InvitationBoard b) {
    int count(String k) => b.counts[k] ?? 0;
    final items = [
      ('Waiting on your answer', count('outstanding')),
      ('You took, office to assign', count('accepted')),
      ('Awarded to you', count('awarded')),
      ('Declined or closed', count('closed')),
    ];
    return Wrap(
      spacing: 8,
      runSpacing: 8,
      children: [
        for (final it in items)
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            decoration: BoxDecoration(
              color: soft,
              borderRadius: BorderRadius.circular(16),
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text('${it.$2}',
                    style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16, color: green)),
                Text(it.$1, style: const TextStyle(fontSize: 11, color: muted)),
              ],
            ),
          ),
      ],
    );
  }

  Widget _inviteCard(Invite m) {
    final canColor = m.status == 'awarded' || m.status == 'accepted'
        ? green
        : (m.status == 'invited' ? warn : muted);
    return SectionCard(
      title: m.projectTitle ?? 'Project',
      subtitle: [m.projectReference, m.hirer]
          .where((x) => x != null && x.isNotEmpty)
          .join(' · '),
      trailing: Tag(m.statusLabel, color: canColor),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          LabeledValue(label: 'Match score', value: '${m.score}%'),
          if (m.round != null) LabeledValue(label: 'Round', value: 'Round ${m.round} of the search'),
          if (m.timeLabel != null && m.timeLabel!.isNotEmpty)
            LabeledValue(label: 'Window', value: m.timeLabel!),
          if (m.matched.isNotEmpty) ...[
            const Padding(
              padding: EdgeInsets.only(top: 8, bottom: 2),
              child: Text('Why this came to you',
                  style: TextStyle(fontWeight: FontWeight.w800, fontSize: 13, color: ink)),
            ),
            for (final line in m.matched)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 2),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Padding(
                      padding: EdgeInsets.only(right: 6, top: 2),
                      child: Icon(Icons.circle, size: 6, color: green),
                    ),
                    Expanded(child: Text(line, style: const TextStyle(fontSize: 13, color: muted))),
                  ],
                ),
              ),
          ],
          if (m.canRespond)
            Padding(
              padding: const EdgeInsets.only(top: 10),
              child: Row(
                children: [
                  Expanded(
                    child: FilledButton(
                      onPressed: () => _accept(m),
                      child: const Text('I take this project'),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: OutlinedButton(
                      onPressed: () => _decline(m),
                      child: const Text('I cannot take it'),
                    ),
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}
