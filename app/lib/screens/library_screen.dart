import 'package:flutter/material.dart';
import '../models/account_models.dart';
import '../models/member_models.dart';
import '../services/api_client.dart';
import '../widgets/ui.dart';

/// My library — the reading the Registrar has put on the shelf for this trainee's courses, plus
/// what is filed for every student. A title only ever reaches the students it was filed for.
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
                    for (final item in entry.value) _row(item),
                  ],
                ),
              ),
          if (_error != null) _notice(_error!),
        ],
      ),
    );
  }

  Widget _row(LibraryRow item) => RecordRow(
        title: item.title,
        subtitle: [item.kindLabel, item.postedOn, item.size]
            .where((x) => x != null && x.isNotEmpty)
            .join(' · '),
        meta: item.note,
        trailing: item.hasFile
            ? TextButton.icon(
                onPressed: () => _read(item),
                icon: const Icon(Icons.visibility_outlined, size: 16),
                label: const Text('Read it'),
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

  Widget _notice(String message) => Padding(
        padding: const EdgeInsets.only(top: 4),
        child: Text(message, style: const TextStyle(color: danger, fontSize: 12.5)),
      );
}
