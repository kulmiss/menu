@php
    /*
     * Landing 2 (owner, 2026-11-22): "Bold blocks". A new design over the same live data as
     * Landing 1 (plans and prices, FAQ answers, the main button, the demo menus). At /landing2
     * it is a preview: noindex and canonical "/". Once picked in Settings it is the home page.
     */
    $l2 = fn (string $key, array $replace = []) => __('landing2.'.$key, $replace);
    $title = $l2('meta_title', ['name' => $platform]);
    $count = count($allStyles);
    $last = strtoupper(chr(ord('a') + $count - 1));
    $letter = fn (int $i) => strtoupper(chr(ord('a') + $i));
    // Line icons drawn for this page (24 × 24, no icon package).
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
        'whatsapp' => '<path d="M3.5 20.5l1.3-4.2A8.5 8.5 0 1 1 8 19.3z"/>',
    ];
    $icon = fn (string $k, string $class = 'ic') => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$paths[$k].'</svg>';
    $ctaLink = function (string $class) use ($cta, $icon): string {
        if ($cta === null) {
            return '';
        }

        return '<a class="'.e($class).'" href="'.e($cta['href']).'"'.($cta['external'] ? ' target="_blank" rel="noopener"' : '').'>'
            .e($cta['label']).' '.$icon('arrow', 'ic ar').'</a>';
    };
    $hero = $shots['hero'];
    $heroDark = $shots['hero_dark'];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ in_array(app()->getLocale(), ['ar', 'fa', 'he', 'ur'], true) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}">
    <link rel="canonical" href="{{ url('/') }}">
    @unless ($isHome)
        {{-- A preview until the Super Admin makes it the home page. --}}
        <meta name="robots" content="noindex">
    @endunless
    <meta name="theme-color" content="{{ $colors['brand'] }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $platform }}">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ url('/') }}">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title }}">
    <meta name="twitter:description" content="{{ $description }}">
    <meta name="twitter:image" content="{{ $ogImage }}">
    <link rel="preload" as="image" type="image/{{ $hero['avif'] ? 'avif' : 'webp' }}" imagesrcset="{{ $hero['avif'] ?? $hero['webp'] }}" imagesizes="220px" media="(prefers-color-scheme: light)" fetchpriority="high">
    <link rel="preload" as="image" type="image/{{ $heroDark['avif'] ? 'avif' : 'webp' }}" imagesrcset="{{ $heroDark['avif'] ?? $heroDark['webp'] }}" imagesizes="220px" media="(prefers-color-scheme: dark)" fetchpriority="high">
    @if ($isHome)
        <script type="application/ld+json">{!! $jsonLd !!}</script>
    @endif
    <style>
        :root{color-scheme:light dark;--bg:#fff;--fg:#0f172a;--muted:#475569;--card:#fff;--soft:#f1f5f9;--line:#e2e8f0;--brand:{{ $colors['brand'] }};--on-brand:{{ $colors['on_brand'] }};--ink:{{ $colors['ink'] }};--r:26px}
        @media (prefers-color-scheme:dark){:root{--bg:#0b1120;--fg:#f1f5f9;--muted:#a5b4c8;--card:#111a2e;--soft:#131d33;--line:#25324d;--ink:{{ $colors['ink_dark'] }}}}
        *,*::before,*::after{box-sizing:border-box}
        html{-webkit-text-size-adjust:100%;scroll-padding-top:4.5rem}
        @media (prefers-reduced-motion:no-preference){html{scroll-behavior:smooth}}
        body{margin:0;min-height:100vh;display:flex;flex-direction:column;background:var(--bg);color:var(--fg);font:1rem/1.6 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
        img{max-width:100%;height:auto;display:block}
        a{color:inherit}
        h1,h2,h3{line-height:1.08;letter-spacing:-.02em;margin:0;text-wrap:balance}
        h2{font-size:clamp(1.75rem,4.5vw,2.6rem);font-weight:800}
        p{margin:0}
        .wrap{width:min(72rem,100% - 2rem);margin-inline:auto}
        .sr{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}
        .skip{position:absolute;inset-inline-start:1rem;top:-4rem;background:var(--fg);color:var(--bg);padding:.6rem 1rem;border-radius:99px;z-index:20}
        .skip:focus{top:1rem}
        :focus-visible{outline:3px solid var(--brand);outline-offset:3px}
        .ic{width:1.35rem;height:1.35rem;flex:none}
        [dir=rtl] .ar{transform:scaleX(-1)}
        .btn{display:inline-flex;align-items:center;justify-content:center;gap:.5rem;min-height:3rem;padding:.7rem 1.4rem;border-radius:99px;font-weight:800;text-decoration:none;border:2px solid transparent}
        .btn-light{background:#fff;color:#111827}
        .btn-dark{background:var(--fg);color:var(--bg)}
        .btn-line{border-color:currentColor}
        .btn-brand{background:var(--brand);color:var(--on-brand)}
        /* Header */
        .top{position:sticky;top:0;z-index:10;background:color-mix(in srgb,var(--bg) 92%,transparent);backdrop-filter:blur(8px);border-bottom:1px solid var(--line)}
        .top .wrap{display:flex;align-items:center;gap:1rem;min-height:4rem}
        .logo{display:flex;align-items:center;gap:.6rem;font-weight:800;text-decoration:none;font-size:1.1rem}
        .logo img{width:2rem;height:2rem;border-radius:.6rem;object-fit:cover}
        .logo .mk{width:2rem;height:2rem;border-radius:.6rem;display:grid;place-items:center;background:var(--brand);color:var(--on-brand)}
        .nav{display:none;gap:1.4rem;margin-inline-start:auto}
        .nav a{text-decoration:none;font-weight:600;color:var(--muted)}
        .nav a:hover{color:var(--fg)}
        .top .btn{margin-inline-start:auto;min-height:2.75rem;padding:.5rem 1.1rem}
        @media (min-width:56rem){.nav{display:flex}.top .btn{margin-inline-start:0}}
        /* Hero */
        .hero{margin:1rem auto 0;background:var(--brand);color:var(--on-brand);border-radius:var(--r);padding:2.25rem 1.5rem 0;display:grid;gap:1.5rem;overflow:hidden}
        .kick{font-weight:700;font-size:.95rem}
        .hero h1{font-size:clamp(2.4rem,9vw,4.4rem);font-weight:900;margin:.5rem 0 1rem}
        .hero .lead{font-size:1.125rem;max-width:34rem}
        .acts{display:flex;flex-wrap:wrap;gap:.75rem;margin-top:1.5rem}
        .hero .btn-line{color:var(--on-brand)}
        .pts{display:flex;flex-wrap:wrap;gap:.5rem 1.25rem;margin:1.25rem 0 0;padding:0;list-style:none;font-weight:600;font-size:.95rem}
        .pts li{display:flex;align-items:center;gap:.35rem}
        .shot{justify-self:center;width:220px;align-self:end;margin-bottom:-3rem;border:8px solid #0f172a;border-bottom:0;border-radius:2rem 2rem 0 0;overflow:hidden;background:#0f172a;box-shadow:0 30px 60px -20px rgb(0 0 0/.45)}
        .shot img{width:100%;aspect-ratio:300/608;object-fit:cover;object-position:top}
        @media (min-width:56rem){.hero{grid-template-columns:1.25fr .75fr;padding:3.5rem 3.5rem 0}.hero>div:first-child{padding-bottom:3.5rem}.shot{width:260px}}
        @media (prefers-reduced-motion:no-preference){.shot{animation:rise .6s ease-out both}@keyframes rise{from{transform:translateY(24px);opacity:0}}}
        section{padding:4.5rem 0 0}
        .sub{color:var(--muted);margin-top:.6rem;font-size:1.1rem;max-width:40rem}
        /* Steps */
        .steps{display:grid;gap:1rem;margin:2rem 0 0;padding:0;list-style:none;counter-reset:s}
        .steps li{background:var(--soft);border-radius:var(--r);padding:1.5rem;counter-increment:s}
        .steps li::before{content:counter(s);display:block;font-weight:900;font-size:2.25rem;line-height:1;color:var(--ink);margin-bottom:.75rem}
        .steps h3{font-size:1.2rem;margin-bottom:.4rem;display:flex;align-items:center;gap:.5rem}
        .steps p{color:var(--muted)}
        @media (min-width:48rem){.steps{grid-template-columns:repeat(3,1fr)}}
        /* Styles */
        .row{display:flex;gap:1rem;overflow-x:auto;scroll-snap-type:x mandatory;scroll-padding-inline:1rem;padding:1.5rem 1rem 1rem;margin:1rem -1rem 0;scrollbar-width:thin}
        .sty{flex:none;width:9.5rem;scroll-snap-align:start;text-decoration:none;color:inherit}
        .sty .ph{border:5px solid #0f172a;border-radius:1.4rem;overflow:hidden;background:var(--soft);aspect-ratio:180/365}
        .sty .ph img{width:100%;height:100%;object-fit:cover;object-position:top}
        .sty b{display:block;margin-top:.5rem;font-size:.95rem}
        a.sty:hover .ph{border-color:var(--brand)}
        /* QR and features */
        .qr{display:grid;gap:2rem;align-items:center;background:#0f172a;color:#f8fafc;border-radius:var(--r);padding:2rem 1.5rem}
        .qr .sub{color:#cbd5e1}
        .qr figure{margin:0;justify-self:center;text-align:center;font-size:.9rem}
        .qr svg.code{width:10rem;height:10rem;background:#fff;padding:.6rem;border-radius:1rem}
        @media (min-width:48rem){.qr{grid-template-columns:1fr auto;padding:3rem}}
        @media (prefers-color-scheme:dark){.qr{background:#16213b;border:1px solid var(--line)}.shot,.sty .ph{border-color:#334155}}
        .feats{display:grid;gap:1rem;margin:2rem 0 0;padding:0;list-style:none}
        .feats li{border:2px solid var(--line);border-radius:var(--r);padding:1.4rem}
        .feats .ic{width:2rem;height:2rem;color:var(--ink);margin-bottom:.6rem}
        .feats h3{font-size:1.15rem;margin-bottom:.35rem}
        .feats p{color:var(--muted)}
        @media (min-width:48rem){.feats{grid-template-columns:repeat(2,1fr)}}
        @media (min-width:64rem){.feats{grid-template-columns:repeat(4,1fr)}}
        /* Prices */
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
        /* FAQ */
        .faq{margin-top:2rem;display:grid;gap:.75rem;max-width:48rem}
        .faq details{background:var(--soft);border-radius:1.1rem;padding:0 1.25rem}
        .faq summary{cursor:pointer;font-weight:700;padding:1rem 0;list-style-position:inside}
        .faq p{padding:0 0 1.1rem;color:var(--muted)}
        /* Final band, sticky phone bar */
        .final{background:var(--brand);color:var(--on-brand);border-radius:var(--r);padding:3rem 1.5rem;text-align:center;display:grid;gap:1rem;justify-items:center;margin-bottom:4.5rem}
        .final h2{max-width:36rem}
        .bar{position:fixed;inset-inline:.75rem;bottom:.75rem;z-index:15;display:flex;justify-content:center}
        .bar .btn{width:100%;max-width:26rem;box-shadow:0 12px 30px -8px rgb(0 0 0/.45)}
        .js .bar{transform:translateY(160%);transition:transform .25s}
        .js .bar.on{transform:none}
        @media (prefers-reduced-motion:reduce){.js .bar{transition:none}}
        @media (min-width:56rem){.bar{display:none}}
    </style>
    @include('site.partials.footer-style')
    <script nonce="{{ \App\Support\Security\Csp::nonce() }}">document.documentElement.classList.add('js')</script>
</head>
<body>
<a class="skip" href="#main">{{ $l2('skip') }}</a>
<header class="top">
    <div class="wrap">
        <a class="logo" href="{{ url('/') }}">
            @if ($logo)<img src="{{ $logo }}" alt="" width="32" height="32">@else<span class="mk" aria-hidden="true">{{ mb_strtoupper(mb_substr($platform, 0, 1)) }}</span>@endif
            <span>{{ $platform }}</span>
        </a>
        <nav class="nav" aria-label="{{ $l2('nav.label') }}">
            <a href="#how">{{ $l2('nav.how') }}</a>
            <a href="#styles">{{ $l2('nav.styles') }}</a>
            @if ($plans !== [])<a href="#pricing">{{ $l2('nav.pricing') }}</a>@endif
            <a href="#faq">{{ $l2('nav.faq') }}</a>
            <a href="/signin">{{ $l2('nav.signin') }}</a>
        </nav>
        {!! $ctaLink('btn btn-dark') !!}
    </div>
</header>

<main id="main">
    <div class="wrap hero">
        <div>
            <p class="kick">{{ $l2('hero.kicker') }}</p>
            <h1>{{ $l2('hero.title') }}</h1>
            <p class="lead">{{ $l2('hero.text') }}</p>
            <div class="acts">
                {!! $ctaLink('btn btn-light') !!}
                @if ($demoUrl)<a class="btn btn-line" href="{{ $demoUrl }}">{{ $l2('hero.demo') }}</a>@endif
            </div>
            <ul class="pts">
                @foreach ($l2('hero.points') as $point)
                    <li>{!! $icon('check') !!} {{ $point }}</li>
                @endforeach
            </ul>
        </div>
        <div class="shot">
            <picture>
                @if ($heroDark['avif'])<source type="image/avif" media="(prefers-color-scheme: dark)" srcset="{{ $heroDark['avif'] }}" sizes="260px">@endif
                <source type="image/webp" media="(prefers-color-scheme: dark)" srcset="{{ $heroDark['webp'] }}" sizes="260px">
                @if ($hero['avif'])<source type="image/avif" srcset="{{ $hero['avif'] }}" sizes="260px">@endif
                <img src="{{ $hero['src'] }}" srcset="{{ $hero['webp'] }}" sizes="260px" width="300" height="608" alt="{{ $l2('hero.phone_alt') }}" fetchpriority="high">
            </picture>
        </div>
    </div>

    <section id="how" aria-labelledby="how-t">
        <div class="wrap">
            <h2 id="how-t">{{ $l2('how.title') }}</h2>
            <ol class="steps">
                @foreach ($l2('how.steps') as $i => $step)
                    <li><h3>{!! $icon(['dish', 'palette', 'qr'][$i]) !!}{{ $step['title'] }}</h3><p>{{ $step['text'] }}</p></li>
                @endforeach
            </ol>
        </div>
    </section>

    <section id="styles" aria-labelledby="styles-t">
        <div class="wrap">
            <h2 id="styles-t">{{ $l2('styles.title', ['count' => $count, 'last' => $last]) }}</h2>
            <p class="sub">{{ $l2('styles.text') }}</p>
            <div class="row">
                @foreach ($allStyles as $i => $s)
                    @php($name = $letter($i).' · '.__('landing.demo.style_names.'.$s['style']))
                    @if ($s['href'])<a class="sty" href="{{ $s['href'] }}" aria-label="{{ $l2('styles.open', ['style' => $name]) }}">@else<div class="sty">@endif
                        <div class="ph">
                            @if ($s['shot'])
                                <picture>
                                    @if ($s['shot']['avif'])<source type="image/avif" srcset="{{ $s['shot']['avif'] }}" sizes="152px">@endif
                                    <img src="{{ $s['shot']['src'] }}" srcset="{{ $s['shot']['webp'] }}" sizes="152px" width="180" height="365" alt="{{ $l2('styles.shot_alt', ['style' => $name]) }}" loading="lazy" decoding="async">
                                </picture>
                            @endif
                        </div>
                        <b>{{ $name }}</b>
                    @if ($s['href'])</a>@else</div>@endif
                @endforeach
            </div>
        </div>
    </section>

    <section aria-labelledby="qr-t">
        <div class="wrap qr">
            <div>
                <h2 id="qr-t">{{ $l2('qr.title') }}</h2>
                <p class="sub">{{ $l2('qr.text') }}</p>
            </div>
            @if ($qr)
                <figure>
                    <svg class="code" viewBox="0 0 {{ $qr['size'] }} {{ $qr['size'] }}" role="img" aria-label="{{ $l2('qr.scan') }}" shape-rendering="crispEdges"><path fill="#0f172a" d="{{ $qr['path'] }}"/></svg>
                    <figcaption>{{ $l2('qr.scan') }}</figcaption>
                </figure>
            @endif
        </div>
    </section>

    <section aria-labelledby="feat-t">
        <div class="wrap">
            <h2 id="feat-t">{{ $l2('features.title') }}</h2>
            <ul class="feats">
                @foreach ($l2('features.items') as $key => $item)
                    <li>{!! $icon(['order' => 'order', 'languages' => 'languages', 'fast' => 'fast', 'insights' => 'chart'][$key]) !!}<h3>{{ $item['title'] }}</h3><p>{{ $item['text'] }}</p></li>
                @endforeach
            </ul>
        </div>
    </section>

    @if ($plans !== [])
        <section id="pricing" aria-labelledby="price-t">
            <div class="wrap">
                <h2 id="price-t">{{ $l2('pricing.title') }}</h2>
                <p class="sub">{{ $l2('pricing.text') }}</p>
                <ul class="plans">
                    @foreach ($plans as $plan)
                        <li class="plan{{ $plan['highlight'] ? ' hl' : '' }}">
                            @if ($plan['highlight'])<span class="tag">{{ $l2('pricing.popular') }}</span>@endif
                            <h3>{{ $plan['name'] }}</h3>
                            <p class="price">{{ $plan['price'] }} <small>{{ $plan['per'] }}</small></p>
                            @if ($plan['year'])<p class="muted">{{ $plan['year'] }}</p>@endif
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
            </div>
        </section>
    @endif

    <section id="faq" aria-labelledby="faq-t">
        <div class="wrap">
            <h2 id="faq-t">{{ $t('faq.title') }}</h2>
            <div class="faq">
                @foreach (array_keys(__('landing.faq.items')) as $key)
                    <details><summary>{{ $t("faq.items.{$key}.q") }}</summary><p>{{ $t("faq.items.{$key}.a") }}</p></details>
                @endforeach
            </div>
        </div>
    </section>

    <section aria-labelledby="final-t">
        <div class="wrap final" id="final">
            <h2 id="final-t">{{ $l2('final.title') }}</h2>
            <p>{{ $l2('final.text') }}</p>
            {!! $ctaLink('btn btn-light') !!}
        </div>
    </section>

    {{-- The phone bar (inside main, so it is in a landmark). --}}
    @if ($cta)
        <div class="bar" id="bar">{!! $ctaLink('btn btn-dark') !!}</div>
    @endif
</main>

@include('site.partials.footer')

<script nonce="{{ \App\Support\Security\Csp::nonce() }}">
(function () {
    // The phone bar shows once the hero's buttons have scrolled away, and hides at the final band.
    var bar = document.getElementById('bar'), hero = document.querySelector('.hero'), end = document.getElementById('final');
    if (!bar || !hero || !('IntersectionObserver' in window)) { if (bar) bar.classList.add('on'); return; }
    var past = false, atEnd = false, set = function () { bar.classList.toggle('on', past && !atEnd); };
    new IntersectionObserver(function (e) { past = !e[0].isIntersecting; set(); }).observe(hero);
    if (end) new IntersectionObserver(function (e) { atEnd = e[0].isIntersecting; set(); }).observe(end);
})();
</script>
</body>
</html>
