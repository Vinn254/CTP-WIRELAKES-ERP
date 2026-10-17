import 'package:flutter/material.dart';
import '../models/field_models.dart';
import '../models/member_models.dart';
import '../services/api_client.dart';
import '../widgets/ui.dart';

/// E-Waste collection — what the member registered, and for an E-Fundi the CTP lines waiting
/// to be carried to a collection point. Mirrors web/resources/views/portal/ewaste.blade.php.
class EwasteScreen extends StatefulWidget {
  final ApiClient api;
  final Board board;
  const EwasteScreen({super.key, required this.api, required this.board});

  @override
  State<EwasteScreen> createState() => _EwasteScreenState();
}

class _EwasteScreenState extends State<EwasteScreen> {
  EwasteBoard? _b;
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
      final b = await widget.api.ewaste();
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

  // ---- actions --------------------------------------------------------------

  Future<void> _register(EwasteBoard b) async {
    final body = await showFormSheet(
      context,
      title: 'Register a collection',
      subtitle: 'One line per kind of item. A mixed bag of cables and a pallet of monitors are not '
          'the same collection — register them separately so the condition and the route stay true.',
      cta: 'Register this collection',
      fields: [
        FieldSpec.dropdown('item_type', 'What it is', b.itemTypes, helper: 'Choose the item'),
        FieldSpec('quantity', 'How many', kind: FieldKind.integer, initial: '1'),
        FieldSpec('collected_on', 'Date collected', kind: FieldKind.date,
            helper: 'You register what you have already picked up, so the date cannot be in the future.'),
        FieldSpec.dropdown('condition', 'Condition', b.conditions),
        FieldSpec.dropdown('county', 'County', {for (final c in b.counties) c: c},
            initial: widget.board.member.county),
        FieldSpec('ward', 'Ward', required: false),
        FieldSpec.dropdown('community_id', 'Community it came from', {
          '': 'Not a registered community',
          for (final c in b.communities) '${c.id}': c.name,
        }, required: false),
        FieldSpec('estimated_weight_kg', 'Estimated weight (kg)',
            kind: FieldKind.number, required: false, helper: 'e.g. 45'),
        FieldSpec('note', 'What you know about it', kind: FieldKind.multiline, required: false,
            helper: 'e.g. 12 desktops written off by the school; 4 still boot. Stored dry at the '
                "chief's camp."),
        FieldSpec.photo('photo', 'A photo of the items',
            photoHelper: 'Jpg or png, up to 6 MB. The collection point sorts from the picture.'),
      ],
    );
    if (body == null) return;
    await _post(() => widget.api.storeEwaste(body));
  }

  Future<void> _forward(EwasteBoard b, CollectionLine line) async {
    final body = await showFormSheet(
      context,
      title: 'Forward ${line.reference} to the programme office',
      subtitle: 'You can correct the condition on the way, because what it looks like in bulk is '
          'not what the CTP could see.',
      cta: 'Forward to the office',
      fields: [
        FieldSpec.dropdown('condition', 'Condition as you saw it', b.conditions),
        FieldSpec('note', 'Where you left it', kind: FieldKind.multiline, required: false,
            helper: 'e.g. Left at the Nyanda store with the caretaker; 2 monitors cracked in transit.'),
      ],
    );
    if (body == null) return;
    await _post(() => widget.api.forwardEwaste(
          line.id,
          '${body['condition']}',
          note: body['note'] as String?,
        ));
  }

  Future<void> _openPhoto(int collectionId) async {
    try {
      final file = await widget.api.ewastePhoto(collectionId);
      if (mounted) showPicture(context, file);
    } catch (e) {
      if (mounted) showError(context, e);
    }
  }

  // ---- page -----------------------------------------------------------------

