/// One class, five papers — the tabs the web teaching desk runs across, on the phone.
library;

import 'package:flutter/material.dart';
import '../../services/api_client.dart';
import '../../widgets/ui.dart';
import 'lessons_tab.dart';
import 'marks_tab.dart';
import 'materials_tab.dart';
import 'register_tab.dart';
import 'timetable_tab.dart';

class CohortPapersScreen extends StatelessWidget {
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
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 5,
      child: Scaffold(
        appBar: AppBar(
          title: Text(cohortLabel),
          bottom: const TabBar(
            isScrollable: true,
            labelColor: Colors.white,
            unselectedLabelColor: Color(0xFFBFD8C4),
            indicatorColor: lime,
            indicatorWeight: 3,
            tabs: [
              Tab(text: 'Attendance'),
              Tab(text: 'Marks'),
              Tab(text: 'Timetable'),
              Tab(text: 'Lesson plan'),
              Tab(text: 'Material'),
            ],
          ),
        ),
        body: TabBarView(
          children: [
            RegisterTab(api: api, cohortId: cohortId),
            MarksTab(api: api, cohortId: cohortId),
            TimetableTab(api: api, cohortId: cohortId),
            LessonsTab(api: api, cohortId: cohortId),
            MaterialsTab(api: api, cohortId: cohortId),
          ],
        ),
      ),
    );
  }
}
