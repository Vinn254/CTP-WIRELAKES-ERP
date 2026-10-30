/// The member's own navigation — the same list, in the same order, with the same badges as
/// the portal sidebar in web/resources/views/layouts/member.blade.php. A CTP is never offered
/// an E-Fundi screen and vice versa, so the app cannot show a page the website hides.
library;

import 'package:flutter/material.dart';
import '../models/member_models.dart';

class MemberPage {
  final String key;
  final String label;
  final IconData icon;
  final int badge;

  /// One line telling the member what they do on that page — the sidebar has room for words,
  /// the app's tiles are the only place a first-time user learns what a page is for.
  final String hint;

  const MemberPage(this.key, this.label, this.icon, {this.badge = 0, this.hint = ''});
}

/// The pages this member can open, exactly as the sidebar builds them.
List<MemberPage> memberPages(Member member, Counts counts,
    {bool canTrain = false, bool invoiced = false}) {
  final isCtp = !member.isFundi;
  final pages = <MemberPage>[
    const MemberPage('overview', 'Overview', Icons.dashboard_outlined),
  ];

  if (isCtp) {
    pages.addAll(const [
      MemberPage('training', 'My training', Icons.school_outlined,
          hint: 'Cohorts you applied to, your attendance and the modules you still need.'),
      MemberPage('class', 'My class', Icons.class_outlined,
          hint: 'The week your trainer approved, your units and marks, and the handouts.'),
      MemberPage('library', 'My library', Icons.menu_book_outlined,
          hint: 'Reading the Registrar has filed for the course you sit in.'),
      MemberPage('mobilisation', 'My mobilisation', Icons.campaign_outlined,
          hint: 'The people you brought into the programme and what they went on to do.'),
    ]);
    pages.add(MemberPage('communities', 'My communities', Icons.holiday_village_outlined,
        badge: counts.communities,
        hint: 'Areas you mobilise, the needs you recorded and the beneficiaries you named.'));
  } else {
    pages.addAll(const [
      MemberPage('efundi', 'My E-Fundi record', Icons.engineering_outlined,
          hint: 'Your grade, the work you take, your skills and your paperwork.'),
      MemberPage('invitations', 'Invited to you', Icons.mark_email_unread_outlined,
          hint: 'Projects the matching desk put to you. Answer within 48 hours.'),
    ]);
    if (canTrain) {
      pages.add(const MemberPage('trainer', 'Training CTPs', Icons.workspace_premium_outlined,
          hint: 'The sites you train at, your cohort and the progress you record.'));
    }
  }

  // What the office has billed this member. A member who has never been invoiced is never shown a balance screen.
  if (invoiced) {
    pages.add(MemberPage('invoices', 'My invoices', Icons.mail_outline,
        badge: counts.invoices,
        hint: 'What the office has billed you, what it has received and the Paybill to settle it.'));
  }

  pages.add(const MemberPage('ewaste', 'E-Waste collection', Icons.recycling_outlined,
      hint: 'Register what you picked up; the office weighs it in and pays for it.'));
  pages.add(MemberPage(
      'work',
      isCtp ? 'My attachment work' : 'My work orders',
      Icons.assignment_outlined,
      badge: counts.workOrders,
      hint: 'Accept the order, log the days, ask for completion sign-off.'));
  pages.add(MemberPage('fixes', 'Items to put right', Icons.build_outlined,
      badge: counts.fixes, hint: 'Work an inspector failed. Prove the fix with a photo.'));

  if (!isCtp) {
    pages.add(MemberPage('tech', 'My repair jobs', Icons.handyman_outlined,
        badge: counts.repairJobs, hint: 'Devices sent to you for repair and what you returned.'));
    pages.add(const MemberPage('procurement', 'Purchase request', Icons.shopping_bag_outlined,
        hint: 'Ask for materials the programme should provide for your site. The office decides.'));
    pages.add(const MemberPage('earnings', 'My earnings', Icons.payments_outlined,
        hint: 'Every payout, what was paid out and what was deducted from it.'));
    // Leave is for the people who work in the programme — an E-Fundi holds a post, a CTP trainee does not.
    pages.add(const MemberPage('leave', 'My leave', Icons.schedule_outlined,
        hint: 'Your days left of each type, and an application for Human Resources to answer.'));
  }

  pages
    ..add(MemberPage('projects', 'My projects', Icons.work_outline,
        badge: counts.projects, hint: 'The projects published for your level and area.'))
    ..add(MemberPage('applications', 'My applications', Icons.list_alt_outlined,
        badge: counts.applications, hint: 'Where each application you sent stands.'))
    ..add(const MemberPage('profile', 'My profile', Icons.person_outline,
        hint: 'Your photo, contact details and the record the office holds.'));

  return pages;
}
