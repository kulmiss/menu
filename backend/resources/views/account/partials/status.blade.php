{{-- One-time messages after a form (session "status" good, "warning" bad). --}}
@if (session('status'))
    @include('account.partials.notice', ['notice' => ['ok' => true, 'text' => session('status')]])
@endif
@if (session('warning'))
    @include('account.partials.notice', ['notice' => ['ok' => false, 'text' => session('warning')]])
@endif
