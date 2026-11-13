<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @if ($noindex ?? false)<meta name="robots" content="noindex">@endif
    <title>@yield('title')</title>
    <style>
        :root { color-scheme: light dark; --bg: #fff; --fg: #0f172a; --muted: #475569; --line: #e2e8f0; --primary: #0f172a; --on-primary: #fff; }
        @media (prefers-color-scheme: dark) { :root { --bg: #020817; --fg: #f1f5f9; --muted: #a3b1c6; --line: #1e293b; --primary: #f1f5f9; --on-primary: #020817; } }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; font-family: system-ui, sans-serif; line-height: 1.6; background: var(--bg); color: var(--fg); }
        header, main, footer { max-width: 44rem; margin: 0 auto; padding: 1rem 1.25rem; }
        header { display: flex; align-items: center; justify-content: space-between; gap: 1rem; border-bottom: 1px solid var(--line); }
        header a { color: inherit; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: .5rem; min-height: 44px; }
        header img { height: 32px; width: auto; }
        h1 { font-size: 1.75rem; line-height: 1.25; margin: 1.5rem 0 1rem; }
        p { margin: 0 0 1rem; }
        .muted { color: var(--muted); }
        .actions { display: flex; flex-wrap: wrap; gap: .75rem; margin-top: 1.5rem; }
        .btn { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; padding: 0 1.25rem; border-radius: .5rem; border: 1px solid var(--primary); font-weight: 600; text-decoration: none; color: var(--fg); }
        .btn.primary { background: var(--primary); color: var(--on-primary); }
        .btn:focus-visible, a:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; }
        footer { border-top: 1px solid var(--line); font-size: .875rem; }
        footer a { color: inherit; display: inline-flex; align-items: center; min-height: 44px; margin-inline-end: 1rem; }
    </style>
    @stack('styles')
</head>
<body>
    <header>
        <a href="/">@if ($logo ?? null)<img src="{{ $logo }}" alt="">@endif<span>{{ $platform }}</span></a>
    </header>
    <main id="content">@yield('content')</main>
    <footer>
        <a href="/terms">{{ __('site.terms') }}</a>
        <a href="/privacy">{{ __('site.privacy') }}</a>
        <span class="muted">© {{ date('Y') }} {{ $platform }}</span>
    </footer>
</body>
</html>
