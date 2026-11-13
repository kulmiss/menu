@php
    /** Lucide icons (ISC licence), drawn inline. */
    $paths = [
        'check' => '<path d="M20 6 9 17l-5-5"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'x' => '<path d="M18 6 6 18M6 6l12 12"/>',
        'arrow' => '<path d="M5 12h14M12 5l7 7-7 7"/>',
        'external' => '<path d="M15 3h6v6M10 14 21 3M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
        'message' => '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/>',
        'pencil' => '<path d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
        'printer' => '<path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect width="12" height="8" x="6" y="14"/>',
        'scan' => '<path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2M7 12h10"/>',
        'fast' => '<path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z"/>',
        'styles' => '<rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/>',
        'photos' => '<rect width="18" height="18" x="3" y="3" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.09-3.09a2 2 0 0 0-2.82 0L6 21"/>',
        'soldout' => '<circle cx="12" cy="12" r="10"/><path d="m4.9 4.9 14.2 14.2"/>',
        'branches' => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
        'order' => '<path d="m3 17 2 2 4-4M3 7l2 2 4-4M13 6h8M13 12h8M13 18h8"/>',
        'share' => '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.59 13.51 6.83 3.98M15.41 6.51l-6.82 3.98"/>',
        'insights' => '<path d="M3 3v18h18M18 17V9M13 17V5M8 17v-3"/>',
        'anyphone' => '<rect width="14" height="20" x="5" y="2" rx="2"/><path d="M12 18h.01"/>',
    ];
    $icon = fn (string $name, string $class = 'icon') => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$paths[$name].'</svg>';
    $nav = array_filter([
        'features' => $sections['features'],
        'styles' => $sections['styles'],
        'pricing' => $sections['pricing'],
        'faq' => $sections['faq'],
    ]);
    $title = __('landing.meta_title', ['name' => $platform]);
    $description = __('landing.meta_description');
    $ogImage = asset('landing/og.jpg');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}">
    <link rel="canonical" href="{{ url('/') }}">
    <meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="{{ \App\Support\BrandColor::DARK_BG }}" media="(prefers-color-scheme: dark)">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $platform }}">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ url('/') }}">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="{{ __('landing.hero.phone_alt') }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title }}">
    <meta name="twitter:description" content="{{ $description }}">
    <meta name="twitter:image" content="{{ $ogImage }}">
    <link rel="preload" as="image" href="{{ asset('landing/hero-600.webp') }}" imagesrcset="{{ asset('landing/hero-300.webp') }} 300w, {{ asset('landing/hero-450.webp') }} 450w, {{ asset('landing/hero-600.webp') }} 600w" imagesizes="240px" fetchpriority="high">
    <script type="application/ld+json">{!! $jsonLd !!}</script>
    <script>document.documentElement.classList.add('js')</script>
    <style>
        :root{color-scheme:light dark;--bg:#fff;--fg:#0f172a;--muted:#475569;--card:#fff;--soft:#f6f7f9;--line:#e4e7ec;--brand:{{ $colors['brand'] }};--on-brand:{{ $colors['on_brand'] }};--ink:{{ $colors['ink'] }};--shadow:0 1px 2px rgb(15 23 42/.06),0 8px 24px rgb(15 23 42/.08);--radius:16px}
        @media (prefers-color-scheme:dark){.phone{box-shadow:0 30px 60px -20px rgb(0 0 0/.6),0 0 0 1px rgb(255 255 255/.16)}:root{--bg:{{ \App\Support\BrandColor::DARK_BG }};--fg:#f1f5f9;--muted:#a7b3c5;--card:#121a2e;--soft:#0f1628;--line:#24304a;--ink:{{ $colors['ink_dark'] }};--shadow:0 1px 2px rgb(0 0 0/.4),0 8px 24px rgb(0 0 0/.35)}}
        *,*::before,*::after{box-sizing:border-box}
        html{scroll-behavior:smooth;scroll-padding-top:72px;-webkit-text-size-adjust:100%}
        @media (prefers-reduced-motion:reduce){html{scroll-behavior:auto}}
        body{margin:0;font-family:ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,"Noto Sans","Noto Sans Ethiopic",Arial,sans-serif;line-height:1.6;background:var(--bg);color:var(--fg);-webkit-font-smoothing:antialiased}
        img,svg{display:block;max-width:100%}
        a{color:inherit}
        h1,h2,h3{line-height:1.15;letter-spacing:-.02em;margin:0;text-wrap:balance}
        p{margin:0}
        .wrap{width:100%;max-width:1200px;margin:0 auto;padding:0 20px}
        @media (min-width:768px){.wrap{padding:0 32px}}
        .icon{width:20px;height:20px;flex:none}
        .skip{position:absolute;left:-9999px;top:8px;z-index:100;background:var(--fg);color:var(--bg);padding:10px 16px;border-radius:8px}
        .skip:focus{left:8px}
        :focus-visible{outline:3px solid var(--ink);outline-offset:3px;border-radius:6px}
        .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:44px;padding:0 20px;border-radius:999px;font-weight:600;font-size:1rem;text-decoration:none;border:1px solid transparent;transition:transform .15s,box-shadow .15s,background-color .15s;white-space:nowrap}
        .btn-primary{background:var(--brand);color:var(--on-brand);box-shadow:0 6px 20px -6px color-mix(in srgb,var(--brand) 70%,transparent)}
        .btn-primary:hover{transform:translateY(-1px)}
        .btn-ghost{border-color:var(--line);background:var(--card)}
        .btn-ghost:hover{border-color:var(--muted)}
        .btn-lg{min-height:52px;padding:0 26px;font-size:1.05rem}
        @media (prefers-reduced-motion:reduce){.btn,.btn-primary:hover{transition:none;transform:none}}
        /* Header */
        .site-header{position:sticky;top:0;z-index:50;background:color-mix(in srgb,var(--bg) 85%,transparent);backdrop-filter:saturate(1.6) blur(12px);-webkit-backdrop-filter:saturate(1.6) blur(12px);border-bottom:1px solid transparent;transition:border-color .2s}
        .site-header.scrolled{border-bottom-color:var(--line)}
        .bar{display:flex;align-items:center;gap:16px;height:68px;transition:height .2s}
        .scrolled .bar{height:58px}
        .brand{display:inline-flex;align-items:center;gap:10px;font-weight:800;font-size:1.15rem;text-decoration:none;min-height:44px;margin-right:auto}
        .brand img{height:32px;width:auto}
        .brand-mark{width:32px;height:32px;border-radius:9px;background:var(--brand);color:var(--on-brand);display:grid;place-items:center;font-size:1rem}
        .nav-links{display:none;gap:4px}
        .nav-links a{text-decoration:none;color:var(--muted);font-weight:500;padding:10px 12px;border-radius:8px;min-height:44px;display:inline-flex;align-items:center}
        .nav-links a:hover{color:var(--fg)}
        .bar .actions{display:none;gap:8px;align-items:center}
        .menu-btn{display:inline-grid;place-items:center;width:44px;height:44px;border-radius:12px;border:1px solid var(--line);background:var(--card);color:var(--fg);cursor:pointer}
        .mobile-nav{border-top:1px solid var(--line);padding-top:12px;padding-bottom:20px}
        .mobile-nav a:not(.btn){display:flex;align-items:center;min-height:48px;text-decoration:none;font-weight:600;border-bottom:1px solid var(--line)}
        .mobile-nav .btn{width:100%;margin-top:12px}
        @media (min-width:960px){.nav-links,.bar .actions{display:flex}.menu-btn,.mobile-nav{display:none!important}}
        /* Hero */
        .hero{position:relative;overflow:hidden;padding:48px 0 64px}
        .hero::before{content:"";position:absolute;inset:-20% -10% auto;height:120%;background:radial-gradient(40% 50% at 15% 20%,color-mix(in srgb,var(--brand) 22%,transparent),transparent 70%),radial-gradient(35% 45% at 85% 30%,color-mix(in srgb,var(--brand) 14%,transparent),transparent 70%);z-index:-1}
        .hero::after{content:"";position:absolute;inset:0;background-image:radial-gradient(color-mix(in srgb,var(--fg) 12%,transparent) 1px,transparent 1px);background-size:22px 22px;mask-image:linear-gradient(to bottom,#000,transparent 85%);-webkit-mask-image:linear-gradient(to bottom,#000,transparent 85%);z-index:-1;opacity:.5}
        .hero-grid{display:grid;gap:48px;align-items:center}
        @media (min-width:960px){.hero{padding:80px 0 96px}.hero-grid{grid-template-columns:1.15fr .85fr}}
        .eyebrow{display:inline-flex;align-items:center;gap:8px;font-size:.875rem;font-weight:600;color:var(--ink);background:color-mix(in srgb,var(--brand) 10%,var(--card));border:1px solid color-mix(in srgb,var(--brand) 25%,var(--line));padding:6px 12px;border-radius:999px}
        .hero h1{font-size:clamp(2.2rem,6vw,3.75rem);font-weight:800;margin:18px 0 18px}
        .hero .lead{font-size:clamp(1.05rem,2.2vw,1.25rem);color:var(--muted);max-width:36rem}
        .cta-row{display:flex;flex-wrap:wrap;gap:12px;margin-top:28px}
        .trust{display:flex;flex-wrap:wrap;gap:8px 18px;margin:22px 0 0;padding:0;list-style:none;color:var(--muted);font-size:.95rem}
        .trust li{display:inline-flex;align-items:center;gap:6px}
        .trust .icon{color:var(--ink);width:18px;height:18px}
        .stage{position:relative;justify-self:center;padding:0 24px}
        .phone{position:relative;width:260px;border-radius:42px;padding:10px;background:#0b0f19;box-shadow:0 30px 60px -20px rgb(15 23 42/.45),0 0 0 1px rgb(255 255 255/.06) inset}
        .phone::before{content:"";position:absolute;top:18px;left:50%;width:76px;height:20px;margin-left:-38px;background:#0b0f19;border-radius:999px;z-index:2}
        .phone img{width:100%;height:auto;border-radius:32px;background:var(--soft)}
        .phone-sm{width:220px;border-radius:36px;padding:8px}
        .phone-sm::before{top:14px;width:60px;height:16px;margin-left:-30px}
        .phone-sm img{border-radius:28px}
        .qr-card{position:absolute;left:-28px;bottom:48px;z-index:3;display:flex;align-items:center;gap:12px;background:var(--card);color:var(--fg);padding:12px 14px 12px 12px;border-radius:18px;box-shadow:var(--shadow);border:1px solid var(--line);max-width:230px}
        .qr-card svg{width:76px;height:76px;background:#fff;border-radius:8px;padding:4px;flex:none}
        .qr-card strong{display:block;font-size:.95rem}
        .qr-card span{font-size:.8rem;color:var(--muted);line-height:1.35;display:block}
        @media (min-width:960px){.qr-card{left:-90px}}
        @media (max-width:380px){.qr-card{left:-12px;bottom:24px}}
        .float{animation:float 6s ease-in-out infinite}
        @keyframes float{50%{transform:translateY(-8px)}}
        @media (prefers-reduced-motion:reduce){.float{animation:none}}
        /* Sections */
        section{padding:72px 0}
        @media (min-width:960px){section{padding:96px 0}}
        .soft{background:var(--soft)}
        .head{max-width:40rem;margin:0 auto 44px;text-align:center}
        .head h2{font-size:clamp(1.75rem,4vw,2.5rem);font-weight:800}
        .head p{color:var(--muted);margin-top:12px;font-size:1.1rem}
        .steps{display:grid;gap:20px;counter-reset:step;margin:0;padding:0;list-style:none}
        @media (min-width:768px){.steps{grid-template-columns:repeat(3,1fr)}}
        .card{background:var(--card);border:1px solid var(--line);border-radius:var(--radius);padding:24px}
        .step{position:relative}
        .step .num{position:absolute;top:20px;right:22px;font-size:2.5rem;font-weight:800;color:color-mix(in srgb,var(--fg) 10%,transparent);line-height:1}
        .tile{width:48px;height:48px;border-radius:14px;display:grid;place-items:center;background:color-mix(in srgb,var(--brand) 12%,var(--card));color:var(--ink);margin-bottom:16px}
        .tile .icon{width:24px;height:24px}
        .card h3{font-size:1.15rem;font-weight:700;margin-bottom:6px}
        .card p{color:var(--muted)}
        .grid-features{display:grid;gap:16px}
        @media (min-width:640px){.grid-features{grid-template-columns:repeat(2,1fr)}}
        @media (min-width:1024px){.grid-features{grid-template-columns:repeat(3,1fr)}}
        .card.hover{transition:transform .2s,box-shadow .2s}
        .card.hover:hover{transform:translateY(-2px);box-shadow:var(--shadow)}
        .styles{display:grid;gap:32px 20px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));justify-items:center}
        .style{display:flex;flex-direction:column;align-items:center;text-align:center;gap:14px}
        .style h3{font-size:1.1rem;font-weight:700}
        .style p{color:var(--muted);font-size:.95rem;max-width:16rem}
        .link{display:inline-flex;align-items:center;gap:6px;color:var(--ink);font-weight:600;min-height:44px;text-underline-offset:4px}
        .link .icon{width:16px;height:16px}
        .speed{display:grid;gap:40px;align-items:center}
        @media (min-width:960px){.speed{grid-template-columns:1fr 1fr}}
        .speed h2{font-size:clamp(1.75rem,4vw,2.5rem);font-weight:800}
        .speed .text{color:var(--muted);margin:14px 0 20px;font-size:1.1rem}
        .ticks{list-style:none;margin:0;padding:0;display:grid;gap:12px}
        .ticks li{display:flex;gap:10px;align-items:flex-start}
        .ticks .icon{color:var(--ink);margin-top:3px}
        .meter{padding:28px}
        .meter .label{font-weight:700;margin-bottom:52px}
        .track{position:relative;height:14px;border-radius:999px;background:linear-gradient(90deg,#16a34a,#16a34a 43%,color-mix(in srgb,var(--fg) 14%,transparent) 43%)}
        .track .mark{position:absolute;left:43.3%;top:-34px;transform:translateX(-50%);font-size:.85rem;font-weight:700;background:var(--fg);color:var(--bg);padding:3px 8px;border-radius:6px;white-space:nowrap}
        .track .mark::after{content:"";position:absolute;left:50%;bottom:-5px;margin-left:-5px;border:5px solid transparent;border-bottom:0;border-top-color:var(--fg)}
        .scale{display:flex;justify-content:space-between;margin-top:10px;color:var(--muted);font-size:.85rem}
        .plans{display:flex;flex-wrap:wrap;justify-content:center;gap:28px 20px}
        .plan{display:flex;flex-direction:column;gap:16px;position:relative;flex:1 1 200px;max-width:320px;min-width:0}
        .plan.hl{border:2px solid var(--brand);box-shadow:var(--shadow)}
        .badge{position:absolute;top:-13px;left:24px;background:var(--brand);color:var(--on-brand);font-size:.8rem;font-weight:700;padding:3px 10px;border-radius:999px}
        .price{color:var(--fg)!important;font-size:1.9rem;font-weight:800;letter-spacing:-.02em;line-height:1.2}.price small{white-space:nowrap}
        .price small{font-size:.95rem;font-weight:500;color:var(--muted);letter-spacing:0}
        .plan ul{list-style:none;margin:0;padding:0;display:grid;gap:8px;font-size:.95rem}
        .plan li{display:flex;gap:8px;align-items:flex-start}
        .plan li .icon{width:18px;height:18px;color:var(--ink);margin-top:3px}
        .plan .muted-li{color:var(--muted)}
        .plan .btn{margin-top:auto;width:100%;white-space:normal;text-align:center;padding:8px 14px;line-height:1.25}
        .note{text-align:center;color:var(--muted);margin-top:24px}
        .quotes{display:grid;gap:20px;grid-template-columns:repeat(auto-fit,minmax(260px,1fr))}
        .quotes blockquote{margin:0}
        .quotes p{color:var(--fg);font-size:1.05rem}
        .quotes footer{margin-top:14px;color:var(--muted);font-size:.95rem}
        .faq{max-width:48rem;margin:0 auto;display:grid;gap:12px}
        .faq details{background:var(--card);border:1px solid var(--line);border-radius:14px}
        .faq summary{cursor:pointer;list-style:none;display:flex;justify-content:space-between;gap:16px;align-items:center;padding:16px 20px;min-height:56px;font-weight:600;font-size:1.05rem}
        .faq summary::-webkit-details-marker{display:none}
        .faq summary::after{content:"+";font-size:1.5rem;line-height:1;color:var(--ink);transition:transform .2s}
        .faq details[open] summary::after{transform:rotate(45deg)}
        .faq .answer{padding:0 20px 18px;color:var(--muted)}
        .band{position:relative;overflow:hidden;border-radius:28px;padding:48px 24px;text-align:center;background:var(--brand);color:var(--on-brand)}
        .band::before{content:"";position:absolute;inset:0;background:radial-gradient(60% 80% at 10% 0%,rgb(255 255 255/.18),transparent 60%),radial-gradient(50% 70% at 100% 100%,rgb(0 0 0/.18),transparent 60%)}
        .band>*{position:relative}
        .band h2{font-size:clamp(1.75rem,4vw,2.5rem);font-weight:800}
        .band p{margin:12px auto 28px;max-width:32rem;font-size:1.1rem;opacity:.95}
        .band .btn{background:var(--on-brand);color:var(--brand)}
        @media (min-width:768px){.band{padding:64px 40px}}
        .site-footer{border-top:1px solid var(--line);padding:48px 0 32px;font-size:.95rem}
        .foot{display:grid;gap:32px}
        @media (min-width:768px){.foot{grid-template-columns:2fr 1fr 1fr}}
        .foot h2{font-size:.85rem;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin-bottom:10px}
        .foot ul{list-style:none;margin:0;padding:0}
        .foot li a{display:inline-flex;align-items:center;min-height:44px;text-decoration:none}
        .foot li a:hover{text-decoration:underline}
        .foot .about{color:var(--muted);margin-top:12px;max-width:26rem}
        .copy{margin-top:32px;color:var(--muted)}
        .reveal{transition:opacity .6s ease,transform .6s ease}
        .js .reveal{opacity:0;transform:translateY(18px)}
        .js .reveal.in{opacity:1;transform:none}
        @media (prefers-reduced-motion:reduce){.js .reveal{opacity:1;transform:none;transition:none}}
.free-tag{display:inline-block;background:var(--brand);color:var(--on-brand);font-weight:800;font-size:.75rem;letter-spacing:.04em;text-transform:uppercase;padding:2px 8px;border-radius:999px;margin-right:2px}
        .free-wrap{padding:56px 0 0}
        .free{display:grid;gap:24px;align-items:center;padding:28px;border-radius:24px;border:2px dashed color-mix(in srgb,var(--brand) 55%,var(--line));background:color-mix(in srgb,var(--brand) 7%,var(--card))}
        @media (min-width:960px){.free{grid-template-columns:auto 1fr auto;padding:32px 40px;gap:36px}.free-big{min-width:120px;height:120px;font-size:2.2rem;border-radius:28px}}
        .free-big{display:inline-grid;place-items:center;min-width:88px;height:88px;padding:0 14px;border-radius:22px;background:var(--brand);color:var(--on-brand);font-size:1.6rem;font-weight:900;letter-spacing:-.02em;transform:rotate(-4deg);box-shadow:0 14px 30px -12px color-mix(in srgb,var(--brand) 80%,transparent)}
        .free h2{font-size:clamp(1.4rem,3vw,1.9rem);font-weight:800}
        .free-body>p{color:var(--muted);margin-top:8px}
        .free .free-inc{margin-top:14px;color:var(--fg);font-weight:600}
        .free ul{list-style:none;margin:8px 0 0;padding:0;display:grid;gap:6px 18px;grid-template-columns:repeat(auto-fit,minmax(180px,1fr))}
        .free li{display:flex;gap:8px;align-items:flex-start}
        .free li .icon{color:var(--ink);width:18px;height:18px;margin-top:3px}
        .free-actions{display:flex;flex-direction:column;align-items:flex-start;gap:6px}
        @media (min-width:960px){.free-actions{align-items:center}}
        .band-trust{font-weight:700;margin-top:-16px!important;margin-bottom:24px!important}
        .sticky-cta{position:fixed;left:12px;right:12px;bottom:12px;z-index:40;display:flex;align-items:center;justify-content:space-between;gap:10px;padding:8px 8px 8px 14px;border-radius:999px;background:var(--card);border:1px solid var(--line);box-shadow:var(--shadow);font-size:.85rem;font-weight:600}
        .sticky-cta>span{white-space:nowrap}.sticky-cta .btn{min-height:44px;padding:0 16px;font-size:.9rem}
        @media (max-width:340px){.sticky-cta>span{display:none}.sticky-cta .btn{width:100%}}
        @media (min-width:960px){.sticky-cta{display:none!important}}
        .has-sticky .site-footer{padding-bottom:96px}
        .sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);border:0}
    </style>
</head>
<body>
<a class="skip" href="#content">{{ __('landing.skip') }}</a>

<header class="site-header" id="top">
    <div class="wrap bar">
        <a class="brand" href="/">
            @if ($logo)
                <img src="{{ $logo }}" alt="" height="32">
            @else
                <span class="brand-mark" aria-hidden="true">{{ mb_strtoupper(mb_substr($platform, 0, 1)) }}</span>
            @endif
            <span>{{ $platform }}</span>
        </a>
        <nav class="nav-links" aria-label="{{ __('landing.nav.label') }}">
            @foreach ($nav as $key => $on)
                <a href="#{{ $key }}">{{ __("landing.nav.{$key}") }}</a>
            @endforeach
        </nav>
        <div class="actions">
            <a class="btn btn-ghost" href="/auth/sign-in">{{ __('landing.sign_in') }}</a>
            @if ($signupOpen)
                <a class="btn btn-primary" href="/auth/sign-up">{{ __('landing.create_account') }}</a>
            @endif
        </div>
        <button class="menu-btn" type="button" aria-expanded="false" aria-controls="mobile-nav" data-open="{{ __('landing.nav.menu') }}" data-close="{{ __('landing.nav.close') }}" aria-label="{{ __('landing.nav.menu') }}">
            <span data-icon="open">{!! $icon('menu') !!}</span><span data-icon="close" hidden>{!! $icon('x') !!}</span>
        </button>
    </div>
    <nav class="wrap mobile-nav" id="mobile-nav" aria-label="{{ __('landing.nav.label') }}" hidden>
        @foreach ($nav as $key => $on)
            <a href="#{{ $key }}">{{ __("landing.nav.{$key}") }}</a>
        @endforeach
        <a href="/auth/sign-in">{{ __('landing.sign_in') }}</a>
        @if ($signupOpen)
            <a class="btn btn-primary" href="/auth/sign-up">{{ __('landing.create_account') }}</a>
        @endif
    </nav>
</header>

<main id="content">
    <div class="hero">
        <div class="wrap hero-grid">
            <div>
                <span class="eyebrow"><b class="free-tag">{{ __('landing.free.badge') }}</b> {{ __('landing.hero.eyebrow') }} · {{ __('landing.hero.eyebrow_rest') }}</span>
                <h1>{{ $headline }}</h1>
                <p class="lead">{{ $subline }}</p>
                <div class="cta-row">
                    @if ($signupOpen)
                        <a class="btn btn-primary btn-lg" href="/auth/sign-up">{{ __('landing.create_account') }} {!! $icon('arrow') !!}</a>
                    @else
                        <a class="btn btn-primary btn-lg" href="/auth/sign-in">{{ __('landing.sign_in') }}</a>
                    @endif
                    @if ($demoUrl)
                        <a class="btn btn-ghost btn-lg" href="{{ $demoUrl }}" target="_blank" rel="noopener">{{ __('landing.hero.demo') }} {!! $icon('external') !!}<span class="sr-only"> {{ __('landing.new_tab') }}</span></a>
                    @endif
                </div>
                <ul class="trust">
                    @foreach (__('landing.hero.trust') as $point)
                        <li>{!! $icon('check') !!} {{ $point }}</li>
                    @endforeach
                </ul>
            </div>
            <div class="stage">
                <div class="phone">
                    <img src="{{ asset('landing/hero-600.webp') }}" srcset="{{ asset('landing/hero-300.webp') }} 300w, {{ asset('landing/hero-450.webp') }} 450w, {{ asset('landing/hero-600.webp') }} 600w" sizes="240px" width="300" height="608" alt="{{ __('landing.hero.phone_alt') }}" fetchpriority="high">
                </div>
                <div class="qr-card float">
                    <svg viewBox="0 0 {{ $qr['size'] }} {{ $qr['size'] }}" role="img" aria-label="{{ __('landing.hero.qr_alt') }}" shape-rendering="crispEdges"><path fill="#0b0f19" d="{{ $qr['path'] }}"/></svg>
                    <div><strong>{{ __('landing.hero.qr_title') }}</strong><span>{{ $demoUrl ? __('landing.hero.qr_demo') : __('landing.hero.qr_signup') }}</span></div>
                </div>
            </div>
        </div>
    </div>

    @if ($sections['how'])
        <section class="soft" id="how" aria-labelledby="how-title">
            <div class="wrap">
                <div class="head reveal"><h2 id="how-title">{{ __('landing.how.title') }}</h2><p>{{ __('landing.how.subtitle') }}</p></div>
                <ol class="steps">
                    @foreach (__('landing.how.steps') as $i => $step)
                        <li class="card step reveal">
                            <span class="num" aria-hidden="true">{{ $i + 1 }}</span>
                            <div class="tile">{!! $icon(['pencil', 'printer', 'scan'][$i]) !!}</div>
                            <h3><span class="sr-only">{{ $i + 1 }}. </span>{{ $step['title'] }}</h3>
                            <p>{{ $step['text'] }}</p>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>
    @endif

    @if ($free && $signupOpen)
        <section class="free-wrap" aria-labelledby="free-title-1">
            <div class="wrap">
                <div class="free reveal">
                    <div>
                        <span class="free-big" aria-hidden="true">{{ __('landing.free.badge') }}</span>
                    </div>
                    <div class="free-body">
                        <h2 id="free-title-1">{{ __('landing.free.title') }}</h2>
                        <p>{{ __('landing.free.text') }}</p>
                        @if (true)
                            <p class="free-inc">{{ __('landing.free.includes') }}</p>
                            <ul>
                                @foreach (array_slice([...$free['limits'], ...$free['features']], 0, 6) as $line)
                                    <li>{!! $icon('check') !!}<span>{{ $line }}</span></li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                    <div class="free-actions">
                        <a class="btn btn-primary btn-lg" href="/auth/sign-up">{{ __('landing.create_account') }} {!! $icon('arrow') !!}</a>
                        @if ($sections['pricing'])<a class="link" href="#pricing">{{ __('landing.free.more') }}</a>@endif
                    </div>
                </div>
            </div>
        </section>
    @endif

    @if ($sections['features'])
        <section id="features" aria-labelledby="features-title">
            <div class="wrap">
                <div class="head reveal"><h2 id="features-title">{{ __('landing.features.title') }}</h2><p>{{ __('landing.features.subtitle') }}</p></div>
                <div class="grid-features">
                    @foreach (__('landing.features.items') as $key => $feature)
                        <div class="card hover reveal">
                            <div class="tile">{!! $icon($key) !!}</div>
                            <h3>{{ $feature['title'] }}</h3>
                            <p>{{ $feature['text'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($sections['styles'])
        <section class="soft" id="styles" aria-labelledby="styles-title">
            <div class="wrap">
                <div class="head reveal"><h2 id="styles-title">{{ __('landing.styles.title') }}</h2><p>{{ __('landing.styles.subtitle') }}</p></div>
                <div class="styles">
                    @foreach (__('landing.styles.names') as $key => [$styleName, $styleText])
                        <div class="style reveal">
                            <div class="phone phone-sm">
                                <img src="{{ asset("landing/{$key}-600.webp") }}" srcset="{{ asset("landing/{$key}-300.webp") }} 300w, {{ asset("landing/{$key}-450.webp") }} 450w, {{ asset("landing/{$key}-600.webp") }} 600w" sizes="204px" width="300" height="608" loading="lazy" decoding="async" alt="{{ __('landing.styles.alt', ['style' => $styleName]) }}">
                            </div>
                            <h3>{{ $styleName }}</h3>
                            <p>{{ $styleText }}</p>
                            @if ($styles[$key])
                                <a class="link" href="{{ $styles[$key] }}" target="_blank" rel="noopener" aria-label="{{ __('landing.styles.view_live_label', ['style' => $styleName]) }}">{{ __('landing.styles.view_live') }} {!! $icon('external') !!}</a>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($free && $signupOpen)
        <section class="free-wrap" aria-labelledby="free-title-2">
            <div class="wrap">
                <div class="free reveal">
                    <div>
                        <span class="free-big" aria-hidden="true">{{ __('landing.free.badge') }}</span>
                    </div>
                    <div class="free-body">
                        <h2 id="free-title-2">{{ __('landing.free.title') }}</h2>
                        <p>{{ __('landing.free.text') }}</p>
                        @if (false)
                            <p class="free-inc">{{ __('landing.free.includes') }}</p>
                            <ul>
                                @foreach (array_slice([...$free['limits'], ...$free['features']], 0, 6) as $line)
                                    <li>{!! $icon('check') !!}<span>{{ $line }}</span></li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                    <div class="free-actions">
                        <a class="btn btn-primary btn-lg" href="/auth/sign-up">{{ __('landing.create_account') }} {!! $icon('arrow') !!}</a>
                        @if ($sections['pricing'])<a class="link" href="#pricing">{{ __('landing.free.more') }}</a>@endif
                    </div>
                </div>
            </div>
        </section>
    @endif

    @if ($sections['speed'])
        <section id="speed" aria-labelledby="speed-title">
            <div class="wrap speed">
                <div class="reveal">
                    <h2 id="speed-title">{{ __('landing.speed.title') }}</h2>
                    <p class="text">{{ __('landing.speed.text') }}</p>
                    <ul class="ticks">
                        @foreach (__('landing.speed.points', ['name' => $platform]) as $point)
                            <li>{!! $icon('check') !!}<span>{{ $point }}</span></li>
                        @endforeach
                    </ul>
                </div>
                <figure class="card meter reveal" style="margin:0">
                    <figcaption class="label">{{ __('landing.speed.bar_label') }}</figcaption>
                    <div class="track" role="img" aria-label="{{ __('landing.speed.bar_limit') }}">
                        <span class="mark" aria-hidden="true">{{ __('landing.speed.bar_limit') }}</span>
                    </div>
                    <div class="scale" aria-hidden="true">
                        @foreach (__('landing.speed.bar_scale') as $tick)
                            <span>{{ $tick }}</span>
                        @endforeach
                    </div>
                </figure>
            </div>
        </section>
    @endif

    @if ($sections['pricing'])
        <section class="soft" id="pricing" aria-labelledby="pricing-title">
            <div class="wrap">
                <div class="head reveal"><h2 id="pricing-title">{{ __('landing.pricing.title') }}</h2><p>{{ __('landing.pricing.subtitle') }}</p></div>
                <div class="plans">
                    @foreach ($plans as $plan)
                        <article class="card plan reveal {{ $plan['highlight'] ? 'hl' : '' }}" aria-labelledby="plan-{{ $loop->index }}">
                            @if ($plan['highlight'])<span class="badge">{{ __('landing.pricing.popular') }}</span>@endif
                            <h3 id="plan-{{ $loop->index }}">{{ $plan['name'] }}</h3>
                            <p class="price">{{ $plan['price'] }} <small>{{ $plan['per'] }}</small></p>
                            <ul>
                                @foreach ($plan['limits'] as $line)
                                    <li class="muted-li">{!! $icon('check') !!}<span>{{ $line }}</span></li>
                                @endforeach
                                @foreach ($plan['features'] as $line)
                                    <li>{!! $icon('check') !!}<span>{{ $line }}</span></li>
                                @endforeach
                            </ul>
                            @if ($plan['free'])
                                @if ($signupOpen)
                                    <a class="btn btn-primary" href="/auth/sign-up">{{ __('landing.create_account') }}</a>
                                @endif
                            @elseif ($plan['contact'])
                                <a class="btn btn-ghost" href="{{ $plan['contact']['href'] }}" @if ($plan['contact']['whatsapp']) target="_blank" rel="noopener" @endif>{!! $icon('message') !!} {{ $plan['contact']['label'] }}</a>
                            @endif
                        </article>
                    @endforeach
                </div>
                <p class="note">{{ __('landing.pricing.upgrade_note') }}</p>
            </div>
        </section>
    @endif

    @if ($sections['testimonials'])
        <section id="testimonials" aria-labelledby="testimonials-title">
            <div class="wrap">
                <div class="head reveal"><h2 id="testimonials-title">{{ __('landing.testimonials.title') }}</h2></div>
                <div class="quotes">
                    @foreach ($testimonials as $quote)
                        <figure class="card reveal" style="margin:0">
                            <blockquote><p>“{{ $quote['quote'] }}”</p></blockquote>
                            <figcaption class="muted" style="margin-top:14px;color:var(--muted)">— {{ $quote['name'] }}@if (! empty($quote['role'])), {{ $quote['role'] }}@endif</figcaption>
                        </figure>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($sections['faq'])
        <section class="{{ $sections['testimonials'] ? 'soft' : '' }}" id="faq" aria-labelledby="faq-title">
            <div class="wrap">
                <div class="head reveal"><h2 id="faq-title">{{ __('landing.faq.title') }}</h2></div>
                <div class="faq">
                    @foreach (__('landing.faq.items') as $item)
                        <details class="reveal">
                            <summary>{{ $item['q'] }}</summary>
                            <p class="answer">{{ $item['a'] }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($sections['cta'])
        <section aria-labelledby="cta-title">
            <div class="wrap">
                <div class="band reveal">
                    <h2 id="cta-title">{{ __('landing.cta.title') }}</h2>
                    <p>{{ __('landing.cta.text') }}</p>
                    <p class="band-trust">{{ __('landing.cta.trust') }}</p>
                    @if ($signupOpen)
                        <a class="btn btn-lg" href="/auth/sign-up">{{ __('landing.create_account') }} {!! $icon('arrow') !!}</a>
                    @elseif ($demoUrl)
                        <a class="btn btn-lg" href="{{ $demoUrl }}" target="_blank" rel="noopener">{{ __('landing.hero.demo') }}</a>
                    @endif
                </div>
            </div>
        </section>
    @endif
</main>

@if ($signupOpen)
    <div class="sticky-cta" id="sticky-cta" hidden>
        <span><b class="free-tag">{{ __('landing.free.badge') }}</b> {{ __('landing.free.sticky') }}</span>
        <a class="btn btn-primary" href="/auth/sign-up">{{ __('landing.create_account') }}</a>
    </div>
@endif

<footer class="site-footer">
    <div class="wrap">
        <div class="foot">
            <div>
                <a class="brand" href="/">
                    @if ($logo)
                        <img src="{{ $logo }}" alt="" height="32" loading="lazy">
                    @else
                        <span class="brand-mark" aria-hidden="true">{{ mb_strtoupper(mb_substr($platform, 0, 1)) }}</span>
                    @endif
                    <span>{{ $platform }}</span>
                </a>
                <p class="about">{{ __('landing.footer.about') }}</p>
            </div>
            <nav aria-labelledby="foot-links">
                <h2 id="foot-links">{{ __('landing.footer.links') }}</h2>
                <ul>
                    <li><a href="/terms">{{ __('landing.footer.terms') }}</a></li>
                    <li><a href="/privacy">{{ __('landing.footer.privacy') }}</a></li>
                    <li><a href="/auth/sign-in">{{ __('landing.sign_in') }}</a></li>
                    @if ($supportEmail)
                        <li><a href="mailto:{{ $supportEmail }}">{{ __('landing.footer.contact') }}</a></li>
                    @endif
                </ul>
            </nav>
            @if ($contact['whatsapp'] || $socials)
                <nav aria-labelledby="foot-follow">
                    <h2 id="foot-follow">{{ __('landing.footer.follow') }}</h2>
                    <ul>
                        @if ($contact['whatsapp'])
                            <li><a href="{{ $contact['whatsapp'] }}" target="_blank" rel="noopener">{{ __('landing.social.whatsapp') }}</a></li>
                        @endif
                        @foreach ($socials as $network => $href)
                            <li><a href="{{ $href }}" target="_blank" rel="noopener me">{{ __("landing.social.{$network}") }}</a></li>
                        @endforeach
                    </ul>
                </nav>
            @endif
        </div>
        <p class="copy">{{ __('landing.footer.rights', ['year' => date('Y'), 'name' => $platform]) }}</p>
    </div>
</footer>

<script>
(function () {
    var header = document.querySelector('.site-header');
    var onScroll = function () { header.classList.toggle('scrolled', window.scrollY > 8); };
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });

    var btn = document.querySelector('.menu-btn'), panel = document.getElementById('mobile-nav');
    var set = function (open) {
        btn.setAttribute('aria-expanded', String(open));
        btn.setAttribute('aria-label', open ? btn.dataset.close : btn.dataset.open);
        btn.querySelector('[data-icon=open]').hidden = open;
        btn.querySelector('[data-icon=close]').hidden = !open;
        panel.hidden = !open;
    };
    btn.addEventListener('click', function () { set(panel.hidden); });
    panel.addEventListener('click', function (e) { if (e.target.closest('a')) set(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !panel.hidden) { set(false); btn.focus(); } });

    var sticky = document.getElementById('sticky-cta'), hero = document.querySelector('.hero'), band = document.querySelector('.band');
    if (sticky && 'IntersectionObserver' in window) {
        var seen = { hero: true, band: false };
        var update = function () { sticky.hidden = seen.hero || seen.band; };
        document.body.classList.add('has-sticky');
        new IntersectionObserver(function (es) { es.forEach(function (e) { seen[e.target === hero ? 'hero' : 'band'] = e.isIntersecting; }); update(); }).observe(hero);
        if (band) new IntersectionObserver(function (es) { seen.band = es[0].isIntersecting; update(); }).observe(band);
    }

    var items = document.querySelectorAll('.reveal');
    if (!('IntersectionObserver' in window)) { items.forEach(function (el) { el.classList.add('in'); }); return; }
    var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } });
    }, { rootMargin: '0px 0px -8% 0px' });
    items.forEach(function (el) { io.observe(el); });
})();
</script>
</body>
</html>
