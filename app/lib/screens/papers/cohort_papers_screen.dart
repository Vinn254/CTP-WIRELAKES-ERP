/// One class, five papers and the shelf — the pages the web teaching desk runs, opened from the raised bar along
/// the foot of the screen instead of a strip of tabs under the title. The bar is the same one the trainer's own
/// shell wears, so a paper is never more than one press whatever side of the account you came from.
library;

import 'package:flutter/material.dart';
import '../../services/api_client.dart';
import '../../widgets/footer_nav.dart';
import '../library_screen.dart';
import 'lessons_tab.dart';
import 'marks_tab.dart';
import 'materials_tab.dart';
import 'register_tab.dart';
import 'timetable_tab.dart';

/// The desk's pages in the order it runs them.
const paperPages = <FooterItem>[
  FooterItem('register', 'Attendance', Icons.how_to_reg_outlined),
  FooterItem('marks', 'Marks', Icons.assignment_outlined),
  FooterItem('timetable', 'Timetable', Icons.calendar_month_outlined),
  FooterItem('lessons', 'Lesson plan', Icons.event_note_outlined),
  FooterItem('materials', 'Material', Icons.folder_open_outlined),
  FooterItem('library', 'Library', Icons.menu_book_outlined),
];

String paperLabel(String page) =>
    paperPages.firstWhere((p) => p.key == page, orElse: () => paperPages.first).label;

/// The screen one page of the desk opens on. A class is needed for the five papers; the shelf is the
/// Registrar's own and reaches every programme this account teaches, with or without a class.
Widget paperBody(String page, {required ApiClient api, required int? cohortId}) {
  if (page == 'library') return LibraryScreen(api: api, trainer: true);
  if (cohortId == null) return const SizedBox.shrink();

  return switch (page) {
    'marks' => MarksTab(api: api, cohortId: cohortId),
    'timetable' => TimetableTab(api: api, cohortId: cohortId),
    'lessons' => LessonsTab(api: api, cohortId: cohortId),
    'materials' => MaterialsTab(api: api, cohortId: cohortId),
    _ => RegisterTab(api: api, cohortId: cohortId),
  };
}

class CohortPapersScreen extends StatefulWidget {
  final ApiClient api;
  final int cohortId;
  final String cohortLabel;

  const CohortPapersScreen({
    super.key,
    required this.api,
    required this.cohortId,
    this.cohortLabel = 'Class papers',
  });

  @override
  State<CohortPapersScreen> createState() => _CohortPapersScreenState();
}

class _CohortPapersScreenState extends State<CohortPapersScreen> {
  String _page = 'register';

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('${widget.cohortLabel} — ${paperLabel(_page)}'),
      ),
      body: paperBody(_page, api: widget.api, cohortId: widget.cohortId),
      bottomNavigationBar: FooterNav(
        items: paperPages,
        current: _page,
        onOpen: (key) => setState(() => _page = key),
      ),
    );
  }
}
