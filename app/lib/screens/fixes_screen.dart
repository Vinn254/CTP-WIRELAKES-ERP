import 'package:flutter/material.dart';
import '../models/member_models.dart';
import '../models/work_models.dart';
import '../services/api_client.dart';
import '../widgets/ui.dart';

/// Items to put right — the failed inspection answers page from
/// web/resources/views/portal/fixes.blade.php. Only the inspector clears an item, so this
/// screen has no "mark it passed" control, exactly as the web page has none.
class FixesScreen extends StatefulWidget {
  final ApiClient api;
  final Board board;
  const FixesScreen({super.key, required this.api, required this.board});

  @override
  State<FixesScreen> createState() => _FixesScreenState();
}

class _FixesScreenState extends State<FixesScreen> {
  FixBoard? _data;
  bool _loading = false;
  bool _busy = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final first = _data == null;
    if (first) setState(() { _loading = true; _error = null; });
    try {
      final data = await widget.api.fixes();
      if (mounted) setState(() { _data = data; _loading = false; _error = null; });
    } catch (e) {
      if (!mounted) return;
      if (first) {
        setState(() { _error = '$e'; _loading = false; });
      } else {
        showError(context, e);
      }
    }
  }

  Future<void> _prove(FixItem item) async {
    final body = await showFormSheet(
      context,
      title: item.hasProof ? 'Send the new photograph' : 'Answer the flagged item',
      subtitle: 'The inspector re-checks the item against the photograph, so send the same item, not a new one.',
      fields: [
        FieldSpec('proof_note', 'What you did to put it right',
            kind: FieldKind.multiline,
            maxLines: 3,
            initial: item.proofNote,
            helper: 'Exactly the rework, so the inspector knows what to look for'),
        FieldSpec.photo('proof', 'Photograph of the fix',
            required: true,
            photoHelper: 'One clear shot of the finished rework is what the inspector re-checks against.'),
      ],
      cta: item.hasProof ? 'Send the new photograph' : 'Send the proof',
    );
    if (body == null || !mounted) return;

    setState(() => _busy = true);
    try {
      final message = await widget.api.proveFix(item.id, body);
      if (!mounted) return;
      setState(() => _busy = false);
      say(context, message);
      await _load();
    } catch (e) {
      if (mounted) {
        setState(() => _busy = false);
        showError(context, e);
      }
    }
  }

  Future<void> _showProof(FixItem item) async {
    try {
      final file = await widget.api.fixPhoto(item.id);
      if (mounted) await showPicture(context, file);
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

    final data = _data!;

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(14, 14, 14, 28),
        children: [
          const Text('Items to put right',
              style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: ink)),
          const SizedBox(height: 8),
          const Text(
              'When the inspector visits your site and marks something failed, it comes here (§11.4 step 5). '
              'You answer it with what you did and the photograph of the rework — the same item, not a new one. '
              'The inspector then re-checks it on the office\'s side; only they can mark it passed, and a report '
              'cannot be signed off while an item of yours is still standing.',
              style: TextStyle(color: muted, fontSize: 13, height: 1.45)),
          const SizedBox(height: 14),
          _Stats(tiles: <(String, int)>[
            ('Waiting on your proof', data.fixes.length),
            ('Re-checked already', data.cleared.length),
          ]),
          const SizedBox(height: 10),
          if (data.fixes.isEmpty)
            const Card(
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.all(Radius.circular(16)),
                side: cardBorder,
              ),
              child: EmptyHint(
                icon: Icons.verified_outlined,
                title: 'Nothing is flagged on your sites',
                body: 'An inspection item appears here only when the inspector marks it failed and it has not '
                    'been re-checked yet. Keep logging your days on My work orders — the inspector\'s visit is '
                    'arranged off the back of that log.',
              ),
            )
          else
            for (final item in data.fixes)
              _FixCard(
                item: item,
                busy: _busy,
                onProve: () => _prove(item),
                onShowProof: () => _showProof(item),
              ),
          if (data.cleared.isNotEmpty) ...[
            const SizedBox(height: 8),
            SectionCard(
              title: 'Re-checked',
              subtitle: 'The inspector has been back. Your upload never marks an item passed.',
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  for (final c in data.cleared)
                    RecordRow(
                      title: c.item,
                      subtitle: c.proofNote ?? '—',
                      meta: [
                        if ((c.project ?? '').isNotEmpty) c.project,
                        _day(c.recheckedOn) ?? 're-checked',
                        c.result,
                      ].where((x) => (x ?? '').toString().isNotEmpty).join(' · '),
                      trailing: c.hasProof
                          ? IconButton(
                              tooltip: 'Your proof photograph',
                              icon: const Icon(Icons.photo_outlined, color: green, size: 20),
                              onPressed: () => _showProof(c),
                            )
                          : const Icon(Icons.photo_outlined, color: muted, size: 20),
                    ),
                ],
              ),
            ),
          ],
          const SizedBox(height: 8),
          const SectionCard(
            title: 'How an inspection runs (§11.4)',
            child: _Steps(items: [
              ('The office schedules the visit', 'with the items to be checked listed'),
              ('The inspector marks each item', 'pass, fail, or not applicable on your site'),
              ('You answer a failure here', 'what you did, with the photograph of the rework'),
              ('The inspector re-checks it',
                  'only they clear a flagged item — your upload never marks it passed'),
              ('The report is signed off', 'not while an item of yours is still standing'),
            ]),
          ),
          const Padding(
            padding: EdgeInsets.only(top: 4),
            child: Text(
                'Your progress, your days and your completion photographs stay on My work orders — this '
                'screen is only for what an inspection flagged.',
                style: TextStyle(color: muted, fontSize: 12, height: 1.4)),
          ),
        ],
      ),
    );
  }
}

