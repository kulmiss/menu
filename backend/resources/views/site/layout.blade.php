@php
    // Site pages (Terms, Privacy, About us, Contact us, Cookie info, Help, news e-mail links): the landing page's
    // colours, header and footer (owner, 2026-11-18, QUESTIONS #230).
    $colors = \App\Support\BrandColor::palette(\App\Support\PlatformSettings::get('brand_color'));
    $siteLogo = $logo ?? \App\Support\EmailLogo::url();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @if ($noindex ?? false)<meta name="robots" content="noindex">@endif
    <title>@yield('title')</title>
    @hasSection('description')<meta name="description" content="@yield('description')">@endif
    @hasSection('canonical')<link rel="canonical" href="@yield('canonical')">@endif
    <meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="{{ \App\Support\BrandColor::DARK_BG }}" media="(prefers-color-scheme: dark)">
    <style>
        :root{color-scheme:light dark;--bg:#fff;--fg:#0f172a;--muted:#475569;--card:#fff;--soft:#f6f7f9;--line:#e4e7ec;--brand:{{ $colors['brand'] }};--on-brand:{{ $colors['on_brand'] }};--ink:{{ $colors['ink'] }};--primary:#0f172a;--on-primary:#fff}
        @media (prefers-color-scheme:dark){:root{--bg:{{ \App\Support\BrandColor::DARK_BG }};--fg:#f1f5f9;--muted:#a7b3c5;--card:#121a2e;--soft:#0f1628;--line:#24304a;--ink:{{ $colors['ink_dark'] }};--primary:#f1f5f9;--on-primary:#0b1020}}
        *{box-sizing:border-box}
        body{margin:0;min-height:100vh;display:flex;flex-direction:column;font-family:ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,"Noto Sans","Noto Sans Ethiopic",Arial,sans-serif;line-height:1.6;background:var(--bg);color:var(--fg);-webkit-font-smoothing:antialiased}
        .site-header{border-bottom:1px solid var(--line);background:var(--bg)}
        .site-header .in{display:flex;align-items:center;justify-content:space-between;gap:1rem;max-width:1200px;margin:0 auto;padding:.5rem 20px}
        @media (min-width:768px){.site-header .in{padding:.5rem 32px}}
        .site-header .brand{display:inline-flex;align-items:center;gap:10px;min-height:44px;font-weight:800;font-size:1.1rem;color:inherit;text-decoration:none}
        .site-header .brand img{height:32px;width:auto}
        .site-header .mark{width:32px;height:32px;border-radius:9px;background:var(--brand);color:var(--on-brand);display:grid;place-items:center}
        .site-header nav{display:flex;gap:4px}
        .site-header nav a{display:inline-flex;align-items:center;min-height:44px;padding:0 12px;border-radius:999px;color:var(--fg);font-weight:600;text-decoration:none}
        .site-header nav a.primary{background:var(--brand);color:var(--on-brand)}
        main{width:100%;max-width:44rem;margin:0 auto;padding:1rem 20px 3rem}
        h1{font-size:1.9rem;line-height:1.2;letter-spacing:-.02em;margin:1.5rem 0 1rem}
        p{margin:0 0 1rem}
        .muted{color:var(--muted)}
        .actions{display:flex;flex-wrap:wrap;gap:.75rem;margin-top:1.5rem}
        .btn{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:0 1.25rem;border-radius:999px;border:1px solid var(--primary);font:inherit;font-weight:600;text-decoration:none;color:var(--fg);background:transparent;cursor:pointer}
        .btn.primary{background:var(--primary);color:var(--on-primary)}
        a:focus-visible,.btn:focus-visible{outline:3px solid var(--ink);outline-offset:3px;border-radius:6px}
        .skip{position:absolute;left:-9999px;top:8px;z-index:100;background:var(--fg);color:var(--bg);padding:10px 16px;border-radius:8px}
        .skip:focus{left:8px}
    </style>
    @stack('styles')
    @include('site.partials.footer-style')
</head>
<body>
    <a class="skip" href="#content">{{ __('site.skip') }}</a>
    <header class="site-header" id="top">
        <div class="in">
            <a class="brand" href="/">@if ($siteLogo)<img src="{{ $siteLogo }}" alt="" height="32">@else<span class="mark" aria-hidden="true">{{ mb_strtoupper(mb_substr($platform, 0, 1)) }}</span>@endif<span>{{ $platform }}</span></a>
            <nav aria-label="{{ __('site.account') }}"><a href="/signin">{{ __('site.sign_in') }}</a></nav>
        </div>
    </header>
    <main id="content">@yield('content')</main>
    @include('site.partials.footer')
</body>
</html>
