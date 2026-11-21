@extends('account.layout')

@php $free = $freePlan ?? true; @endphp

@section('title', __('account.google_finish.title'))

@section('form')
    <h1>{{ __('account.google_finish.title') }}</h1>
    <p class="intro">{{ __('account.google_finish.intro', ['email' => $pending['email']]) }}</p>
    <form method="POST" action="/signup/google" class="stack" novalidate>
        @csrf
        @include('account.partials.field', ['name' => 'restaurant_name', 'label' => __('account.google_finish.restaurant'), 'autocomplete' => 'organization', 'maxlength' => 120, 'autofocus' => true, 'hint' => __('account.signup.restaurant_hint')])
        @php $termsError = $errors->first('terms'); @endphp
        <div class="field">
            <label class="check">
                <input type="checkbox" name="terms" value="1" required @checked(old('terms')) @if ($termsError) aria-invalid="true" aria-describedby="terms-error" @endif>
                <span>{!! __('account.signup.terms', ['terms' => '<a class="link" href="/terms" target="_blank" rel="noopener">'.e(__('account.signup.terms_link')).'</a>', 'privacy' => '<a class="link" href="/privacy" target="_blank" rel="noopener">'.e(__('account.signup.privacy_link')).'</a>']) !!}</span>
            </label>
            @if ($termsError)
                <p class="error" id="terms-error" role="alert">{{ $termsError }}</p>
            @endif
        </div>
        <button class="btn btn-primary btn-block" type="submit">{{ __($free ? 'account.google_finish.submit' : 'account.google_finish.submit_paid') }}</button>
    </form>
@endsection