class _FixCard extends StatelessWidget {
  final FixItem item;
  final bool busy;
  final VoidCallback onProve;
  final VoidCallback onShowProof;
  const _FixCard({
    required this.item,
    required this.busy,
    required this.onProve,
    required this.onShowProof,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      padding: const EdgeInsets.fromLTRB(16, 14, 14, 14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border(
          left: BorderSide(color: danger, width: 4),
          top: BorderSide(color: line),
          right: BorderSide(color: line),
          bottom: BorderSide(color: line),
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Text(item.item,
                    style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14.5, color: ink)),
              ),
              Tag(item.result, color: danger),
            ],
          ),
          Padding(
            padding: const EdgeInsets.only(top: 5),
            child: Text(
              [
                item.project ?? 'your site',
                if ((item.order ?? '').isNotEmpty) 'order ${item.order}',
                if (item.inspectedOn != null) 'visited ${_day(item.inspectedOn)}',
              ].join(' · '),
              style: const TextStyle(color: muted, fontSize: 12),
            ),
          ),
          const SizedBox(height: 10),
          Text.rich(
            TextSpan(
              text: 'What they wrote: ',
              style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: ink),
              children: [
                TextSpan(
                  text: item.note ?? 'no note was left on the mark',
                  style: const TextStyle(fontWeight: FontWeight.w400, color: muted),
                ),
              ],
            ),
          ),
          if (item.hasProof) ...[
            const SizedBox(height: 8),
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(color: soft, borderRadius: BorderRadius.circular(12)),
              child: Row(
                children: [
                  const Icon(Icons.check_circle_outline, size: 18, color: green),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'Your answer is already filed and waiting for the re-check: '
                      '${item.proofNote ?? ''}${item.proofOn != null ? ' — uploaded ${_day(item.proofOn)}' : ''}',
                      style: const TextStyle(fontSize: 12, color: muted, height: 1.35),
                    ),
                  ),
                  IconButton(
                    tooltip: 'open the photograph',
                    icon: const Icon(Icons.photo_outlined, color: green, size: 20),
                    onPressed: onShowProof,
                  ),
                ],
              ),
            ),
          ],
          if (item.canProve) ...[
            const SizedBox(height: 12),
            FilledButton.icon(
              onPressed: busy ? null : onProve,
              icon: const Icon(Icons.handyman_outlined, size: 18),
              label: Text(item.hasProof ? 'Send the new photograph' : 'Put it right'),
            ),
          ],
        ],
      ),
    );
  }
}

class _Stats extends StatelessWidget {
  final List<(String, int)> tiles;
  const _Stats({required this.tiles});

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        for (final t in tiles)
          Expanded(
            child: Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: soft),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('${t.$2}',
                      style: const TextStyle(
                          fontSize: 21, fontWeight: FontWeight.w800, color: green)),
                  Text(t.$1,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(fontSize: 11.5, color: muted, height: 1.25)),
                ],
              ),
            ),
          ),
      ],
    );
  }
}

class _Steps extends StatelessWidget {
  final List<(String, String)> items;
  const _Steps({required this.items});

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        for (var n = 0; n < items.length; n++)
          Padding(
            padding: const EdgeInsets.only(bottom: 10),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 24,
                  height: 24,
                  alignment: Alignment.center,
                  decoration: const BoxDecoration(color: soft, shape: BoxShape.circle),
                  child: Text('${n + 1}',
                      style: const TextStyle(
                          fontWeight: FontWeight.w800, fontSize: 12, color: green)),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(items[n].$1,
                          style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13.5)),
                      Padding(
                        padding: const EdgeInsets.only(top: 2),
                        child: Text(items[n].$2,
                            style: const TextStyle(color: muted, fontSize: 12, height: 1.35)),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
      ],
    );
  }
}

const _months = [
  'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
  'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec',
];

String? _day(String? iso) {
  final d = DateTime.tryParse(iso ?? '');
  if (d == null) return (iso ?? '').isEmpty ? null : iso;
  return '${d.day.toString().padLeft(2, '0')} ${_months[d.month - 1]} ${d.year}';
}
