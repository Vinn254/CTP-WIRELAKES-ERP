/// The shared vocabulary of the member app: one card per section of a portal page, one
/// bottom sheet per form, one photo picker, one picture viewer. Every screen is built from
/// these so the app reads as one product, the way the web portal does.
library;

import 'dart:convert';
import 'dart:typed_data';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import '../services/api_client.dart';

const green = Color(0xFF0E6B3A);
const ink = Color(0xFF10331F);
const muted = Color(0xFF355A40);
const soft = Color(0xFFE7F3E3);

// ---- state views -----------------------------------------------------------

class LoadingView extends StatelessWidget {
  const LoadingView({super.key});

  @override
  Widget build(BuildContext context) =>
      const Center(child: Padding(padding: EdgeInsets.all(32), child: CircularProgressIndicator()));
}

class EmptyHint extends StatelessWidget {
  final IconData icon;
  final String title;
  final String body;
  const EmptyHint({super.key, required this.icon, required this.title, required this.body});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        children: [
          Icon(icon, size: 44, color: Colors.grey),
          const SizedBox(height: 12),
          Text(title, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
          const SizedBox(height: 6),
          Text(body, textAlign: TextAlign.center, style: const TextStyle(color: muted)),
        ],
      ),
    );
  }
}

class ErrorRetry extends StatelessWidget {
  final String message;
  final Future<void> Function() onRetry;
  const ErrorRetry({super.key, required this.message, required this.onRetry});

  @override
  Widget build(BuildContext context) {
    return ListView(
      children: [
        const SizedBox(height: 70),
        EmptyHint(icon: Icons.wifi_off, title: 'Could not load', body: message),
        const SizedBox(height: 8),
        Center(child: FilledButton(onPressed: onRetry, child: const Text('Try again'))),
      ],
    );
  }
}

// ---- building blocks -------------------------------------------------------

class SectionCard extends StatelessWidget {
  final String title;
  final String? subtitle;
  final Widget child;
  final Widget? trailing;
  const SectionCard({
    super.key,
    required this.title,
    required this.child,
    this.subtitle,
    this.trailing,
  });

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 14),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 14, 16, 16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(title,
                          style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: ink)),
                      if (subtitle != null)
                        Padding(
                          padding: const EdgeInsets.only(top: 3),
                          child: Text(subtitle!,
                              style: const TextStyle(color: muted, fontSize: 12.5)),
                        ),
                    ],
                  ),
                ),
                ?trailing,
              ],
            ),
            const SizedBox(height: 10),
            child,
          ],
        ),
      ),
    );
  }
}

class LabeledValue extends StatelessWidget {
  final String label;
  final String value;
  final bool strong;
  const LabeledValue({super.key, required this.label, required this.value, this.strong = true});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3.5),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
              width: 128,
              child: Text(label, style: const TextStyle(color: muted, fontSize: 13))),
          Expanded(
              child: Text(value,
                  style: TextStyle(
                      fontWeight: strong ? FontWeight.w700 : FontWeight.w400, fontSize: 13.5))),
        ],
      ),
    );
  }
}

class Tag extends StatelessWidget {
  final String text;
  final Color color;
  const Tag(this.text, {super.key, this.color = muted});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: color.withValues(alpha: 0.4)),
      ),
      child: Text(text,
          style: TextStyle(color: color, fontSize: 11.5, fontWeight: FontWeight.w700)),
    );
  }
}

/// A one-line row of key/value pairs under a heading, for a table of what happened.
class RecordRow extends StatelessWidget {
  final String title;
  final String? subtitle;
  final String? meta;
  final Widget? trailing;
  final VoidCallback? onTap;
  const RecordRow({
    super.key,
    required this.title,
    this.subtitle,
    this.meta,
    this.trailing,
    this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 9),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                    child: Text(title,
                        style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14))),
                ?trailing,
              ],
            ),
            if (subtitle != null)
              Padding(
                padding: const EdgeInsets.only(top: 2),
                child: Text(subtitle!, style: const TextStyle(color: muted, fontSize: 13)),
              ),
            if (meta != null)
              Padding(
                padding: const EdgeInsets.only(top: 2),
                child: Text(meta!,
                    style: TextStyle(color: Colors.grey.shade600, fontSize: 12)),
              ),
            Divider(height: 18),
          ],
        ),
      ),
    );
  }
}

// ---- pictures --------------------------------------------------------------

/// The API returns a private picture as a data URI; this strips the prefix and decodes it.
Uint8List base64Of(String payload) => base64Decode(payload.split(',').last);

