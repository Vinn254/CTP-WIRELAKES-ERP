{{-- The one form an area page carries: asking the programme to do a piece of its work for you. Nothing else --}}
{{-- — joining and working with us are sent to their own pages below, where an account is what is actually needed. --}}
@php
    use App\Support\ProgrammeAreas;
    use App\Models\ProgrammeApplication;

    $page = ProgrammeAreas::page($area);
    $groups = $page['service_groups'];
    $join = $page['join'];
@endphp

<section class="section" id="apply">
    <div class="split">
        <div class="split-text">
            <span class="tag">Ask us to do it for you</span>
            <h2>{{ $page['apply_title'] }}</h2>
            <p class="sub">{{ $page['apply_note'] }}</p>
            <p class="sub">You do not need an account and you do not attach any document.</p>
        </div>

        <div class="card apply-card">
            @include('partials.errors')

            <form method="POST" action="{{ url($area.'/apply') }}">
                @csrf

                <div class="field">
                    <label>Which service do you need?</label>
                    <select name="category">
                        <option value="" @selected(old('category') === null || old('category') === '')>
                            None of these — I will explain below</option>
                        @foreach ($groups as $groupName => $options)
                            <optgroup label="{{ $groupName }}">
                                @foreach ($options as $option)
                                    <option value="{{ $option }}" @selected(old('category') === $option)>{{ $option }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    <div class="hint">Everything the programme offers is here, whatever page you came from.</div>
                </div>

                <div class="field">
                    <label>What you need doing <span class="req">*</span></label>
                    <textarea name="details" rows="4" maxlength="2000" required
                              placeholder="For example: a 3 kW solar system for a two-bedroom house in Kisumu, we would like it before December."
                    >{{ old('details') }}</textarea>
                </div>

                <div class="form-grid">
                    <div class="field">
                        <label>Your full name <span class="req">*</span></label>
                        <input type="text" name="name" value="{{ old('name') }}" maxlength="150" required>
                    </div>
                    <div class="field">
                        <label>Email <span class="req">*</span></label>
                        <input type="email" name="email" value="{{ old('email') }}" maxlength="150" required>
                    </div>
                    <div class="field">
                        <label>Phone number <span class="req">*</span></label>
                        <input type="text" name="phone" value="{{ old('phone') }}" maxlength="30"
                               placeholder="07XX XXX XXX" required>
                    </div>
                    <div class="field">
                        <label>WhatsApp number, if different</label>
                        <input type="text" name="whatsapp" value="{{ old('whatsapp') }}" maxlength="30"
                               placeholder="Leave blank if it is the same number">
                    </div>
                    <div class="field">
                        <label>Town</label>
                        <input type="text" name="town" value="{{ old('town') }}" maxlength="90"
                               placeholder="Where the work is">
                    </div>
                </div>

                <button class="btn btn-lime btn-lg" type="submit">Send this request</button>
                <p class="hint">You get an email the moment you send it, and the office calls you back within
                    <b>{{ ProgrammeApplication::TURNAROUND }}</b>.</p>
            </form>
        </div>
    </div>
</section>

<section class="section">
    <div class="join-panel">
        <h2>{{ $page['join_title'] }}</h2>
        <p class="sub">{{ $page['join_note'] }}</p>
        <div class="btn-row">
            @foreach ($join as $j)
                <a class="btn {{ $j['class'] ?? 'btn-outline' }}" href="{{ safe_href($j['url']) }}">{{ $j['label'] }}</a>
            @endforeach
        </div>
    </div>
</section>
