@php
    use App\Support\PublicMenu\InlineAssets;
    $preview ??= false;
    $cover = $theme['cover'];
    $style = $theme['style'];
    // Photo widths per menu style, so the browser picks the right file (§15.2.4).
    $sizes = ['bistro' => '104px', 'grid' => '(min-width:40rem) 220px, 50vw', 'editorial' => '(min-width:44rem) 672px, 100vw'][$style];
    // Only photos likely on the first screen load at once; the rest lazily
    // (PLAN.md §15.2.4). How many dishes fit depends on the style and the banner.
    // With a banner, A and B show no dish photo on the first screen (the banner is the LCP).
    $fold = ['editorial' => [2, 0], 'grid' => [4, 0], 'bistro' => [5, 0]][$style][$cover ? 1 : 0];
    $eagerLeft = $fold;
    $blurLeft = 4;
    $position = 0;
    $hasInfo = $week || $info['address'] || $info['links'] || $info['about'] || $info['wifi'] || $info['socials'] || $otherBranches;
    $sheets = [];
    $firstImage = null;
    $cart = [];
    foreach ($menus as $m) { foreach ($m['categories'] as $c) { foreach ($c['items'] as $i) {
        if ($i['sheet'] !== []) { $sheets[$i['id']] = $i['sheet']; }
        if ($orderList) { $cart[$i['id']] = [...$i['cart'], $i['sold_out'] ? 1 : 0]; }
        $firstImage ??= $i['media']['og'] ?? null;
    } } }
    // No dish photo anywhere (switched off, hidden per dish, or none uploaded): the styles use a text layout.
    $data = [
        'id' => $tenant->id,
        'b' => $branch_id,
        'p' => $preview ? 1 : 0,
        's' => InlineAssets::sheetUrl(),
        'z' => $timezone,
        'h' => $hours,
        'x' => $closures,
        'c' => (object) $sheets,
        // "My order" (only when switched on): [price, sizes, add-on groups, sold out] per dish, currency, QR code → table.
        'o' => $orderList ? (object) $cart : null,
        'cu' => \App\Support\PublicMenu\MenuPageData::symbol($tenant->currency_code),
        'q' => $orderList ? (object) $tables : null,
        't' => [
            'so' => __('public.sold_out'), 'sz' => __('public.sizes'), 'al' => __('public.allergens_title'),
            'kc' => __('public.calories'), 'pm' => __('public.prep'), 'ch' => __('public.choose'),
            'up' => __('public.choose_up_to'), 'ex' => __('public.choose_exactly'),
            'on' => __('public.open_until'), 'cl' => __('public.closed'), 'ct' => __('public.closed_today'),
            'oa' => __('public.opens_at'), 'ot' => __('public.opens_tomorrow'), 'od' => __('public.opens_day'),
            'o' => $orderList ? __('public.order') : null,
            'sh' => __('public.share'), 'sl' => __('public.share_link'), 'cp' => __('public.copy_link'), 'cd' => __('public.copied'),
            'ph' => __('public.photos'), 'nx' => __('public.next_photo'), 'pv' => __('public.previous_photo'), 'mu' => __('public.menu_updated'), 'tp' => __('public.back_to_top'),
            'dn' => array_map(fn ($i) => __('public.days_short.'.$i), range(0, 6)),
        ],
    ];

@endphp
<!doctype html>
<html lang="{{ $locale }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>{{ $tenant->name }}{{ $branch ? ' · '.$branch : '' }}</title>
@php
    // Link preview (WhatsApp, Telegram, social): banner first, else the first dish photo.
    $shareImage = $cover['og'] ?? $firstImage;
    $shareText = $info['about'] !== '' ? \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', $info['about']), 155) : __('public.share_description', ['name' => $tenant->name]);