/// Picks a picture with the phone's camera or gallery and hands it back as base64, which
/// the API re-attaches as a real upload for the portal's own storePhoto() writer.
class PhotoPicker extends StatelessWidget {
  final String label;
  final String? helper;
  final PickedPhoto? photo;
  final ValueChanged<PickedPhoto?> onPicked;
  final bool fromCameraOnly;
  const PhotoPicker({
    super.key,
    required this.label,
    required this.photo,
    required this.onPicked,
    this.helper,
    this.fromCameraOnly = false,
  });

  Future<void> _pick(BuildContext context, ImageSource source) async {
    try {
      final file = await ImagePicker()
          .pickImage(source: source, maxWidth: 1600, imageQuality: 82);
      if (file == null) return;
      final bytes = await file.readAsBytes();
      final mime = file.mimeType ?? 'image/jpeg';
      onPicked(PickedPhoto(
        base64: 'data:$mime;base64,${base64Encode(bytes)}',
        name: file.name,
      ));
    } catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text('Could not open the camera: $e')));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13)),
        if (helper != null)
          Padding(
            padding: const EdgeInsets.only(top: 2, bottom: 6),
            child: Text(helper!, style: const TextStyle(color: muted, fontSize: 12)),
          ),
        const SizedBox(height: 6),
        Row(
          children: [
            Container(
              width: 74,
              height: 74,
              decoration: BoxDecoration(
                color: soft,
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: green.withValues(alpha: 0.25)),
              ),
              clipBehavior: Clip.antiAlias,
              child: photo == null
                  ? const Icon(Icons.add_a_photo_outlined, color: green)
                  : Image.memory(base64Of(photo!.base64), fit: BoxFit.cover),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  if (!fromCameraOnly)
                    OutlinedButton.icon(
                      onPressed: () => _pick(context, ImageSource.gallery),
                      icon: const Icon(Icons.photo_library_outlined, size: 18),
                      label: const Text('Gallery'),
                    ),
                  FilledButton.icon(
                    onPressed: () => _pick(context, ImageSource.camera),
                    icon: const Icon(Icons.photo_camera_outlined, size: 18),
                    label: const Text('Camera'),
                  ),
                  if (photo != null)
                    TextButton.icon(
                      onPressed: () => onPicked(null),
                      icon: const Icon(Icons.close, size: 18),
                      label: const Text('Remove'),
                    ),
                ],
              ),
            ),
          ],
        ),
      ],
    );
  }
}

/// Opens a private picture the API returned as a data URI — nothing is a public URL.
Future<void> showPicture(BuildContext context, InlineFile file) async {
  if (!file.hasFile) {
    ScaffoldMessenger.of(context)
        .showSnackBar(const SnackBar(content: Text('There is no picture on that record.')));
    return;
  }

  await showDialog<void>(
    context: context,
    builder: (_) => AlertDialog(
      title: Text(file.name ?? 'Picture'),
      content: SizedBox(
        width: MediaQuery.of(context).size.width * 0.9,
        child: Image.memory(base64Decode(file.dataUri!.split(',').last)),
      ),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context), child: const Text('Close')),
      ],
    ),
  );
}

// ---- the form sheet --------------------------------------------------------

enum FieldKind { text, number, integer, multiline, dropdown, chips, multichips, date, check, photo }

class FieldSpec {
  final String key;
  final String label;
  final FieldKind kind;
  final bool required;
  final String? helper;
  final String? initial;
  final int? maxLines;
  final Map<String, String> options;
  final List<String> values;
  final String? photoHelper;

  FieldSpec(
    this.key,
    this.label, {
    this.kind = FieldKind.text,
    this.required = true,
    this.helper,
    this.initial,
    this.maxLines,
    this.options = const {},
    this.values = const [],
    this.photoHelper,
  });

  FieldSpec.dropdown(this.key, this.label, Map<String, String> choices,
      {this.required = true, this.initial, this.helper})
      : kind = FieldKind.dropdown,
        options = choices,
        values = choices.keys.toList(),
        maxLines = null,
        photoHelper = null;

  FieldSpec.multichips(this.key, this.label, List<String> choices,
      {this.required = true, this.initial, this.helper})
      : kind = FieldKind.multichips,
        options = {for (final c in choices) c: c},
        values = choices,
        maxLines = null,
        photoHelper = null;

  FieldSpec.photo(this.key, this.label, {this.required = false, this.photoHelper})
      : kind = FieldKind.photo,
        helper = null,
        initial = null,
        maxLines = null,
        options = const {},
        values = const [];
}

