import 'package:flutter/material.dart';
import '../models/account_models.dart';
import '../models/member_models.dart';
import '../services/api_client.dart';
import '../widgets/ui.dart';

/// My class — the trainee's own timetable, units and handouts (§ Module 4 / teaching records).
/// Everything here is written by the trainer and approved by the Registrar; the member only reads it
/// and opens the files, exactly as on the portal page.
class ClassScreen extends StatefulWidget {
  final ApiClient api;
  final Board board;
  const ClassScreen({super.key, required this.api, required this.board});

  @override
  State<ClassScreen> createState() => _ClassScreenState();
}

class _ClassScreenState extends State<ClassScreen> {
  List<ClassSeat> _seats = [];
  ClassBoard? _detail;
  int? _cohortId;
  bool _loading = false;
  bool _opening = false;
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
      final seats = await widget.api.classSeats();
      final first = seats.isEmpty ? null : seats.first.cohortId;
      setState(() {
        _seats = seats;
        _loading = false;
        _detail = null;
        _cohortId = first;
      });
      if (first != null) await _open(first);
    } catch (e) {
      if (mounted) {
        setState(() {
          _error = _words(e);
          _loading = false;
        });
      }
    }
  }

  Future<void> _open(int cohortId) async {
    setState(() {
      _cohortId = cohortId;
      _opening = true;
    });
    try {
      final detail = await widget.api.classBoard(cohortId);
      if (mounted) {
        setState(() {
          _detail = detail;
          _opening = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _error = _words(e);
          _opening = false;
        });
      }
    }
  }

  Future<void> _read(MaterialRow m) async {
    try {
      final file = await widget.api.materialFile(_cohortId!, m.id);
      if (mounted) await openDocument(context, file);
    } catch (e) {
      if (mounted) showError(context, e);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading && _seats.isEmpty) return const LoadingView();
    if (_error != null && _seats.isEmpty) {
      return ErrorRetry(message: _error!, onRetry: _load);
    }
    if (_seats.isEmpty) {
      return ListView(
        padding: const EdgeInsets.all(14),
        children: const [
          Card(
            child: EmptyHint(
              icon: Icons.school_outlined,
              title: 'You are not sitting in a class yet',
              body: 'A class opens when the office puts you on a cohort that is running. Your '
                  'applications are on My training.',
            ),
          ),
        ],
      );
    }

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(14),
        children: [
          if (_seats.length > 1)
            SectionCard(
              title: 'Your classes',
              subtitle: 'Tap one to read it.',
              child: Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  for (final s in _seats)
                    ChoiceChip(
                      label: Text(s.label),
                      selected: s.cohortId == _cohortId,
                      onSelected: (_) => _open(s.cohortId),
                    ),
                ],
              ),
            ),
          if (_opening && _detail == null)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 24),
              child: Center(child: CircularProgressIndicator()),
            )
          else if (_detail != null)
            ..._class(_detail!)
          else if (_error != null)
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Row(
                  children: [
                    const Expanded(
                        child: Text('That class could not be read.',
                            style: TextStyle(color: muted))),
                    FilledButton(onPressed: _load, child: const Text('Try again')),
                  ],
                ),
              ),
            ),
        ],
      ),
    );
  }

  List<Widget> _class(ClassBoard b) {
    final c = b.info;
    return [
      SectionCard(
        title: '${c.reference} — ${c.course}',
        subtitle: [c.period, c.dates].where((x) => x != null && x.isNotEmpty).join(' · '),
        trailing: Tag('${c.trackPct.toStringAsFixed(0)}% of track', color: green),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            LabeledValue(label: 'Where', value: c.place ?? '—'),
            LabeledValue(label: 'Trained by', value: c.trainer ?? 'the office'),
            LabeledValue(
                label: 'Your track',
                value: 'The track fills as each module sheet is covered and approved. It reaches '
                    '100% when every unit of this course is marked off.',
                strong: false),
          ],
        ),
      ),
      SectionCard(
        title: 'The week',
        subtitle: b.timetableOn == null
            ? 'No timetable for this class has been approved yet, so the week is not on record.'
            : 'Approved on ${b.timetableOn}.',
        child: b.timetableOn == null
            ? const SizedBox.shrink()
            : Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  for (final day in b.week) ...[
                    Padding(
                      padding: const EdgeInsets.only(top: 8, bottom: 2),
                      child: Text(day.label,
                          style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14, color: ink)),
                    ),
                    if (day.slots.isEmpty)
                      const Padding(
                        padding: EdgeInsets.only(left: 2, bottom: 4),
                        child: Text('No class this day.', style: TextStyle(color: muted, fontSize: 12.5)),
                      )
                    else
                      for (final slot in day.slots)
                        RecordRow(
                          title: '${slot.time} — ${slot.classLabel}',
                          subtitle: slot.unit,
                          meta: slot.room,
                        ),
                  ],
                ],
              ),
      ),
      SectionCard(
        title: 'Units of this course',
        subtitle: 'What has been covered, and the marks the trainer recorded against your name once '
            'the Registrar approved the sheet.',
        child: b.units.isEmpty
            ? const Text('This course has no units on the programme list yet.',
                style: TextStyle(color: muted, fontSize: 13))
            : Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  for (final u in b.units)
                    RecordRow(
                      title: '${u.position}. ${u.title}',
                      subtitle: u.coveredOn ?? 'Not covered yet',
                      trailing: u.marks.isEmpty
                          ? const Tag('No marks yet', color: muted)
                          : Text(u.marks.fold('', (a, m) => '$a ${m.score}'.trim()),
                              style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13.5, color: green)),
                      meta: u.marks.isEmpty
                          ? null
                          : u.marks.map((m) => '${m.label}: ${m.score}').join(' · '),
                    ),
                ],
              ),
      ),
      SectionCard(
        title: 'Handouts and material',
        subtitle: 'Given for this class by your trainer. Tap Open to read the file on this phone.',
        child: b.materials.isEmpty
            ? const Text('Nothing has been given out for this class yet.',
                style: TextStyle(color: muted, fontSize: 13))
            : Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  for (final m in b.materials) _material(m),
                ],
              ),
      ),
    ];
  }

  Widget _material(MaterialRow m) {
    return RecordRow(
      title: m.title,
      subtitle: m.summary,
      meta: m.note,
      trailing: m.hasFile
          ? TextButton.icon(
              onPressed: () => _read(m),
              icon: const Icon(Icons.open_in_new, size: 16),
              label: const Text('Open'),
            )
          : const Tag('No file', color: muted),
    );
  }
}

String _words(Object e) => e is ApiException ? e.message : '$e';
