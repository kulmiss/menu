{{-- Super Admin → Footer: the footer alone, for the editor's preview (light or dark, whatever the computer is set to). --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Footer preview</title>
    <style>
        @if ($dark)
            :root{color-scheme:dark;--bg:{{ \App\Support\BrandColor::DARK_BG }};--fg:#f1f5f9;--muted:#a7b3c5;--card:#121a2e;--soft:#0f1628;--line:#24304a;--brand:{{ $colors['brand'] }};--on-brand:{{ $colors['on_brand'] }};--ink:{{ $colors['ink_dark'] }}}
        @else
            :root{color-scheme:light;--bg:#fff;--fg:#0f172a;--muted:#475569;--card:#fff;--soft:#f6f7f9;--line:#e4e7ec;--brand:{{ $colors['brand'] }};--on-brand:{{ $colors['on_brand'] }};--ink:{{ $colors['ink'] }}}
        @endif
        *{box-sizing:border-box}
        body{margin:0;min-height:100vh;display:flex;flex-direction:column;justify-content:flex-end;font-family:ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,"Noto Sans","Noto Sans Ethiopic",Arial,sans-serif;line-height:1.6;background:var(--bg);color:var(--fg);-webkit-font-smoothing:antialiased}
        h2{line-height:1.2}
    </style>
    @include('site.partials.footer-style')
</head>
<body id="top">
    @include('site.partials.footer', ['footer' => $footer])
</body>
</html>
