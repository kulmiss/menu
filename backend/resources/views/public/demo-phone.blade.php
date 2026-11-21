{{-- A website demo menu in a phone frame, for visitors on a tablet or computer (owner, 2026-11-19).
     No script; the menu itself loads in the frame from its usual (static) address. --}}
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex">
<meta name="description" content="{{ __('public.demo_view.hint') }}">
<meta name="color-scheme" content="dark">
<title>{{ __('public.demo_view.page_title', ['name' => $tenant->name]) }}</title>
<link rel="canonical" href="{{ url($path) }}">
<style>
{!! \App\Support\PublicMenu\InlineAssets::demoCss() !!}
*{box-sizing:border-box;margin:0}
html,body{height:100%}
body{display:flex;flex-direction:column;background:#0b1120;color:#e2e8f0;font:16px/1.5 system-ui,-apple-system,"Segoe UI",sans-serif}
.stage{flex:1;display:flex;align-items:center;justify-content:center;gap:4rem;padding:1.5rem;background:radial-gradient(60rem 40rem at 30% 20%,#1e293b 0,transparent 70%)}
.ph{--k:1;flex:none;width:25.875rem;height:54.25rem;padding:.75rem;border-radius:3.25rem;background:#020617;box-shadow:0 0 0 1px #334155,0 2.5rem 5rem -1.5rem #000c;transform:scale(var(--k));transform-origin:center;margin:calc(54.25rem * (var(--k) - 1) / 2) calc(25.875rem * (var(--k) - 1) / 2)}
/* The menu always lays out at a real phone's width (390 px); on a shorter screen the whole phone is drawn smaller. */
@media (max-height:61.2rem){.ph{--k:.85}}
@media (max-height:52.8rem){.ph{--k:.75}}
@media (max-height:47.4rem){.ph{--k:.65}}
@media (max-height:42rem){.ph{--k:.55}}
.ph iframe{display:block;width:24.375rem;height:52.75rem;border:0;border-radius:2.5rem;background:#fff}
.side{max-width:20rem}
.side h1{font-size:1.75rem;line-height:1.2;color:#f8fafc}
.side p{margin-top:.75rem;color:#94a3b8}
.qr{display:flex;align-items:center;gap:1rem;margin-top:1.5rem;padding:1rem;border-radius:1rem;background:#f8fafc;color:#0f172a;font-weight:600;font-size:.9375rem}
.qr svg{flex:none;width:7rem;height:7rem}
@media (max-width:63.99rem){.side{display:none}}
/* On a phone: no frame, the menu fills the screen. */
@media (max-width:47.99rem){.stage{padding:0}.ph{--k:1;width:100%;height:100%;margin:0;padding:0;border-radius:0;box-shadow:none;transform:none}.ph iframe{width:100%;height:100%;border-radius:0}}
</style>
</head>
<body>
<nav class="dvb" aria-label="{{ __('public.demo_view.label') }}"><b>{{ __('public.demo_view.title') }}</b><span class="dvs"><a aria-current="page"><svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="6" y="2.5" width="12" height="19" rx="2.5"/><path d="M11 18.5h2"/></svg>{{ __('public.demo_view.phone') }}</a><a href="{{ $path }}"><svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="2.5" y="4" width="19" height="13" rx="2"/><path d="M8 20.5h8M12 17v3.5"/></svg>{{ __('public.demo_view.desktop') }}</a></span></nav>
<main class="stage">
<div class="ph"><iframe src="{{ $path }}" title="{{ __('public.demo_view.frame', ['name' => $tenant->name]) }}"></iframe></div>
<div class="side">
<h1>{{ $tenant->name }}</h1>
<p>{{ __('public.demo_view.hint') }}</p>
<div class="qr">{!! preg_replace('/^<\?xml[^>]*>\s*/', '', $qr) !!}<span>{{ __('public.demo_view.scan') }}</span></div>
</div>
</main>
</body>
</html>
