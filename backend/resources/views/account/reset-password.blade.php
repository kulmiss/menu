@extends('account.layout')

@section('title', __($welcome ? 'account.reset.welcome_title' : 'account.reset.title'))

@section('form')
    <h1>{{ __($welcome ? 'account.reset.welcome_title' : 'account.reset.title') }}</h1>
    @if (! $token || ! $email)
        <p class="intro">{{ __('account.reset.invalid') }}</p>
        <div class="stack">
            <a class="btn btn-primary btn-block" href="/forgot-password">{{ __('account.reset.new_link') }}</a>
        </div>
    @else
        <p class="intro">{{ $welcome ? __('account.reset.welcome_intro') : __('account.reset.intro', ['email' => $email]) }}</p>
        @if ($errors->has('email'))
            @include('account.partials.notice', ['notice' => ['ok' => false, 'text' => $errors->first('email')]])
            <p class="after"><a href="/forgot-password">{{ __('account.reset.new_link') }}</a></p>
        @endif
        <form method="POST" action="/reset-password" class="stack" novalidate>
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <input type="hidden" name="email" value="{{ $email }}">
            @if ($welcome)
                <input type="hidden" name="welcome" value="1">
            @endif
            {{-- For password managers: which account this password is for. --}}
            <input type="text" name="username" value="{{ $email }}" autocomplete="username" hidden>
            <div class="field">
                <label for="password">{{ __('account.reset.password') }}</label>
                @include('account.partials.password', ['name' => 'password', 'autocomplete' => 'new-password', 'describedBy' => 'password-hint', 'strength' => true])
                @unless ($errors->has('password'))
                    <p class="hint" id="password-hint">{{ __('account.signup.password_hint') }}</p>
                @endunless
            </div>
            <button class="btn btn-primary btn-block" type="submit">{{ __('account.reset.submit') }}</button>
        </form>
    @endif
@endsection
