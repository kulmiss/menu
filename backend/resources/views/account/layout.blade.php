{{--
    Account pages (QUESTIONS #222): the landing page's header, colours, fonts and
    buttons; a form on the right and short benefits with a demo phone on the left
    (phones: the form first). `$plain`: the Super Admin's own pages — no header
    links, no benefits, not indexed (QUESTIONS #216). Almost no JavaScript: one
    small inline script (CSP nonce) for "Show password"; every form works without it.
--}}
@php
    $plain ??= false;
    $title = trim($__env->yieldContent('title'));
    $nonce = \App\Support\Security\Csp::nonce();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $plain ? $title : $title.' · '.$platform }}</title>
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="{{ \App\Support\BrandColor::DARK_BG }}" media="(prefers-color-scheme: dark)">
    @unless ($plain)
        <link rel="preload" as="image" type="image/{{ $shots['hero']['avif'] ? 'avif' : 'webp' }}" imagesrcset="{{ $shots['hero']['avif'] ?? $shots['hero']['webp'] }}" imagesizes="220px" media="(prefers-color-scheme: light) and (min-width: 960px)">
        <link rel="preload" as="image" type="image/{{ $shots['hero_dark']['avif'] ? 'avif' : 'webp' }}" imagesrcset="{{ $shots['hero_dark']['avif'] ?? $shots['hero_dark']['webp'] }}" imagesizes="220px" media="(prefers-color-scheme: dark) and (min-width: 960px)">
    @endunless
    <script nonce="{{ $nonce }}">document.documentElement.classList.add('js')</script>
    <style>
        :root{color-scheme:light dark;--bg:#fff;--fg:#0f172a;--muted:#475569;--card:#fff;--soft:#f6f7f9;--line:#e4e7ec;--field:#cbd2dc;--brand:{{ $colors['brand'] }};--on-brand:{{ $colors['on_brand'] }};--ink:{{ $colors['ink'] }};--bad:#b42318;--bad-bg:#fef3f2;--good:#067647;--good-bg:#ecfdf3;--shadow:0 1px 2px rgb(15 23 42/.06),0 12px 32px -8px rgb(15 23 42/.12);--radius:20px;--tint:color-mix(in srgb,var(--brand) 10%,var(--card))}
        @media (prefers-color-scheme:dark){:root{--bg:{{ \App\Support\BrandColor::DARK_BG }};--fg:#f1f5f9;--muted:#a7b3c5;--card:#121a2e;--soft:#0f1628;--line:#24304a;--field:#3a4866;--ink:{{ $colors['ink_dark'] }};--bad:#fda29b;--bad-bg:#3b1715;--good:#75e0a7;--good-bg:#0c2a1e;--shadow:0 1px 2px rgb(0 0 0/.4),0 12px 32px -8px rgb(0 0 0/.5);--tint:color-mix(in srgb,var(--brand) 16%,var(--card))}}
        *,*::before,*::after{box-sizing:border-box}
        html{-webkit-text-size-adjust:100%}
        body{margin:0;min-height:100vh;display:flex;flex-direction:column;font-family:ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,"Noto Sans","Noto Sans Ethiopic",Arial,sans-serif;line-height:1.6;background:var(--bg);color:var(--fg);-webkit-font-smoothing:antialiased}
        img,svg{display:block;max-width:100%}
        a{color:inherit}
        h1,h2{line-height:1.15;letter-spacing:-.025em;margin:0;text-wrap:balance}
        p{margin:0}
        .wrap{width:100%;max-width:1120px;margin:0 auto;padding:0 20px}
        @media (min-width:768px){.wrap{padding:0 32px}}
        .icon{width:20px;height:20px;flex:none}
        .skip{position:absolute;left:-9999px;top:8px;z-index:100;background:var(--fg);color:var(--bg);padding:10px 16px;border-radius:8px}
        .skip:focus{left:8px}
        :focus-visible{outline:3px solid var(--ink);outline-offset:3px;border-radius:6px}
        .sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);border:0}
        .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:48px;padding:0 20px;border-radius:999px;font:inherit;font-weight:650;font-size:1rem;text-decoration:none;border:1px solid transparent;transition:transform .15s,box-shadow .15s,background-color .15s;cursor:pointer}
        .btn-primary{background:var(--brand);color:var(--on-brand);box-shadow:0 10px 24px -10px color-mix(in srgb,var(--brand) 80%,transparent)}
        .btn-primary:hover{transform:translateY(-1px)}
        .btn-ghost{border-color:var(--line);background:var(--card);color:var(--fg)}
        .btn-ghost:hover{border-color:var(--muted)}
        .btn-block{width:100%}
        .btn-google{background:#fff;color:#1f1f1f;border-color:#747775}
        .btn-google:hover{background:#f8f9fa}
        @media (prefers-color-scheme:dark){.btn-google{background:#131314;color:#e3e3e3;border-color:#8e918f}.btn-google:hover{background:#1f1f20}}
        .g-logo{width:20px;height:20px;flex:none}
        @media (prefers-reduced-motion:reduce){.btn,.btn-primary:hover{transition:none;transform:none}}
        .link{color:var(--ink);font-weight:650;text-underline-offset:4px}
        /* Header (as on the landing page) */
        .site-header{border-bottom:1px solid var(--line);background:var(--bg)}
        .bar{display:flex;align-items:center;gap:12px;min-height:64px}
        .brand{display:inline-flex;align-items:center;gap:10px;font-weight:800;font-size:1.1rem;text-decoration:none;min-height:44px;margin-right:auto;min-width:0}
        .brand span:last-child{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .brand img{height:32px;width:auto}
        .brand-mark{width:32px;height:32px;border-radius:9px;background:var(--brand);color:var(--on-brand);display:grid;place-items:center;font-size:1rem;flex:none}
        .bar .hint{display:none;color:var(--muted)}
        .bar .btn{min-height:44px;padding:0 16px;white-space:nowrap}
        @media (min-width:640px){.bar .hint{display:inline}}
        /* Page */
        main{flex:1;position:relative;isolation:isolate;overflow:hidden}
        main::before{content:"";position:absolute;inset:-20% -20% auto;height:70%;background:radial-gradient(40% 50% at 15% 20%,color-mix(in srgb,var(--brand) 18%,transparent),transparent 70%),radial-gradient(35% 45% at 85% 30%,color-mix(in srgb,var(--brand) 12%,transparent),transparent 70%);z-index:-1}
        .grid{display:grid;gap:40px;padding-top:32px;padding-bottom:56px;align-items:center}
        @media (min-width:960px){.grid{grid-template-columns:1fr minmax(0,440px);grid-template-areas:"aside form";gap:64px;padding-top:64px;padding-bottom:88px}.form-col{grid-area:form}.aside{grid-area:aside}}
        .card{background:var(--card);border:1px solid var(--line);border-radius:var(--radius);padding:24px;box-shadow:var(--shadow)}
        @media (min-width:480px){.card{padding:32px}}
        .card h1{font-size:clamp(1.75rem,5vw,2.2rem);font-weight:850}
        .card .intro{color:var(--muted);margin-top:8px}
        .stack{display:grid;gap:18px;margin-top:24px}
        .field{display:grid;gap:6px}
        .field label,.label-row{font-weight:650}
        .label-row{display:flex;justify-content:space-between;align-items:baseline;gap:12px}
        .label-row a{font-weight:600;font-size:.95rem;color:var(--ink);text-underline-offset:4px;min-height:44px;display:inline-flex;align-items:center;margin:-12px 0}
        input[type=email],input[type=password],input[type=text]{width:100%;min-height:48px;padding:0 14px;border:1px solid var(--field);border-radius:12px;background:var(--bg);color:var(--fg);font:inherit;font-size:1rem}
        input:focus-visible{outline:3px solid color-mix(in srgb,var(--ink) 55%,transparent);outline-offset:1px;border-color:var(--ink)}
        input[aria-invalid=true]{border-color:var(--bad)}
        .error{color:var(--bad);font-size:.95rem;font-weight:550}
        .pw{position:relative}
        .pw input{padding-right:56px}
        .pw button{position:absolute;right:2px;top:2px;width:44px;height:44px;display:none;place-items:center;border:0;border-radius:10px;background:transparent;color:var(--muted);cursor:pointer}
        .js .pw button{display:grid}
        .pw button svg{width:20px;height:20px}
        .check{display:flex;align-items:center;gap:12px;min-height:44px;cursor:pointer}
        .check input{width:22px;height:22px;accent-color:var(--brand);margin:0;flex:none}
        .notice{display:flex;gap:10px;align-items:flex-start;padding:12px 14px;border-radius:12px;margin-top:20px;font-weight:550}
        .notice.bad{background:var(--bad-bg);color:var(--bad)}
        .notice.good{background:var(--good-bg);color:var(--good)}
        .notice .icon{margin-top:3px}
        .or{display:flex;align-items:center;gap:12px;color:var(--muted);font-size:.95rem}
        .or::before,.or::after{content:"";flex:1;border-top:1px solid var(--line)}
        .after{margin-top:20px;text-align:center;color:var(--muted)}
        .after a{color:var(--ink);font-weight:650;text-underline-offset:4px;display:inline-flex;min-height:44px;align-items:center}
        details{margin-top:20px}
        summary{cursor:pointer;color:var(--ink);font-weight:650;min-height:44px;display:flex;align-items:center}
        /* Benefits + phone */
        .aside h2{font-size:clamp(1.6rem,3.6vw,2.4rem);font-weight:850}
        .points{list-style:none;margin:20px 0 0;padding:0;display:grid;gap:12px;font-size:1.075rem}
        .points li{display:flex;gap:12px;align-items:flex-start}
        .points .icon{color:var(--on-brand);background:var(--brand);border-radius:999px;padding:4px;width:26px;height:26px;margin-top:1px}
        .aside-inner{display:grid;gap:32px;align-items:center}
        @media (min-width:960px){.aside-inner{grid-template-columns:1fr 220px}}
        .phone{display:none;position:relative;width:220px;border-radius:40px;padding:9px;background:#0b0f19;box-shadow:0 40px 80px -24px rgb(15 23 42/.45);justify-self:center}
        .phone::before{content:"";position:absolute;top:16px;left:50%;width:66px;height:18px;margin-left:-33px;background:#0b0f19;border-radius:999px;z-index:2}
        .phone img{width:100%;height:auto;border-radius:31px;background:var(--soft)}
        @media (min-width:960px){.phone{display:block}}
        .site-footer{border-top:1px solid var(--line);padding:20px 0;color:var(--muted);font-size:.95rem}
        .site-footer .wrap{display:flex;flex-wrap:wrap;gap:4px 20px;align-items:center}
        .site-footer a{min-height:44px;display:inline-flex;align-items:center;text-underline-offset:4px}
        /* The Super Admin's plain page */
        .plain main::before{display:none}
        .plain .grid{grid-template-columns:minmax(0,420px);grid-template-areas:none;justify-content:center;padding-top:10vh}
        .plain .form-col{grid-area:auto}
        .plain .card{box-shadow:none}
        .plain .brand-line{color:var(--muted);font-weight:650;margin-bottom:12px}
        .plain .btn-primary{background:var(--fg);color:var(--bg);box-shadow:none}
    </style>
</head>
<body @class(['plain' => $plain])>
<a class="skip" href="#form">{{ __('account.skip') }}</a>
@unless ($plain)
    <header class="site-header">
        <div class="wrap bar">
            <a class="brand" href="/" aria-label="{{ __('account.brand_home', ['name' => $platform]) }}">
                @if ($logo)
                    <img src="{{ $logo }}" alt="" height="32">
                @else
                    <span class="brand-mark" aria-hidden="true">{{ mb_strtoupper(mb_substr($platform, 0, 1)) }}</span>
                @endif
                <span>{{ $platform }}</span>
            </a>
            @yield('header')
        </div>
    </header>
@endunless
<main>
    <div class="wrap grid">
        <div class="form-col" id="form">
            <div class="card">
                @if ($plain)
                    <p class="brand-line">{{ $platform }}</p>
                @endif
                @yield('form')
            </div>
            @yield('after')
        </div>
        @unless ($plain)
            <aside class="aside" aria-labelledby="aside-title">
                <div class="aside-inner">
                    <div>
                        <h2 id="aside-title">{{ __('account.aside.title') }}</h2>
                        <ul class="points">
                            @foreach (__('account.aside.points') as $point)
                                <li><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg><span>{{ $point }}</span></li>
                            @endforeach
                        </ul>
                    </div>
                    <div class="phone">
                        <picture>
                            @if ($shots['hero_dark']['avif'])<source type="image/avif" media="(prefers-color-scheme: dark)" srcset="{{ $shots['hero_dark']['avif'] }}" sizes="220px">@endif
                            <source type="image/webp" media="(prefers-color-scheme: dark)" srcset="{{ $shots['hero_dark']['webp'] }}" sizes="220px">
                            @if ($shots['hero']['avif'])<source type="image/avif" srcset="{{ $shots['hero']['avif'] }}" sizes="220px">@endif
                            <img src="{{ $shots['hero']['src'] }}" srcset="{{ $shots['hero']['webp'] }}" sizes="220px" width="300" height="608" alt="{{ __('account.aside.phone_alt') }}" loading="lazy" decoding="async">
                        </picture>
                    </div>
                </div>
            </aside>
        @endunless
    </div>
</main>
@unless ($plain)
    <footer class="site-footer">
        <div class="wrap">
            <span>© {{ date('Y') }} {{ $platform }}</span>
            <a href="/terms">{{ __('account.terms') }}</a>
            <a href="/privacy">{{ __('account.privacy') }}</a>
        </div>
    </footer>
@endunless
<script nonce="{{ $nonce }}">
document.querySelectorAll('.pw button').forEach(function (b) {
    b.addEventListener('click', function () {
        var i = b.parentNode.querySelector('input'), show = i.type === 'password';
        i.type = show ? 'text' : 'password';
        b.setAttribute('aria-pressed', show ? 'true' : 'false');
        b.setAttribute('aria-label', show ? b.dataset.hide : b.dataset.show);
    });
});
</script>
</body>
</html>