@endphp
<meta name="description" content="{{ $shareText }}">
<meta name="theme-color" content="{{ $theme['theme_color'] }}">
<link rel="canonical" href="{{ url($canonical) }}">
<meta property="og:title" content="{{ $tenant->name }}{{ $branch ? ' · '.$branch : '' }}">
<meta property="og:description" content="{{ $shareText }}">
<meta property="og:site_name" content="{{ $tenant->name }}">
<meta name="twitter:card" content="{{ $shareImage ? 'summary_large_image' : 'summary' }}">
<meta property="og:type" content="website">
<meta property="og:url" content="{{ url($canonical) }}">
@if ($shareImage)<meta property="og:image" content="{{ $shareImage }}"><meta property="og:image:width" content="1200"><meta property="og:image:height" content="630">@endif
<link rel="icon" href="data:,">
@if ($cover)
{{-- The banner is the largest thing on screen: start fetching it before the CSS is parsed (LCP). --}}
@php $preloadAvif = $cover['avif'] !== ''; @endphp
<link rel="preload" as="image" fetchpriority="high" type="image/{{ $preloadAvif ? 'avif' : 'webp' }}" imagesrcset="{{ $preloadAvif ? $cover['avif'] : $cover['webp'] }}" imagesizes="{{ $cover['sizes'] }}">
@endif
<style>{!! InlineAssets::css($style) !!}{!! $theme['css'] !!}</style>
</head>
<body class="S-{{ $style }}{{ $firstImage ? '' : ' NP' }}">
<div @class(['top', 'hc' => $cover, 'ov' => $cover && $cover['over']])>
@if ($cover)
{{-- The banner keeps its shape from the first paint (CSS aspect-ratio + placeholder), so nothing moves. --}}
<div class="cv" style="background-image:url({{ $cover['placeholder'] ?? '' }});background-color:{{ $cover['color'] ?? 'transparent' }}"><picture>
@if ($cover['avif'] !== '')<source type="image/avif" srcset="{{ $cover['avif'] }}" sizes="{{ $cover['sizes'] }}">@endif
<source type="image/webp" srcset="{{ $cover['webp'] }}" sizes="{{ $cover['sizes'] }}">
{{-- No decoding="async" on the largest image: it then appears in the first frame, not one or two later. --}}
<img src="{{ $cover['jpg'] }}" alt="" width="{{ $cover['w'] }}" height="{{ $cover['h'] }}" fetchpriority="high">
</picture></div>
@endif
<header>
@if ($theme['logo'])<img class="lg" src="{{ $theme['logo']['320'] ?? '' }}" alt="" width="76" height="76">@endif
<div><h1>{{ $tenant->name }}</h1>
@if ($branch)<p class="sub">{{ $branch }}</p>@endif
{{-- The open/closed pill is filled in by the script; its space is kept so nothing moves (CLS). --}}
<p class="st ld" id="st"@if (! $hours) hidden @endif>&nbsp;</p></div>
</header>
</div>
<div class="w">
@foreach ($notices as $n)<p class="nt">{{ __('public.closed_on', ['dates' => $n['dates']]) }}@if ($n['note'] !== '') · {{ $n['note'] }}@endif</p>@endforeach
</div>
<div class="bar"><div class="w">
<div class="sr"><input id="q" type="search" placeholder="{{ __('public.search') }}" aria-label="{{ __('public.search') }}" autocomplete="off">@if ($theme['show_share'])<button type="button" class="ib" data-x="H" aria-label="{{ __('public.share') }}"><svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12M7 8l5-5 5 5M5 14v5a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-5"/></svg></button>@endif
@if ($hasInfo)<a class="ib" href="#info" aria-label="{{ __('public.info') }}"><svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg></a>@endif</div>
@if (count($tags) > 1)
<div class="ch" role="group" aria-label="{{ __('public.filter') }}">@foreach ($tags as $key => $label)<button type="button" data-tag="{{ $key }}" aria-pressed="false">{{ $label }}</button>@endforeach</div>
@endif
<nav class="ch cn" aria-label="Categories">@foreach ($menus as $m)@foreach ($m['categories'] as $c)<a href="#c{{ $c['id'] }}">{{ $c['name'] }}</a>@endforeach @endforeach</nav>
</div></div>
<main class="w">
@foreach ($promotions as $p)
@if ($p['type'] === 'announcement')
<p class="an"@if ($p['window']) data-w="{{ json_encode($p['window']) }}"@endif @if ($p['hidden']) hidden @endif><b>{{ $p['title'] }}</b>@if ($p['body'] !== '') {{ $p['body'] }}@endif</p>
@else
<div class="spc"@if ($p['item']) data-i="{{ $p['item']['id'] }}"@endif @if ($p['window']) data-w="{{ json_encode($p['window']) }}"@endif @if ($p['hidden']) hidden @endif>
<{{ $p['item'] ? 'button type=button' : 'div' }} class="{{ $p['item'] ? 'it ' : '' }}sp">
@if ($p['image'])@php $sp = $p['image']; @endphp
{{-- A special is a compact card (photo on the side), never the largest element: its photo waits for load like the ones below, so the banner loads alone. --}}
@php $ss = 'data-ss'; @endphp
<picture>@if ($sp['avif'])<source type="image/avif" {{ $ss }}="{{ $sp['avif'] }}" sizes="(min-width:44rem) 152px, 34vw">@endif<source type="image/webp" {{ $ss }}="{{ $sp['webp'] }}" sizes="(min-width:44rem) 152px, 34vw">
<img data-s="{{ $sp['jpg'] }}" alt="" width="{{ $sp['w'] }}" height="{{ $sp['h'] }}" decoding="async" style="background-color:{{ $sp['color'] ?? 'transparent' }}" loading="lazy"></picture>@endif
<span class="tx"><span class="bd hl">{{ __('public.special') }}</span><span class="nm">{{ $p['title'] }}</span>@if ($p['body'] !== '')<span class="sm">{{ $p['body'] }}</span>@endif @if ($p['item'] && $p['item']['price'] !== '')<span class="pr">{{ $p['item']['price'] }}</span>@endif</span>
</{{ $p['item'] ? 'button' : 'div' }}>
</div>
@endif
@endforeach
@forelse ($menus as $m)
<div class="mn"@if ($m['scheduled']) data-s="{{ json_encode($m['schedules']) }}"@endif>
@if (count($menus) > 1)<h2 class="mh" data-off="{{ __('public.not_now') }}">{{ $m['name'] }}</h2>@endif
@foreach ($m['categories'] as $c)
<section id="c{{ $c['id'] }}">
<h2>{{ $c['name'] }}</h2>
@if ($c['description'] !== '')<p class="cd">{{ $c['description'] }}</p>@endif
<ul>
@foreach ($c['items'] as $i)
<li data-i="{{ $i['id'] }}" data-t="{{ implode(' ', array_column($i['tags'], 'key')) }}"@class(['so' => $i['sold_out']])>
@php $orderable = $orderList && ! $i['sold_out'] && $i['price'] !== ''; @endphp
<button type="button" @class(['it', 'np' => ! $i['media'], 'ao' => $orderable])><span class="tx"><span class="nm">{{ $i['name'] }}</span>
@if ($i['summary'] !== '')<span class="sm">{{ $i['summary'] }}</span>@endif
@if ($i['price'] !== '')<span class="pr">{{ $i['price'] }}</span>@endif
<span class="tg">@if ($i['sold_out'])<span class="bd x0">{{ __('public.sold_out') }}</span>@endif
@if ($i['new'])<span class="bd hl">{{ __('public.new') }}</span>@endif
@if ($i['popular'])<span class="bd hl">{{ __('public.popular') }}</span>@endif
@foreach ($i['tags'] as $t)@if (! in_array($t['key'], ['new', 'popular'], true))<span class="bd">{{ $t['label'] }}</span>@endif @endforeach
</span></span>
{{-- "My order" quick add (the dish sheet has the same as a real button, with sizes and add-ons). --}}
@if ($orderable)<span class="ad" data-x="A" aria-hidden="true">+</span>@endif
@if ($i['media'])
@php $img = $i['media']; $eager = $position < $fold && $eagerLeft-- > 0; @endphp
{{-- The blurred placeholder for the first four photos; further down the dish colour is enough (less to decode, smaller page). --}}
<span class="im" style="@if ($blurLeft-- > 0)background-image:url({{ $img['placeholder'] ?? '' }});@endif background-color:{{ $img['color'] ?? 'transparent' }}"><picture>
{{-- Below the first screen the address is set after load (menu.js: data-ss / data-s), so these never compete with the banner. --}}
@php $ss = $eager ? 'srcset' : 'data-ss'; @endphp
@if ($img['avif'])<source type="image/avif" {{ $ss }}="{{ $img['avif'] }}" sizes="{{ $sizes }}">@endif
<source type="image/webp" {{ $ss }}="{{ $img['webp'] }}" sizes="{{ $sizes }}">
<img {{ $eager ? 'src' : 'data-s' }}="{{ $img['jpg'] }}" alt="" width="{{ $img['w'] }}" height="{{ $img['h'] }}" decoding="async" @if ($eager) fetchpriority="{{ ! $cover && $eagerLeft === $fold - 1 ? 'high' : 'auto' }}" @else loading="lazy" @endif>
</picture></span>
@endif
</button></li>
@php $position++; @endphp
@endforeach
</ul>
</section>
@endforeach
</div>
@empty
<div class="nr"><svg aria-hidden="true" width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M4 11h16a8 8 0 0 1-16 0zM8 3v4M12 2v5M16 3v4"/></svg><p>{{ __('public.empty') }}</p></div>
@endforelse
<p class="nr" id="nr" hidden>{{ __('public.no_results') }}</p>
</main>
@php
    // Footer icons: 24 × 24 line drawings, drawn for this page (no icon package).
    $icons = [
        'phone' => '<path d="M5 4h3l2 5-2.5 1.5a11 11 0 0 0 6 6L15 14l5 2v3a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/>',
        'whatsapp' => '<path d="M3.5 20.5l1.3-4.2A8.5 8.5 0 1 1 8 19.3z"/><path d="M9 9.5c.3 2.4 2.1 4.2 4.5 4.5l1-1.2 1.6.8"/>',
        'telegram' => '<path d="M21 4L3 11l6 2 2 6 3-4 5 4zM9 13l12-9"/>',
        'email' => '<rect x="3" y="5.5" width="18" height="13" rx="2"/><path d="M3.5 7l8.5 6 8.5-6"/>',
        'pin' => '<path d="M12 21s-7-6.1-7-12a7 7 0 0 1 14 0c0 5.9-7 12-7 12z"/><circle cx="12" cy="9" r="2.5"/>',
        'wifi' => '<path d="M2.5 9a14 14 0 0 1 19 0M5.5 12.5a9.5 9.5 0 0 1 13 0M8.8 16a5 5 0 0 1 6.4 0M12 19.5h.01"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'store' => '<path d="M4 10v10h16V10M3 10l2-6h14l2 6zM9 20v-5h6v5"/>',
        'file' => '<path d="M14 3H6v18h12V7zM14 3v4h4M9 13h6M9 17h6"/>',
        'facebook' => '<path d="M14.5 3H13a4 4 0 0 0-4 4v3H7v3.5h2V21h3.5v-7.5H15l.5-3.5h-3V7.5a1 1 0 0 1 1-1h1.5z"/>',
        'instagram' => '<rect x="3.5" y="3.5" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="4"/><path d="M17 7h.01"/>',
        'tiktok' => '<path d="M14 3v11.5a3.5 3.5 0 1 1-3.5-3.5M14 3c.5 2.6 2.4 4.5 5 4.8"/>',
        'x' => '<path d="M4.5 4.5l15 15M19.5 4.5l-15 15"/>',
        'youtube' => '<rect x="2.5" y="5.5" width="19" height="13" rx="3.5"/><path d="M10 9l5 3-5 3z"/>',
        'website' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
    ];
    $icon = fn (string $k): string => '<svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'.$icons[$k].'</svg>';
    $pdfLink = $theme['show_pdf_link'] && $pdfMenu;
