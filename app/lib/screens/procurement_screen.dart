import 'package:flutter/material.dart';
import '../models/field_models.dart';
import '../models/member_models.dart';
import '../services/api_client.dart';
import '../widgets/ui.dart';

/// Purchase request — the member's own requests and how far each has travelled on the
/// §14.4 path. Mirrors web/resources/views/portal/procurement.blade.php.
class ProcurementScreen extends StatefulWidget {
  final ApiClient api;
  final Board board;
  const ProcurementScreen({super.key, required this.api, required this.board});

  @override
  State<ProcurementScreen> createState() => _ProcurementScreenState();
}

class _ProcurementScreenState extends State<ProcurementScreen> {
  ProcurementBoard? _b;
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
      final b = await widget.api.procurement();
      if (mounted) setState(() { _b = b; _loading = false; });
    } catch (e) {
      if (mounted) setState(() { _error = '$e'; _loading = false; });
    }
  }

  Future<void> _raise(ProcurementBoard b) async {
    final body = await showFormSheet(
      context,
      title: 'Raise a request',
      subtitle: 'One request per item or per batch that goes out together. Charge it to the budget '
          'the money really comes out of — a training need to the training line, cable to electrical '
          'or ICT, tools to construction.',
      cta: 'Send the request',
      fields: [
        FieldSpec('item_name', 'What is needed', helper: 'e.g. 13-pin wall sockets, 32A'),
        FieldSpec('quantity', 'Quantity', kind: FieldKind.integer, initial: '1'),
        FieldSpec.dropdown('unit', 'Unit', b.units),
        FieldSpec('estimated_cost_ksh', 'What it will cost (KSH)',
            kind: FieldKind.number, required: false,
            helper: 'An honest estimate — a quote if you have one.'),
        FieldSpec.dropdown('budget_category', 'Charge to budget', b.categories),
        FieldSpec('needed_by', 'Needed by', kind: FieldKind.date, required: false),
        FieldSpec.dropdown(
          'project_id',
          'For which project of yours',
          {
            '': 'General programme need',
            for (final p in b.awarded) '${p.id}': p.name,
          },
          required: false,
          helper: 'You may only charge a request to a project you were awarded or applied for — '
              'otherwise raise it as a general programme need.',
        ),
        FieldSpec('county', 'County the goods go to',
            required: false, initial: widget.board.member.county),
        FieldSpec('description', 'Spec or make to match',
            kind: FieldKind.multiline, required: false, maxLines: 3),
        FieldSpec('justification', 'Why the programme needs it',
            kind: FieldKind.multiline,
            helper: 'A short reason in plain words — the decision and the quotation are both made '
                'on this. (At least 15 characters.)'),
      ],
    );
    if (body == null) return;
    try {
      final message = await widget.api.storeProcurement(body);
      if (!mounted) return;
      say(context, message);
      _load();
    } catch (e) {
      if (mounted) showError(context, e);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading && _b == null) return const LoadingView();
    if (_error != null && _b == null) return ErrorRetry(message: _error!, onRetry: _load);
    final b = _b!;

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(14),
        children: [
          SectionCard(
            title: 'Purchase request',
            subtitle: 'The programme buys materials against a purchase request, and this screen is '
                'where an E-Fundi on a site raises one — a CTP does not, it mobilises the community '
                'and the office buys on its behalf (§14.4). Say what is '
                'needed, how much, what it will cost and why the programme needs it: that justification '
                'is what the decision is made on, not the item name. What you see here is where your '
                'own request has actually got to.',
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                FilledButton.icon(
                  onPressed: () => _raise(b),
                  icon: const Icon(Icons.add_shopping_cart_outlined, size: 18),
                  label: const Text('Raise a purchase request'),
                ),
                const SizedBox(height: 10),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    _Stat('Your requests', '${b.totals['lines'] ?? 0}'),
                    _Stat('Waiting for a decision', '${b.totals['waiting'] ?? 0}'),
                    _Stat('Being bought', '${b.totals['moving'] ?? 0}'),
                    _Stat('Delivered and paid', '${b.totals['filled'] ?? 0}'),
                  ],
                ),
              ],
            ),
          ),
          SectionCard(
            title: 'What happens after you send it (§14.4)',
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                for (var i = 0; i < b.path.length; i++)
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 4),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        _StepDot(number: i + 1, done: false, current: false),
                        const SizedBox(width: 8),
                        Expanded(
                            child: Text(b.path[i],
                                style: const TextStyle(fontSize: 12.5, color: muted))),
                      ],
                    ),
                  ),
              ],
            ),
          ),
          SectionCard(
            title: 'Your requests (${b.mine.length})',
            child: b.mine.isEmpty
                ? const EmptyHint(
                    icon: Icons.shopping_bag_outlined,
                    title: 'Nothing raised under your name yet',
                    body: 'The first request is usually the one everyone is working around — raise '
                        'it rather than buying it out of pocket.',
                  )
                : Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      for (final line in b.mine) _requestCard(b, line),
                      const Text(
                          'You cannot approve, quote, receive or pay a request from here — those '
                          'steps are the office’s, and each one is signed by a named officer. If a '
                          'request was refused and you can see the gap, raise a new one with the '
                          'correction: the old row and its reason stay on the register.',
                          style: TextStyle(color: muted, fontSize: 12)),
                    ],
                  ),
          ),
          const SizedBox(height: 8),
        ],
      ),
    );
  }

  Widget _requestCard(ProcurementBoard b, PurchaseLine line) {
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 14, 16, 16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                    child: Text(line.reference,
                        style: const TextStyle(
                            fontWeight: FontWeight.w800, fontSize: 14.5, color: ink))),
                Tag(line.statusLabel, color: line.status == 'paid' ? green : muted),
              ],
            ),
            const SizedBox(height: 6),
            LabeledValue(label: 'Item', value: line.itemName, strong: false),
            LabeledValue(label: 'Quantity', value: line.quantity, strong: false),
            LabeledValue(label: 'Estimated', value: line.cost, strong: false),
            LabeledValue(label: 'Charge to', value: line.budget, strong: false),
            LabeledValue(label: 'On the register', value: line.charge, strong: false),
            if (line.raisedOn != null)
              LabeledValue(label: 'Raised', value: line.raisedOn!, strong: false),
            if (line.neededBy != null)
              LabeledValue(label: 'Needed by', value: line.neededBy!, strong: false),
            if (line.justification != null && line.justification!.isNotEmpty)
              LabeledValue(
                  label: 'Why it was asked', value: line.justification!, strong: false),
            if (line.decision != null && line.decision!.isNotEmpty)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 6),
                child: Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: soft,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Text('The office answered: ${line.decision}',
                      style: const TextStyle(fontSize: 12.5, color: ink)),
                ),
              ),
            const SizedBox(height: 8),
            Text('Step ${line.step + 1} of ${b.path.length} — ${line.statusLabel}',
                style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: ink)),
            const SizedBox(height: 6),
            for (var i = 0; i < b.path.length; i++)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 3),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    _StepDot(number: i + 1, done: i < line.step, current: i == line.step),
                    const SizedBox(width: 8),
                    Expanded(
                        child: Text(
                      b.path[i],
                      style: TextStyle(
                        fontSize: 12.5,
                        color: i == line.step ? ink : muted,
                        fontWeight: i == line.step ? FontWeight.w800 : FontWeight.w400,
                      ),
                    )),
                  ],
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _StepDot extends StatelessWidget {
  final int number;
  final bool done;
  final bool current;
  const _StepDot({required this.number, required this.done, required this.current});

  @override
  Widget build(BuildContext context) {
    final color = current ? green : (done ? muted : Colors.grey.shade400);
    return Container(
      width: 22,
      height: 22,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: current || done ? color : Colors.transparent,
        shape: BoxShape.circle,
        border: Border.all(color: color, width: 1.4),
      ),
      child: Text('$number',
          style: TextStyle(
              fontSize: 11,
              fontWeight: FontWeight.w800,
              color: current || done ? Colors.white : color)),
    );
  }
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
              style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
        ],
      ),
    );
  }
}
