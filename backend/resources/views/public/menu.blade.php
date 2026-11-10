@php
    use App\Support\PublicMenu\InlineAssets;
    $preview ??= false;
    $cover = $theme['cover'];
    $eagerLeft = $cover ? 2 : 4; // the first photos load at once; the rest lazily (PLAN.md §15.2.4)
    $sizes = ['list' => '88px', 'grid' => '(min-width:44rem) 340px, 50vw', 'large' => '(min-width:44rem) 704px, 100vw'][$theme['layout']];
    $sheets = [];
    $firstImage = null;
    foreach ($menus as $m) { foreach ($m['categories'] as $c) { foreach ($c['items'] as $i) {
        if ($i['sheet'] !== []) { $sheets[$i['id']] = $i['sheet']; }
        $firstImage ??= $i['media']['og'] ?? null;
    } } }
    $data = [
        'id' => $tenant->id,
        'p' => $preview ? 1 : 0,
        'z' => $timezone,
        'c' => (object) $sheets,
        't' => [
            'so' => __('public.sold_out'), 'sz' => __('public.sizes'), 'al' => __('public.allergens_title'),
            'kc' => __('public.calories'), 'pm' => __('public.prep'), 'ch' => __('public.choose'),
            'up' => __('public.choose_up_to'), 'ex' => __('public.choose_exactly'),
        ],
    ];

@endphp
<!doctype html>
<html lang="{{ $locale }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>{{ $tenant->name }}</title>
<meta name="description" content="{{ $tenant->name }} — menu">
<meta name="theme-color" content="{{ $theme['theme_color'] }}">
<link rel="canonical" href="{{ url('/'.$tenant->slug) }}">
<meta property="og:title" content="{{ $tenant->name }}">
<meta property="og:type" content="website">
<meta property="og:url" content="{{ url('/'.$tenant->slug) }}">
@if ($firstImage)<meta property="og:image" content="{{ $firstImage }}">@endif
<link rel="icon" href="data:,">
<style>{!! InlineAssets::css() !!}{!! $theme['css'] !!}</style>
</head>
<body>
@if ($cover)
<div class="cv" style="background-image:url({{ $cover['placeholder'] ?? '' }});background-color:{{ $cover['color'] ?? 'transparent' }}"><picture>
@if (isset($cover['urls']['avif640']))<source type="image/avif" srcset="{{ $cover['urls']['avif640'] }} 640w{{ isset($cover['urls']['avif1280']) ? ', '.$cover['urls']['avif1280'].' 1280w' : '' }}" sizes="(min-width:44rem) 704px, 100vw">@endif
<source type="image/webp" srcset="{{ $cover['urls']['640'] ?? '' }} 640w{{ isset($cover['urls']['1280']) ? ', '.$cover['urls']['1280'].' 1280w' : '' }}" sizes="(min-width:44rem) 704px, 100vw">
<img src="{{ $cover['urls']['jpg'] ?? '' }}" alt="" width="1280" height="549" fetchpriority="high" decoding="async">
</picture></div>
@endif
<div class="w">
<header>
@if ($theme['logo'])<img class="lg" src="{{ $theme['logo']['320'] ?? '' }}" alt="" width="56" height="56">@endif
<h1>{{ $tenant->name }}</h1>
</header>
</div>
<div class="bar"><div class="w">
<div class="sr"><input id="q" type="search" placeholder="{{ __('public.search') }}" aria-label="{{ __('public.search') }}" autocomplete="off"></div>
@if (count($tags) > 1)
<div class="ch" role="group" aria-label="{{ __('public.filter') }}">@foreach ($tags as $key => $label)<button type="button" data-tag="{{ $key }}" aria-pressed="false">{{ $label }}</button>@endforeach</div>
@endif
<nav class="ch" aria-label="Categories">@foreach ($menus as $m)@foreach ($m['categories'] as $c)<a href="#c{{ $c['id'] }}">{{ $c['name'] }}</a>@endforeach @endforeach</nav>
</div></div>
<main class="w L-{{ $theme['layout'] }}">
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
<button type="button" class="it"><span class="tx"><span class="nm">{{ $i['name'] }}</span>
@if ($i['summary'] !== '')<span class="sm">{{ $i['summary'] }}</span>@endif
@if ($i['price'] !== '')<span class="pr">{{ $i['price'] }}</span>@endif
@if ($i['sold_out'])<span class="bd">{{ __('public.sold_out') }}</span>@endif
@if ($i['new'])<span class="bd hl">{{ __('public.new') }}</span>@endif
@if ($i['popular'])<span class="bd hl">{{ __('public.popular') }}</span>@endif
@foreach ($i['tags'] as $t)@if (! in_array($t['key'], ['new', 'popular'], true))<span class="bd">{{ $t['label'] }}</span>@endif @endforeach
</span>
@if ($i['media'])
@php $img = $i['media']; $eager = $eagerLeft-- > 0; @endphp
<span class="im" style="background-image:url({{ $img['placeholder'] ?? '' }});background-color:{{ $img['color'] ?? 'transparent' }}"><picture>
@if ($img['avif'])<source type="image/avif" srcset="{{ $img['avif'] }}" sizes="{{ $sizes }}">@endif
<source type="image/webp" srcset="{{ $img['webp'] }}" sizes="{{ $sizes }}">
<img src="{{ $img['jpg'] }}" alt="" width="{{ $img['w'] }}" height="{{ $img['h'] }}" decoding="async" @if ($eager) fetchpriority="{{ ! $cover && $eagerLeft === 3 ? 'high' : 'auto' }}" @else loading="lazy" @endif>
</picture></span>
@endif
</button></li>
@endforeach
</ul>
</section>
@endforeach
</div>
@empty
<p class="nr">{{ __('public.empty') }}</p>
@endforelse
<p class="nr" id="nr" hidden>{{ __('public.no_results') }}</p>
</main>
<footer>{{ __('public.powered', ['app' => config('app.name')]) }}</footer>
<dialog id="s" aria-label="{{ $tenant->name }}"><div class="sh" id="sb"></div><button type="button" class="x" id="sx" aria-label="{{ __('public.close') }}">×</button></dialog>
<div class="of" id="of" hidden>{{ __('public.offline') }}</div>
<script type="application/json" id="d">{!! json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
<script>{!! InlineAssets::js() !!}</script>
</body>
</html>