@endphp
@if ($hasInfo || $pdfLink || $branding)
{{-- Footer (owner, 2026-11-12): who, when, where, how to reach them. id="info" is the (i) button's target. --}}
<footer class="ft" id="info"><div class="w">
<div class="fh">@if ($theme['logo'])<img class="fl" src="{{ $theme['logo']['320'] ?? '' }}" alt="" width="48" height="48" loading="lazy">@endif
<div><p class="fn">{{ $tenant->name }}</p>@if ($branch)<p class="sub">{{ $branch }}</p>@endif</div></div>
@if ($info['about'] !== '')<p class="ab">{{ $info['about'] }}</p>@endif
@if ($hoursSummary || $info['address'] !== '' || $info['maps'] || $info['links'] || $info['wifi'] || $otherBranches)<div class="fg">
@if ($hoursSummary)<div><h2>{!! $icon('clock') !!}{{ __('public.hours') }}</h2><dl class="hs">@foreach ($hoursSummary as $h)<div><dt>{{ $h['days'] }}</dt><dd>{{ $h['times'] }}</dd></div>@endforeach</dl></div>@endif
@if ($info['address'] !== '' || $info['maps'])<div><h2>{!! $icon('pin') !!}{{ __('public.address') }}</h2>@if ($info['address'] !== '')<p>{{ $info['address'] }}</p>@endif @if ($info['maps'])<a class="fbt" href="{{ $info['maps'] }}" rel="noopener" target="_blank">{{ __('public.open_maps') }}</a>@endif</div>@endif
@if ($info['links'])<div><h2>{{ __('public.contact') }}</h2><ul class="fc">@foreach ($info['links'] as $l)<li><a href="{{ $l['href'] }}" rel="noopener">{!! $icon($l['icon']) !!}<span><small>{{ $l['label'] }}</small>{{ $l['text'] }}</span></a></li>@endforeach</ul></div>@endif
@if ($info['wifi'])<div><h2>{!! $icon('wifi') !!}Wi-Fi</h2><p>{{ __('public.wifi_name') }}: <b>{{ $info['wifi']['name'] }}</b>@if ($info['wifi']['password'])<br>{{ __('public.wifi_password') }}: <b class="pw">{{ $info['wifi']['password'] }}</b>@endif</p></div>@endif
@if ($otherBranches)<div><h2>{!! $icon('store') !!}{{ __('public.other_branches') }}</h2><ul class="ob">@foreach ($otherBranches as $b)<li><a href="{{ $b['path'] }}">{{ $b['name'] }}</a>@if ($b['address'] !== '')<span>{{ $b['address'] }}</span>@endif</li>@endforeach</ul></div>@endif
</div>@endif
@if ($info['socials'])<ul class="fso">@foreach ($info['socials'] as $l)<li><a href="{{ $l['href'] }}" rel="noopener" target="_blank" aria-label="{{ $l['label'] }}" title="{{ $l['label'] }}">{!! $icon($l['icon']) !!}</a></li>@endforeach</ul>@endif
@if ($pdfLink)<p class="pdl"><a href="{{ rtrim($canonical, '/') }}/menu.pdf">{!! $icon('file') !!}{{ __('public.pdf_menu') }}</a></p>@endif
@if ($branding)<p class="pb">{{ __('public.powered', ['app' => \App\Support\PlatformSettings::get('name')]) }}</p>@endif
</div></footer>
@endif
<dialog id="s" aria-label="{{ $tenant->name }}"><div class="sh" id="sb"></div><button type="button" class="x" id="sx" aria-label="{{ __('public.close') }}">×</button></dialog>
<div class="of" id="of" hidden>{{ __('public.offline') }}</div>
<script type="application/json" id="d">{!! json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
<script>{!! InlineAssets::js() !!}</script>
</body>
</html>
