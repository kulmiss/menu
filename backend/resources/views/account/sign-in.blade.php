@extends('account.layout')

@section('title', $plain ? __('account.super.title') : __('account.signin.title'))

@section('header')
    @if ($signupOpen)
        <span class="hint">{{ __('account.no_account') }}</span>
        <a class="btn btn-ghost" href="/auth/sign-up">{{ __('account.sign_up_link') }}</a>
    @endif
@endsection

@section('form')
    <h1>{{ $plain ? __('account.super.title') : __('account.signin.title') }}</h1>
    <p class="intro">{{ $plain ? __('account.super.intro') : __('account.signin.intro') }}</p>
    @include('account.partials.notice')
    <div class="stack">
        @if ($google && ! $plain)
            @include('account.partials.google')
        @endif
        <form method="POST" action="{{ $action }}" class="stack" style="margin-top:0" novalidate>
            @csrf
            @php $emailError = $errors->first('email'); @endphp
            <div class="field">
                <label for="email">{{ __('account.signin.email') }}</label>
                <input id="email" name="email" type="email" autocomplete="username" inputmode="email" required autofocus
                    value="{{ old('email') }}" placeholder="{{ __('account.signin.email_placeholder') }}"
                    @if ($emailError) aria-invalid="true" aria-describedby="email-error" @endif>
                @if ($emailError)
                    <p class="error" id="email-error" role="alert">{{ $emailError }}</p>
                @endif
            </div>
            <div class="field">
                <div class="label-row">
                    <label for="password">{{ __('account.signin.password') }}</label>
                    <a href="/auth/forgot-password">{{ __('account.signin.forgot') }}</a>
                </div>
                @include('account.partials.password', ['name' => 'password', 'autocomplete' => 'current-password'])
            </div>
            <label class="check">
                <input type="checkbox" name="remember" value="1" @checked(old('remember', true))>
                <span>{{ __('account.signin.remember') }}</span>
            </label>
            <button class="btn btn-primary btn-block" type="submit">{{ __('account.signin.submit') }}</button>
        </form>
    </div>
@endsection

@section('after')
    @if (! $plain && $signupOpen)
        <p class="after">{{ __('account.no_account') }} <a href="/auth/sign-up">{{ __('account.sign_up_link') }}</a></p>
    @endif
@endsection
