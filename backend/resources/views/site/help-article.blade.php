@extends('site.layout')

@section('title', $article['title'].' · '.$platform)
@section('description', $article['summary'])
@section('canonical', url('/support/'.$article['slug']))

@push('styles')
<style>
    .ha-back { display: inline-flex; align-items: center; gap: .4rem; min-height: 44px; color: var(--muted); text-decoration: none; margin-top: .5rem; }
    .ha-back:hover { color: var(--fg); }
    .ha-sum { color: var(--muted); font-size: 1.05rem; }
    .ha h2 { font-size: 1.25rem; margin: 1.75rem 0 .5rem; }
    .ha h3 { font-size: 1.05rem; margin: 1.25rem 0 .5rem; }
    .ha ul, .ha ol { padding-inline-start: 1.25rem; margin: 0 0 1rem; }
    .ha li { margin: .3rem 0; }
    .ha a { color: var(--ink); text-underline-offset: 3px; }
    .ha code { font-size: .9em; background: var(--soft); padding: .1rem .35rem; border-radius: .35rem; }
    .ha table { border-collapse: collapse; width: 100%; margin: 0 0 1rem; font-size: .95rem; }
    .ha th, .ha td { border-bottom: 1px solid var(--line); padding: .45rem .5rem; text-align: start; vertical-align: top; }
    /* Screenshots: the phone picture on phones, the computer picture on wide screens. */
    .ha img { display: block; max-width: 100%; height: auto; margin: 1rem 0; border: 1px solid var(--line); border-radius: .75rem; }
    .ha img[title="phone"] { max-width: 320px; }
    .ha img[title="desktop"] { display: none; }
    @media (min-width: 720px) { .ha img[title="phone"] { display: none; } .ha img[title="desktop"] { display: block; } }
    .ha-more { margin-top: 2rem; padding-top: 1rem; border-top: 1px solid var(--line); }
</style>
@endpush

@section('content')
    <a class="ha-back" href="/support">← {{ __('site.help.back') }}</a>
    <article class="ha">
        <h1>{{ $article['title'] }}</h1>
        @if ($article['summary'] !== '')<p class="ha-sum">{{ $article['summary'] }}</p>@endif
        {{-- Rendered from Markdown with raw HTML escaped and unsafe links removed (HelpRenderer::html). --}}
        {!! $article['html'] !!}
    </article>
    <p class="ha-more"><a href="/contact">{{ __('site.help.more') }}</a></p>
@endsection
