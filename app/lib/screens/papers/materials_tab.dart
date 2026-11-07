/// The shelf the class reads from: handouts the trainer puts on the cohort, each one opened, rewritten
/// or taken off, with the file the trainees download carried along with it.
library;

import 'package:flutter/material.dart';
import '../../models/teaching_models.dart';
import '../../services/api_client.dart';
import '../../widgets/ui.dart';

class MaterialsTab extends StatefulWidget {
  final ApiClient api;
  final int cohortId;
  const MaterialsTab({super.key, required this.api, required this.cohortId});

  @override
  State<MaterialsTab> createState() => _MaterialsTabState();
}

class _MaterialsTabState extends State<MaterialsTab> {
  MaterialsBoard? _board;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final first = _board == null;
    if (first) setState(() => _error = null);
    try {
      final board = await widget.api.teachingMaterials(widget.cohortId);
      if (!mounted) return;
      setState(() { _board = board; _error = null; });
    } catch (e) {
      if (!mounted) return;
      if (first) {
        setState(() => _error = '$e');
      } else {
        showError(context, e);
      }
    }
  }

  Map<String, String> get _units => {
        '': 'No unit — the whole class',
        for (final u in _board!.units) '${u.id}': u.title,
      };

  Map<String, String> get _lessons => {
        '': 'No particular lesson',
        for (final line in _board!.lessons)
          '${line.id}': 'Lesson ${line.position} — ${line.topic}',
      };

  Future<void> _write({Handout? paper}) async {
    final board = _board;
    if (board == null) return;

    final answers = await showFormSheet(
      context,
      title: paper == null ? 'Put material on the shelf' : 'Rewrite the handout',
      subtitle: 'Every trainee on ${board.cohort.reference} opens what you put here.',
      cta: paper == null ? 'Put it on the shelf' : 'Keep the changes',
      fields: [
        FieldSpec('title', 'Title', initial: paper?.title),
        FieldSpec.dropdown('kind', 'Type', board.kinds, initial: paper?.kind),
        FieldSpec.dropdown('training_module_id', 'Unit', _units,
            required: false, initial: paper == null ? '' : '${paper.unitId ?? ''}'),
        FieldSpec.dropdown('lesson_line_id', 'Lesson', _lessons,
            required: false, initial: paper == null ? '' : '${paper.lessonId ?? ''}'),
        FieldSpec('given_on', 'Handed out on',
            kind: FieldKind.date, required: false, initial: paper?.givenOn),
        FieldSpec('note', 'Note',
            kind: FieldKind.multiline, required: false, maxLines: 2, initial: paper?.note),
        FieldSpec.photo('file', 'The file',
            photoHelper: paper == null
                ? 'A photo or scan of the handout — pdf, slides and notes sit on the web desk.'
                : 'A new photo overwrites this one — the trainees open the file you keep now.'),
      ],
    );
    if (answers == null || !mounted) return;
    if (answers['file'] == null && paper == null) {
      showError(context, 'Choose the handout to put on the shelf.');
      return;
    }

    final body = <String, dynamic>{
      for (final key in ['title', 'kind', 'training_module_id', 'lesson_line_id', 'given_on', 'note'])
        if (answers[key] != null && '${answers[key]}'.isNotEmpty) key: answers[key],
      if (answers['file'] != null) 'file': answers['file'],
    };

    try {
      final message = paper == null
          ? await widget.api.storeHandout(widget.cohortId, body)
          : await widget.api.updateHandout(widget.cohortId, paper.id, body);
      if (mounted) say(context, message);
      await _load();
    } catch (e) {
      if (mounted) showError(context, e);
    }
  }

  Future<void> _remove(Handout paper) async {
    final sure = await confirm(
      context,
      title: 'Take “${paper.title}” off the shelf?',
      body: 'The trainees lose the file with it. Put it back on the shelf any time you teach it again.',
      cta: 'Take it off',
    );
    if (sure != true || !mounted) return;
    try {
      final message = await widget.api.removeHandout(widget.cohortId, paper.id);
      if (mounted) say(context, message);
      await _load();
    } catch (e) {
      if (mounted) showError(context, e);
    }
  }

  Future<void> _open(Handout paper) async {
    try {
      final file = await widget.api.openHandout(widget.cohortId, paper.id);
      if (mounted) await openDocument(context, file);
    } catch (e) {
      if (mounted) showError(context, e);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_error != null && _board == null) return ErrorRetry(message: _error!, onRetry: _load);
    final board = _board;
    if (board == null) return const LoadingView();

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(14, 12, 14, 28),
        children: [
          SectionCard(
            title: 'The class shelf',
            subtitle: '${board.papers.length} ${board.papers.length == 1 ? 'handout' : 'handouts'}',
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('Notes, slides, assignments and past papers the trainees keep. The file a trainee '
                    'opens here is the same one the web shelf carries.',
                    style: TextStyle(color: muted, fontSize: 12.5, height: 1.4)),
                const SizedBox(height: 10),
                FilledButton.icon(
                  onPressed: _write,
                  style: FilledButton.styleFrom(
                      backgroundColor: green, foregroundColor: Colors.white),
                  icon: const Icon(Icons.add, size: 18),
                  label: const Text('Put material on the shelf'),
                ),
              ],
            ),
          ),
          if (board.papers.isEmpty)
            const EmptyHint(
              icon: Icons.folder_open_outlined,
              title: 'Nothing on the shelf yet',
              body: 'Put the first handout up and the class has something to read between lessons.',
            )
          else
            for (final paper in board.papers) _card(paper),
        ],
      ),
    );
  }

  Widget _card(Handout paper) {
    final meta = [
      if (paper.unit != null && paper.unit!.isNotEmpty) paper.unit!,
      paper.dateLabel,
      if (paper.sizeLabel.isNotEmpty) paper.sizeLabel,
    ].join(' · ');

    return SectionCard(
      title: paper.title,
      subtitle: '${paper.kindLabel}${meta.isEmpty ? '' : ' · $meta'}',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (paper.lesson != null && paper.lesson!.isNotEmpty)
            Padding(
              padding: const EdgeInsets.only(bottom: 6),
              child: Text('For ${paper.lesson}',
                  style: const TextStyle(color: muted, fontSize: 12.5)),
            ),
          if (paper.note != null && paper.note!.isNotEmpty)
            Padding(
              padding: const EdgeInsets.only(bottom: 6),
              child: Text(paper.note!, style: const TextStyle(fontSize: 13, height: 1.4)),
            ),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              if (paper.fileName != null && paper.fileName!.isNotEmpty)
                OutlinedButton.icon(
                  onPressed: () => _open(paper),
                  icon: const Icon(Icons.description_outlined, size: 18),
                  label: Text(paper.fileName!, overflow: TextOverflow.ellipsis),
                ),
              OutlinedButton.icon(
                onPressed: () => _write(paper: paper),
                icon: const Icon(Icons.edit_outlined, size: 18),
                label: const Text('Rewrite'),
              ),
              TextButton.icon(
                onPressed: () => _remove(paper),
                style: TextButton.styleFrom(foregroundColor: danger),
                icon: const Icon(Icons.delete_outline, size: 18),
                label: const Text('Take off the shelf'),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
