@extends('layouts.app')
@section('title', 'FAQ')

@section('content')
<section class="section" style="max-width:820px;">
    <span class="tag">FAQ</span>
    <h1>Frequently asked questions</h1>
    @php $faqs = [
        ['q' => 'What is a CTP?', 'a' => 'A Community Technology Promoter — the on-the-ground agent who is simultaneously a trainee (learning a skill), a mobilizer (bringing people into the programme) and a community agent (identifying needs and registering communities).'],
        ['q' => 'Who can become a CTP?', 'a' => 'School leavers (training track) and junior graduates wanting practical experience (junior practical-check track). There is a one-time membership fee set by the programme.'],
        ['q' => 'What is the difference between a Fundi-Intern, an Attachment applicant, a Junior Fundi and a Senior Fundi?', 'a' => 'Fundi-Intern: graduated, no experience, seeking an internship. Attachment applicant: still in school and not yet graduated — only they can apply for industrial attachment. Junior Fundi: recent graduate who already has internship experience. Senior Fundi: long industry experience, fully eligible to lead projects and mentor.'],
        ['q' => 'Do I have to be a CTP first to become a Fundi?', 'a' => 'No. Fundi registration is a standalone path. Skilled workers apply directly with their certificates and a referee.'],
        ['q' => 'What documents do I need to register?', 'a' => 'CTP: national ID scan, passport photo, and any school certificate. Fundi: ID, business certificate (if any), trade certificates and up to 6 portfolio photos. Hirer: an ID or organisation document.'],
        ['q' => 'Is registering as a hirer free?', 'a' => 'Yes — hirer registration is free, but every organisation is verified by an admin to prevent fraud.'],
        ['q' => 'How is the membership fee paid?', 'a' => 'Via M-Pesa STK Push or bank transfer. The payment module is being switched on in the next phase; your application simply waits at "pending payment" until then.'],
        ['q' => 'How long does approval take?', 'a' => 'Directors and admins review applications with all documents. You will be notified by email and SMS once a decision is made.'],
    ]; @endphp
    @foreach ($faqs as $f)
        <div class="card" style="margin-bottom:14px;">
            <h3 style="margin-bottom:6px;">{{ $f['q'] }}</h3>
            <p style="margin:0;color:var(--muted);">{{ $f['a'] }}</p>
        </div>
    @endforeach
</section>
@endsection