/// One bottom sheet for every form in the app. Returns the answers as a body ready to post,
/// or null when the member backs out. Empty optional fields are left out by ApiClient.
Future<Map<String, dynamic>?> showFormSheet(
  BuildContext context, {
  required String title,
  required List<FieldSpec> fields,
  String? subtitle,
  String cta = 'Send',
}) {
  return showModalBottomSheet<Map<String, dynamic>>(
    context: context,
    isScrollControlled: true,
    backgroundColor: Colors.white,
    builder: (_) => _FormSheet(title: title, subtitle: subtitle, fields: fields, cta: cta),
  );
}

class _FormSheet extends StatefulWidget {
  final String title;
  final String? subtitle;
  final List<FieldSpec> fields;
  final String cta;
  const _FormSheet({
    required this.title,
    required this.fields,
    required this.cta,
    this.subtitle,
  });

  @override
  State<_FormSheet> createState() => _FormSheetState();
}

class _FormSheetState extends State<_FormSheet> {
  final _controllers = <String, TextEditingController>{};
  final _answers = <String, dynamic>{};
  final _formKey = GlobalKey<FormState>();

  @override
  void initState() {
    super.initState();
    for (final f in widget.fields) {
      if (f.kind == FieldKind.multichips) {
        _answers[f.key] = <String>[];
      } else if (f.kind == FieldKind.check) {
        _answers[f.key] = f.initial == '1';
      } else if (f.kind == FieldKind.date) {
        final day = DateTime.tryParse(f.initial ?? '') ?? DateTime.now();
        _answers[f.key] = _iso(day);
      } else if (f.kind != FieldKind.photo) {
        _controllers[f.key] = TextEditingController(text: f.initial ?? '');
        if (f.kind == FieldKind.text ||
            f.kind == FieldKind.multiline ||
            f.kind == FieldKind.number ||
            f.kind == FieldKind.integer) {
          _answers[f.key] = f.initial ?? '';
        }
      }
      if (f.kind == FieldKind.dropdown) _answers[f.key] = f.initial;
    }
  }

  @override
  void dispose() {
    for (final c in _controllers.values) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;
    Navigator.pop(context, _answers);
  }

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: Padding(
        padding: EdgeInsets.only(
            bottom: MediaQuery.of(context).viewInsets.bottom),
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(18, 14, 18, 22),
          child: Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(widget.title,
                    style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w800, color: ink)),
                if (widget.subtitle != null)
                  Padding(
                    padding: const EdgeInsets.only(top: 4, bottom: 8),
                    child: Text(widget.subtitle!, style: const TextStyle(color: muted, fontSize: 13)),
                  ),
                const SizedBox(height: 6),
                for (final f in widget.fields) _field(f),
                const SizedBox(height: 16),
                FilledButton(
                  onPressed: _submit,
                  child: Text(widget.cta),
                ),
                TextButton(
                  onPressed: () => Navigator.pop(context),
                  child: const Text('Cancel'),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _field(FieldSpec f) {
    // PhotoPicker writes its own caption, so the shared label row would repeat it.
    if (f.kind == FieldKind.photo) {
      return Padding(
        padding: const EdgeInsets.only(bottom: 12),
        child: _input(f),
      );
    }
    final input = Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(f.label + (f.required ? ' *' : ''),
              style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13)),
          if (f.helper != null)
            Padding(
              padding: const EdgeInsets.only(top: 2, bottom: 6),
              child: Text(f.helper!, style: const TextStyle(color: muted, fontSize: 12)),
            ),
          const SizedBox(height: 6),
          _input(f),
        ],
      ),
    );
    return input;
  }

  Widget _input(FieldSpec f) {
    switch (f.kind) {
      case FieldKind.multiline:
        return TextFormField(
          controller: _controllers[f.key],
          maxLines: f.maxLines ?? 4,
          onChanged: (v) => _answers[f.key] = v.trim(),
          decoration: _deco(f),
          validator: (v) => f.required && (v == null || v.trim().isEmpty) ? 'Required' : null,
        );
      case FieldKind.number:
      case FieldKind.integer:
        return TextFormField(
          controller: _controllers[f.key],
          keyboardType: TextInputType.numberWithOptions(decimal: f.kind == FieldKind.number),
          onChanged: (v) => _answers[f.key] = v.trim(),
          decoration: _deco(f),
          validator: (v) => f.required && (v == null || v.trim().isEmpty) ? 'Required' : null,
        );
      case FieldKind.date:
        return _DateField(
          label: f.label,
          initial: DateTime.tryParse(_answers[f.key] as String? ?? '') ?? DateTime.now(),
          onPicked: (d) => _answers[f.key] = _iso(d),
        );
      case FieldKind.dropdown:
        return _DropdownField(
          value: _answers[f.key] as String?,
          options: f.options,
          hint: 'Choose',
          required: f.required,
          onChanged: (v) => setState(() => _answers[f.key] = v),
        );
      case FieldKind.chips:
        return Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [
            for (final entry in f.options.entries)
              ChoiceChip(
                label: Text(entry.value),
                selected: _answers[f.key] == entry.key,
                onSelected: (_) => setState(() => _answers[f.key] = entry.key),
              ),
          ],
        );
      case FieldKind.multichips:
        final chosen = (_answers[f.key] as List).cast<String>();
        return Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [
            for (final value in f.values)
              FilterChip(
                label: Text(value),
                selected: chosen.contains(value),
                onSelected: (on) => setState(() {
                      if (on) {
                        chosen.add(value);
                      } else {
                        chosen.remove(value);
                      }
                    }),
              ),
          ],
        );
      case FieldKind.check:
        return CheckboxListTile(
          value: _answers[f.key] as bool,
          title: Text(f.helper ?? f.label, style: const TextStyle(fontSize: 13)),
          onChanged: (v) => setState(() => _answers[f.key] = v ?? false),
          controlAffinity: ListTileControlAffinity.leading,
          contentPadding: EdgeInsets.zero,
        );
      case FieldKind.photo:
        return PhotoPicker(
          label: f.label,
          helper: f.photoHelper,
          photo: _answers[f.key] as PickedPhoto?,
          onPicked: (p) => setState(() => _answers[f.key] = p),
        );
      default:
        return TextFormField(
          controller: _controllers[f.key],
          onChanged: (v) => _answers[f.key] = v.trim(),
          decoration: _deco(f),
          validator: (v) => f.required && (v == null || v.trim().isEmpty) ? 'Required' : null,
        );
    }
  }

  InputDecoration _deco(FieldSpec f) => InputDecoration(
        hintText: f.helper,
        border: const OutlineInputBorder(),
        isDense: true,
      );
}

