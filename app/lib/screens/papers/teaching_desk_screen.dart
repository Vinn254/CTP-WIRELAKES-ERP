/// My teaching on the phone — the classes the office handed this trainer. Choosing one here is what puts the
/// five papers in the bar along the foot of the screen, so the register, the marks, the week, the plan and the
/// material are all reachable without opening the class again.
/// The same list web/resources/views/teaching draws for the Training desk and the trainer's portal page.
library;

import 'package:flutter/material.dart';
import '../../models/teaching_models.dart';
import '../../services/api_client.dart';
import '../../widgets/ui.dart';

class TeachingDeskScreen extends StatefulWidget {
  final ApiClient api;

  /// The board the shell already holds, so the bar knows the classes before this page repaints.
  final TeachingDeskBoard? board;
  final int? selectedId;
  final ValueChanged<TeachingDeskBoard> onLoaded;
  final ValueChanged<TaughtCohort> onSelect;

  const TeachingDeskScreen({
    super.key,
    required this.api,
    required this.onSelect,
    this.onLoaded = _ignoreBoard,
    this.board,
    this.selectedId,
  });

  static void _ignoreBoard(TeachingDeskBoard _) {}

  @override
  State<TeachingDeskScreen> createState() => _TeachingDeskScreenState();
}

class _TeachingDeskScreenState extends State<TeachingDeskScreen> {
  TeachingDeskBoard? _board;
  bool _loading = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _board = widget.board;
    _load();
  }

  Future<void> _load() async {
    setState(() { _loading = true; _error = null; });
    try {
      final board = await widget.api.teachingDesk();
      if (!mounted) return;
      setState(() { _board = board; _loading = false; });
      widget.onLoaded(board);
    } catch (e) {
      if (mounted) setState(() { _error = '$e'; _loading = false; });
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading && _board == null) return const LoadingView();
    if (_error != null && _board == null) {
      return ErrorRetry(message: _error!, onRetry: _load);
    }
    final board = _board;
    if (board == null) return const LoadingView();

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(14, 14, 14, 28),
        children: [
          Text('My teaching',
              style: const TextStyle(fontSize: 19, fontWeight: FontWeight.w800, color: ink)),
          const Padding(
            padding: EdgeInsets.only(top: 4, bottom: 12),
            child: Text('Choose the class you are working on and its papers open along the bottom of the '
                'screen — the attendance register, the marks, the class week, the lesson plan and the '
                'material, written here and sent to the Academic Registrar.',
                style: TextStyle(color: muted, fontSize: 12.5, height: 1.4)),
          ),
          if (board.cohorts.isEmpty)
            EmptyHint(
              icon: Icons.class_outlined,
              title: 'No class is handed to you yet',
              body: board.note ??
                  'The office posts a trainer on a cohort before its papers open.',
            )
          else
            for (final cohort in board.cohorts) _card(cohort, board.office),
        ],
      ),
    );
  }

  Widget _card(TaughtCohort cohort, bool office) {
    final meta = [
      if (cohort.programme != null && cohort.programme!.isNotEmpty) cohort.programme!,
      if (cohort.venue != null && cohort.venue!.isNotEmpty) cohort.venue!,
      cohort.period,
      '${cohort.seats} ${cohort.seats == 1 ? 'CTP' : 'CTPs'} on the roll',
      '${cohort.units} ${cohort.units == 1 ? 'unit' : 'units'}',
    ].where((x) => x != null && x.isNotEmpty).join(' · ');
    final chosen = cohort.id == widget.selectedId;

    return SectionCard(
      title: '${cohort.reference} — ${cohort.course}',
      subtitle: meta,
      trailing: chosen
          ? const Tag('Working on this', color: onLime)
          : Tag(cohort.statusLabel, color: cohort.status == 'running' ? green : muted),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (cohort.stage != null && cohort.stage!.isNotEmpty)
            Padding(
              padding: const EdgeInsets.only(bottom: 6),
              child: Text(cohort.stage!, style: const TextStyle(color: muted, fontSize: 12.5)),
            ),
          if (office)
            const Padding(
              padding: EdgeInsets.only(bottom: 6),
              child: Text('The Training desk records on every run, so this class is not yours alone.',
                  style: TextStyle(color: warn, fontSize: 12)),
            ),
          FilledButton.icon(
            onPressed: () => widget.onSelect(cohort),
            style: FilledButton.styleFrom(
                backgroundColor: chosen ? lime : green,
                foregroundColor: chosen ? onLime : Colors.white,
                minimumSize: const Size.fromHeight(44)),
            icon: Icon(chosen ? Icons.touch_app_outlined : Icons.description_outlined, size: 18),
            label: Text(chosen ? 'Open its attendance register' : 'Work on this class'),
          ),
        ],
      ),
    );
  }
}
