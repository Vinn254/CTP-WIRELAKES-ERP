/// My profile for an office account — the staff file Human Resources holds, the papers sent in to be
/// checked and the asks to have a detail corrected. Nothing on this screen writes onto the record by
/// itself: what is typed here waits for the desk that owns the file, exactly as it does on the website.
library;

import 'package:flutter/material.dart';
import '../models/account_models.dart';
import '../services/api_client.dart';
import '../widgets/ui.dart';

class StaffProfileScreen extends StatefulWidget {
  final ApiClient api;
  const StaffProfileScreen({super.key, required this.api});

  @override
  State<StaffProfileScreen> createState() => _StaffProfileScreenState();
}

class _StaffProfileScreenState extends State<StaffProfileScreen> {
  StaffProfileBoard? _board;
  InlineFile? _photo;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final first = _board == null;
    try {
      final board = await widget.api.staffProfile();
      final photo = board.hasPhoto ? await widget.api.staffProfilePhoto() : const InlineFile.none();
      if (!mounted) return;
      setState(() { _board = board; _photo = photo; _error = null; });
    } catch (e) {
      if (!mounted) return;
      if (first) {
        setState(() { _error = '$e'; });
      } else {
        showError(context, e);
      }
    }
  }

  Future<void> _run(Future<String> Function() action) async {
    try {
      final message = await action();
      if (mounted) say(context, message);
      await _load();
    } catch (e) {
      if (mounted) showError(context, e);
    }
  }

  Future<void> _sendPaper({ProfilePaper? replacing}) async {
    final board = _board;
    if (board == null) return;

    final answers = await showFormSheet(
      context,
      title: replacing == null ? 'Send a document' : 'Send a better copy',
      subtitle: 'A photograph of the paper is enough. It goes to ${board.office} to be checked.',
      cta: 'Send to ${board.office}',
      fields: [
        FieldSpec.dropdown('kind', 'Which document', board.papers,
            initial: replacing?.kind),
        FieldSpec.photo('file', 'The file',
            required: true, photoHelper: 'JPG, PNG or PDF, up to 8 MB.'),
      ],
    );
    if (answers == null) return;
    if (answers['file'] == null) {
      if (mounted) say(context, 'Choose the document to send in.');
      return;
    }

    await _run(() => widget.api.staffSendDocument({
          'kind': answers['kind'],
          'file': answers['file'],
          if (replacing != null) 'document': replacing.id,
        }));
  }

  Future<void> _openPaper(ProfilePaper paper) async {
    try {
      final file = await widget.api.staffDocumentFile(paper.id);
      if (mounted) await openDocument(context, file);
    } catch (e) {
      if (mounted) showError(context, e);
    }
  }

  Future<void> _withdrawPaper(ProfilePaper paper) async {
    final sure = await confirm(
      context,
      title: 'Withdraw the ${paper.label}?',
      body: 'It comes off your file and you can send another copy at any time.',
      cta: 'Withdraw',
    );
    if (sure != true || !mounted) return;
    await _run(() => widget.api.staffWithdrawDocument(paper.id));
  }

  /// The details a person types themselves. Names go in three parts, the way the office files them.
  Future<void> _addDetails({String? focus}) async {
    final board = _board;
    if (board == null) return;

    final fields = <FieldSpec>[];
    for (final line in board.askable) {
      if (focus != null && line.field != focus) continue;

      if (line.kind == 'names') {
        fields.addAll([
          FieldSpec('name_first', '${line.label} — first name',
              required: false, initial: line.parts['first']),
          FieldSpec('name_middle', '${line.label} — middle name',
              required: false, initial: line.parts['middle']),
          FieldSpec('name_last', '${line.label} — last name',
              required: false, initial: line.parts['last']),
        ]);
        continue;
      }

      fields.add(switch (line.kind) {
        'choice' => FieldSpec.dropdown(line.field, line.label, line.choices,
            required: false, initial: line.raw),
        'date' => FieldSpec(line.field, line.label,
            kind: FieldKind.date, required: false, initial: line.raw),
        'number' => FieldSpec(line.field, line.label,
            kind: FieldKind.number, required: false, initial: line.raw),
        _ => FieldSpec(line.field, line.label, required: false, initial: line.raw),
      });
    }

    fields.add(FieldSpec('note', 'Why, if the office needs to know',
        kind: FieldKind.multiline, maxLines: 2, required: false));

    final answers = await showFormSheet(
      context,
      title: focus == null ? 'Add my details' : 'Correct a detail',
      subtitle: [
        if (board.need.isNotEmpty)
          'Your profile is not complete. Still to fill: ${_shortGap(board.need)}.',
        'Fill what ${board.office} is missing, or correct what is wrong. Leave anything you do not '
            'know blank.',
      ].join('\n'),
      cta: 'Send to ${board.office}',
      fields: fields,
    );
    if (answers == null) return;

    await _run(() => widget.api.staffAskChange(answers));
  }

  Future<void> _withdrawAsk(ProfileAsk ask) async {
    final sure = await confirm(
      context,
      title: 'Withdraw this ask so you can send another one?',
      body: 'Nothing on your record has changed yet, and withdrawing leaves it that way.',
      cta: 'Withdraw the ask',
    );
    if (sure != true || !mounted) return;
    await _run(() => widget.api.staffWithdrawChange(ask.id));
  }

  @override
  Widget build(BuildContext context) {
    final board = _board;

    if (board == null) {
      return _error != null
          ? ErrorRetry(message: _error!, onRetry: _load)
          : const LoadingView();
    }

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(14, 12, 14, 28),
        children: [
          _onFile(board),
          _documents(board),
          if (board.waiting != null) _waiting(board.waiting!, board.office),
          if (board.changes.isNotEmpty) _everyAsk(board),
        ],
      ),
    );
  }

  Widget _onFile(StaffProfileBoard board) {
    return SectionCard(
      title: 'On file',
      subtitle: '${board.kind} · answered by ${board.office}',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              SizedBox(
                width: 68,
                height: 68,
                child: _photo != null && _photo!.hasFile
                    ? ClipOval(child: Image.memory(base64Of(_photo!.dataUri!), fit: BoxFit.cover))
                    : CircleAvatar(
                        backgroundColor: green,
                        foregroundColor: Colors.white,
                        child: Text(board.initials.isEmpty ? '?' : board.initials,
                            style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w800)),
                      ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Text(board.person,
                    style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: ink)),
              ),
            ],
          ),
          const SizedBox(height: 8),
          const Text('This is what the office holds. Anything missing or wrong goes in through Add my details '
              'and is written onto your record only when Human Resources agrees.',
              style: TextStyle(color: muted, fontSize: 12.5, height: 1.4)),
          const SizedBox(height: 10),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              FilledButton.icon(
                onPressed: _addDetails,
                style: FilledButton.styleFrom(
                    backgroundColor: green, foregroundColor: Colors.white),
                icon: const Icon(Icons.edit_outlined, size: 18),
                label: Text(board.waiting == null ? 'Add my details' : 'Correct a detail'),
              ),
              OutlinedButton.icon(
                onPressed: _sendPaper,
                icon: const Icon(Icons.upload_file_outlined, size: 18),
                label: const Text('Send a document'),
              ),
            ],
          ),
          const Divider(height: 24, color: line),
          for (final line in board.lines)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 5),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(flex: 5, child: Text(line.label,
                      style: const TextStyle(color: muted, fontSize: 12.5))),
                  Expanded(flex: 6, child: Text(line.empty ? 'Not on file' : line.value,
                      style: TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w700,
                          color: line.empty ? muted : ink))),
                  SizedBox(
                    width: 84,
                    child: line.officeOnly
                        ? const Text('typed by office',
                            textAlign: TextAlign.right,
                            style: TextStyle(color: muted, fontSize: 11))
                        : TextButton(
                            onPressed: () => _addDetails(focus: line.field),
                            style: TextButton.styleFrom(
                                padding: EdgeInsets.zero,
                                minimumSize: const Size(0, 30),
                                tapTargetSize: MaterialTapTargetSize.shrinkWrap),
                            child: Text(line.empty ? 'Add' : 'Correct',
                                style: const TextStyle(fontSize: 12)),
                          ),
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }

  Widget _documents(StaffProfileBoard board) {
    return SectionCard(
      title: 'Documents',
      subtitle: board.missing.isEmpty
          ? 'Every paper the office asked for is on file.'
          : 'Still needed: ${board.missing.join(', ')}',
      child: board.documents.isEmpty
          ? const Text('Nothing has been sent in from this profile yet.',
              style: TextStyle(color: muted, fontSize: 12.5))
          : Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                for (final paper in board.documents)
                  Card(
                    margin: const EdgeInsets.only(bottom: 10),
                    child: Padding(
                      padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Expanded(
                                  child: Text(paper.label,
                                      style: const TextStyle(
                                          fontWeight: FontWeight.w800, fontSize: 13.5, color: ink))),
                              Tag(paper.statusLabel,
                                  color: paper.status == 'approved'
                                      ? green
                                      : paper.status == 'pending'
                                          ? warn
                                          : danger),
                            ],
                          ),
                          Padding(
                            padding: const EdgeInsets.only(top: 3),
                            child: Text([
                              paper.fileName,
                              paper.size,
                              paper.sentOn == null ? null : 'sent ${paper.sentOn}',
                            ].where((x) => x != null && x.isNotEmpty).join(' · '),
                                style: const TextStyle(color: muted, fontSize: 12)),
                          ),
                          if (paper.note != null)
                            Padding(
                              padding: const EdgeInsets.only(top: 4),
                              child: Text(paper.note!,
                                  style: const TextStyle(fontSize: 12.5, height: 1.4)),
                            ),
                          if (paper.answeredBy != null)
                            Padding(
                              padding: const EdgeInsets.only(top: 2),
                              child: Text(paper.answeredBy!,
                                  style: const TextStyle(color: muted, fontSize: 12)),
                            ),
                          Padding(
                            padding: const EdgeInsets.only(top: 8),
                            child: Wrap(
                              spacing: 8,
                              runSpacing: 8,
                              children: [
                                if (paper.openable)
                                  OutlinedButton.icon(
                                    onPressed: () => _openPaper(paper),
                                    icon: const Icon(Icons.description_outlined, size: 18),
                                    label: const Text('Open'),
                                  ),
                                if (paper.replaceable)
                                  TextButton.icon(
                                    onPressed: () => _sendPaper(replacing: paper),
                                    icon: const Icon(Icons.swap_horiz, size: 18),
                                    label: const Text('Send again'),
                                  ),
                                if (paper.waiting)
                                  TextButton.icon(
                                    onPressed: () => _withdrawPaper(paper),
                                    style: TextButton.styleFrom(foregroundColor: danger),
                                    icon: const Icon(Icons.close, size: 18),
                                    label: const Text('Withdraw'),
                                  ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
              ],
            ),
    );
  }

  Widget _waiting(ProfileAsk ask, String office) {
    return SectionCard(
      title: 'Waiting on $office',
      subtitle: 'Your last ask · ${ask.statusLabel}',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          for (final line in ask.lines)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 3),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(flex: 4, child: Text(line['label'] ?? '',
                      style: const TextStyle(color: muted, fontSize: 12.5))),
                  Expanded(flex: 3, child: Text(line['from'] ?? '—',
                      style: const TextStyle(fontSize: 12.5))),
                  Expanded(flex: 3, child: Text(line['to'] ?? '',
                      textAlign: TextAlign.right,
                      style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 12.5))),
                ],
              ),
            ),
          if (ask.note != null)
            Padding(
              padding: const EdgeInsets.only(top: 6),
              child: Text('You said: ${ask.note}',
                  style: const TextStyle(color: muted, fontSize: 12)),
            ),
          const SizedBox(height: 8),
          OutlinedButton(
            onPressed: () => _withdrawAsk(ask),
            child: const Text('Withdraw the ask'),
          ),
        ],
      ),
    );
  }

  Widget _everyAsk(StaffProfileBoard board) {
    return SectionCard(
      title: 'Every ask from this profile',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          for (final ask in board.changes)
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                          child: Text(ask.fields,
                              style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13))),
                      Tag(ask.statusLabel,
                          color: ask.statusLabel.toLowerCase() == 'approved'
                              ? green
                              : ask.pending
                                  ? warn
                                  : danger),
                    ],
                  ),
                  Padding(
                    padding: const EdgeInsets.only(top: 2),
                    child: Text([
                      ask.askedOn == null ? null : 'asked ${ask.askedOn}',
                      ask.answeredBy,
                    ].where((x) => x != null && x.isNotEmpty).join(' · '),
                        style: const TextStyle(color: muted, fontSize: 12)),
                  ),
                  if (ask.note != null)
                    Padding(
                      padding: const EdgeInsets.only(top: 2),
                      child: Text(ask.note!, style: const TextStyle(fontSize: 12.5)),
                    ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}

/// The gaps are met with a short list rather than the whole register, the way the web board puts them.
String _shortGap(List<String> need) {
  final more = need.length > 6 ? ', and ${need.length - 6} more' : '';
  return '${need.take(6).join(', ')}$more';
}
