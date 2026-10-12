@if (session('status'))
    <div class="flash-ok">{{ session('status') }}</div>
@endif
@if (session('error'))
    <div class="flash-error">{{ session('error') }}</div>
@endif
