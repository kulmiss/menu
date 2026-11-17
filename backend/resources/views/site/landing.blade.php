@php
    /** Lucide icons (ISC licence), drawn inline. */
    $paths = [
        'check' => '<path d="M20 6 9 17l-5-5"/>',
        'x' => '<path d="M18 6 6 18M6 6l12 12"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'arrow' => '<path d="M5 12h14M12 5l7 7-7 7"/>',
        'external' => '<path d="M15 3h6v6M10 14 21 3M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
        'message' => '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/>',
        'mail' => '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>',
        'printer' => '<path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect width="12" height="8" x="6" y="14"/>',
        'receipt' => '<path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8M12 17.5v-11"/>',
        'clock' => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'user' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/>',
        'camera' => '<path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/><circle cx="12" cy="13" r="3"/>',
        'qr' => '<rect width="5" height="5" x="3" y="3" rx="1"/><rect width="5" height="5" x="16" y="3" rx="1"/><rect width="5" height="5" x="3" y="16" rx="1"/><path d="M21 16h-3a2 2 0 0 0-2 2v3M21 21v.01M12 7v3a2 2 0 0 1-2 2H7M3 12h.01M12 3h.01M12 16v.01M16 12h1M21 12v.01M12 21v-1"/>',
        'styles' => '<path d="M12 22a1 1 0 0 1 0-20 10 9 0 0 1 10 9 5 5 0 0 1-5 5h-2.25a1.75 1.75 0 0 0-1.4 2.8l.3.4a1.75 1.75 0 0 1-1.4 2.8z"/><circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/>',
        'prices' => '<path d="M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42z"/><circle cx="7.5" cy="7.5" r=".5" fill="currentColor"/>',
        'promotions' => '<path d="M19 5 5 19"/><circle cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/>',
        'pdf' => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4M10 9H8M16 13H8M16 17H8"/>',
        'insights' => '<path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/>',
    ];
    $paths['tables'] = $paths['qr'];
    $paths['print'] = $paths['printer'];
    $paths['old'] = $paths['receipt'];
    $paths['wait'] = $paths['clock'];
    $icon = fn (string $name, string $class = 'icon') => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$paths[$name].'</svg>';
    // The main button of a section: "Create my free menu", or "Contact us" when sign-up is off.
    $ctaButton = function (string $class = 'btn btn-primary btn-lg', bool $arrow = true) use ($cta, $icon): string {
        if ($cta === null) {
            return '';
        }
        $attrs = 'class="'.e($class).'" href="'.e($cta['href']).'"'
            .($cta['external'] ? ' target="_blank" rel="noopener"' : '')
            .($cta['track'] ? ' data-track="'.e($cta['track']).'"' : '');

        return '<a '.$attrs.'>'.($cta['track'] ? '' : $icon('message')).e($cta['label']).($arrow && $cta['track'] ? ' '.$icon('arrow') : '').'</a>';
    };
    $nav = array_filter([
        'demo' => $sections['demo'],
        'how' => $sections['how'],
        'features' => $sections['features'],
        'pricing' => $sections['pricing'],
        'faq' => $sections['faq'],
    ]);
    $title = $t('meta_title', ['name' => $platform]);
    // A phone screenshot (built-in or uploaded in Super Admin), light and dark, AVIF when there is one.
    $picture = function (string $slot, string $alt, bool $lazy) use ($shots): string {
        $sources = '';
        foreach ([[$shots[$slot.'_dark'], ' media="(prefers-color-scheme: dark)"'], [$shots[$slot], '']] as [$shot, $media]) {
            if ($shot['avif']) {
                $sources .= '<source type="image/avif"'.$media.' srcset="'.e($shot['avif']).'" sizes="240px">';
            }
            if ($media !== '') {
                $sources .= '<source type="image/webp"'.$media.' srcset="'.e($shot['webp']).'" sizes="240px">';
            }
        }

        return '<picture>'.$sources.'<img src="'.e($shots[$slot]['src']).'" srcset="'.e($shots[$slot]['webp']).'" sizes="240px" width="300" height="608" alt="'.e($alt).'"'
            .($lazy ? ' loading="lazy" decoding="async"' : ' fetchpriority="high"').'></picture>';
    };
    $preload = fn (array $shot, string $scheme) => '<link rel="preload" as="image" type="image/'.($shot['avif'] ? 'avif' : 'webp').'" imagesrcset="'.e($shot['avif'] ?? $shot['webp']).'" imagesizes="240px" media="(prefers-color-scheme: '.$scheme.')" fetchpriority="high">';
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
    <meta property="og:image:alt" content="{{ $t('hero.phone_alt') }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title }}">
    <meta name="twitter:description" content="{{ $description }}">
    <meta name="twitter:image" content="{{ $ogImage }}">
    {!! $preload($shots['hero'], 'light') !!}
    {!! $preload($shots['hero_dark'], 'dark') !!}
    <script type="application/ld+json">{!! $jsonLd !!}</script>
    <script nonce="{{ \App\Support\Security\Csp::nonce() }}">document.documentElement.classList.add('js')</script>
    <style>
        :root{color-scheme:light dark;--bg:#fff;--fg:#0f172a;--muted:#475569;--card:#fff;--soft:#f6f7f9;--line:#e4e7ec;--brand:{{ $colors['brand'] }};--on-brand:{{ $colors['on_brand'] }};--ink:{{ $colors['ink'] }};--shadow:0 1px 2px rgb(15 23 42/.06),0 12px 32px -8px rgb(15 23 42/.12);--radius:20px;--tint:color-mix(in srgb,var(--brand) 10%,var(--card))}
        @media (prefers-color-scheme:dark){.phone{box-shadow:0 30px 60px -20px rgb(0 0 0/.6),0 0 0 1px rgb(255 255 255/.16)}:root{--bg:{{ \App\Support\BrandColor::DARK_BG }};--fg:#f1f5f9;--muted:#a7b3c5;--card:#121a2e;--soft:#0f1628;--line:#24304a;--ink:{{ $colors['ink_dark'] }};--shadow:0 1px 2px rgb(0 0 0/.4),0 12px 32px -8px rgb(0 0 0/.5);--tint:color-mix(in srgb,var(--brand) 16%,var(--card))}}
        *,*::before,*::after{box-sizing:border-box}
        html{scroll-behavior:smooth;scroll-padding-top:72px;-webkit-text-size-adjust:100%}
        @media (prefers-reduced-motion:reduce){html{scroll-behavior:auto}}
        body{margin:0;font-family:ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,"Noto Sans","Noto Sans Ethiopic",Arial,sans-serif;line-height:1.6;background:var(--bg);color:var(--fg);-webkit-font-smoothing:antialiased}
        img,svg{display:block;max-width:100%}
        a{color:inherit}
        h1,h2,h3{line-height:1.1;letter-spacing:-.025em;margin:0;text-wrap:balance}
        p{margin:0}
        .wrap{width:100%;max-width:1200px;margin:0 auto;padding:0 20px}
        @media (min-width:768px){.wrap{padding:0 32px}}
        .icon{width:20px;height:20px;flex:none}
        .skip{position:absolute;left:-9999px;top:8px;z-index:100;background:var(--fg);color:var(--bg);padding:10px 16px;border-radius:8px}
        .skip:focus{left:8px}
        :focus-visible{outline:3px solid var(--ink);outline-offset:3px;border-radius:6px}
        .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:44px;padding:0 20px;border-radius:999px;font-weight:650;font-size:1rem;text-decoration:none;border:1px solid transparent;transition:transform .15s,box-shadow .15s,background-color .15s;white-space:nowrap;cursor:pointer}
        .btn-primary{background:var(--brand);color:var(--on-brand);box-shadow:0 10px 24px -10px color-mix(in srgb,var(--brand) 80%,transparent)}
        .btn-primary:hover{transform:translateY(-1px);box-shadow:0 14px 30px -10px color-mix(in srgb,var(--brand) 90%,transparent)}
        .btn-ghost{border-color:var(--line);background:var(--card)}
        .btn-ghost:hover{border-color:var(--muted)}
        .btn-lg{min-height:56px;padding:0 28px;font-size:1.075rem}
        @media (prefers-reduced-motion:reduce){.btn,.btn-primary:hover{transition:none;transform:none}}
        .link{display:inline-flex;align-items:center;gap:6px;color:var(--ink);font-weight:650;min-height:44px;text-underline-offset:4px}
        .link .icon{width:18px;height:18px}
        /* Header */
        .site-header{position:sticky;top:0;z-index:50;background:color-mix(in srgb,var(--bg) 85%,transparent);backdrop-filter:saturate(1.6) blur(12px);-webkit-backdrop-filter:saturate(1.6) blur(12px);border-bottom:1px solid transparent;transition:border-color .2s}
        .site-header.scrolled{border-bottom-color:var(--line)}
        .bar{display:flex;align-items:center;gap:16px;height:68px;transition:height .2s}
        .scrolled .bar{height:58px}
        .brand{display:inline-flex;align-items:center;gap:10px;font-weight:800;font-size:1.15rem;text-decoration:none;min-height:44px;margin-right:auto}
        .brand img{height:32px;width:auto}
        .brand-mark{width:32px;height:32px;border-radius:9px;background:var(--brand);color:var(--on-brand);display:grid;place-items:center;font-size:1rem}
        .nav-links{display:none;gap:2px}
        .nav-links a{text-decoration:none;color:var(--muted);font-weight:500;padding:10px 12px;border-radius:8px;min-height:44px;display:inline-flex;align-items:center}
        .nav-links a:hover{color:var(--fg)}
        .bar .actions{display:none;gap:8px;align-items:center}
        .menu-btn{display:inline-grid;place-items:center;width:44px;height:44px;border-radius:12px;border:1px solid var(--line);background:var(--card);color:var(--fg);cursor:pointer}
        .mobile-nav{border-top:1px solid var(--line);padding-top:12px;padding-bottom:20px}
        .mobile-nav a:not(.btn){display:flex;align-items:center;min-height:48px;text-decoration:none;font-weight:600;border-bottom:1px solid var(--line)}
        .mobile-nav .btn{width:100%;margin-top:12px}
        @media (min-width:1040px){.nav-links,.bar .actions{display:flex}.menu-btn,.mobile-nav{display:none!important}}
        /* Hero */
        .hero{position:relative;isolation:isolate;overflow:hidden;padding:40px 0 72px}
        .hero::before{content:"";position:absolute;inset:-30% -20% 0;background:radial-gradient(40% 45% at 12% 18%,color-mix(in srgb,var(--brand) 24%,transparent),transparent 70%),radial-gradient(35% 40% at 88% 28%,color-mix(in srgb,var(--brand) 16%,transparent),transparent 70%),radial-gradient(40% 30% at 60% 100%,color-mix(in srgb,var(--brand) 10%,transparent),transparent 70%);z-index:-1}
        .hero::after{content:"";position:absolute;inset:0;background-image:radial-gradient(color-mix(in srgb,var(--fg) 12%,transparent) 1px,transparent 1px);background-size:22px 22px;mask-image:linear-gradient(to bottom,#000,transparent 80%);-webkit-mask-image:linear-gradient(to bottom,#000,transparent 80%);z-index:-1;opacity:.45}
        .hero-grid{display:grid;gap:48px;align-items:center}
        @media (min-width:960px){.hero{padding:88px 0 112px}.hero-grid{grid-template-columns:1.2fr .8fr}}
        .eyebrow{display:inline-flex;align-items:center;gap:8px;font-size:.875rem;font-weight:650;color:var(--ink);background:var(--tint);border:1px solid color-mix(in srgb,var(--brand) 25%,var(--line));padding:6px 14px;border-radius:999px}
        .hero h1{font-size:clamp(2.5rem,7vw,4.6rem);font-weight:850;margin:20px 0}
        .hero .lead{font-size:clamp(1.1rem,2.3vw,1.3rem);color:var(--muted);max-width:34rem}
        .cta-row{display:flex;flex-wrap:wrap;align-items:center;gap:12px 20px;margin-top:32px}
        @media (max-width:520px){.cta-row .btn{width:100%}}
        /* Google's sign-in button: white, the four-colour G, never the brand colour. */
        .btn-google{background:#fff;color:#1f1f1f;border-color:#747775;box-shadow:none}
        .btn-google:hover{background:#f8f9fa}
        @media (prefers-color-scheme:dark){.btn-google{background:#131314;color:#e3e3e3;border-color:#8e918f}.btn-google:hover{background:#1f1f20}}
        .g-logo{width:20px;height:20px;flex:none}
        .trust{display:flex;flex-wrap:wrap;gap:8px 20px;margin:24px 0 0;padding:0;list-style:none;color:var(--muted);font-size:.95rem;font-weight:500}
        .trust li{display:inline-flex;align-items:center;gap:6px}
        .trust .icon{color:var(--ink);width:18px;height:18px}
        .stage{position:relative;justify-self:center;padding:0 24px}
        .phone{position:relative;width:260px;border-radius:44px;padding:10px;background:#0b0f19;box-shadow:0 40px 80px -24px rgb(15 23 42/.45),0 0 0 1px rgb(255 255 255/.06) inset}
        .phone::before{content:"";position:absolute;top:18px;left:50%;width:76px;height:20px;margin-left:-38px;background:#0b0f19;border-radius:999px;z-index:2}
        .phone img{width:100%;height:auto;border-radius:34px;background:var(--soft)}
        .glow{position:absolute;inset:10% -10%;background:radial-gradient(closest-side,color-mix(in srgb,var(--brand) 26%,transparent),transparent);z-index:-1}
        .qr-card{position:absolute;left:-90px;bottom:56px;z-index:3;display:none;align-items:center;gap:12px;background:var(--card);color:var(--fg);padding:12px 14px 12px 12px;border-radius:18px;box-shadow:var(--shadow);border:1px solid var(--line);max-width:230px}
        .qr-card svg{width:76px;height:76px;background:#fff;border-radius:8px;padding:4px;flex:none}
        .qr-card strong{display:block;font-size:.95rem}
        .qr-card span{font-size:.8rem;color:var(--muted);line-height:1.35;display:block}
        @media (min-width:960px){.qr-card{display:flex}}
        .float{animation:float 6s ease-in-out infinite}
        @keyframes float{50%{transform:translateY(-8px)}}
        @media (prefers-reduced-motion:reduce){.float{animation:none}}
        /* Sections */
        section{padding:64px 0;overflow-x:clip}
        @media (min-width:960px){section{padding:112px 0}}
        .soft{background:var(--soft)}
        .head{max-width:42rem;margin:0 auto 48px;text-align:center}
        .head h2,.split h2{font-size:clamp(2rem,4.6vw,3rem);font-weight:850}
        .head p,.split .text{color:var(--muted);margin-top:14px;font-size:1.15rem}
        .kicker{display:block;color:var(--ink);font-weight:700;font-size:.95rem;letter-spacing:.02em;margin-bottom:12px}
        .more{display:flex;justify-content:center;margin-top:44px}
        .card{background:var(--card);border:1px solid var(--line);border-radius:var(--radius);padding:26px}
        .card h3{font-size:1.2rem;font-weight:750;margin-bottom:6px;letter-spacing:-.015em}
        .card p{color:var(--muted)}
        .tile{width:52px;height:52px;border-radius:16px;display:grid;place-items:center;background:var(--tint);color:var(--ink);margin-bottom:18px}
        .tile .icon{width:26px;height:26px}
        .grid3{display:grid;gap:18px}
        @media (min-width:700px){.grid3{grid-template-columns:repeat(3,1fr)}}
        .grid-features{display:grid;gap:18px}
        @media (min-width:640px){.grid-features{grid-template-columns:repeat(2,1fr)}}
        @media (min-width:1024px){.grid-features{grid-template-columns:repeat(3,1fr)}}
        .card.hover{transition:transform .2s,box-shadow .2s}
        .card.hover:hover{transform:translateY(-3px);box-shadow:var(--shadow)}
        @media (prefers-reduced-motion:reduce){.card.hover,.card.hover:hover{transition:none;transform:none}}
        .tag{display:inline-block;margin-left:6px;vertical-align:middle;font-size:.75rem;font-weight:700;letter-spacing:.01em;padding:2px 8px;border-radius:999px;background:var(--soft);color:var(--muted);border:1px solid var(--line)}
        .pain{border-style:dashed}
        .pain .tile{background:color-mix(in srgb,var(--fg) 6%,var(--card));color:var(--muted)}
        .fix{display:flex;flex-direction:column;align-items:center;gap:10px;margin:40px auto 0;max-width:40rem;text-align:center;font-size:1.2rem;font-weight:650;text-wrap:balance}
        .fix .icon{color:var(--on-brand);background:var(--brand);border-radius:999px;padding:5px;width:30px;height:30px}
        @media (min-width:700px){.fix{flex-direction:row;justify-content:center}}
        /* Phones: icon beside the text, so the cards stay short. */
        @media (max-width:699px){.grid3 .card,.grid-features .card{display:grid;grid-template-columns:auto 1fr;column-gap:16px;align-items:start;padding:20px}.grid3 .tile,.grid-features .tile{grid-row:span 2;margin-bottom:0;width:46px;height:46px}}
        /* Demo */
        .split{display:grid;gap:48px;align-items:center}
        @media (min-width:960px){.split{grid-template-columns:1fr 1fr}}
        .scan{display:none;align-items:center;gap:20px;margin-top:32px;padding:18px;border-radius:var(--radius);background:var(--card);border:1px solid var(--line);box-shadow:var(--shadow);max-width:28rem}
        .scan svg{width:132px;height:132px;background:#fff;border-radius:12px;padding:8px;flex:none}
        .scan strong{display:block;font-size:1.15rem}
        .scan span{display:block;color:var(--muted)}
        .scan .link{margin-top:4px}
        .open-demo{margin-top:28px}
        .open-demo .btn{width:100%}
        @media (min-width:960px){.scan{display:flex}.open-demo{display:none}}
        .looks{display:flex;flex-wrap:wrap;align-items:center;gap:4px 14px;margin-top:20px;color:var(--muted)}
        .looks .link{font-weight:600}
        .demo-phone{justify-self:center;position:relative;text-decoration:none}
        /* How */
        .steps{display:grid;gap:40px;margin:0;padding:0;list-style:none;position:relative}
        @media (min-width:768px){.steps{grid-template-columns:repeat(3,1fr)}.steps::before{content:"";position:absolute;top:52px;left:16%;right:16%;border-top:2px dashed color-mix(in srgb,var(--brand) 35%,var(--line))}}
        .step{position:relative;text-align:center;background:transparent;border:0;padding:0 12px}
        .step .tile{margin:0 auto 18px;width:76px;height:76px;border-radius:24px;background:var(--brand);color:var(--on-brand);box-shadow:0 14px 30px -14px color-mix(in srgb,var(--brand) 90%,transparent);position:relative}
        .step .tile .icon{width:32px;height:32px}
        .step .num{position:absolute;top:-8px;right:-8px;width:28px;height:28px;border-radius:999px;background:var(--card);color:var(--fg);border:1px solid var(--line);font-size:.85rem;font-weight:800;display:grid;place-items:center}
        /* Compare */
        .compare{display:grid;gap:18px;max-width:920px;margin:0 auto}
        @media (min-width:768px){.compare{grid-template-columns:1fr 1fr}}
        .compare h3{display:flex;align-items:center;gap:10px;font-size:1.3rem;margin-bottom:18px}
        .compare ul{list-style:none;margin:0;padding:0;display:grid;gap:14px}
        .compare li{display:flex;gap:12px;align-items:flex-start;font-size:1.05rem}
        .compare li .icon{margin-top:3px;width:22px;height:22px;padding:3px;border-radius:999px}
        .before{background:transparent;border-style:dashed}
        .before li{color:var(--muted)}
        .before li .icon{background:color-mix(in srgb,var(--fg) 8%,transparent)}
        .after{border:2px solid var(--brand);background:var(--tint);box-shadow:var(--shadow)}
        .after li .icon{background:var(--brand);color:var(--on-brand)}
        /* Pricing */
        .plans{display:flex;flex-wrap:wrap;justify-content:center;gap:28px 20px}
        .plan{display:flex;flex-direction:column;gap:16px;position:relative;flex:1 1 230px;max-width:310px;min-width:0}
        .plan.hl{border:2px solid var(--brand);box-shadow:var(--shadow)}
        .badge{position:absolute;top:-14px;left:24px;background:var(--brand);color:var(--on-brand);font-size:.8rem;font-weight:750;padding:4px 12px;border-radius:999px}
        .price{color:var(--fg)!important;font-size:2rem;font-weight:850;letter-spacing:-.02em;line-height:1.2}
        .price small{font-size:.95rem;font-weight:500;color:var(--muted);letter-spacing:0;white-space:nowrap}
        .plan ul{list-style:none;margin:0;padding:0;display:grid;gap:8px;font-size:.95rem}
        .plan li{display:flex;gap:8px;align-items:flex-start}
        .plan li .icon{width:18px;height:18px;color:var(--ink);margin-top:3px}
        .plan .muted-li{color:var(--muted)}
        .plan .year{margin-top:-12px;color:var(--muted);font-size:.9rem}
        .plan .everything{font-weight:650;font-size:.95rem;margin-bottom:-6px}
        .plan .actions{margin-top:auto;display:grid;gap:4px;justify-items:center}
        .plan .btn{width:100%;white-space:normal;text-align:center;padding:8px 14px;line-height:1.25}
        .plan .link{font-size:.875rem;gap:4px;text-align:center}
        .note{text-align:center;color:var(--muted);margin-top:28px}
        .quotes{display:grid;gap:20px;grid-template-columns:repeat(auto-fit,minmax(260px,1fr))}
        .quotes blockquote{margin:0}
        .quotes p{color:var(--fg);font-size:1.075rem}
        .quotes figcaption{margin-top:14px;color:var(--muted)}
        .faq{max-width:48rem;margin:0 auto;display:grid;gap:12px}
        .faq details{background:var(--card);border:1px solid var(--line);border-radius:16px}
        .faq summary{cursor:pointer;list-style:none;display:flex;justify-content:space-between;gap:16px;align-items:center;padding:16px 20px;min-height:60px;font-weight:650;font-size:1.075rem}
        .faq summary::-webkit-details-marker{display:none}
        .faq summary::after{content:"+";font-size:1.5rem;line-height:1;color:var(--ink);transition:transform .2s}
        .faq details[open] summary::after{transform:rotate(45deg)}
        .faq .answer{padding:0 20px 18px;color:var(--muted)}
        .band{position:relative;overflow:hidden;border-radius:32px;padding:56px 24px;text-align:center;background:var(--brand);color:var(--on-brand)}
        .band::before{content:"";position:absolute;inset:0;background:radial-gradient(60% 80% at 10% 0%,rgb(255 255 255/.2),transparent 60%),radial-gradient(50% 70% at 100% 100%,rgb(0 0 0/.2),transparent 60%)}
        .band>*{position:relative}
        .band h2{font-size:clamp(2rem,4.6vw,3rem);font-weight:850}
        .band p{margin:14px auto 30px;max-width:32rem;font-size:1.15rem;opacity:.95}
        .band .btn{background:var(--on-brand);color:var(--brand);box-shadow:none}
        @media (min-width:768px){.band{padding:80px 40px}}
        .site-footer{border-top:1px solid var(--line);padding:48px 0 32px;font-size:.95rem}
        .foot{display:grid;gap:32px}
        @media (min-width:768px){.foot{grid-template-columns:2fr 1fr 1fr}}
        .foot h2{font-size:.85rem;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin-bottom:10px}
        .foot ul{list-style:none;margin:0;padding:0}
        .foot li a{display:inline-flex;align-items:center;min-height:44px;text-decoration:none}
        .foot li a:hover{text-decoration:underline}
        .foot .about{color:var(--muted);margin-top:12px;max-width:26rem}
        .copy{margin-top:32px;color:var(--muted)}
        .reveal{transition:opacity .7s ease,transform .7s ease}
        .js .reveal{opacity:0;transform:translateY(20px)}
        .js .reveal.in{opacity:1;transform:none}
        @media (prefers-reduced-motion:reduce){.js .reveal{opacity:1;transform:none;transition:none}}
        /* Phone: sticky sign-up bar. Desktop: the WhatsApp chat button. */
        .sticky-cta{position:fixed;left:12px;right:12px;bottom:12px;z-index:40;display:flex;align-items:center;gap:8px;padding:8px;border-radius:999px;background:var(--card);border:1px solid var(--line);box-shadow:var(--shadow)}
        .sticky-cta .btn{flex:1;min-height:48px;padding:0 16px}
        .sticky-cta .chat-icon{flex:none;width:48px;min-height:48px;padding:0;border-radius:999px}
        .sticky-cta[hidden]{display:none}
        @media (min-width:960px){.sticky-cta{display:none!important}}
        .has-sticky .site-footer{padding-bottom:104px}
        .chat{position:fixed;right:24px;bottom:24px;z-index:40;display:none;box-shadow:var(--shadow)}
        @media (min-width:960px){.chat{display:inline-flex}}
        .sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);border:0}
    </style>
</head>
<body>
<a class="skip" href="#content">{{ $t('skip') }}</a>

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
        <nav class="nav-links" aria-label="{{ $t('nav.label') }}">
            @foreach ($nav as $key => $on)
                <a href="#{{ $key }}">{{ $t("nav.{$key}") }}</a>
            @endforeach
        </nav>
        <div class="actions">
            <a class="btn btn-ghost" href="/auth/sign-in">{{ $t('sign_in') }}</a>
            {!! $ctaButton('btn btn-primary', false) !!}
        </div>
        <button class="menu-btn" type="button" aria-expanded="false" aria-controls="mobile-nav" data-open="{{ $t('nav.menu') }}" data-close="{{ $t('nav.close') }}" aria-label="{{ $t('nav.menu') }}">
            <span data-icon="open">{!! $icon('menu') !!}</span><span data-icon="close" hidden>{!! $icon('x') !!}</span>
        </button>
    </div>
    <nav class="wrap mobile-nav" id="mobile-nav" aria-label="{{ $t('nav.label') }}" hidden>
        @foreach ($nav as $key => $on)
            <a href="#{{ $key }}">{{ $t("nav.{$key}") }}</a>
        @endforeach
        <a href="/auth/sign-in">{{ $t('sign_in') }}</a>
        {!! $ctaButton('btn btn-primary', false) !!}
    </nav>
</header>

<main id="content">
    <div class="hero">
        <div class="wrap hero-grid">
            <div>
                <span class="eyebrow">{{ $t('hero.eyebrow') }}</span>
                <h1>{{ $headline }}</h1>
                <p class="lead">{{ $subline }}</p>
                <div class="cta-row">
                    @if ($cta)
                        {!! $ctaButton() !!}
                    @else
                        <a class="btn btn-primary btn-lg" href="/auth/sign-in">{{ $t('sign_in') }}</a>
                    @endif
                    @if ($google)
                        <a class="btn btn-google btn-lg" href="/auth/google" data-track="google"><svg class="g-logo" viewBox="0 0 48 48" aria-hidden="true"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg> {{ $t('google') }}</a>
                    @endif
                    @if ($demoUrl)
                        <a class="link" href="{{ $demoUrl }}" target="_blank" rel="noopener" data-track="demo">{{ $t('hero.demo') }} {!! $icon('arrow') !!}<span class="sr-only"> {{ $t('new_tab') }}</span></a>
                    @endif
                </div>
                <ul class="trust">
                    @foreach (array_keys(__('landing.hero.trust')) as $key)
                        {{-- "Free plan forever" only while a free sign-up plan exists. --}}
                        @continue($key === 'free' && ! $free)
                        <li>{!! $icon('check') !!} {{ $t("hero.trust.{$key}") }}</li>
                    @endforeach
                </ul>
            </div>
            <div class="stage">
                <div class="glow" aria-hidden="true"></div>
                <div class="phone">
                    {!! $picture('hero', $t('hero.phone_alt'), false) !!}
                </div>
                @if ($qr)
                    <div class="qr-card float">
                        <svg viewBox="0 0 {{ $qr['size'] }} {{ $qr['size'] }}" role="img" aria-label="{{ $t('hero.qr_alt') }}" shape-rendering="crispEdges"><path fill="#0b0f19" d="{{ $qr['path'] }}"/></svg>
                        <div><strong>{{ $t('hero.qr_title') }}</strong><span>{{ $demoUrl ? $t('hero.qr_demo') : $t('hero.qr_signup') }}</span></div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @foreach ($order as $section)
        @include('site.landing.'.$section, ['soft' => $loop->odd])
    @endforeach
</main>

@if ($cta)
    <div class="sticky-cta" id="sticky-cta" hidden>
        {!! $ctaButton('btn btn-primary', false) !!}
        @if ($chat && $cta['track'])
            <a class="btn btn-ghost chat-icon" href="{{ $chat }}" target="_blank" rel="noopener" aria-label="{{ $t('chat.label') }} {{ $t('new_tab') }}">{!! $icon('message') !!}</a>
        @endif
    </div>
@endif
@if ($chat)
    <a class="btn btn-ghost chat" href="{{ $chat }}" target="_blank" rel="noopener">{!! $icon('message') !!} {{ $t('chat.label') }}<span class="sr-only"> {{ $t('new_tab') }}</span></a>
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
                <p class="about">{{ $t('footer.about') }}</p>
            </div>
            <nav aria-labelledby="foot-links">
                <h2 id="foot-links">{{ $t('footer.links') }}</h2>
                <ul>
                    <li><a href="/terms">{{ $t('footer.terms') }}</a></li>
                    <li><a href="/privacy">{{ $t('footer.privacy') }}</a></li>
                    <li><a href="/auth/sign-in">{{ $t('sign_in') }}</a></li>
                    @if ($supportEmail)
                        <li><a href="mailto:{{ $supportEmail }}">{{ $t('footer.contact') }}</a></li>
                    @endif
                </ul>
            </nav>
            @if ($contact['whatsapp'] || $socials)
                <nav aria-labelledby="foot-follow">
                    <h2 id="foot-follow">{{ $t('footer.follow') }}</h2>
                    <ul>
                        @if ($contact['whatsapp'])
                            <li><a href="{{ $contact['whatsapp'] }}" target="_blank" rel="noopener">{{ $t('social.whatsapp') }}</a></li>
                        @endif
                        @foreach ($socials as $network => $href)
                            <li><a href="{{ $href }}" target="_blank" rel="noopener me">{{ $t("social.{$network}") }}</a></li>
                        @endforeach
                    </ul>
                </nav>
            @endif
        </div>
        <p class="copy">{{ $t('footer.rights', ['year' => date('Y'), 'name' => $platform]) }}</p>
    </div>
</footer>

<script nonce="{{ \App\Support\Security\Csp::nonce() }}">
(function () {
    var header = document.querySelector('.site-header');
    // Not called on load: reading the scroll position there would force a layout. The page opens at the top.
    var onScroll = function () { header.classList.toggle('scrolled', window.scrollY > 8); };
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

    // Counts clicks on the main buttons for Super Admin → Landing page: only the
    // button's name is sent. No cookie, nothing about the visitor.
    document.addEventListener('click', function (e) {
        var a = e.target.closest && e.target.closest('[data-track]');
        if (a && navigator.sendBeacon) { navigator.sendBeacon('/api/v1/landing/click', new URLSearchParams({ k: a.dataset.track })); }
    });

    if (!('IntersectionObserver' in window)) {
        document.querySelectorAll('.reveal').forEach(function (el) { el.classList.add('in'); });
        return;
    }

    // Phone bar: shown once the hero has scrolled away, hidden again at the final band and the footer.
    var sticky = document.getElementById('sticky-cta');
    if (sticky) {
        var watched = [document.querySelector('.hero'), document.querySelector('.band'), document.querySelector('.site-footer')].filter(Boolean);
        var seen = new Map();
        document.body.classList.add('has-sticky');
        var io2 = new IntersectionObserver(function (es) {
            es.forEach(function (e) { seen.set(e.target, e.isIntersecting); });
            sticky.hidden = watched.some(function (el) { return seen.get(el) !== false; });
        });
        watched.forEach(function (el) { io2.observe(el); });
    }

    var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } });
    }, { rootMargin: '0px 0px -8% 0px' });
    document.querySelectorAll('.reveal').forEach(function (el) { io.observe(el); });
})();
</script>
</body>
</html>