class _DateField extends StatefulWidget {
  final String label;
  final DateTime initial;
  final ValueChanged<DateTime> onPicked;
  const _DateField({required this.label, required this.initial, required this.onPicked});

  @override
  State<_DateField> createState() => _DateFieldState();
}

class _DateFieldState extends State<_DateField> {
  late DateTime _day = widget.initial;

  @override
  Widget build(BuildContext context) {
    return OutlinedButton.icon(
      onPressed: () async {
        final picked = await showDatePicker(
          context: context,
          initialDate: _day,
          firstDate: DateTime(2015),
          lastDate: DateTime.now(),
        );
        if (picked == null) return;
        setState(() => _day = picked);
        widget.onPicked(picked);
      },
      icon: const Icon(Icons.calendar_today_outlined, size: 18),
      label: Text(
          '${_day.day.toString().padLeft(2, '0')} ${_months[_day.month - 1]} ${_day.year}'),
    );
  }
}

const _months = [
  'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
  'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec',
];

class _DropdownField extends StatelessWidget {
  final String? value;
  final Map<String, String> options;
  final String hint;
  final bool required;
  final ValueChanged<String?> onChanged;
  const _DropdownField({
    required this.value,
    required this.options,
    required this.hint,
    required this.onChanged,
    this.required = true,
  });

  @override
  Widget build(BuildContext context) {
    return DropdownButtonFormField<String>(
      initialValue: options.containsKey(value) ? value : null,
      hint: Text(hint),
      isExpanded: true,
      items: [
        for (final entry in options.entries)
          DropdownMenuItem(value: entry.key, child: Text(entry.value, overflow: TextOverflow.ellipsis)),
      ],
      onChanged: onChanged,
      validator: (v) => required && v == null ? 'Choose one' : null,
    );
  }
}

/// The API takes dates as yyyy-MM-dd, which is what Laravel's date validators compare.
String _iso(DateTime d) =>
    '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

/// A short toast with the server's own sentence — the portal words every action.
void say(BuildContext context, String message) {
  ScaffoldMessenger.of(context).showSnackBar(
    SnackBar(content: Text(message), backgroundColor: ink, duration: const Duration(seconds: 4)),
  );
}

/// Every screen shows the server's refusal message unchanged, so an app answer is the
/// same answer the website would have given.
void showError(BuildContext context, Object error) {
  final message = error is ApiException ? error.message : '$error';
  ScaffoldMessenger.of(context)
      .showSnackBar(SnackBar(content: Text(message), backgroundColor: Colors.red.shade800));
}
