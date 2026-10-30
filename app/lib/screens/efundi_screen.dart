import 'package:flutter/material.dart';
import '../models/member_models.dart';
import '../models/portal_models.dart';
import '../services/api_client.dart';
import '../widgets/ui.dart';

/// My E-Fundi record — the one the programme office, the project matching and the training
/// cohorts all read. The fundi keeps availability and skills here; the office holds the rest.
class EfundiScreen extends StatefulWidget {
  final ApiClient api;
  final Board board;
  const EfundiScreen({super.key, required this.api, required this.board});

  @override
  State<EfundiScreen> createState() => _EfundiScreenState();
}

class _EfundiScreenState extends State<EfundiScreen> {
  EfundiRecord? _rec;
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
      final r = await widget.api.efundi();
      if (mounted) {
        setState(() {
          _rec = r;
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

  Future<void> _updateAvailability(EfundiRecord r) async {
    final body = await showFormSheet(
      context,
      title: 'Availability',
      subtitle: 'Say it here as it changes. Matching only looks at fundis marked Available for work.',
      fields: [
        FieldSpec.dropdown('availability', 'I am', r.availabilityOptions,
            initial: r.availability),
        FieldSpec('note', 'Note for the office',
            required: false,
            helper: 'e.g. Free from 5 December — the Kisumu job ends on the 30th'),
      ],
      cta: 'Save availability',
    );
    if (body == null) return;
    try {
      final msg = await widget.api.saveAvailability(
          body['availability'] as String, note: body['note'] as String?);
      if (mounted) say(context, msg);
      _load();
    } catch (e) {
      if (mounted) showError(context, e);
    }
  }

  Future<void> _updateSkills(EfundiRecord r) async {
    final body = await showFormSheet(
      context,
      title: 'Skills and counties you cover',
      subtitle: 'Only the trades the programme runs are on this list. Pick at least one — a job is '
          'matched to a fundi by trade first, then by the counties you said you will travel to.',
      fields: [
        FieldSpec.multichips(
            'specialisations', 'What you can do', r.specialisationOptions.values.toList()),
        FieldSpec.multichips('counties', 'Counties you will work in', r.countyOptions,
            required: false, helper: 'Leave empty for your home county only.'),
      ],
      cta: 'Save skills',
    );
    if (body == null) return;
    final specs = (body['specialisations'] as List).cast<String>();
    final counties = (body['counties'] as List).cast<String>();
    if (specs.isEmpty) {
      if (mounted) say(context, 'Pick at least one trade — a job is matched by trade first.');
      return;
    }
    try {
      final msg = await widget.api.saveSkills(specs, counties);
      if (mounted) say(context, msg);
      _load();
    } catch (e) {
      if (mounted) showError(context, e);
    }
  }

  Future<void> _openFile(RecordFile doc) async {
    try {
      final file = await widget.api.efundiFile(doc.slot);
      if (!mounted) return;
      if (file.hasFile) {
        // A picture opens on screen; a certificate or portfolio goes to the app that reads it.
        await openDocument(context, file);
      } else {
        // The portal keeps the document list; a slot with nothing stored is said so, not hidden.
        say(context, '${doc.label} is not on file. Ask the programme office to add it — '
            'it is recorded on your E-Fundi file.');
      }
    } catch (e) {
      if (mounted) showError(context, e);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading && _rec == null) return const LoadingView();
    if (_error != null && _rec == null) {
      return ErrorRetry(message: _error!, onRetry: _load);
    }
    final r = _rec!;

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(14),
        children: [
          SectionCard(
            title: r.level,
            subtitle: 'This is your E-Fundi record — the one the programme office, the project '
                'matching and the training cohorts all read. Keep your availability and skills '
                'current here; your grade, membership and documents are held by the office and '
                'corrected by them.',
            trailing: Tag(r.statusLabel, color: r.status == 'active' ? green : warn),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (r.place != null && r.place!.isNotEmpty)
                  LabeledValue(label: 'Where you work', value: r.place!),
                if (widget.board.member.code != null && widget.board.member.code!.isNotEmpty)
                  LabeledValue(label: 'E-Fundi ID', value: widget.board.member.code!),
              ],
            ),
          ),
          SectionCard(
            title: 'What your grade carries',
            subtitle: 'Your grade decides which of these your own portal opens. The programme office '
                'moves a fundi up when they are ready to carry more.',
            trailing: Tag(r.level),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                for (final c in r.capabilities)
                  RecordRow(title: c.title, subtitle: c.blurb),
                RecordRow(
                  title: 'Not open to your grade',
                  subtitle: r.locked.isEmpty
                      ? 'Nothing — a senior fundi carries the whole list.'
                      : r.locked.map((c) => c.title).join(' · '),
                ),
              ],
            ),
          ),
          SectionCard(
            title: 'Availability',
            subtitle: 'A fundi marked Available for work is the one the office and the matching see '
                'for a job; On a job and On leave take you out of it until you say otherwise.',
            trailing: Tag(r.availabilityLabel, color: r.availability == 'available' ? green : muted),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                LabeledValue(label: 'I am', value: r.availabilityLabel),
                if (r.availabilityNote != null && r.availabilityNote!.isNotEmpty)
                  LabeledValue(label: 'Note for the office', value: r.availabilityNote!, strong: false),
                Padding(
                  padding: const EdgeInsets.only(top: 8),
                  child: FilledButton(
                    onPressed: () => _updateAvailability(r),
                    child: const Text('Update availability'),
                  ),
                ),
              ],
            ),
          ),
          SectionCard(
            title: 'Skills and counties you cover',
            subtitle: '${r.specialisations.length} '
                'skill${r.specialisations.length == 1 ? '' : 's'} · '
                '${r.serviceCounties.length} count${r.serviceCounties.length == 1 ? 'y' : 'ies'}',
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Wrap(
                  spacing: 6,
                  runSpacing: 6,
                  children: [for (final s in r.specialisations) Tag(s)],
                ),
                if (r.serviceCounties.isNotEmpty) ...[
                  const SizedBox(height: 8),
                  Wrap(
                    spacing: 6,
                    runSpacing: 6,
                    children: [for (final c in r.serviceCounties) Tag(c, color: ink)],
                  ),
                ],
                Padding(
                  padding: const EdgeInsets.only(top: 10),
                  child: FilledButton(
                    onPressed: () => _updateSkills(r),
                    child: const Text('Update skills'),
                  ),
                ),
              ],
            ),
          ),
          if (r.canTrain)
            SectionCard(
              title: 'Sites you train CTPs at',
              subtitle: 'The office approves a trainer site by site. Applying for another site, and '
                  'the marks you put in, are on Training CTPs.',
              trailing: Tag('${r.sites.length}'),
              child: r.sites.isEmpty
                  ? const Text('You are not registered to train anywhere yet. Put a site forward on '
                      'Training CTPs — the office approves a trainer site by site before a cohort '
                      'is handed over.',
                      style: TextStyle(color: muted, fontSize: 13))
                  : Column(
                      children: [
                        for (final s in r.sites)
                          RecordRow(
                            title: s.venue ?? 'A site',
                            subtitle: [
                              s.county,
                              s.trainings.isEmpty ? 'no trainings listed' : s.trainings.join(' · ')
                            ].where((x) => x != null && x.isNotEmpty).join(' · '),
                            trailing: Tag(s.statusLabel,
                                color: s.status == 'approved'
                                    ? green
                                    : s.status == 'applied'
                                        ? warn
                                        : muted),
                          ),
                      ],
                    ),
            ),
          SectionCard(
            title: 'Documents on file (${r.documents.length})',
            subtitle: 'A grade, a document or a standing is the office’s decision — ask them and '
                'they edit this record. Nothing is deleted.',
            child: r.documents.isEmpty
                ? const Text('Nothing was uploaded with your application. Ask the programme office '
                    'to add a trade certificate or portfolio — they are recorded on your E-Fundi file.',
                    style: TextStyle(color: muted, fontSize: 13))
                : Column(
                    children: [
                      for (final d in r.documents)
                        RecordRow(
                          title: d.label,
                          meta: 'Tap to open',
                          trailing: const Icon(Icons.picture_as_pdf_outlined, size: 18, color: muted),
                          onTap: () => _openFile(d),
                        ),
                    ],
                  ),
          ),
        ],
      ),
    );
  }
}
