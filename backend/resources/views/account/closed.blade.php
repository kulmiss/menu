@extends('account.layout', ['indexable' => true])

@section('title', __('account.closed.title'))

@section('header')
    <a class="btn btn-ghost" href="/signin">{{ __('account.sign_in') }}</a>
@endsection

@section('form')
    <h1>{{ __('account.closed.title') }}</h1>
    <p class="intro">{{ __('account.closed.text') }}</p>
    <div class="stack">
        @if ($whatsapp)
            <a class="btn btn-primary btn-block" href="{{ $whatsapp }}" target="_blank" rel="noopener">
                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/></svg>{{ __('account.closed.whatsapp') }}</a>
        @endif
        @if ($email)
            <a class="btn {{ $whatsapp ? 'btn-ghost' : 'btn-primary' }} btn-block" href="{{ $email }}">{{ __('account.closed.email') }}</a>
        @endif
    </div>
@endsection

@section('after')
    <p class="after">{{ __('account.have_account') }} <a href="/signin">{{ __('account.sign_in') }}</a></p>
@endsection
