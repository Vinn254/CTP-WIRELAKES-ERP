import 'package:flutter/material.dart';
import '../models/field_models.dart';
import '../models/member_models.dart';
import '../services/api_client.dart';
import '../widgets/ui.dart';

/// My mobilisation — the referral code, the credit for the people brought and the 15/30 term
/// milestone. Read-only, exactly like web/resources/views/portal/partials/mobilisation.blade.php.
class MobilisationScreen extends StatefulWidget {
  final ApiClient api;
  final Board board;
  const MobilisationScreen({super.key, required this.api, required this.board});

  @override
  State<MobilisationScreen> createState() => _MobilisationScreenState();
}

class _MobilisationScreenState extends State<MobilisationScreen> {
  Mobilisation? _m;
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
      final m = await widget.api.mobilisation();
      if (mounted) setState(() { _m = m; _loading = false; });
    } catch (e) {
      if (mounted) setState(() { _error = '$e'; _loading = false; });
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading && _m == null) return const LoadingView();
    if (_error != null && _m == null) return ErrorRetry(message: _error!, onRetry: _load);
    final m = _m!;

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(14),
        children: [
          SectionCard(
            title: 'Bring young people in — your referral code',
            subtitle: 'Drop the link in a WhatsApp or community chat. Anyone who joins the programme '
                'through it counts for you — whether they come in as a trainee CTP or register as an '
                'E-Fundi.',
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Center(
                  child: Padding(
                    padding: const EdgeInsets.symmetric(vertical: 12),
                    child: Text(
                      m.code ?? 'Issued on approval',
                      style: TextStyle(
                        fontSize: m.code != null ? 30 : 16,
                        fontWeight: FontWeight.w900,
                        letterSpacing: 3,
                        color: m.code != null ? green : muted,
                      ),
                    ),
                  ),
                ),
                if (m.link != null && m.link!.isNotEmpty) ...[
                  const SizedBox(height: 4),
                  Text('Your link', style: const TextStyle(color: muted, fontSize: 12)),
                  SelectableText(m.link!, style: const TextStyle(fontSize: 13)),
                ],
              ],
            ),
          ),
          SectionCard(
            title: 'This term — ${m.term}',
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                LabeledValue(label: 'You have brought', value: '${m.thisTerm} of ${m.next}'),
                const SizedBox(height: 6),
                ClipRRect(
                  borderRadius: BorderRadius.circular(8),
                  child: LinearProgressIndicator(
                    value: (m.percent / 100).clamp(0.0, 1.0).toDouble(),
                    minHeight: 10,
                    backgroundColor: soft,
                    valueColor: const AlwaysStoppedAnimation(green),
                  ),
                ),
                const SizedBox(height: 6),
                Text('people you brought this term (${m.term})',
                    style: const TextStyle(color: muted, fontSize: 12.5)),
                const SizedBox(height: 10),
                if (m.reward != null)
                  Text('You have earned ${m.reward} on this term’s training fee.',
                      style: const TextStyle(
                          fontWeight: FontWeight.w800, color: green, fontSize: 13.5))
                else if (m.hint != null)
                  Text(m.hint!, style: const TextStyle(color: muted, fontSize: 13)),
                const SizedBox(height: 10),
                LabeledValue(
                    label: 'Counted over the whole programme',
                    value: '${m.lifetime} people'),
              ],
            ),
          ),
          SectionCard(
            title: 'People you have brought in',
            subtitle: '${m.brought.length} brought · ${m.lifetime} counting',
            child: m.brought.isEmpty
                ? const EmptyHint(
                    icon: Icons.campaign_outlined,
                    title: 'Nobody yet',
                    body: 'Share your link — a person counts once they have paid and the programme '
                        'office has approved them as a trainee CTP or an E-Fundi.',
                  )
                : Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      for (final p in m.brought)
                        RecordRow(
                          title: '${p['name'] ?? ''}',
                          subtitle: p['code'] == null
                              ? null
                              : 'Code ${p['code']}',
                          meta: [
                            p['place'] ?? 'area not recorded',
                            p['on'] ?? '—',
                          ].join(' · '),
                          trailing: Tag('${p['label'] ?? p['kind'] ?? ''}',
                              color: '${p['label'] ?? ''}'.toLowerCase() == 'active'
                                  ? green
                                  : muted),
                        ),
                    ],
                  ),
          ),
          SectionCard(
            title: 'How counting works',
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: const [
                _Rule('A trainee CTP you brought counts once they are active, graduated or deployed.'),
                _Rule('An E-Fundi you brought counts once the office approves their registration.'),
                _Rule('Only people brought inside the current term count toward the discount.'),
                _Rule('The discount is a credit on this term’s fee — never a refund.'),
                _Rule('You cannot bring yourself, and each person counts for only one mobiliser.'),
                _Rule('If someone you brought is later rejected, they drop off your count.'),
              ],
            ),
          ),
          const SizedBox(height: 8),
        ],
      ),
    );
  }
}

class _Rule extends StatelessWidget {
  final String text;
  const _Rule(this.text);

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Padding(
            padding: EdgeInsets.only(right: 8, top: 2),
            child: Icon(Icons.circle, size: 6, color: green),
          ),
          Expanded(
              child: Text(text, style: const TextStyle(fontSize: 13, color: ink))),
        ],
      ),
    );
  }
}
