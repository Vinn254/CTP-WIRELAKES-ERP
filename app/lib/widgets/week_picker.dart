/// The month, year and week-of-the-month row the ruled papers open with — the same three picks the
/// web time log and the attendance register carry, so a trainer looks for a week the same way on both.
library;

import 'package:flutter/material.dart';
import '../models/teaching_models.dart';
import 'ui.dart';

const monthNames = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December',
];

class WeekPicker extends StatelessWidget {
  /// `YYYY-MM` of the month the paper is open on.
  final String month;
  final int weekNo;
  final List<int> years;

  /// The weeks of the month the server answers with, so the dates beside each label are its own.
  final List<WeekOption> weeks;
  final ValueChanged<String> onMonth;
  final ValueChanged<int> onWeek;

  const WeekPicker({
    super.key,
    required this.month,
    required this.weekNo,
    required this.weeks,
    required this.onMonth,
    required this.onWeek,
    this.years = const [],
  });

  @override
  Widget build(BuildContext context) {
    final parts = month.split('-');
    final year = int.tryParse(parts.first) ?? DateTime.now().year;
    final number = parts.length > 1 ? int.tryParse(parts.last) ?? DateTime.now().month : 1;
    final everyYear = years.isEmpty ? [year] : years;

    return Wrap(
      spacing: 10,
      runSpacing: 10,
      crossAxisAlignment: WrapCrossAlignment.end,
      children: [
        _pick('Month', {for (var m = 1; m <= 12; m++) m.toString(): monthNames[m - 1]},
            '$number', (v) {
          if (v == null) return;
          onMonth('$year-${int.parse(v).toString().padLeft(2, '0')}');
        }),
        _pick('Year', {for (final y in everyYear) '$y': '$y'}, '$year', (v) {
          if (v == null) return;
          onMonth('$v-${number.toString().padLeft(2, '0')}');
        }),
        _pick('Week of the month', {
          for (var i = 0; i < weeks.length; i++) '${i + 1}': '${weeks[i].label} (${weeks[i].dates})'
        }, '$weekNo', (v) {
          if (v == null) return;
          onWeek(int.parse(v));
        }),
      ],
    );
  }

  Widget _pick(String label, Map<String, String> options, String value,
      ValueChanged<String?> onChanged) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label, style: const TextStyle(color: muted, fontSize: 12)),
        const SizedBox(height: 4),
        ConstrainedBox(
          constraints: const BoxConstraints(minWidth: 150),
          child: DropdownButtonHideUnderline(
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 10),
              decoration: BoxDecoration(
                  border: Border.all(color: line, width: 1.5),
                  borderRadius: BorderRadius.circular(10)),
              child: DropdownButton<String>(
                value: options.containsKey(value) ? value : options.keys.first,
                isExpanded: true,
                items: [
                  for (final entry in options.entries)
                    DropdownMenuItem(
                        value: entry.key,
                        child: Text(entry.value,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(fontSize: 13))),
                ],
                onChanged: onChanged,
              ),
            ),
          ),
        ),
      ],
    );
  }
}

/// `02 Nov 2026` from the ISO day the API sends — the way every ruled paper on the site writes a date.
String prettyDay(String? iso) {
  if (iso == null || iso.length < 10) return iso ?? '—';
  final day = DateTime.tryParse(iso);
  if (day == null) return iso;
  return '${day.day.toString().padLeft(2, '0')} ${monthNames[day.month - 1].substring(0, 3)} ${day.year}';
}
