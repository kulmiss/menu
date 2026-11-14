@extends('site.layout')

@section('title', __('campaign.page.title').' · '.$platform)

@section('content')
    <h1>{{ __('campaign.page.title') }}</h1>
    @if ($state === 'confirm')
        <p>{{ __('campaign.page.confirm', ['platform' => $platform, 'email' => $email]) }}</p>
        <p class="muted">{{ __('campaign.page.confirm_note') }}</p>
        <form method="POST" action="{{ $unsubscribeUrl }}" class="actions">
            <button class="btn primary" type="submit">{{ __('campaign.page.button') }}</button>
        </form>
    @elseif ($state === 'done')
        <p role="status">{{ __('campaign.page.done', ['platform' => $platform, 'email' => $email]) }}</p>
        <p class="muted">{{ __('campaign.page.confirm_note') }}</p>
        <form method="POST" action="{{ $resubscribeUrl }}" class="actions">
            <button class="btn" type="submit">{{ __('campaign.page.again') }}</button>
        </form>
    @else
        <p role="status">{{ __('campaign.page.resubscribed', ['platform' => $platform, 'email' => $email]) }}</p>
    @endif
@endsection
