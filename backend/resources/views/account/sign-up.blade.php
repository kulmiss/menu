@extends('account.layout', ['indexable' => true])

@php $free = $freePlan ?? true; @endphp

@section('title', __($free ? 'account.signup.title' : 'account.signup.title_paid'))

@section('header')
    @include('account.partials.signin-link')
@endsection

@section('form')
    <h1>{{ __($free ? 'account.signup.title' : 'account.signup.title_paid') }}</h1>
    <p class="intro">{{ __($free ? 'account.signup.intro' : 'account.signup.intro_paid') }}</p>
    @include('account.partials.notice')
    <div class="stack">
        @if ($google)
            @include('account.partials.google')
        @endif
        <form method="POST" action="/signup" class="stack flush" novalidate>
            @csrf
            @include('account.partials.field', ['name' => 'name', 'label' => __('account.signup.name'), 'autocomplete' => 'name', 'maxlength' => 120, 'autofocus' => ! $errors->any()])
            @include('account.partials.field', ['name' => 'restaurant_name', 'label' => __('account.signup.restaurant'), 'autocomplete' => 'organization', 'maxlength' => 120, 'hint' => __('account.signup.restaurant_hint')])
            @include('account.partials.field', ['name' => 'email', 'type' => 'email', 'label' => __('account.signup.email'), 'autocomplete' => 'email', 'inputmode' => 'email', 'placeholder' => __('account.signin.email_placeholder')])
            <div class="field">
                <label for="password">{{ __('account.signup.password') }}</label>
                @include('account.partials.password', ['name' => 'password', 'autocomplete' => 'new-password', 'describedBy' => 'password-hint', 'strength' => true])
                @unless ($errors->has('password'))
                    <p class="hint" id="password-hint">{{ __('account.signup.password_hint') }}</p>
                @endunless
            </div>
            {{-- A field people never see: bots that fill it are ignored. --}}
            <div class="trap" aria-hidden="true">
                <label for="website">{{ __('account.signup.website') }}</label>
                <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
            </div>
            @php $termsError = $errors->first('terms'); @endphp
            <div class="field">
                <label class="check">
                    <input type="checkbox" name="terms" value="1" required @checked(old('terms')) @if ($termsError) aria-invalid="true" aria-describedby="terms-error" @endif>
                    <span>
                        @if ($terms && $privacy)
                            {!! __('account.signup.terms', ['terms' => '<a class="link" href="/terms" target="_blank" rel="noopener">'.e(__('account.signup.terms_link')).'</a>', 'privacy' => '<a class="link" href="/privacy" target="_blank" rel="noopener">'.e(__('account.signup.privacy_link')).'</a>']) !!}
                        @else
                            {{ __('account.signup.terms_plain') }}
                        @endif
                    </span>
                </label>
                @if ($termsError)
                    <p class="error" id="terms-error" role="alert">{{ $termsError }}</p>
                @endif
            </div>
            @if ($turnstile)
                <div class="cf-turnstile" data-sitekey="{{ $turnstile }}"></div>
                @if ($errors->has('turnstile'))
                    <p class="error" role="alert">{{ $errors->first('turnstile') }}</p>
                @endif
            @endif
            <button class="btn btn-primary btn-block" type="submit">{{ __($free ? 'account.signup.submit' : 'account.signup.submit_paid') }}</button>
        </form>
    </div>
@endsection

@section('after')
    <p class="after">{{ __('account.have_account') }} <a href="/signin">{{ __('account.sign_in') }}</a></p>
@endsection

@section('scripts')
    @if ($turnstile)
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    @endif
@endsection
