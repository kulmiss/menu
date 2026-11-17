@extends('account.layout')

@section('title', __('account.waiting.title'))

@section('form')
    <svg class="big-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
    <h1>{{ __('account.waiting.title') }}</h1>
    <p class="intro">{{ __('account.waiting.text', ['restaurant' => $restaurant]) }}</p>
    <p class="intro">{{ __('account.waiting.meanwhile') }}</p>
    <div class="stack">
        <a class="btn btn-primary btn-block" href="/admin?welcome=1">{{ __('account.waiting.button') }}</a>
    </div>
@endsection