  @override
  Widget build(BuildContext context) {
    if (_loading && _b == null) return const LoadingView();
    if (_error != null && _b == null) return ErrorRetry(message: _error!, onRetry: _load);
    final b = _b!;
    final isFundi = widget.board.member.isFundi;

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(14),
        children: [
          SectionCard(
            title: 'E-Waste collection',
            subtitle: 'Most of what the programme picks up is electronics — a dead monitor, a bag '
                'of cables, routers that a school has written off. Every line you register here goes '
                'on the e-waste register with your own name against it, the condition you found it in '
                'and a photo. The programme office weighs it in at a collection point, then routes it: '
                'repair, refurbish, recycle or safe disposal. '
                '${isFundi ? 'You register your own collections and forward the ones a CTP logged near you.' : 'You register what you collect in your area; an E-Fundi or the office carries it there.'}',
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                FilledButton.icon(
                  onPressed: () => _register(b),
                  icon: const Icon(Icons.recycling_outlined, size: 18),
                  label: const Text('Register a collection'),
                ),
                const SizedBox(height: 10),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    _Stat('Lines registered', b.totals['lines'] ?? '0'),
                    _Stat('Items', b.totals['items'] ?? '0'),
                    _Stat('Estimated', _kg(b.totals['estimated'])),
                    _Stat('Weighed in', _kg(b.totals['weighed'])),
                    if (!isFundi) _Stat('Waiting near you', b.totals['waiting'] ?? '0'),
                  ],
                ),
              ],
            ),
          ),
          SectionCard(
            title: 'What I registered (${b.mine.length})',
            child: b.mine.isEmpty
                ? const EmptyHint(
                    icon: Icons.recycling_outlined,
                    title: 'Nothing on the register under your name yet',
                    body: 'Add the first collection — even a small bag of cables counts, and the '
                        'programme reports the kilos it keeps out of the community.',
                  )
                : Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      for (final line in b.mine) _lineRow(line),
                      const Text('A collection line is never deleted. If the office weighs something '
                          'differently, they record the weight on the same line and the first figure '
                          'stays as what you estimated.',
                          style: TextStyle(color: muted, fontSize: 12)),
                    ],
                  ),
          ),
          // Only an E-Fundi sees the waiting list and may forward — the API enforces the same rule.
          if (isFundi)
            SectionCard(
              title: 'Registered by CTPs, waiting to be taken in (${b.waiting.length})',
              subtitle: 'These are in your county. Carry them to a collection point and forward the '
                  'line — you can correct the condition on the way, because what it looks like in '
                  'bulk is not what the CTP could see.',
              child: b.waiting.isEmpty
                  ? const Text('Nothing is waiting in your county right now.',
                      style: TextStyle(color: muted, fontSize: 13))
                  : Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        for (final line in b.waiting) ...[
                          _lineRow(line),
                          if (line.canForward)
                            Align(
                              alignment: Alignment.centerLeft,
                              child: FilledButton.tonalIcon(
                                onPressed: () => _forward(b, line),
                                icon: const Icon(Icons.local_shipping_outlined, size: 18),
                                label: const Text('Forward to a collection point'),
                              ),
                            ),
                        ],
                      ],
                    ),
            ),
          const SizedBox(height: 8),
        ],
      ),
    );
  }

  Widget _lineRow(CollectionLine line) {
    final meta = [
      line.collectedOn,
      line.place,
      line.weight,
      if (line.collector != null) 'by ${line.collector}',
    ].where((x) => x != null && x.isNotEmpty).join(' · ');

    return RecordRow(
      title: line.reference,
      subtitle: '${line.item} ×${line.quantity} · ${line.condition}',
      meta: [
        meta,
        if (line.note != null) 'You wrote: ${line.note}',
        if (line.forwardNote != null) 'What the E-Fundi reported: ${line.forwardNote}',
      ].where((x) => x.isNotEmpty).join(' — '),
      trailing: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (line.hasPhoto)
            IconButton(
              tooltip: 'Photo of ${line.reference}',
              icon: const Icon(Icons.photo_outlined, size: 20, color: green),
              onPressed: () => _openPhoto(line.id),
            ),
          Tag(line.statusLabel, color: line.status == 'received' ? green : muted),
        ],
      ),
    );
  }
}

String _kg(String? v) {
  final n = double.tryParse(v ?? '') ?? 0;
  return n == 0 ? '—' : '${n.toStringAsFixed(1)} kg';
}

class _Stat extends StatelessWidget {
  final String label;
  final String value;
  const _Stat(this.label, this.value);

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: soft,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: const TextStyle(color: muted, fontSize: 11)),
          Text(value,
              style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: ink)),
        ],
      ),
    );
  }
}
