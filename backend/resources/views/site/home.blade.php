@extends('site.layout')

@section('title', $platform)

@section('content')
    <h1>{{ __('site.home_title', ['name' => $platform]) }}</h1>
    <p class="muted">{{ __('site.home_text') }}</p>
    <div class="actions">
        @if ($signupOpen)
            <a class="btn primary" href="/auth/sign-up">{{ __('site.create_account') }}</a>
        @endif
        <a class="btn" href="/auth/sign-in">{{ __('site.sign_in') }}</a>
    </div>
@endsection
