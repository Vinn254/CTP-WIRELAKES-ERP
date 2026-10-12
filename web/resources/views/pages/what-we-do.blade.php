@extends('layouts.app')
@section('title', 'What We Do')

@section('content')
<section class="section">
    <span class="tag">What we do</span>
    <h1>Six areas of work, one connected ecosystem</h1>
    <p class="sub">Train and certify → deploy to projects → build and maintain → recycle → start businesses → employ the community. Then the loop repeats.</p>

    <div class="grid-3">
        @php $cards = [
            ['icon' => '🎓', 'title' => 'Training & Certification', 'desc' => 'Courses and cohorts at community sites and partner schools — theory, practical work, assessment and a certificate with a verifiable ID.', 'link' => 'register.ctp', 'cta' => 'Join as a CTP trainee'],
            ['icon' => '🛠️', 'title' => 'E-Fundi Marketplace', 'desc' => 'Verified skilled workers — electricians, masons, ICT and solar technicians — matched to real projects by specialisation and county.', 'link' => 'register.fundi', 'cta' => 'Register as a Fundi'],
            ['icon' => '🏗️', 'title' => 'Projects & Construction', 'desc' => 'Hirers post projects — from computer labs to roofs and fences. We assess, quote, assign, execute and sign off.', 'link' => 'register.hirer', 'cta' => 'Post a Project'],
            ['icon' => '♻️', 'title' => 'E-Waste Management', 'desc' => 'Collection, weighing, sorting, testing — then repair, refurbish, recycle or dispose safely. Refurbished gear returns to schools and communities.', 'link' => 'contact', 'cta' => 'Partner with us'],
            ['icon' => '💼', 'title' => 'Entrepreneurship', 'desc' => 'Fundis and CTPs get business support, toolkits and first contracts so a skill becomes an enterprise that hires locally.', 'link' => 'donate', 'cta' => 'Fund a toolkit'],
            ['icon' => '🌱', 'title' => 'Green Economy & Technology', 'desc' => 'Solar, ICT, networking, robotics, programming and AI services delivered by our own trained workforce.', 'link' => 'about', 'cta' => 'Learn more'],
        ]; @endphp
        @foreach ($cards as $c)
            <div class="card link-card">
                <div class="icon">{{ $c['icon'] }}</div>
                <h3>{{ $c['title'] }}</h3>
                <p>{{ $c['desc'] }}</p>
                <a href="{{ route($c['link']) }}">{{ $c['cta'] }} →</a>
            </div>
        @endforeach
    </div>
</section>
@endsection
