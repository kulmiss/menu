@php
    /*
     * Landing 2 (owner, 2026-11-22): "Bold blocks", in English (/landing2, "/") and Amharic
     * (/am/landing2, "/am"). Every text comes from Landing2Texts ($t), every part has an ON/OFF
     * switch in Super Admin → Landing 2 ($on). Live data: plans and prices, the demo menus, the
     * real numbers, restaurants and payment methods. Nothing heavy loads on open: pictures below
     * the hero, the style switcher's screens and the video load when tapped or scrolled near.
     */
    $title = $t('meta_title', ['name' => $platform]);
    $count = count($allStyles);
    $last = strtoupper(chr(ord('a') + $count - 1));
    $letter = fn (int $i) => strtoupper(chr(ord('a') + $i));
    $am = $locale === 'am';
    // Line icons drawn for this page (24 × 24, no icon package, no brand logos).
    $paths = [
        'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'dish' => '<path d="M3 15h18M5 15a7 7 0 0 1 14 0M12 8V6M9 19h6"/>',
        'palette' => '<circle cx="12" cy="12" r="9"/><circle cx="8.5" cy="10" r="1.2"/><circle cx="12" cy="7.5" r="1.2"/><circle cx="15.5" cy="10" r="1.2"/><path d="M12 21a3 3 0 0 1 0-6h1.5"/>',
        'qr' => '<rect x="3.5" y="3.5" width="6" height="6" rx="1"/><rect x="14.5" y="3.5" width="6" height="6" rx="1"/><rect x="3.5" y="14.5" width="6" height="6" rx="1"/><path d="M14.5 14.5h2.5v2.5M20.5 14.5v6h-6"/>',
        'order' => '<path d="M8 4h8l1 3H7zM6 7h12l-1 13H7z"/><path d="M10 11h4M10 15h4"/>',
        'languages' => '<path d="M4 6h9M8.5 4v2M6 6c0 4 3 7 6 8M11 6c0 3-2.5 6-6 8"/><path d="M13 20l4-9 4 9M14.5 17h5"/>',
        'fast' => '<path d="M13 3L5 14h6l-1 7 8-11h-6z"/>',
        'chart' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'check' => '<path d="M5 12.5l4.5 4.5L19 7"/>',
        'x' => '<path d="M6 6l12 12M18 6L6 18"/>',
        'print' => '<path d="M7 9V3h10v6M7 17H5a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2M7 14h10v7H7z"/>',
        'old' => '<path d="M20 12a8 8 0 1 1-2.3-5.7M20 4v4h-4"/><path d="M12 8v4l3 2"/>',
        'wait' => '<circle cx="12" cy="13" r="8"/><path d="M12 9v4l2.5 2.5M9 2h6"/>',
        'receipt' => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2zM9 8h6M9 12h6"/>',
        'chev' => '<path d="M15 6l-6 6 6 6"/>',
        'play' => '<path d="M8 5.5v13l10.5-6.5z" fill="currentColor"/>',
        'phone' => '<rect x="6.5" y="2.5" width="11" height="19" rx="2.5"/><path d="M10.5 18.5h3"/>',
        'bank' => '<path d="M3 10l9-6 9 6M5 10v8M9.5 10v8M14.5 10v8M19 10v8M3 20.5h18"/>',
        'card' => '<rect x="2.5" y="5.5" width="19" height="13" rx="2"/><path d="M2.5 10h19M6 15h4"/>',
        'cash' => '<rect x="2.5" y="6.5" width="19" height="11" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 9.5v5M18 9.5v5"/>',
        'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c3 3.2 3 14.8 0 18M12 3c-3 3.2-3 14.8 0 18"/>',
    ];
    $icon = fn (string $k, string $class = 'ic') => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$paths[$k].'</svg>';
    // $short: a shorter label on phones (the header), so it stays on one line.
    $ctaLink = function (string $class, ?string $short = null) use ($cta, $icon): string {
        if ($cta === null) {
            return '';
        }
        $label = $short === null ? e($cta['label'])
            : '<span class="lg">'.e($cta['label']).'</span><span class="sm">'.e($short).'</span>';

        return '<a class="'.e($class).'" href="'.e($cta['href']).'"'.($cta['external'] ? ' target="_blank" rel="noopener"' : '').'>'
            .$label.' '.$icon('arrow', 'ic ar').'</a>';
    };
    $hero = $shots['hero'];
    $heroDark = $shots['hero_dark'];
    // The switcher: styles with a screen; the hero phone is the demo café, so its chip starts checked.
    $first = $hero['custom'] ? null : $demoStyle;
    $switch = array_values(array_filter($allStyles, fn (array $s) => $s['switch'] !== null || $s['style'] === $first));
    $showSwitch = $on['switcher'] && count($switch) > 1;
    $heroSizes = '(min-width: 56rem) 260px, 220px';
    // Monthly / Yearly: only when a plan has a yearly price that saves months.
    $yearPlans = array_filter($plans, fn (array $p) => ($p['yearly']['months'] ?? 0) > 0);
    $billing = $on['billing'] && $yearPlans !== [];
    $saveMonths = $billing ? min(array_map(fn (array $p) => $p['yearly']['months'], $yearPlans)) : 0;
    $languages = $on['languages'] ? ['label' => $t('lang.label'), 'items' => [
        ['code' => 'en', 'name' => $t('lang.en'), 'href' => $urls['en'], 'current' => ! $am],
        ['code' => 'am', 'name' => $t('lang.am'), 'href' => $urls['am'], 'current' => $am],
    ]] : null;
    $font = fn (int $w) => asset("landing/fonts/noto-sans-ethiopic-{$w}.woff2");
    // Our own small pattern (a jebena, a mesob and cups), under 1 KB.
    $pattern = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='140' height='140' fill='none' stroke='%23fff' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M30 66c-9 0-15-6-15-13 0-8 7-13 15-13s15 5 15 13c0 7-6 13-15 13zM27 40c0-7 1-11 3-15 2 4 3 8 3 15M44 48c6-2 9-7 10-12M16 46c-7 1-7 11 0 12M22 72h16'/%3E%3Cpath d='M82 118h28l-4-12H86zM84 106c0-13 5-21 12-26 7 5 12 13 12 26M96 80v-5M88 112h16'/%3E%3Cpath d='M98 34h10c0 6-2 9-5 9s-5-3-5-9zM112 34h10c0 6-2 9-5 9s-5-3-5-9zM96 48h28'/%3E%3Cpath d='M56 104l4-4 4 4-4 4zM124 78l3-3 3 3-3 3zM10 108l3-3 3 3-3 3z'/%3E%3C/svg%3E";
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}">
    <link rel="canonical" href="{{ $canonical }}">
    <link rel="alternate" hreflang="en" href="{{ $urls['en'] }}">
    <link rel="alternate" hreflang="am" href="{{ $urls['am'] }}">
    <link rel="alternate" hreflang="x-default" href="{{ $urls['en'] }}">
    @unless ($isHome)
        {{-- A preview until the Super Admin makes it the home page. --}}
        <meta name="robots" content="noindex">
    @endunless
    <meta name="theme-color" content="{{ $colors['brand'] }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $platform }}">
    <meta property="og:locale" content="{{ $am ? 'am_ET' : 'en_US' }}">
    <meta property="og:locale:alternate" content="{{ $am ? 'en_US' : 'am_ET' }}">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title }}">
    <meta name="twitter:description" content="{{ $description }}">
    <meta name="twitter:image" content="{{ $ogImage }}">
    {{-- The hero phone is the biggest picture: asked for first, at the size it is shown. --}}
    <link rel="preload" as="image" type="image/{{ $hero['avif'] ? 'avif' : 'webp' }}" imagesrcset="{{ $hero['avif'] ?? $hero['webp'] }}" imagesizes="{{ $heroSizes }}" media="(prefers-color-scheme: light)" fetchpriority="high">
    <link rel="preload" as="image" type="image/{{ $heroDark['avif'] ? 'avif' : 'webp' }}" imagesrcset="{{ $heroDark['avif'] ?? $heroDark['webp'] }}" imagesizes="{{ $heroSizes }}" media="(prefers-color-scheme: dark)" fetchpriority="high">
    @if ($am)
        {{-- Amharic: only the letters the texts use (about 16 KB each), see scripts/landing2-font.py. --}}
        <link rel="preload" as="font" type="font/woff2" href="{{ $font(400) }}" crossorigin>
        <link rel="preload" as="font" type="font/woff2" href="{{ $font(700) }}" crossorigin>
    @endif
    @if ($isHome)
        <script type="application/ld+json">{!! $jsonLd !!}</script>
    @endif
    <style>
        @if ($am)
        @font-face{font-family:"L2 Ethiopic";src:url({{ $font(400) }}) format("woff2");font-weight:100 550;font-display:swap;unicode-range:U+1200-139F,U+2D80-2DDF,U+AB00-AB2F}
        @font-face{font-family:"L2 Ethiopic";src:url({{ $font(700) }}) format("woff2");font-weight:551 1000;font-display:swap;unicode-range:U+1200-139F,U+2D80-2DDF,U+AB00-AB2F}
        @endif
        :root{color-scheme:light dark;--bg:#fff;--fg:#0f172a;--muted:#475569;--card:#fff;--soft:#f1f5f9;--line:#e2e8f0;--band:#f7f3ee;--brand:{{ $colors['brand'] }};--on-brand:{{ $colors['on_brand'] }};--ink:{{ $colors['ink'] }};--r:26px}
        @media (prefers-color-scheme:dark){:root{--bg:#0b1120;--fg:#f1f5f9;--muted:#a5b4c8;--card:#111a2e;--soft:#131d33;--line:#25324d;--band:#0f1a30;--ink:{{ $colors['ink_dark'] }}}}
        *,*::before,*::after{box-sizing:border-box}
        html{-webkit-text-size-adjust:100%;scroll-padding-top:4.5rem}
        @media (prefers-reduced-motion:no-preference){html{scroll-behavior:smooth}}
        body{margin:0;min-height:100vh;display:flex;flex-direction:column;background:var(--bg);color:var(--fg);font:1rem/1.6 {!! $am ? '"L2 Ethiopic",' : '' !!}system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;overflow-wrap:break-word}
        img{max-width:100%;height:auto;display:block}
        a{color:inherit}
        h1,h2,h3{line-height:1.08;letter-spacing:-.02em;margin:0;text-wrap:balance}
        h2{font-size:clamp(1.75rem,4.5vw,2.6rem);font-weight:800}
        p{margin:0}
        /* Amharic: Ethiopic letters are taller and wider; no tight letter spacing, more line height. */
        :lang(am) h1,:lang(am) h2,:lang(am) h3{letter-spacing:0;line-height:1.25}
        :lang(am) .hero h1{font-size:clamp(2.1rem,8vw,3.9rem)}
        :lang(am) .sf h2{letter-spacing:0}
        .wrap{width:min(72rem,100% - 2rem);margin-inline:auto}
        .sr,.bi{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}
        .skip{position:absolute;inset-inline-start:1rem;top:-4rem;background:var(--fg);color:var(--bg);padding:.6rem 1rem;border-radius:99px;z-index:20}
        .skip:focus{top:1rem}
        :focus-visible{outline:3px solid var(--brand);outline-offset:3px}
        .ic{width:1.35rem;height:1.35rem;flex:none}
        .btn{display:inline-flex;align-items:center;justify-content:center;gap:.5rem;min-height:3rem;padding:.7rem 1.4rem;border-radius:99px;font-weight:800;text-decoration:none;border:2px solid transparent;text-align:center}
        .btn-light{background:#fff;color:#111827}
        .btn-dark{background:var(--fg);color:var(--bg)}
        .btn-line{border-color:currentColor}
        .btn-brand{background:var(--brand);color:var(--on-brand)}
        @if ($on['motion'])
        /* 12. Premium touches: buttons lift, cards fade in once (CSS only, no layout shift). */
        @media (prefers-reduced-motion:no-preference){
            .btn,.chip>span,.arrows button{transition:transform .15s ease,box-shadow .15s ease}
            .btn:hover{transform:translateY(-2px);box-shadow:0 10px 22px -10px rgb(0 0 0/.45)}
            .btn:active{transform:translateY(0) scale(.98);box-shadow:none}
            @supports (animation-timeline:view()){
                .fade>li,.fade>div{animation:fade both linear;animation-timeline:view();animation-range:entry 0% entry 35%}
                @keyframes fade{from{opacity:0;transform:translateY(18px)}}
            }
        }
        @endif
        /* Header */
        .top{position:sticky;top:0;z-index:10;background:color-mix(in srgb,var(--bg) 92%,transparent);backdrop-filter:blur(8px);border-bottom:1px solid var(--line)}
        .top .wrap{display:flex;align-items:center;gap:.75rem;min-height:4rem}
        .logo{display:flex;align-items:center;gap:.6rem;font-weight:800;text-decoration:none;font-size:1.1rem;min-width:0}
        .logo img{width:2rem;height:2rem;border-radius:.6rem;object-fit:cover}
        .logo .mk{width:2rem;height:2rem;border-radius:.6rem;display:grid;place-items:center;background:var(--brand);color:var(--on-brand);flex:none}
        .logo span:last-child{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .nav{display:none;gap:1.4rem;margin-inline-start:auto}
        .nav a{text-decoration:none;font-weight:600;color:var(--muted)}
        .nav a:hover{color:var(--fg)}
        .lang{display:inline-flex;align-items:center;gap:.3rem;margin-inline-start:auto;min-height:2.75rem;padding:0 .4rem;font-weight:700;font-size:.95rem;text-decoration:none;color:var(--muted);flex:none}
        .lang:hover{color:var(--fg)}
        .lang .ic{width:1.1rem;height:1.1rem}
        .top .btn{margin-inline-start:auto;min-height:2.75rem;padding:.5rem 1.1rem;white-space:nowrap;flex:none}
        .top .lang+.btn{margin-inline-start:0}
        .top .sm{display:none}
        @media (max-width:39.99rem){.top .lg{display:none}.top .sm{display:inline}}
        @media (min-width:56rem){.nav{display:flex}.lang,.top .btn{margin-inline-start:0}}
        /* Hero (8: a warm, blurred food photo under the brand colour) */
        .hero{position:relative;isolation:isolate;margin:1rem auto 0;background:var(--brand);color:var(--on-brand);border-radius:var(--r);padding:2.25rem 1.5rem 0;display:grid;gap:1.5rem;overflow:hidden}
        @if ($heroPhoto)
        .hero::before{content:"";position:absolute;inset:0;z-index:-1;background:linear-gradient(color-mix(in srgb,var(--brand) 90%,transparent),color-mix(in srgb,var(--brand) 84%,transparent)),url({{ $heroPhoto }}) center/cover}
        @else
        .hero::before{content:"";position:absolute;inset:0;z-index:-1;background:radial-gradient(70% 60% at 85% 15%,rgb(255 255 255/.16),transparent 70%),radial-gradient(60% 50% at 10% 100%,rgb(0 0 0/.14),transparent 70%)}
        @endif
        .hero>div{min-width:0}
        .kick{font-weight:700;font-size:.95rem}
        .hero h1{font-size:clamp(2.4rem,9vw,4.4rem);font-weight:900;margin:.5rem 0 1rem}
        .hero .lead{font-size:1.125rem;max-width:34rem}
        .acts{display:flex;flex-wrap:wrap;gap:.75rem;margin-top:1.5rem}
        .hero .btn-line{color:var(--on-brand)}
        .pts{display:flex;flex-wrap:wrap;gap:.5rem 1.25rem;margin:1.25rem 0 0;padding:0;list-style:none;font-weight:600;font-size:.95rem}
        .pts li{display:flex;align-items:center;gap:.35rem}
        .shot{position:relative;justify-self:center;width:220px;align-self:end;margin-bottom:-3rem;border:8px solid #0f172a;border-bottom:0;border-radius:2rem 2rem 0 0;overflow:hidden;background:#0f172a;box-shadow:0 30px 60px -20px rgb(0 0 0/.45)}
        .shot img{width:100%;aspect-ratio:300/608;object-fit:cover;object-position:top}
        .shot .swi{position:absolute;inset:0;height:auto;opacity:0;transition:opacity .35s ease}
        .shot .swi.on{opacity:1}
        @media (prefers-reduced-motion:reduce){.shot .swi{transition:none}}
        @media (min-width:56rem){.hero{grid-template-columns:1.25fr .75fr;padding:3.5rem 3.5rem 0}.hero>div:first-child{padding-bottom:3.5rem}.shot{width:260px}}
        @media (prefers-reduced-motion:no-preference){.shot{animation:rise .6s ease-out both}@keyframes rise{from{transform:translateY(24px);opacity:0}}}
        /* 1. The style switcher (only with JavaScript: it changes the phone's screen). */
        .sw{display:none;margin-top:1.5rem}
        .js .sw{display:block}
        .sw-l{font-weight:800;font-size:.95rem;margin-bottom:.5rem}
        .chips{display:flex;gap:.5rem;overflow-x:auto;scrollbar-width:none;padding:.25rem .25rem .5rem;margin:0 -.25rem}
        .chips::-webkit-scrollbar{display:none}
        .chip{flex:none;position:relative;cursor:pointer}
        .chip input{position:absolute;opacity:0;width:100%;height:100%;margin:0;cursor:pointer}
        .chip>span{display:inline-flex;align-items:center;min-height:2.75rem;padding:.3rem .95rem;border-radius:99px;border:2px solid color-mix(in srgb,var(--on-brand) 55%,transparent);font-weight:700;font-size:.9rem;white-space:nowrap}
        .chip input:checked+span{background:var(--on-brand);color:var(--brand);border-color:var(--on-brand)}
        .chip input:focus-visible+span{outline:3px solid var(--on-brand);outline-offset:2px}
        .chip:hover>span{border-color:var(--on-brand)}
        .sw-open{display:inline-flex;align-items:center;gap:.4rem;min-height:2.75rem;font-weight:800}
        .sw-open[hidden]{display:none}
        section{padding:4.5rem 0 0}
        /* Parts far below the first screen are laid out only when they come near (less work on open). */
        #styles,#compare,#poster,#features,#restaurants,#pricing,#faq{content-visibility:auto;contain-intrinsic-size:auto 640px}
        .sub{color:var(--muted);margin-top:.6rem;font-size:1.1rem;max-width:40rem}
        @if ($on['bands'])
        /* 7. Light bands behind steps, prices and questions (AA contrast in light and dark). */
        .band{background:var(--band);margin-top:4.5rem;padding-bottom:4.5rem}
        .band+section,.band+.band{margin-top:0}
        .band .steps li,.band .faq details{background:var(--card)}
        @endif
        /* 13. Real numbers */
        .nums ul{display:grid;grid-template-columns:repeat(auto-fit,minmax(10rem,1fr));gap:1rem;margin:0;padding:0;list-style:none}
        .nums li{border-inline-start:4px solid var(--brand);padding:.25rem 0 .25rem 1rem}
        .nums b{display:block;font-size:clamp(1.8rem,5vw,2.4rem);font-weight:900;line-height:1.1}
        .nums span{color:var(--muted);font-weight:600}
        .nums small{display:block;color:var(--muted);font-size:.8rem;margin-top:.2rem}
        /* Steps */
        .steps{display:grid;gap:1rem;margin:2rem 0 0;padding:0;list-style:none;counter-reset:s}
        .steps li{background:var(--soft);border-radius:var(--r);padding:1.5rem;counter-increment:s}
        .steps li::before{content:counter(s);display:block;font-weight:900;font-size:2.25rem;line-height:1;color:var(--ink);margin-bottom:.75rem}
        .steps h3{font-size:1.2rem;margin-bottom:.4rem;display:flex;align-items:center;gap:.5rem}
        .steps p{color:var(--muted)}
        @media (min-width:48rem){.steps{grid-template-columns:repeat(3,1fr)}}
        /* Problem and Paper vs QR */
        .pains{display:grid;gap:.75rem;margin:1.5rem 0 0;padding:0;list-style:none}
        .pains li{display:flex;gap:.9rem;align-items:flex-start;background:var(--soft);border-radius:1.1rem;padding:1rem 1.15rem;color:var(--muted)}
        .pains b{display:block;color:var(--fg);font-size:1.05rem}
        .pains .ic{width:1.6rem;height:1.6rem;color:#b91c1c;margin-top:.1rem}
        @media (prefers-color-scheme:dark){.pains .ic,.side.paper .ic{color:#f87171}}
        .fix{display:flex;gap:.6rem;align-items:center;margin-top:1rem;font-weight:700}
        .fix .ic{color:var(--ink)}
        @media (min-width:48rem){.pains{grid-template-columns:repeat(3,1fr)}}
        .vs{display:grid;gap:1rem;margin-top:1.5rem}
        .side{border-radius:var(--r);padding:1.4rem}
        .side h3{display:flex;align-items:center;gap:.5rem;font-size:1.2rem;margin-bottom:.75rem}
        .side ul{margin:0;padding:0;list-style:none;display:grid;gap:.55rem}
        .side li{display:flex;gap:.55rem;align-items:flex-start}
        .side li .ic{width:1.15rem;height:1.15rem;margin-top:.25rem}
        .side.paper{background:var(--soft);color:var(--muted)}
        .side.paper .ic{color:#b91c1c}
        .side.qrm{background:var(--brand);color:var(--on-brand)}
        @media (min-width:48rem){.vs{grid-template-columns:1fr 1fr}}
        /* 2. "Change a price": plays when on screen, every 8 s; still (the new price) with reduced motion. */
        .pd{display:grid;gap:2rem;align-items:center}
        .pd-demo{position:relative;justify-self:center;width:min(22rem,100%);display:grid;gap:.75rem}
        .pd-row{display:flex;align-items:center;justify-content:space-between;gap:1rem;background:var(--card);border:2px solid var(--line);border-radius:1.1rem;padding:.9rem 1.1rem;font-weight:700}
        .pd-row .ic{color:var(--ink)}
        .pd-p{position:relative;display:inline-grid;min-width:4.5rem;justify-items:end;font-weight:900;font-size:1.15rem}
        .pd-p>span{grid-area:1/1}
        .pd-n{text-decoration:underline 3px var(--brand);text-underline-offset:4px}
        .pd-tables{display:grid;grid-template-columns:repeat(3,1fr);gap:.5rem}
        .pd-t{background:var(--soft);border-radius:.9rem;padding:.6rem .5rem;text-align:center;font-size:.85rem;color:var(--muted)}
        .pd-t b{display:block;color:var(--fg);font-size:1rem}
        .pd-toast{justify-self:center;display:inline-flex;align-items:center;gap:.4rem;background:var(--fg);color:var(--bg);border-radius:99px;padding:.5rem 1rem;font-weight:700;font-size:.9rem}
        /* Default (and reduced motion): the final state. */
        .pd-o{opacity:0}
        @media (prefers-reduced-motion:no-preference){
            .js .pd-o{animation:pdo 8s infinite both paused}
            .js .pd-n{animation:pdn 8s infinite both paused}
            .js .pd-toast{animation:pdt 8s infinite both paused}
            .js .pd.play .pd-o,.js .pd.play .pd-n,.js .pd.play .pd-toast{animation-play-state:running}
            @keyframes pdo{0%,22%{opacity:1;transform:none}30%,100%{opacity:0;transform:translateY(-.6rem)}}
            @keyframes pdn{0%,24%{opacity:0;transform:translateY(.6rem)}32%,100%{opacity:1;transform:none}}
            @keyframes pdt{0%,30%{opacity:0;transform:translateY(.5rem)}36%,78%{opacity:1;transform:none}86%,100%{opacity:0;transform:none}}
        }
        @media (min-width:48rem){.pd{grid-template-columns:1fr 1fr}}
        /* Styles row */
        .row{display:flex;gap:1rem;overflow-x:auto;scroll-snap-type:x mandatory;scroll-padding-inline:1rem;padding:1.5rem 1rem 1rem;margin:1rem -1rem 0;scrollbar-width:none}
        .row::-webkit-scrollbar{display:none}
        .arrows{display:none;gap:.5rem;justify-content:flex-end;margin-top:.5rem}
        .arrows button{width:2.75rem;height:2.75rem;border-radius:99px;border:2px solid var(--line);background:var(--card);color:var(--fg);display:grid;place-items:center;cursor:pointer}
        .arrows button:hover{border-color:var(--fg)}
        .arrows .nx svg{transform:scaleX(-1)}
        @media (min-width:48rem) and (hover:hover){.js .arrows{display:flex}}
        .sty{position:relative;flex:none;width:9.5rem;scroll-snap-align:start;text-decoration:none;color:inherit}
        .sty .ph{border:5px solid #0f172a;border-radius:1.4rem;overflow:hidden;background:var(--soft);aspect-ratio:180/365}
        .sty .ph img{width:100%;height:100%;object-fit:cover;object-position:top}
        .sty b{display:block;margin-top:.5rem;font-size:.95rem}
        a.sty:hover .ph{border-color:var(--brand)}
        /* 9. The video: a poster with ▶; the video itself only after a tap. */
        .vid{display:grid;gap:1.5rem;align-items:center}
        .vbox{position:relative;border-radius:var(--r);overflow:hidden;background:#0f172a;aspect-ratio:16/9}
        .vbox img,.vbox video{width:100%;height:100%;object-fit:cover;display:block}
        .vp{position:absolute;inset:0;display:grid;place-items:center;color:#fff;text-decoration:none}
        .vp .play{width:4.5rem;height:4.5rem;border-radius:99px;background:rgb(15 23 42/.78);display:grid;place-items:center;position:absolute}
        .vp .play .ic{width:2rem;height:2rem;margin-inline-start:.2rem}
        .vp:hover .play{background:var(--brand);color:var(--on-brand)}
        @media (min-width:56rem){.vid{grid-template-columns:.8fr 1.2fr}}
        /* QR and features */
        .qr{display:grid;gap:2rem;align-items:center;background:#0f172a;color:#f8fafc;border-radius:var(--r);padding:2rem 1.5rem}
        .qr .sub{color:#cbd5e1}
        .qr figure{margin:0;justify-self:center;text-align:center;font-size:.9rem;display:none}
        .qr svg.code{width:10rem;height:10rem;background:#fff;padding:.6rem;border-radius:1rem}
        .qr-open{justify-self:start}
        @media (min-width:48rem){.qr{grid-template-columns:1fr auto;padding:3rem}.qr figure{display:block}.qr-open{display:none}}
        @media (prefers-color-scheme:dark){.qr{background:#16213b;border:1px solid var(--line)}.shot,.sty .ph{border-color:#334155}}
        /* 11. Free QR poster: a live preview, made in the browser (nothing sent). */
        .po{display:grid;gap:2rem;align-items:start}
        .pf{display:grid;gap:1rem;margin-top:1.5rem}
        .pf label,.pf legend{font-weight:700}
        .pf input[type=text]{display:block;width:100%;margin-top:.35rem;min-height:3rem;padding:.6rem .9rem;border-radius:.9rem;border:2px solid var(--line);background:var(--card);color:var(--fg);font:inherit}
        .pf fieldset{border:0;margin:0;padding:0}
        .pf legend{padding:0;margin-bottom:.35rem}
        .opts{display:flex;flex-wrap:wrap;gap:.5rem}
        .opt{position:relative}
        .opt input{position:absolute;opacity:0;inset:0;margin:0;cursor:pointer}
        .opt>span{display:inline-flex;align-items:center;gap:.4rem;min-height:2.75rem;min-width:2.75rem;padding:.3rem .8rem;border-radius:99px;border:2px solid var(--line);font-weight:700}
        .opt input:checked+span{border-color:var(--fg);box-shadow:inset 0 0 0 2px var(--bg)}
        .opt input:focus-visible+span{outline:3px solid var(--brand);outline-offset:2px}
        .opt i{width:1.4rem;height:1.4rem;border-radius:99px;display:block}
        .pp{margin:0;justify-self:center;width:min(19rem,100%);aspect-ratio:1/1.414;--pc:var(--brand);background:#fff;color:#0f172a;border-radius:.6rem;box-shadow:0 20px 50px -20px rgb(0 0 0/.45);display:grid;grid-template-rows:auto 1fr auto auto;text-align:center;overflow:hidden}
        .pp-top{background:var(--pc);color:#fff;padding:1.4rem 1rem;font-weight:900;font-size:1.5rem;line-height:1.15;overflow-wrap:anywhere}
        .pp svg{width:62%;height:auto;margin:auto;align-self:center}
        .pp-scan{font-weight:800;font-size:1.05rem;padding:0 1rem;color:var(--pc)}
        .pp small{display:block;padding:.4rem 1rem 1rem;color:#475569;font-size:.75rem}
        .pp-note{color:var(--muted);font-size:.95rem;margin-top:.5rem}
        @media (min-width:48rem){.po{grid-template-columns:1fr auto}}
        .feats{display:grid;gap:1rem;margin:2rem 0 0;padding:0;list-style:none}
        .feats li{border:2px solid var(--line);border-radius:var(--r);padding:1.4rem}
        .feats .ic{width:2rem;height:2rem;color:var(--ink);margin-bottom:.6rem}
        .feats h3{font-size:1.15rem;margin-bottom:.35rem}
        .feats p{color:var(--muted)}
        @media (min-width:48rem){.feats{grid-template-columns:repeat(2,1fr)}}
        @media (min-width:64rem){.feats{grid-template-columns:repeat(4,1fr)}}
        /* 4. Real restaurants */
        .rest{display:grid;gap:1rem;margin:2rem 0 0;padding:0;list-style:none}
        .rest figure{margin:0;height:100%;display:flex;flex-direction:column;gap:1rem;justify-content:space-between;border:2px solid var(--line);border-radius:var(--r);padding:1.4rem;background:var(--card)}
        .rest blockquote{margin:0;font-size:1.05rem}
        .rest figcaption{display:flex;align-items:center;gap:.75rem}
        .rest img{width:3rem;height:3rem;border-radius:.8rem;object-fit:cover;background:var(--soft)}
        .rest figcaption span{display:block;color:var(--muted);font-size:.9rem}
        @media (min-width:48rem){.rest{grid-template-columns:repeat(auto-fit,minmax(16rem,1fr))}}
        /* Prices, 6. Monthly / Yearly, 5. payment methods */
        .bill{display:inline-flex;gap:.25rem;margin-top:1.5rem;padding:.3rem;border-radius:99px;background:var(--card);border:2px solid var(--line)}
        .bill label{display:inline-flex;align-items:center;gap:.4rem;min-height:2.75rem;padding:.3rem 1.1rem;border-radius:99px;font-weight:800;cursor:pointer}
        .bill .save{font-size:.8rem;font-weight:800;background:color-mix(in srgb,var(--brand) 16%,transparent);color:var(--fg);border-radius:99px;padding:.1rem .5rem}
        #bill-m:checked~.bill [for=bill-m],#bill-y:checked~.bill [for=bill-y]{background:var(--fg);color:var(--bg)}
        #bill-y:checked~.bill [for=bill-y] .save{background:var(--bg);color:var(--fg)}
        #bill-m:focus-visible~.bill [for=bill-m],#bill-y:focus-visible~.bill [for=bill-y]{outline:3px solid var(--brand);outline-offset:2px}
        .py{display:none}
        #bill-y:checked~.plans .hy .py{display:block}
        #bill-y:checked~.plans .hy .pm{display:none}
        .plans{display:grid;gap:1rem;margin:2rem 0 0;padding:0;list-style:none}
        .plan{border:2px solid var(--line);border-radius:var(--r);padding:1.5rem;display:flex;flex-direction:column;gap:.75rem;background:var(--card)}
        .plan.hl{border-color:var(--brand);box-shadow:0 0 0 4px color-mix(in srgb,var(--brand) 18%,transparent)}
        .plan .tag{align-self:flex-start;background:var(--brand);color:var(--on-brand);border-radius:99px;padding:.15rem .7rem;font-size:.8rem;font-weight:800}
        .plan h3{font-size:1.25rem}
        .price{font-size:2.2rem;font-weight:900;line-height:1}
        .price small{font-size:.95rem;font-weight:600;color:var(--muted)}
        .plan ul{margin:0;padding:0;list-style:none;display:grid;gap:.4rem}
        .plan li{display:flex;gap:.5rem;align-items:flex-start}
        .plan li .ic{width:1.15rem;height:1.15rem;margin-top:.2rem;color:var(--ink)}
        .plan .muted{color:var(--muted);font-size:.95rem}
        .plan .btn{margin-top:auto}
        @media (min-width:48rem){.plans{grid-template-columns:repeat(auto-fit,minmax(15rem,1fr))}}
        .pay{display:flex;flex-wrap:wrap;align-items:center;gap:.5rem;margin-top:1.5rem;color:var(--muted);font-weight:600}
        .pay ul{display:flex;flex-wrap:wrap;gap:.5rem;margin:0;padding:0;list-style:none}
        .pay li{display:inline-flex;align-items:center;gap:.4rem;padding:.35rem .8rem;border-radius:99px;background:var(--card);border:1px solid var(--line);color:var(--fg);font-weight:700}
        .pay .ic{width:1.1rem;height:1.1rem;color:var(--ink)}
        /* FAQ */
        .faq{margin-top:2rem;display:grid;gap:.75rem;max-width:48rem}
        .faq details{background:var(--soft);border-radius:1.1rem;border:1px solid var(--line)}
        .faq summary{cursor:pointer;list-style:none;display:flex;justify-content:space-between;align-items:center;gap:1rem;padding:1rem 1.25rem;min-height:3.5rem;font-weight:700;border-radius:1.1rem}
        .faq summary::-webkit-details-marker{display:none}
        .faq summary::after{content:"+";flex:none;font-size:1.6rem;line-height:1;font-weight:500;color:var(--ink);transition:transform .2s}
        .faq details[open] summary::after{transform:rotate(45deg)}
        .faq p{padding:0 1.25rem 1.1rem;color:var(--muted)}
        @media (prefers-reduced-motion:no-preference){.faq details[open] p{animation:open .2s ease-out}@keyframes open{from{opacity:0;transform:translateY(-4px)}}}
        @media (prefers-reduced-motion:reduce){.faq summary::after{transition:none}}
        /* Final band (10: our own small pattern), sticky phone bar */
        .final{position:relative;isolation:isolate;overflow:hidden;background:var(--brand);color:var(--on-brand);border-radius:var(--r);padding:3rem 1.5rem;text-align:center;display:grid;gap:1rem;justify-items:center;margin-bottom:4.5rem}
        @if ($on['pattern'])
        .final::before{content:"";position:absolute;inset:0;z-index:-1;opacity:.14;background:url("{!! $pattern !!}") 0 0/140px}
        @endif
        .final h2{max-width:36rem}
        .bar{position:fixed;inset-inline:.75rem;bottom:.75rem;z-index:15;display:flex;justify-content:center}
        .bar .btn{width:100%;max-width:26rem;box-shadow:0 12px 30px -8px rgb(0 0 0/.45)}
        .js .bar{transform:translateY(160%);transition:transform .25s}
        .js .bar.on{transform:none}
        @media (prefers-reduced-motion:reduce){.js .bar{transition:none}}
        @media (min-width:56rem){.bar{display:none}}
        .sf-lang{display:flex;gap:.25rem;flex-wrap:wrap}
        .sf-lang a{display:inline-flex;align-items:center;min-height:2.75rem;padding:0 .6rem;border-radius:99px;text-decoration:none;font-weight:700}
        .sf-lang a[aria-current]{background:var(--fg);color:var(--bg)}
    </style>
    @include('site.partials.footer-style')
    <script nonce="{{ \App\Support\Security\Csp::nonce() }}">document.documentElement.classList.add('js')</script>
</head>
<body>
<a class="skip" href="#main">{{ $t('skip') }}</a>
<header class="top">
    <div class="wrap">
        <a class="logo" href="{{ $isHome ? $urls[$locale] : url('/') }}">
            @if ($logo)<img src="{{ $logo }}" alt="" width="32" height="32">@else<span class="mk" aria-hidden="true">{{ mb_strtoupper(mb_substr($platform, 0, 1)) }}</span>@endif
            <span>{{ $platform }}</span>
        </a>
        <nav class="nav" aria-label="{{ $t('nav.label') }}">
            <a href="#how">{{ $t('nav.how') }}</a>
            <a href="#styles">{{ $t('nav.styles') }}</a>
            @if ($plans !== [] && $on['pricing'])<a href="#pricing">{{ $t('nav.pricing') }}</a>@endif
            @if ($on['faq'])<a href="#faq">{{ $t('nav.faq') }}</a>@endif
            <a href="/signin">{{ $t('nav.signin') }}</a>
        </nav>
        @if ($languages)
            <a class="lang" href="{{ $urls[$otherLocale] }}" lang="{{ $otherLocale }}" hreflang="{{ $otherLocale }}" data-lang>{!! $icon('globe') !!}{{ $t('lang.'.$otherLocale) }}</a>
        @endif
        {!! $ctaLink('btn btn-dark', $t('sticky')) !!}
    </div>
</header>

<main id="main">
    @if ($qr && ($on['qr'] || $on['poster']))
        {{-- The demo QR code, drawn once and used by the QR band and the poster preview. --}}
        <svg width="0" height="0" aria-hidden="true" style="position:absolute"><defs><path id="qrp" fill="#0f172a" d="{{ $qr['path'] }}"/></defs></svg>
    @endif
    <div class="wrap hero" id="top">
        <div>
            <p class="kick">{{ $t('hero.kicker') }}</p>
            <h1>{{ $t('hero.title') }}</h1>
            <p class="lead">{{ $t('hero.text') }}</p>
            <div class="acts">
                {!! $ctaLink('btn btn-light') !!}
                @if ($demoUrl)<a class="btn btn-line" href="{{ $demoUrl }}">{{ $t('hero.demo') }}</a>@endif
            </div>
            <ul class="pts">
                @foreach ($t('hero.points') as $point)
                    <li>{!! $icon('check') !!} {{ $point }}</li>
                @endforeach
            </ul>
            @if ($showSwitch)
                @php($start = collect($switch)->firstWhere('style', $first))
                <div class="sw" id="switcher">
                    <p class="sw-l" id="sw-l">{{ $t('switcher.label') }}</p>
                    <div class="chips" role="radiogroup" aria-labelledby="sw-l">
                        @foreach ($switch as $s)
                            @php($i = array_search($s['style'], array_column($allStyles, 'style'), true))
                            @php($name = $letter($i).' · '.$s['name'])
                            <label class="chip"><input type="radio" name="sw" value="{{ $s['style'] }}" data-src="{{ $s['style'] === $first ? '' : $s['switch']['src'] }}" data-srcset="{{ $s['style'] === $first ? '' : $s['switch']['srcset'] }}" data-href="{{ $s['style'] === $first ? $demoUrl : $s['href'] }}" data-alt="{{ $t('switcher.shot_alt', ['style' => $name]) }}" @checked($s['style'] === $first)><span>{{ $name }}</span></label>
                        @endforeach
                    </div>
                    <a class="sw-open" id="sw-open" href="{{ $start ? ($demoUrl ?? '') : '' }}" @if (! $start || ! $demoUrl) hidden @endif>{{ $t('switcher.open') }} {!! $icon('arrow', 'ic ar') !!}</a>
                </div>
            @endif
        </div>
        <div class="shot">
            <picture>
                @if ($heroDark['avif'])<source type="image/avif" media="(prefers-color-scheme: dark)" srcset="{{ $heroDark['avif'] }}" sizes="{{ $heroSizes }}">@endif
                <source type="image/webp" media="(prefers-color-scheme: dark)" srcset="{{ $heroDark['webp'] }}" sizes="{{ $heroSizes }}">
                @if ($hero['avif'])<source type="image/avif" srcset="{{ $hero['avif'] }}" sizes="{{ $heroSizes }}">@endif
                <img src="{{ $hero['src'] }}" srcset="{{ $hero['webp'] }}" sizes="{{ $heroSizes }}" width="300" height="608" alt="{{ $t('hero.phone_alt') }}" fetchpriority="high" id="hero-img">
            </picture>
            @if ($showSwitch)<img class="swi" alt="" width="220" height="446" decoding="async" hidden><img class="swi" alt="" width="220" height="446" decoding="async" hidden>@endif
        </div>
    </div>

    @if ($on['numbers'])
        <section id="numbers" class="nums" aria-label="{{ $t('numbers.label') }}">
            <div class="wrap">
                <ul class="fade">
                    @foreach ($numbers as $n)
                        <li><b>{{ $n['value'] }}</b><span>{{ $n['label'] }}</span>@if ($n['source'])<small>{{ $n['source'] }}</small>@endif</li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    @if ($on['problem'])
        <section id="problem" aria-labelledby="problem-t">
            <div class="wrap">
                <h2 id="problem-t">{{ $t('problem.title') }}</h2>
                <ul class="pains fade">
                    @foreach (array_keys($t('problem.items')) as $key)
                        <li>{!! $icon(in_array($key, ['print', 'old', 'wait'], true) ? $key : 'x') !!}<span><b>{{ $t("problem.items.{$key}.title") }}</b> {{ $t("problem.items.{$key}.text") }}</span></li>
                    @endforeach
                </ul>
                <p class="fix">{!! $icon('check') !!}<span>{{ $t('problem.text') }}</span></p>
            </div>
        </section>
    @endif

    @if ($on['how'])
        <section id="how" class="band" aria-labelledby="how-t">
            <div class="wrap">
                <h2 id="how-t">{{ $t('how.title') }}</h2>
                <ol class="steps fade">
                    @foreach ($t('how.steps') as $i => $step)
                        <li><h3>{!! $icon(['dish', 'palette', 'qr'][$i] ?? 'check') !!}{{ $step['title'] }}</h3><p>{{ $step['text'] }}</p></li>
                    @endforeach
                </ol>
            </div>
        </section>
    @endif

    @if ($on['price_demo'])
        <section id="price" aria-labelledby="price-demo-t">
            <div class="wrap pd" id="pd">
                <div>
                    <h2 id="price-demo-t">{{ $t('price_demo.title') }}</h2>
                    <p class="sub">{{ $t('price_demo.text') }}</p>
                </div>
                <div class="pd-demo" role="img" aria-label="{{ $t('price_demo.label') }}">
                    <div class="pd-row" aria-hidden="true">
                        <span>{{ $t('price_demo.dish') }}</span>
                        <span class="pd-p"><span class="pd-o">{{ $t('price_demo.old') }}</span><span class="pd-n">{{ $t('price_demo.new') }}</span></span>
                    </div>
                    <div class="pd-tables" aria-hidden="true">
                        @foreach ([1, 2, 3] as $n)
                            <div class="pd-t">{!! $icon('phone', 'ic') !!}<b class="pd-p"><span class="pd-o">{{ $t('price_demo.old') }}</span><span class="pd-n">{{ $t('price_demo.new') }}</span></b></div>
                        @endforeach
                    </div>
                    <p class="pd-toast" aria-hidden="true">{{ $t('price_demo.toast') }}</p>
                </div>
            </div>
        </section>
    @endif

    @if ($on['styles'])
        <section id="styles" aria-labelledby="styles-t">
            <div class="wrap">
                <h2 id="styles-t">{{ $t('styles.title', ['count' => $count, 'last' => $last]) }}</h2>
                <p class="sub">{{ $t('styles.text') }}</p>
                <div class="arrows">
                    <button type="button" class="pv" data-dir="-1" aria-controls="style-row" aria-label="{{ $t('styles.prev') }}">{!! $icon('chev') !!}</button>
                    <button type="button" class="nx" data-dir="1" aria-controls="style-row" aria-label="{{ $t('styles.next') }}">{!! $icon('chev') !!}</button>
                </div>
                <div class="row" id="style-row">
                    @foreach ($allStyles as $i => $s)
                        @php($name = $letter($i).' · '.$s['name'])
                        {{-- A link is named by its visible text (+ a hint for screen readers); its picture is then decoration. --}}
                        @php($alt = $s['href'] ? '' : $t('styles.shot_alt', ['style' => $name]))
                        @if ($s['href'])<a class="sty" href="{{ $s['href'] }}">@else<div class="sty">@endif
                            <div class="ph">
                                @if ($s['shot'])
                                    {{-- Loaded when scrolled near (data-srcset), not on open. --}}
                                    <img data-srcset="{{ $s['shot']['webp'] }}" data-src="{{ $s['shot']['src'] }}" sizes="152px" width="180" height="365" alt="{{ $alt }}" decoding="async">
                                    <noscript><img src="{{ $s['shot']['src'] }}" srcset="{{ $s['shot']['webp'] }}" sizes="152px" width="180" height="365" alt="{{ $alt }}" loading="lazy"></noscript>
                                @endif
                            </div>
                            <b>{{ $name }}</b>@if ($s['href'])<span class="sr"> ({{ $t('styles.open') }})</span>@endif
                        @if ($s['href'])</a>@else</div>@endif
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($on['video'])
        <section id="video" aria-labelledby="video-t">
            <div class="wrap vid">
                <div>
                    <h2 id="video-t">{{ $t('video.title') }}</h2>
                    <p class="sub">{{ $t('video.text') }}</p>
                </div>
                <div class="vbox" id="vbox">
                    {{-- No video bytes until the tap: a link to the file, played here by the script. --}}
                    <a class="vp" id="vp" href="{{ $video['mp4'] }}" data-en="{{ $video['captions']['en'] }}" data-am="{{ $video['captions']['am'] }}" data-label-en="{{ $t('lang.en') }}" data-label-am="{{ $t('lang.am') }}" data-no-video="{{ $t('video.no_video') }}">
                        <img data-src="{{ $video['poster'] }}" alt="" width="960" height="540" decoding="async">
                        <span class="play">{!! $icon('play') !!}</span><span class="sr">{{ $t('video.play') }}</span>
                    </a>
                </div>
            </div>
        </section>
    @endif

    @if ($on['compare'])
        <section id="compare" aria-labelledby="compare-t">
            <div class="wrap">
                <h2 id="compare-t">{{ $t('compare.title') }}</h2>
                <p class="sub">{{ $t('compare.subtitle') }}</p>
                <div class="vs fade">
                    <div class="side paper">
                        <h3>{!! $icon('receipt') !!} {{ $t('compare.before') }}</h3>
                        <ul>
                            @foreach ($t('compare.rows') as $row)
                                <li>{!! $icon('x') !!}<span>{{ $row[0] }}</span></li>
                            @endforeach
                        </ul>
                    </div>
                    <div class="side qrm">
                        <h3>{!! $icon('qr') !!} {{ $t('compare.after') }}</h3>
                        <ul>
                            @foreach ($t('compare.rows') as $row)
                                <li>{!! $icon('check') !!}<span>{{ $row[1] }}</span></li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </section>
    @endif

    @if ($on['qr'])
        <section id="qr" aria-labelledby="qr-t">
            <div class="wrap qr">
                <div>
                    <h2 id="qr-t">{{ $t('qr.title') }}</h2>
                    <p class="sub">{{ $t('qr.text') }}</p>
                </div>
                @if ($demoUrl)
                    <a class="btn btn-light qr-open" href="{{ $demoUrl }}">{{ $t('qr.open') }} {!! $icon('arrow', 'ic ar') !!}</a>
                @endif
                @if ($qr)
                    <figure>
                        <svg class="code" viewBox="0 0 {{ $qr['size'] }} {{ $qr['size'] }}" role="img" aria-label="{{ $t('qr.scan') }}" shape-rendering="crispEdges"><use href="#qrp"/></svg>
                        <figcaption>{{ $t('qr.scan') }}</figcaption>
                    </figure>
                @endif
            </div>
        </section>
    @endif

    @if ($on['poster'])
        <section id="poster" aria-labelledby="poster-t">
            <div class="wrap po">
                <div>
                    <h2 id="poster-t">{{ $t('poster.title') }}</h2>
                    <p class="sub">{{ $t('poster.text') }}</p>
                    <form class="pf" id="pf" action="#poster">
                        <label>{{ $t('poster.name') }}<input type="text" id="pf-name" maxlength="40" autocomplete="organization" placeholder="{{ $t('poster.name_example') }}"></label>
                        <fieldset>
                            <legend>{{ $t('poster.colour') }}</legend>
                            <div class="opts">
                                @foreach (['brand' => $colors['brand'], 'red' => '#b91c1c', 'green' => '#15803d', 'blue' => '#1d4ed8', 'gold' => '#a16207', 'black' => '#0f172a'] as $key => $hex)
                                    <label class="opt"><input type="radio" name="pf-colour" value="{{ $hex }}" @checked($key === 'brand')><span><i style="background:{{ $hex }}"></i><span class="sr">{{ $t("poster.colours.{$key}") }}</span></span></label>
                                @endforeach
                            </div>
                        </fieldset>
                        <fieldset>
                            <legend>{{ $t('poster.language') }}</legend>
                            <div class="opts">
                                @foreach (['en', 'am'] as $code)
                                    <label class="opt"><input type="radio" name="pf-lang" value="{{ $code }}" @checked($code === $locale)><span lang="{{ $code }}">{{ $t('lang.'.$code) }}</span></label>
                                @endforeach
                            </div>
                        </fieldset>
                        @if ($cta)
                            <div>
                                <a class="btn btn-brand" href="{{ $signupOpen ? '/signup' : $cta['href'] }}"@if (! $signupOpen && $cta['external']) target="_blank" rel="noopener"@endif>{{ $signupOpen ? $t('poster.download') : $cta['label'] }}</a>
                                <p class="pp-note">{{ $t('poster.note') }}</p>
                            </div>
                        @endif
                    </form>
                </div>
                <figure class="pp" id="pp" aria-label="{{ $t('poster.preview') }}" style="--pc:{{ $colors['brand'] }}">
                    <div class="pp-top" id="pp-name" data-empty="{{ $t('poster.name_example') }}">{{ $t('poster.name_example') }}</div>
                    @if ($qr)
                        <svg viewBox="0 0 {{ $qr['size'] }} {{ $qr['size'] }}" role="img" aria-label="{{ $t('poster.sample') }}" shape-rendering="crispEdges"><use href="#qrp"/></svg>
                    @endif
                    <p class="pp-scan" id="pp-scan" lang="{{ $locale }}" data-en="{{ $posterScan['en'] }}" data-am="{{ $posterScan['am'] }}">{{ $posterScan[$locale] }}</p>
                    <small>{{ $t('poster.sample') }}</small>
                </figure>
            </div>
        </section>
    @endif

    @if ($on['features'])
        <section id="features" aria-labelledby="feat-t">
            <div class="wrap">
                <h2 id="feat-t">{{ $t('features.title') }}</h2>
                <ul class="feats fade">
                    @foreach ($t('features.items') as $key => $item)
                        <li>{!! $icon(['order' => 'order', 'languages' => 'languages', 'fast' => 'fast', 'insights' => 'chart'][$key] ?? 'check') !!}<h3>{{ $item['title'] }}</h3><p>{{ $item['text'] }}</p></li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    @if ($on['restaurants'])
        <section id="restaurants" aria-labelledby="rest-t">
            <div class="wrap">
                <h2 id="rest-t">{{ $t('restaurants.title') }}</h2>
                <p class="sub">{{ $t('restaurants.text') }}</p>
                <ul class="rest fade">
                    @foreach ($restaurants as $r)
                        <li><figure>
                            <blockquote>“{{ $r['quote'] }}”</blockquote>
                            <figcaption>
                                @if ($r['logo'])<img data-src="{{ $r['logo']['src'] }}" data-srcset="{{ $r['logo']['srcset'] }}" alt="" width="48" height="48" decoding="async">@endif
                                <span><b>{{ $r['name'] }}</b>@if ($r['city'])<span>{{ $r['city'] }}</span>@endif</span>
                            </figcaption>
                        </figure></li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    @if ($plans !== [] && $on['pricing'])
        <section id="pricing" class="band" aria-labelledby="price-t">
            <div class="wrap">
                <h2 id="price-t">{{ $t('pricing.title') }}</h2>
                <p class="sub">{{ $t('pricing.text') }}</p>
                @if ($billing)
                    {{-- Monthly / Yearly: two real radio buttons (keyboard and screen readers), CSS shows the prices. --}}
                    <input class="bi" type="radio" name="bill" id="bill-m" checked>
                    <input class="bi" type="radio" name="bill" id="bill-y">
                    <div class="bill">
                        <label for="bill-m">{{ $t('pricing.monthly') }}</label>
                        <label for="bill-y">{{ $t('pricing.yearly') }} <span class="save">{{ \App\Support\Landing\Landing2Texts::choice($locale, 'pricing.months_free', $saveMonths) }}</span></label>
                    </div>
                @endif
                <ul class="plans">
                    @foreach ($plans as $plan)
                        @php($hy = $billing && ($plan['yearly']['months'] ?? 0) > 0)
                        <li class="plan{{ $plan['highlight'] ? ' hl' : '' }}{{ $hy ? ' hy' : '' }}">
                            @if ($plan['highlight'])<span class="tag">{{ $t('pricing.popular') }}</span>@endif
                            <h3>{{ $plan['name'] }}</h3>
                            <p class="price pm">{{ $plan['price'] }} <small>{{ $plan['per'] }}</small></p>
                            @if ($hy)
                                <p class="price py">{{ $plan['yearly']['price'] }} <small>{{ $t('pricing.per_year') }}</small></p>
                                <p class="muted py">{{ \App\Support\Landing\Landing2Texts::choice($locale, 'pricing.months_free', $plan['yearly']['months']) }} · {{ $t('pricing.per_month_then', ['price' => $plan['price']]) }}</p>
                            @elseif ($plan['year'])
                                <p class="muted">{{ $plan['year'] }}</p>
                            @endif
                            @if ($plan['everything'])<p class="muted">{{ $plan['everything'] }}</p>@endif
                            <ul>
                                @foreach ([...$plan['limits'], ...$plan['features']] as $line)
                                    <li>{!! $icon('check') !!}<span>{{ $line }}</span></li>
                                @endforeach
                            </ul>
                            @if ($plan['free'])
                                {!! $ctaLink('btn btn-brand') !!}
                            @elseif ($plan['contact'])
                                <a class="btn btn-line" href="{{ $plan['contact']['href'] }}"@if ($plan['contact']['whatsapp']) target="_blank" rel="noopener"@endif>{{ $plan['contact']['label'] }}</a>
                            @endif
                        </li>
                    @endforeach
                </ul>
                @if ($on['payments'])
                    <div class="pay"><span id="pay-t">{{ $t('pricing.pay_with') }}</span>
                        <ul aria-labelledby="pay-t">
                            @foreach ($payments as $p)
                                <li>{!! $icon($p['icon']) !!}{{ $p['label'] }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </section>
    @endif

    @if ($on['faq'])
        <section id="faq" class="band" aria-labelledby="faq-t">
            <div class="wrap">
                <h2 id="faq-t">{{ $t('faq.title') }}</h2>
                <div class="faq">
                    @foreach ($t('faq.items') as $item)
                        <details><summary>{{ $item['q'] }}</summary><p>{{ $item['a'] }}</p></details>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section id="end" aria-labelledby="final-t" @if (! $on['final']) hidden @endif>
        <div class="wrap final" id="final">
            <h2 id="final-t">{{ $t('final.title') }}</h2>
            <p>{{ $t('final.text') }}</p>
            {!! $ctaLink('btn btn-light') !!}
        </div>
    </section>

    {{-- The phone bar (inside main, so it is in a landmark). --}}
    @if ($cta)
        <div class="bar" id="bar">{!! $ctaLink('btn btn-dark') !!}</div>
    @endif
</main>

@include('site.partials.footer', ['footer' => $footer, 'languages' => $languages])

<script nonce="{{ \App\Support\Security\Csp::nonce() }}">
(function () {
    var d = document, io = 'IntersectionObserver' in window, reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
    var $ = function (id) { return d.getElementById(id); };
    // The phone bar shows once the hero's buttons have scrolled away, and hides at the final band.
    var bar = $('bar'), hero = d.querySelector('.hero'), end = $('final');
    if (bar && hero && io) {
        var past = false, atEnd = false, set = function () { bar.classList.toggle('on', past && !atEnd); };
        new IntersectionObserver(function (e) { past = !e[0].isIntersecting; set(); }).observe(hero);
        if (end) new IntersectionObserver(function (e) { atEnd = e[0].isIntersecting; set(); }).observe(end);
    } else if (bar) bar.classList.add('on');
    // Pictures below the hero load when they come near the screen (not on open).
    var load = function (img) { if (img.dataset.srcset) img.srcset = img.dataset.srcset; if (img.dataset.src) img.src = img.dataset.src; img.removeAttribute('data-src'); img.removeAttribute('data-srcset'); };
    var lazy = d.querySelectorAll('img[data-src]');
    if (io) {
        var lo = new IntersectionObserver(function (es) { es.forEach(function (e) { if (e.isIntersecting) { lo.unobserve(e.target); load(e.target); } }); }, { rootMargin: '300px 300px' });
        lazy.forEach(function (i) { lo.observe(i); });
    } else lazy.forEach(load);
    // The styles row: arrows on computers (the scrollbar is hidden; phones swipe).
    var row = $('style-row');
    if (row) d.querySelectorAll('.arrows button').forEach(function (b) {
        b.addEventListener('click', function () { row.scrollBy({ left: Number(b.dataset.dir) * row.clientWidth * 0.8, behavior: reduce ? 'auto' : 'smooth' }); });
    });
    // 1. The style switcher: a chip's screen loads on tap; then the next two load while idle.
    // Two layers over the hero picture: the new screen fades in over the old one.
    var sw = $('switcher'), layers = [].slice.call(d.querySelectorAll('.shot .swi')), base = $('hero-img'), open = $('sw-open');
    if (sw && layers.length === 2) {
        var radios = [].slice.call(sw.querySelectorAll('input')), token = 0, up = 0;
        var prefetch = function (i) { [i + 1, i + 2].forEach(function (j) { var r = radios[j]; if (r && r.dataset.src) { var im = new Image(); im.srcset = r.dataset.srcset; im.src = r.dataset.src; } }); };
        var idle = window.requestIdleCallback || function (f) { setTimeout(f, 200); };
        radios.forEach(function (r, i) {
            r.addEventListener('change', function () {
                var my = ++token;
                if (r.dataset.href) { open.href = r.dataset.href; open.hidden = false; } else open.hidden = true;
                if (!r.dataset.src) { layers.forEach(function (l) { l.classList.remove('on'); l.alt = ''; }); base.removeAttribute('aria-hidden'); return; }
                var next = layers[1 - up];
                next.onload = function () {
                    if (my !== token) return;
                    var prev = layers[up]; up = 1 - up;
                    next.hidden = false; next.alt = r.dataset.alt; next.style.zIndex = 2; prev.style.zIndex = 1; prev.alt = '';
                    base.setAttribute('aria-hidden', 'true');
                    void next.offsetWidth; next.classList.add('on');
                    setTimeout(function () { if (layers[up] !== prev) prev.classList.remove('on'); }, 400);
                    idle(function () { prefetch(i); });
                };
                next.srcset = r.dataset.srcset; next.src = r.dataset.src;
            });
        });
    }
    // 2. "Change a price" plays only while it is on screen.
    var pd = $('pd');
    if (pd && io && !reduce) new IntersectionObserver(function (e) { pd.classList.toggle('play', e[0].isIntersecting); }).observe(pd);
    // 9. The video: made only when tapped (no video bytes before), with captions in both languages.
    var vp = $('vp');
    if (vp) vp.addEventListener('click', function (ev) {
        ev.preventDefault();
        var v = d.createElement('video'), lang = d.documentElement.lang;
        v.controls = true; v.playsInline = true; v.preload = 'auto';
        var s = d.createElement('source'); s.src = vp.href; s.type = 'video/mp4'; v.appendChild(s);
        ['en', 'am'].forEach(function (l) { if (!vp.dataset[l]) return; var tr = d.createElement('track'); tr.kind = 'captions'; tr.srclang = l; tr.label = vp.dataset['label' + l.charAt(0).toUpperCase() + l.slice(1)]; tr.src = vp.dataset[l]; if (l === lang) tr.default = true; v.appendChild(tr); });
        v.appendChild(d.createTextNode(vp.dataset.noVideo));
        vp.parentNode.replaceChild(v, vp);
        v.focus(); var p = v.play(); if (p && p.catch) p.catch(function () {});
    });
    // 11. The poster preview: name, colour and language (made here, nothing is sent).
    var pf = $('pf'), pp = $('pp'), pn = $('pp-name'), ps = $('pp-scan'), name = $('pf-name');
    if (pf && pp) {
        pf.addEventListener('submit', function (e) { e.preventDefault(); });
        name.addEventListener('input', function () { pn.textContent = name.value.trim() || pn.dataset.empty; });
        pf.addEventListener('change', function (e) {
            if (e.target.name === 'pf-colour') pp.style.setProperty('--pc', e.target.value);
            if (e.target.name === 'pf-lang') { ps.textContent = ps.dataset[e.target.value]; ps.lang = e.target.value; }
        });
    }
    // The language switch keeps the section on screen (English ⇄ Amharic).
    d.querySelectorAll('a[data-lang]').forEach(function (a) {
        a.addEventListener('click', function () {
            var cur = '';
            d.querySelectorAll('main section[id]').forEach(function (s) { if (s.getBoundingClientRect().top < innerHeight * 0.4) cur = s.id; });
            a.href = a.href.split('#')[0] + (cur ? '#' + cur : '');
        });
    });
})();
</script>
</body>
</html>
