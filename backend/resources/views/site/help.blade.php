@extends('site.layout')

@section('title', __('site.help.title').' · '.$platform)
@section('description', __('site.help.description', ['name' => $platform]))
@section('canonical', url('/support'))

@push('styles')
<style>
    .hc-intro { color: var(--muted); }
    .hc-group { margin-top: 2rem; }
    .hc-group h2 { font-size: .8125rem; text-transform: uppercase; letter-spacing: .08em; color: var(--muted); margin: 0 0 .5rem; }
    .hc-list { list-style: none; margin: 0; padding: 0; border-top: 1px solid var(--line); }
    .hc-list li { border-bottom: 1px solid var(--line); }
    .hc-list a { display: block; padding: .8rem 0; min-height: 44px; color: inherit; text-decoration: none; }
    .hc-list a:hover b { text-decoration: underline; text-underline-offset: 3px; }
    .hc-list b { display: block; font-weight: 650; }
    .hc-list span { display: block; color: var(--muted); font-size: .95rem; }
</style>
@endpush

@section('content')
    <h1>{{ __('site.help.title') }}</h1>
    <p class="hc-intro">{{ __('site.help.intro') }}</p>
    @foreach ($groups as $group)
        <section class="hc-group" aria-labelledby="hc-{{ $loop->index }}">
            <h2 id="hc-{{ $loop->index }}">{{ $group['title'] }}</h2>
            <ul class="hc-list">
                @foreach ($group['items'] as $item)
                    <li><a href="/support/{{ $item['slug'] }}"><b>{{ $item['title'] }}</b>@if ($item['summary'] !== '')<span>{{ $item['summary'] }}</span>@endif</a></li>
                @endforeach
            </ul>
        </section>
    @endforeach
@endsection
