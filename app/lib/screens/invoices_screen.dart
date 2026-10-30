import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../models/account_models.dart';
import '../models/member_models.dart';
import '../services/api_client.dart';
import '../widgets/ui.dart';

/// My invoices — what the programme office has billed this member, and the money it has seen.
/// Read-only by design: the office raises and amends an invoice, a member never edits one here.
class InvoicesScreen extends StatefulWidget {
  final ApiClient api;
  final Board board;
  const InvoicesScreen({super.key, required this.api, required this.board});

  @override
  State<InvoicesScreen> createState() => _InvoicesScreenState();
}

class _InvoicesScreenState extends State<InvoicesScreen> {
  InvoiceBoard? _data;
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
      final b = await widget.api.invoices();
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
            title: 'These are the invoices the programme office has raised against your name',
            subtitle: 'Settle the balance shown and the office marks the invoice paid when the money '
                'is seen.',
            child: _totals(b),
          ),
          SectionCard(
            title: 'How to pay',
            subtitle: 'Put the invoice number (INV-…) as the reason for each payment — that is what '
                'lets the office match your money to the right invoice. If you paid and it is still '
                'showing here, contact the office with the amount, date and phone number you paid from.',
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    _paybillTile('Paybill', b.paybillNumber),
                    _paybillTile('Account number', b.paybillAccount),
                  ],
                ),
                if (_payable(b)) ...[
                  const SizedBox(height: 10),
                  Align(
                    alignment: Alignment.centerLeft,
                    child: FilledButton.icon(
                      onPressed: () => _copy(context, b),
                      icon: const Icon(Icons.copy, size: 18),
                      label: const Text('Copy the payment to make'),
                    ),
                  ),
                ],
              ],
            ),
          ),
          for (final i in b.invoices) _invoiceCard(i),
          const Padding(
            padding: EdgeInsets.only(top: 4),
            child: Text('If a figure here is wrong, or a payment you made is missing, the correction '
                'is made by the programme office on the same invoice — the invoice is amended, never '
                're-raised under a new number.',
                style: TextStyle(color: muted, fontSize: 12)),
          ),
        ],
      ),
    );
  }

  Widget _totals(InvoiceBoard b) {
    final items = [
      ('Total billed', b.totals['billed'] ?? '—'),
      ('Received so far', b.totals['paid'] ?? '—'),
      ('Balance you owe', b.totals['owing'] ?? '—'),
      ('Invoices to settle', '${b.openCount}'),
    ];
    return Wrap(
      spacing: 8,
      runSpacing: 8,
      children: [
        for (final it in items)
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            decoration: BoxDecoration(color: soft, borderRadius: BorderRadius.circular(16)),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(it.$2,
                    style: const TextStyle(
                        fontWeight: FontWeight.w800, fontSize: 14, color: greenDark)),
                Text(it.$1, style: const TextStyle(fontSize: 11, color: muted)),
              ],
            ),
          ),
      ],
    );
  }

  Widget _paybillTile(String label, String value) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
        decoration: BoxDecoration(color: limeSoft, borderRadius: BorderRadius.circular(12)),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(value,
                style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 17, color: ink)),
            Text(label, style: const TextStyle(fontSize: 11.5, color: muted)),
          ],
        ),
      );

  bool _payable(InvoiceBoard b) =>
      b.paybillNumber.isNotEmpty && b.paybillAccount.isNotEmpty && b.openCount > 0;

  /// One line the member can paste into the M-Pesa Paybill form — the words the office asks for.
  Future<void> _copy(BuildContext context, InvoiceBoard b) async {
    InvoiceRow? open;
    for (final i in b.invoices) {
      if (i.status == 'open') {
        open = i;
        break;
      }
    }

    final text = 'Pay ${open?.balance ?? b.totals['owing']} to Paybill ${b.paybillNumber}, '
        'Account ${b.paybillAccount}, Reason ${open?.reference ?? 'CTP-WireLakes'}.';
    await Clipboard.setData(ClipboardData(text: text));
    if (context.mounted) say(context, 'Copied. Paste it into your M-Pesa Paybill.');
  }

  Widget _invoiceCard(InvoiceRow i) {
    final owing = !i.cancelled && !i.settled;
    return SectionCard(
      title: '${i.reference} — ${i.title}',
      subtitle: i.details,
      trailing: Tag(i.statusLabel + (i.overdue ? ' · overdue' : ''),
          color: i.settled ? green : i.cancelled ? muted : i.overdue ? danger : warn),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          LabeledValue(label: 'Issued', value: i.issuedOn ?? '—'),
          LabeledValue(label: 'Due by', value: i.dueOn),
          LabeledValue(label: 'Billed', value: i.amount, strong: false),
          LabeledValue(label: 'Received', value: i.paid, strong: false),
          LabeledValue(label: 'Balance', value: i.balance),
          if (i.note != null) LabeledValue(label: 'Note', value: i.note!, strong: false),
          if (i.cancelled)
            Padding(
              padding: const EdgeInsets.only(top: 6),
              child: Text(
                  'Withdrawn by ${i.withdrawnBy ?? 'the office'}'
                  '${i.cancelReason != null ? ' — ${i.cancelReason}' : ''}. You do not owe this one.',
                  style: const TextStyle(color: muted, fontSize: 12.5)),
            ),
          if (i.payments.isNotEmpty) ...[
            const SizedBox(height: 10),
            const Text('What the office has received against this invoice',
                style: TextStyle(fontWeight: FontWeight.w800, fontSize: 13.5, color: ink)),
            for (final p in i.payments)
              RecordRow(
                title: p.amount,
                subtitle: p.on,
                meta: '${p.method} · ${p.reference}',
              ),
          ],
          if (owing)
            Padding(
              padding: const EdgeInsets.only(top: 6),
              child: Text('Use the Paybill above and quote ${i.reference}.',
                  style: const TextStyle(color: muted, fontSize: 12.5)),
            ),
        ],
      ),
    );
  }
}
