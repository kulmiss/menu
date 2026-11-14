{{-- Friendly error pages (pre-launch check). No database, no session, no scripts: they must work when something is broken. --}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex">
<meta name="color-scheme" content="light dark">
<title>{{ __("errors.{$code}.title") }} · {{ config('app.name') }}</title>
<style>
:root{--bg:#faf8f6;--fg:#1c1917;--muted:#57534e;--line:#e7e5e4;--btn:#1c1917;--btnfg:#fff}
@media (prefers-color-scheme:dark){:root{--bg:#0c0a09;--fg:#f5f5f4;--muted:#a8a29e;--line:#292524;--btn:#f5f5f4;--btnfg:#0c0a09}}
*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;font:16px/1.5 system-ui,-apple-system,"Segoe UI",sans-serif;color:var(--fg);background:var(--bg);padding:1.5rem;text-align:center}
main{max-width:28rem}.code{font-size:4.5rem;font-weight:800;line-height:1;margin:0 0 .5rem;letter-spacing:-.03em}
h1{font-size:1.35rem;margin:0 0 .5rem}p{color:var(--muted);margin:0 0 1.5rem}
a{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:0 1.25rem;border-radius:.6rem;background:var(--btn);color:var(--btnfg);text-decoration:none;font-weight:600}
a:focus-visible{outline:3px solid var(--muted);outline-offset:3px}
</style>
</head>
<body>
<main>
<p class="code" aria-hidden="true">{{ $code }}</p>
<h1>{{ __("errors.{$code}.title") }}</h1>
<p>{{ __("errors.{$code}.text") }}</p>
@unless ($code === 503)<a href="{{ url('/') }}">{{ __('errors.home') }}</a>@endunless
</main>
</body>
</html>
