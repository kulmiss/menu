@extends('site.layout')

@section('title', $title.' · '.$platform)

@push('styles')
<style>
    .legal { max-width: 40rem; }
    .legal h1 { margin-bottom: .25rem; }
    .legal .updated { color: var(--muted); font-size: .9375rem; margin-bottom: 1.5rem; }
    .legal h3 { font-size: 1.0625rem; margin: 1.25rem 0 .5rem; }
    .legal ul { margin: 0 0 1rem; padding-inline-start: 1.25rem; }
    .legal li { margin: .3rem 0; }
    .legal li ul { margin: .3rem 0 0; }
    .legal a { color: inherit; text-decoration-thickness: 1px; text-underline-offset: 2px; }
    .legal strong { font-weight: 650; }
    .toc { border: 1px solid var(--line); border-radius: .75rem; padding: 1rem 1.25rem; margin: 1.5rem 0; }
    .toc h2 { font-size: .8125rem; text-transform: uppercase; letter-spacing: .06em; color: var(--muted); margin: 0 0 .5rem; }
    .toc ol { list-style: none; margin: 0; padding: 0; columns: 2 16rem; column-gap: 1.5rem; }
    .toc li { margin: 0; break-inside: avoid; }
    .toc a { display: flex; align-items: center; min-height: 44px; text-decoration: none; }
    .toc a:hover { text-decoration: underline; }
    .tools { display: flex; flex-wrap: wrap; gap: .5rem; margin: 0 0 .5rem; }
    .tools button { font: inherit; font-size: .9375rem; font-weight: 600; min-height: 44px; padding: 0 1rem; border-radius: .5rem; border: 1px solid var(--line); background: transparent; color: var(--fg); cursor: pointer; }
    .tools button:hover { background: var(--line); }
    .tools button:focus-visible, .sec > summary:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; }
    .tools [hidden] { display: none; }
    .sec { border-bottom: 1px solid var(--line); scroll-margin-top: 1rem; }
    .sec:first-of-type { border-top: 1px solid var(--line); }
    .sec > summary { list-style: none; display: flex; align-items: center; justify-content: space-between; gap: 1rem; min-height: 44px; padding: .75rem 0; cursor: pointer; border-radius: .25rem; }
    .sec > summary::-webkit-details-marker { display: none; }
    .sec > summary h2 { font-size: 1.125rem; line-height: 1.35; margin: 0; }
    .sec .chev { flex: none; width: 20px; height: 20px; color: var(--muted); }
    .sec[open] .chev { transform: rotate(180deg); }
    .sec .body { padding: 0 0 1rem; }
    @media (prefers-reduced-motion: no-preference) {
        :root { interpolate-size: allow-keywords; }
        .sec .chev { transition: transform .2s ease; }
        .sec::details-content { height: 0; overflow: clip; transition: height .25s ease, content-visibility .25s allow-discrete; }
        .sec[open]::details-content { height: auto; }
    }
    .top { display: inline-flex; align-items: center; min-height: 44px; margin-top: 1.5rem; color: var(--muted); }
    @media print {
        header, footer, .toc, .tools, .top, .sec .chev { display: none !important; }
        .sec::details-content { content-visibility: visible; height: auto; }
        .sec { border: 0; }
    }
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
            @include('site.partials.legal-blocks', ['blocks' => $doc['intro']])

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

            @if ($doc['sections'] !== [])
                {{-- Shown by the script: without JavaScript every section still opens by tapping it. --}}
                <div class="tools">
                    <button type="button" data-all="open" hidden>{{ __('site.expand_all') }}</button>
                    <button type="button" data-all="close" hidden>{{ __('site.collapse_all') }}</button>
                </div>
                @foreach ($doc['sections'] as $section)
                    <details class="sec" id="{{ $section['id'] }}">
                        <summary><h2>{{ $section['title'] }}</h2><svg class="chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></summary>
                        <div class="body">@include('site.partials.legal-blocks', ['blocks' => $section['blocks']])</div>
                    </details>
                @endforeach
            @endif

            <a class="top" href="#top">{{ __('site.back_to_top') }}</a>
        @endif
    </article>
    <script nonce="{{ \App\Support\Security\Csp::nonce() }}">
        (function () {
            var d = document, all = [].slice.call(d.querySelectorAll('details.sec'));
            // Open the section a link points to (contents, or #section in the address), then show it.
            function show(id, scroll) {
                var el = id && d.getElementById(id);
                if (!el || el.tagName !== 'DETAILS') return;
                el.open = true;
                if (scroll) el.scrollIntoView();
            }
            var hash = function () { try { return decodeURIComponent(location.hash.slice(1)); } catch (e) { return ''; } };
            show(hash(), true);
            addEventListener('hashchange', function () { show(hash(), true); });
            [].forEach.call(d.querySelectorAll('.toc a'), function (a) {
                a.addEventListener('click', function () { show(a.getAttribute('href').slice(1), false); });
            });
            [].forEach.call(d.querySelectorAll('[data-all]'), function (b) {
                b.hidden = false;
                b.addEventListener('click', function () {
                    var open = b.getAttribute('data-all') === 'open';
                    all.forEach(function (x) { x.open = open; });
                });
            });
            // Printing shows every section, then puts them back as they were.
            addEventListener('beforeprint', function () { all.forEach(function (x) { x.setAttribute('data-was', x.open ? '1' : ''); x.open = true; }); });
            addEventListener('afterprint', function () { all.forEach(function (x) { x.open = x.getAttribute('data-was') === '1'; }); });
        })();
    </script>
@endsection
