import 'package:flutter/material.dart';
import '../models/account_models.dart';
import '../models/member_models.dart';
import '../services/api_client.dart';
import '../widgets/ui.dart';

/// My library — everything the Registrar has left on the shelf. The course a title is filed under groups it
/// and says who the reading was written for; it does not lock the title away from the other students.
class LibraryScreen extends StatefulWidget {
  final ApiClient api;
  final Board board;
  const LibraryScreen({super.key, required this.api, required this.board});

  @override
  State<LibraryScreen> createState() => _LibraryScreenState();
}

class _LibraryScreenState extends State<LibraryScreen> {
  LibraryBoard? _data;
  String? _course;
  String? _kind;
  bool _loading = false;
  String? _error;
  String? _note;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final b = await widget.api.library(course: _course, kind: _kind);
      if (mounted) {
        setState(() {
          _data = b;
          _loading = false;
          _error = null;
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

  Future<void> _read(LibraryRow item) async {
    try {
      final file = await widget.api.libraryFile(item.id);
      if (mounted) await openDocument(context, file);
    } catch (e) {
      if (mounted) showError(context, e);
    }
  }

  /// Keeping a title is the paid part. A trainee is only handed the file once the Registrar has confirmed the money,
  /// and the API refuses the save until then, so the app never has to trust its own screen.
  Future<void> _keep(LibraryRow item) async {
    final b = _data!;

    if (!b.charges || item.paid) {
      try {
        final file = await widget.api.libraryFile(item.id, save: true);
        if (mounted) await openDocument(context, file);
      } catch (e) {
        if (mounted) showError(context, e);
      }
      return;
    }

    final sent = await showFormSheet(
      context,
      title: 'Save this title to your phone',
      subtitle: '${item.title} — ${item.price}. Reading it here costs nothing; keeping the file is what costs. '
          'Pay on Paybill ${b.paybillNumber}, account number ${b.paybillAccount}, then tell the office. '
          'The Academic Registrar confirms the money and your download opens then.',
      fields: const [],
      cta: item.status == 'requested'
          ? 'Already told the office — waiting'
          : 'I have paid — tell the office',
    );

    if (sent == null || !mounted) return;
    if (item.status == 'requested') return;

    try {
      final message = await widget.api.libraryPay(item.id);
      if (mounted) {
        setState(() => _note = message);
        _load();
      }
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
            title: 'Find reading',
            subtitle: 'Only what the Registrar has approved is on the shelf, and only the reading '
                'filed for a course you sit in class for.',
            child: Column(
              children: [
                _picker(
                  label: 'Programme',
                  value: _course,
                  options: {'': 'Every programme', ...b.courses},
                  onChanged: (v) {
                    setState(() => _course = v);
                    _load();
                  },
                ),
                const SizedBox(height: 8),
                _picker(
                  label: 'Type',
                  value: _kind,
                  options: {'': 'Any type', ...b.kinds},
                  onChanged: (v) {
                    setState(() => _kind = v);
                    _load();
                  },
                ),
              ],
            ),
          ),
          if (b.charges)
            SectionCard(
              title: 'Keeping a title',
              subtitle: 'Read anything here for nothing. Saving a book or notes to your phone is KSh 100 and a past '
                  'paper is KSh 50, sent to Paybill ${b.paybillNumber}, account number ${b.paybillAccount}. '
                  'The Academic Registrar confirms it, then your download opens.',
              child: const SizedBox.shrink(),
            ),
          if (_note != null) _notice(_note!, color: onLime),
          if (b.items.isEmpty)
            const Card(
              child: EmptyHint(
                icon: Icons.menu_book_outlined,
                title: 'Nothing on the shelf for that filter',
                body: 'Try every programme and every type, or ask the office to file a title for your course.',
              ),
            )
          else
            for (final entry in b.byCourse.entries)
              SectionCard(
                title: entry.key,
                subtitle: '${entry.value.length} '
                    '${entry.value.length == 1 ? 'title' : 'titles'}',
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    for (final item in entry.value)
                      _row(item, free: !b.charges || item.paid),
                  ],
                ),
              ),
          if (_error != null) _notice(_error!),
        ],
      ),
    );
  }

  Widget _row(LibraryRow item, {required bool free}) => RecordRow(
        title: item.title,
        subtitle: [item.kindLabel, item.postedOn, item.size]
            .where((x) => x != null && x.isNotEmpty)
            .join(' · '),
        meta: item.note,
        trailing: item.hasFile
            ? Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  TextButton.icon(
                    onPressed: () => _read(item),
                    icon: const Icon(Icons.visibility_outlined, size: 16),
                    label: const Text('Read it'),
                  ),
                  TextButton.icon(
                    onPressed: () => _keep(item),
                    icon: Icon(free ? Icons.download_outlined : Icons.lock_outline, size: 16),
                    label: Text(free
                        ? 'Download'
                        : item.status == 'requested'
                            ? 'Waiting for the office'
                            : 'Keep it · ${item.price}'),
                  ),
                ],
              )
            : const Tag('No file', color: muted),
      );

  Widget _picker({
    required String label,
    required String? value,
    required Map<String, String> options,
    required ValueChanged<String?> onChanged,
  }) {
    return Row(
      children: [
        SizedBox(
            width: 92,
            child: Text(label, style: const TextStyle(color: muted, fontSize: 13))),
        Expanded(
          child: DropdownButton<String>(
            value: options.containsKey(value ?? '') ? value ?? '' : '',
            isExpanded: true,
            underline: const SizedBox.shrink(),
            items: [
              for (final e in options.entries)
                DropdownMenuItem(value: e.key, child: Text(e.value, overflow: TextOverflow.ellipsis)),
            ],
            onChanged: onChanged,
          ),
        ),
      ],
    );
  }

  Widget _notice(String message, {Color color = danger}) => Padding(
        padding: const EdgeInsets.only(top: 4),
        child: Text(message, style: TextStyle(color: color, fontSize: 12.5)),
      );
}
