@extends('site.layout')

@section('title', $title.' · '.$platform)

@push('styles')
<style>
    .legal { max-width: 40rem; }
    .legal h1 { margin-bottom: .25rem; }
    .legal .updated { color: var(--muted); font-size: .9375rem; margin-bottom: 1.5rem; }
    .legal h2 { font-size: 1.25rem; line-height: 1.3; margin: 2.25rem 0 .75rem; scroll-margin-top: 1rem; }
    .legal h3 { font-size: 1.0625rem; margin: 1.5rem 0 .5rem; scroll-margin-top: 1rem; }
    .legal ul { margin: 0 0 1rem; padding-inline-start: 1.25rem; }
    .legal li { margin: .3rem 0; }
    .legal li ul { margin: .3rem 0 0; }
    .legal a { color: inherit; text-decoration-thickness: 1px; text-underline-offset: 2px; }
    .legal strong { font-weight: 650; }
    .toc { border: 1px solid var(--line); border-radius: .75rem; padding: 1rem 1.25rem; margin: 0 0 1.5rem; }
    .toc h2 { font-size: .8125rem; text-transform: uppercase; letter-spacing: .06em; color: var(--muted); margin: 0 0 .5rem; }
    .toc ol { list-style: none; margin: 0; padding: 0; columns: 2 16rem; column-gap: 1.5rem; }
    .toc li { margin: 0; break-inside: avoid; }
    .toc a { display: flex; align-items: center; min-height: 44px; text-decoration: none; }
    .toc a:hover { text-decoration: underline; }
    .top { display: inline-flex; align-items: center; min-height: 44px; margin-top: 1.5rem; color: var(--muted); }
</style>
@endpush

@section('content')
    <article class="legal" id="top">
        <h1>{{ $title }}</h1>
        @if ($doc['updated'])
            <p class="updated">{{ __('site.updated', ['date' => $doc['updated']]) }}</p>
        @endif

        @if ($doc['blocks'] === [])
            <p class="muted">{{ __('site.not_written') }}</p>
        @else
            @if (count($doc['toc']) > 1)
                <nav class="toc" aria-labelledby="toc-title">
                    <h2 id="toc-title">{{ __('site.contents') }}</h2>
                    <ol>
                        @foreach ($doc['toc'] as $entry)
                            <li><a href="#{{ $entry['id'] }}">{{ $entry['text'] }}</a></li>
                        @endforeach
                    </ol>
                </nav>
            @endif

            @foreach ($doc['blocks'] as $block)
                @switch($block['type'])
                    @case('h2')
                        <h2 id="{{ $block['id'] }}">{{ $block['text'] }}</h2>
                        @break
                    @case('h3')
                        <h3 id="{{ $block['id'] }}">{{ $block['text'] }}</h3>
                        @break
                    @case('ul')
                        @include('site.partials.legal-list', ['items' => $block['items']])
                        @break
                    @default
                        <p>{!! implode('<br>', array_map(fn ($line) => \App\Support\Legal\LegalText::html($line)->toHtml(), $block['lines'])) !!}</p>
                @endswitch
            @endforeach

            <a class="top" href="#top">{{ __('site.back_to_top') }}</a>
        @endif
    </article>
@endsection
