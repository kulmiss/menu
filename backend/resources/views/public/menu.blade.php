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
    $fold = ['editorial' => [2, 0], 'grid' => [4, 0], 'bistro' => [5, 2]][$style][$cover ? 1 : 0];
    $eagerLeft = $fold;
    $position = 0;
    $hasInfo = $week || $info['address'] || $info['links'] || $info['about'] || $info['wifi'] || $info['socials'];
    $sheets = [];
    $firstImage = null;
    foreach ($menus as $m) { foreach ($m['categories'] as $c) { foreach ($c['items'] as $i) {
        if ($i['sheet'] !== []) { $sheets[$i['id']] = $i['sheet']; }
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
        't' => [
            'so' => __('public.sold_out'), 'sz' => __('public.sizes'), 'al' => __('public.allergens_title'),
            'kc' => __('public.calories'), 'pm' => __('public.prep'), 'ch' => __('public.choose'),
            'up' => __('public.choose_up_to'), 'ex' => __('public.choose_exactly'),
            'on' => __('public.open_until'), 'cl' => __('public.closed'), 'ct' => __('public.closed_today'),
            'oa' => __('public.opens_at'), 'ot' => __('public.opens_tomorrow'), 'od' => __('public.opens_day'),
            'sh' => __('public.share'), 'sl' => __('public.share_link'), 'cp' => __('public.copy_link'), 'cd' => __('public.copied'),
            'ph' => __('public.photos'), 'nx' => __('public.next_photo'), 'pv' => __('public.previous_photo'),
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
<img src="{{ $cover['jpg'] }}" alt="" width="{{ $cover['w'] }}" height="{{ $cover['h'] }}" fetchpriority="high" decoding="async">
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
@if ($branches)
<nav class="ch bs" aria-label="{{ __('public.branches') }}">@foreach ($branches as $b)<a href="{{ $b['path'] }}"@if ($b['current']) class="on" aria-current="page"@endif>{{ $b['name'] }}</a>@endforeach</nav>
@endif
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
@if ($p['image'])<img src="{{ $p['image'] }}" alt="" width="640" height="427" loading="lazy" decoding="async">@endif
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
<button type="button" @class(['it', 'np' => ! $i['media']])><span class="tx"><span class="nm">{{ $i['name'] }}</span>
@if ($i['summary'] !== '')<span class="sm">{{ $i['summary'] }}</span>@endif
@if ($i['price'] !== '')<span class="pr">{{ $i['price'] }}</span>@endif
<span class="tg">@if ($i['sold_out'])<span class="bd x0">{{ __('public.sold_out') }}</span>@endif
@if ($i['new'])<span class="bd hl">{{ __('public.new') }}</span>@endif
@if ($i['popular'])<span class="bd hl">{{ __('public.popular') }}</span>@endif
@foreach ($i['tags'] as $t)@if (! in_array($t['key'], ['new', 'popular'], true))<span class="bd">{{ $t['label'] }}</span>@endif @endforeach
</span></span>
@if ($i['media'])
@php $img = $i['media']; $eager = $position < $fold && $eagerLeft-- > 0; @endphp
<span class="im" style="background-image:url({{ $img['placeholder'] ?? '' }});background-color:{{ $img['color'] ?? 'transparent' }}"><picture>
@if ($img['avif'])<source type="image/avif" srcset="{{ $img['avif'] }}" sizes="{{ $sizes }}">@endif
<source type="image/webp" srcset="{{ $img['webp'] }}" sizes="{{ $sizes }}">
<img src="{{ $img['jpg'] }}" alt="" width="{{ $img['w'] }}" height="{{ $img['h'] }}" decoding="async" @if ($eager) fetchpriority="{{ ! $cover && $eagerLeft === $fold - 1 ? 'high' : 'auto' }}" @else loading="lazy" @endif>
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
@if ($hasInfo)
<details class="inf w" id="info"><summary>{{ __('public.info') }}</summary>
@if ($info['about'] !== '')<p class="ab">{{ $info['about'] }}</p>@endif
@if ($week)<h3>{{ __('public.hours') }}</h3><table>@foreach ($week as $row)<tr><th scope="row">{{ $row['day'] }}</th><td>{{ $row['times'] }}</td></tr>@endforeach</table>@endif
@if ($info['address'] !== '' || $info['maps'])<h3>{{ __('public.address') }}</h3>@if ($info['address'] !== '')<p>{{ $info['address'] }}</p>@endif @if ($info['maps'])<p><a href="{{ $info['maps'] }}" rel="noopener" target="_blank">{{ __('public.open_maps') }}</a></p>@endif @endif
@if ($info['links'])<h3>{{ __('public.contact') }}</h3><ul class="ln">@foreach ($info['links'] as $l)<li><a href="{{ $l['href'] }}" rel="noopener">{{ $l['label'] }}: {{ $l['text'] }}</a></li>@endforeach</ul>@endif
@if ($info['wifi'])<h3>Wi-Fi</h3><p>{{ __('public.wifi_name') }}: <b>{{ $info['wifi']['name'] }}</b>@if ($info['wifi']['password'])<br>{{ __('public.wifi_password') }}: <b class="pw">{{ $info['wifi']['password'] }}</b>@endif</p>@endif
@if ($info['socials'])<ul class="ln so2">@foreach ($info['socials'] as $l)<li><a href="{{ $l['href'] }}" rel="noopener" target="_blank">{{ $l['label'] }}</a></li>@endforeach</ul>@endif
</details>
@endif
@if ($branding)@if ($theme['show_pdf_link'] && $pdfMenu)<p class="pdl"><a href="{{ rtrim($canonical, '/') }}/menu.pdf">{{ __('public.pdf_menu') }}</a></p>@endif
<footer>{{ __('public.powered', ['app' => \App\Support\PlatformSettings::get('name')]) }}</footer>@endif
<dialog id="s" aria-label="{{ $tenant->name }}"><div class="sh" id="sb"></div><button type="button" class="x" id="sx" aria-label="{{ __('public.close') }}">×</button></dialog>
<div class="of" id="of" hidden>{{ __('public.offline') }}</div>
<script type="application/json" id="d">{!! json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
<script>{!! InlineAssets::js() !!}</script>
</body>
</html>
