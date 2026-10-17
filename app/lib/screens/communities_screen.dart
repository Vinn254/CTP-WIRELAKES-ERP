import 'package:flutter/material.dart';
import '../models/field_models.dart';
import '../models/member_models.dart';
import '../services/api_client.dart';
import '../widgets/ui.dart';

/// My communities — the areas this CTP mobilises, the needs they logged and the people
/// counted. Mirrors web/resources/views/portal/partials/communities.blade.php on the same API.
class CommunitiesScreen extends StatefulWidget {
  final ApiClient api;
  final Board board;
  const CommunitiesScreen({super.key, required this.api, required this.board});

  @override
  State<CommunitiesScreen> createState() => _CommunitiesScreenState();
}

class _CommunitiesScreenState extends State<CommunitiesScreen> {
  CommunityBoard? _b;
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
      final b = await widget.api.communities();
      if (mounted) setState(() { _b = b; _loading = false; });
    } catch (e) {
      if (mounted) setState(() { _error = '$e'; _loading = false; });
    }
  }

  Future<void> _post(Future<String> Function() send) async {
    try {
      final message = await send();
      if (!mounted) return;
      say(context, message);
      _load();
    } catch (e) {
      if (mounted) showError(context, e);
    }
  }

  // ---- the three actions ---------------------------------------------------

  Future<void> _propose(CommunityBoard b) async {
    final body = await showFormSheet(
      context,
      title: 'Propose a community',
      subtitle: 'Describe the place and the office confirms it and issues its COM ID. Use the ward '
          'and village the people themselves name — a duplicate is sent back with the reason, and '
          'nothing you log is lost.',
      cta: 'Send proposal',
      fields: [
        FieldSpec('name', 'Community name', helper: 'e.g. Nyanda youth cluster'),
        FieldSpec.dropdown('county', 'County', {for (final c in b.counties) c: c},
            initial: widget.board.member.county),
        FieldSpec('sub_county', 'Sub-county'),
        FieldSpec('ward', 'Ward'),
        FieldSpec('village', 'Village / location', required: false),
        FieldSpec('households', 'Households you counted',
            kind: FieldKind.integer, required: false, helper: 'e.g. 980'),
        FieldSpec('population', 'Population',
            kind: FieldKind.integer, required: false, helper: 'e.g. 4200'),
        FieldSpec('summary', 'What this community is like',
            kind: FieldKind.multiline, required: false,
            helper: 'Who lives here, what the young people do, what already exists.'),
        FieldSpec('gps_lat', 'GPS latitude',
            kind: FieldKind.number, required: false, helper: '-0.0918020'),
        FieldSpec('gps_lng', 'GPS longitude',
            kind: FieldKind.number, required: false, helper: '34.7680000'),
      ],
    );
    if (body == null) return;
    await _post(() => widget.api.storeCommunity(body));
  }

  Future<void> _logNeed(CommunityBoard b, {String? preset}) async {
    if (b.communities.isEmpty) {
      say(context, 'Put an area forward first — a need is always logged against one of your communities.');
      return;
    }
    final body = await showFormSheet(
      context,
      title: 'Flag a need',
      subtitle: 'Keep logging what you find — the needs you add here are read while the office decides.',
      cta: 'Send to the programme office',
      fields: [
        FieldSpec.dropdown('community_id', 'Which area',
            {for (final c in b.communities) '${c.id}': c.name},
            initial: preset),
        FieldSpec.dropdown('type', 'What kind of need', b.needTypes),
        FieldSpec('title', 'Short title', helper: 'e.g. Library hall has no power'),
        FieldSpec('detail', 'What you saw', kind: FieldKind.multiline, required: false,
            helper: 'How many people it affects, what they do without it, who already tried to fix it.'),
        FieldSpec('gps_lat', 'GPS latitude',
            kind: FieldKind.number, required: false, helper: '-0.0918020'),
        FieldSpec('gps_lng', 'GPS longitude',
            kind: FieldKind.number, required: false, helper: '34.7680000'),
        FieldSpec.photo('photo', 'Photo of the need',
            photoHelper: 'JPG or PNG up to 3 MB. Take it on the spot — a pinned photo is what '
                'gets a need funded.'),
      ],
    );
    if (body == null) return;
    await _post(() => widget.api.storeNeed(body));
  }

  Future<void> _countBeneficiary(CommunityBoard b, {String? preset}) async {
    final confirmed = b.communities.where((c) => c.confirmed).toList();
    if (confirmed.isEmpty) {
      // The portal's own explanation for why an unconfirmed area accepts no count.
      say(context, 'Beneficiaries are counted once the office confirms this community.');
      return;
    }
    final body = await showFormSheet(
      context,
      title: 'Register beneficiary',
      subtitle: 'The programme office links a beneficiary to the project that reached them — you '
          'just record who you mobilised and when.',
      cta: 'Record beneficiary',
      fields: [
        FieldSpec.dropdown('community_id', 'Which confirmed area',
            {for (final c in confirmed) '${c.id}': c.name},
            initial: preset ?? '${confirmed.first.id}'),
        FieldSpec('name', 'Name'),
        FieldSpec.dropdown('type', 'Who they are', b.beneficiaryTypes),
        FieldSpec('age', 'Age', kind: FieldKind.integer, required: false,
            helper: 'Between 1 and 120'),
        FieldSpec.dropdown('gender', 'Gender',
            const {'male': 'Male', 'female': 'Female', 'other': 'Other'},
            required: false, helper: 'Not recorded'),
        FieldSpec('benefited_on', 'Date they benefited', kind: FieldKind.date),
        FieldSpec('id_consent', 'ID consent', kind: FieldKind.check, required: false,
            helper: 'They agreed to give their ID number for follow-up programmes'),
        FieldSpec('id_number', 'ID number (only with consent)', required: false,
            helper: 'Required when consent is given'),
      ],
    );
    if (body == null) return;
    await _post(() => widget.api.storeBeneficiary(body));
  }

  // ---- page ---------------------------------------------------------------

  @override
  Widget build(BuildContext context) {
    if (_loading && _b == null) return const LoadingView();
    if (_error != null && _b == null) return ErrorRetry(message: _error!, onRetry: _load);
    final b = _b!;

    final needsLogged = b.communities.fold<int>(0, (n, c) => n + c.needs.length);
    final reached = b.communities.fold<int>(0, (n, c) => n + c.beneficiaries.length);

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(14),
        children: [
          SectionCard(
            title: 'My communities',
            subtitle: 'You are the programme’s person on the ground in these places. What you log '
                'here is what the programme office uses to book training, match projects and count '
                'impact — nothing you record is changed without you seeing it.',
            child: Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                Tag('${b.communities.length} areas', color: green),
                Tag('$needsLogged needs logged'),
                Tag('$reached people reached'),
              ],
            ),
          ),
          SectionCard(
            title: 'What you can do',
            child: Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                FilledButton.icon(
                  onPressed: () => _propose(b),
                  icon: const Icon(Icons.add_location_alt_outlined, size: 18),
                  label: const Text('Put an area forward'),
                ),
                OutlinedButton.icon(
                  onPressed: () => _logNeed(b),
                  icon: const Icon(Icons.report_problem_outlined, size: 18),
                  label: const Text('Log a need'),
                ),
                OutlinedButton.icon(
                  onPressed: () => _countBeneficiary(b),
                  icon: const Icon(Icons.person_add_alt_outlined, size: 18),
                  label: const Text('Count a beneficiary'),
                ),
              ],
            ),
          ),
          if (b.communities.isEmpty)
            const SectionCard(
              title: 'You are not on a community roster yet',
              child: Text(
                  'Your County Coordinator assigns communities after approval. You can still put one '
                  'forward yourself — describe the place and the office confirms it and issues its COM ID.',
                  style: TextStyle(color: muted, fontSize: 13)),
            )
          else
            for (final c in b.communities) _communityCard(b, c),
          _recentCard(b),
          const SizedBox(height: 8),
        ],
      ),
    );
  }

  Widget _communityCard(CommunityBoard b, CommunityRow c) {
    return SectionCard(
      title: c.name,
      trailing: Tag(c.statusLabel, color: c.confirmed ? green : muted),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Wrap(
            spacing: 6,
            runSpacing: 6,
            children: [
              Tag(c.code ?? 'Awaiting a COM ID'),
              Tag(c.role, color: c.role == 'Lead CTP' ? green : muted),
              Tag('${c.ctps} CTP${c.ctps == 1 ? '' : 's'} on this area'),
            ],
          ),
          const SizedBox(height: 6),
          LabeledValue(label: 'Location',
              value: c.location?.isNotEmpty == true ? c.location! : 'not recorded',
              strong: false),
          if (c.population != null)
            LabeledValue(label: 'Population', value: '${c.population}', strong: false),
          if (c.households != null)
            LabeledValue(label: 'Households', value: '${c.households}', strong: false),
          if (c.status == 'pending_review')
            const Padding(
              padding: EdgeInsets.only(top: 6),
              child: Text('Waiting on the programme office to confirm this proposal. Keep logging '
                  'what you find — the needs you add here are read while they decide.',
                  style: TextStyle(color: muted, fontSize: 12.5)),
            ),
          if (c.status == 'rejected')
            const Padding(
              padding: EdgeInsets.only(top: 6),
              child: Text('Not confirmed — ask your County Coordinator for the office’s reason.',
                  style: TextStyle(color: muted, fontSize: 12.5, fontWeight: FontWeight.w700)),
            ),
          const SizedBox(height: 10),
          Text('What needs work here (${c.needs.length})',
              style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13.5, color: ink)),
          if (c.needs.isEmpty)
            const Text('Nothing logged yet.', style: TextStyle(color: muted, fontSize: 13))
          else
            for (final n in c.needs)
              RecordRow(
                title: n.title,
                subtitle: n.typeLabel,
                meta: n.on,
                trailing: Tag(n.statusLabel),
              ),
          Align(
            alignment: Alignment.centerLeft,
            child: TextButton.icon(
              onPressed: () => _logNeed(b, preset: '${c.id}'),
              icon: const Icon(Icons.report_problem_outlined, size: 18),
              label: const Text('Flag a need in this community'),
            ),
          ),
          const SizedBox(height: 6),
          Text('People reached (${c.beneficiaries.length})',
              style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13.5, color: ink)),
          if (c.beneficiaries.isEmpty)
            const Text('Nobody registered yet.', style: TextStyle(color: muted, fontSize: 13))
          else
            for (final person in c.beneficiaries)
              RecordRow(
                title: person.name,
                subtitle: person.typeLabel,
                meta: person.on,
              ),
          if (c.confirmed)
            Align(
              alignment: Alignment.centerLeft,
              child: TextButton.icon(
                onPressed: () => _countBeneficiary(b, preset: '${c.id}'),
                icon: const Icon(Icons.person_add_alt_outlined, size: 18),
                label: const Text('Register a beneficiary'),
              ),
            )
          else
            const Padding(
              padding: EdgeInsets.only(top: 4),
              child: Text('Beneficiaries are counted once the office confirms this community.',
                  style: TextStyle(color: muted, fontSize: 12.5)),
            ),
        ],
      ),
    );
  }

  Widget _recentCard(CommunityBoard b) {
    return SectionCard(
      title: 'Field work you have logged',
      subtitle: 'Rules the office applies: a need is counted once per community, a beneficiary once '
          'per project, and only confirmed communities carry impact numbers. Priority and conversion '
          'decisions are made by the programme office — you always see the result here.',
      child: b.recent.isEmpty
          ? const Text('You have not flagged a need yet.',
              style: TextStyle(color: muted, fontSize: 13))
          : Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                for (final n in b.recent)
                  RecordRow(
                    title: n.title,
                    subtitle: n.community ?? 'community removed',
                    meta: n.on,
                    trailing: Tag(n.typeLabel),
                  ),
              ],
            ),
    );
  }
}
