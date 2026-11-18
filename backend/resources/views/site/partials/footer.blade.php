@php
    // The website footer (QUESTIONS #230). $footer: Footer::view() data (the preview passes the draft);
    // otherwise the live one. Every text is escaped; links were checked when saved (Footer::safeUrl).
    $f = $footer ?? \App\Support\Site\Footer::view(\App\Support\Site\Footer::published());
    $logoUrl = $footerLogo ?? \App\Support\EmailLogo::url();
    $mark = mb_strtoupper(mb_substr($f['name'], 0, 1));
    // 24 × 24 line icons, drawn for this footer (no icon package, no brand logos).
    $paths = [
        'whatsapp' => '<path d="M3.5 20.5l1.3-4.2A8.5 8.5 0 1 1 8 19.3z"/><path d="M9 9.5c.3 2.4 2.1 4.2 4.5 4.5l1-1.2 1.6.8"/>',
        'phone' => '<path d="M5 4h3l2 5-2.5 1.5a11 11 0 0 0 6 6L15 14l5 2v3a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/>',
        'email' => '<rect x="3" y="5.5" width="18" height="13" rx="2"/><path d="M3.5 7l8.5 6 8.5-6"/>',
        'address' => '<path d="M12 21s-7-6.1-7-12a7 7 0 0 1 14 0c0 5.9-7 12-7 12z"/><circle cx="12" cy="9" r="2.5"/>',
        'hours' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'facebook' => '<path d="M14.5 3H13a4 4 0 0 0-4 4v3H7v3.5h2V21h3.5v-7.5H15l.5-3.5h-3V7.5a1 1 0 0 1 1-1h1.5z"/>',
        'instagram' => '<rect x="3.5" y="3.5" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="4"/><path d="M17 7h.01"/>',
        'tiktok' => '<path d="M14 3v11.5a3.5 3.5 0 1 1-3.5-3.5M14 3c.5 2.6 2.4 4.5 5 4.8"/>',
        'youtube' => '<rect x="2.5" y="5.5" width="19" height="13" rx="3.5"/><path d="M10 9l5 3-5 3z"/>',
        'telegram' => '<path d="M21 4L3 11l6 2 2 6 3-4 5 4zM9 13l12-9"/>',
        'x' => '<path d="M4.5 4.5l15 15M19.5 4.5l-15 15"/>',
        'linkedin' => '<rect x="3" y="3" width="18" height="18" rx="3"/><path d="M8 10.5V17M8 7.5v.01M12 17v-6.5M12 13.5a2.5 2.5 0 0 1 5 0V17"/>',
        'chevron' => '<path d="M6 9l6 6 6-6"/>',
        'up' => '<path d="M12 19V5M5 12l7-7 7 7"/>',
        'external' => '<path d="M15 3h6v6M10 14 21 3M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
    ];
    $icon = fn (string $k) => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$paths[$k].'</svg>';
@endphp
<footer class="sf" aria-label="{{ __('footer.label') }}">
@if ($f['illustration'])
{{-- Our own line drawing: a city, a coffee ceremony (jebena and cups) and a phone with a QR code. --}}
<div class="sf-art" aria-hidden="true"><svg viewBox="0 0 1200 80" preserveAspectRatio="xMidYMax slice" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M0 78H1200"/><path d="M60 78V40h40v38M110 78V22h34v56M127 22V12M154 78V48h50v30M214 78V30h28v48M252 78V52h44v26M306 78V36h36v42M352 78V56h46v22"/><path d="M70 50h8M82 50h8M70 60h8M82 60h8M118 32h6M130 32h6M118 44h6M130 44h6M118 56h6M130 56h6M222 40h12M222 52h12M222 64h12M314 46h8M326 46h8M314 58h8M326 58h8M164 58h30M262 62h24"/><path d="M528 74h150"/><path d="M600 72c-20 0-28-12-28-23 0-13 12-22 28-22s28 9 28 22c0 11-8 23-28 23z"/><path d="M594 27c0-8 2-13 6-19 4 6 6 11 6 19M626 42c9-3 15-11 17-21M574 38c-11 2-12 17-1 20"/><path d="M648 72c-1-6 0-10 0-10h14c1 4 1 7 0 10zM670 72c-1-6 0-10 0-10h14c1 4 1 7 0 10z"/><path d="M655 55c-4-4 4-8 0-12M677 55c-4-4 4-8 0-12"/><path d="M760 22c4-3 8-3 10 0 2-3 6-3 10 0M800 34c3-2 6-2 8 0 2-2 5-2 8 0"/><path d="M880 78V18a6 6 0 0 1 6-6h40a6 6 0 0 1 6 6v60"/><path d="M896 28h10v10h-10zM916 28h10v10h-10zM896 48h10v10h-10zM916 48h4v4M922 54h4v4M900 66h12"/><path d="M990 78c0-20 6-34 18-42M1008 36c-10 4-14 12-14 20M1008 36c4 10 2 20-6 26"/><path d="M1066 78V46M1066 46c-14-2-20-12-16-24 10 0 18 10 16 24zM1066 54c12-2 18-10 16-20-10 0-16 8-16 20z"/><circle cx="1130" cy="26" r="10"/></svg></div>
@endif
<div class="sf-in">
<div class="sf-grid">
<div>
<a class="sf-brand" href="/">@if ($logoUrl)<img src="{{ $logoUrl }}" alt="" height="32" loading="lazy">@else<span class="sf-mark" aria-hidden="true">{{ $mark }}</span>@endif<span>{{ $f['name'] }}</span></a>
@if ($f['trust'])<p class="sf-trust">{{ $f['trust'] }}</p>@endif
@if ($f['contact'])
{{-- Contact stays open on phones: WhatsApp first. --}}
<div class="sf-contact">
<h2>{{ $f['contact']['title'] }}</h2>
@isset($f['contact']['whatsapp'])<a class="sf-wa" href="{{ $f['contact']['whatsapp'] }}" target="_blank" rel="noopener">{!! $icon('whatsapp') !!}{{ __('footer.contact.whatsapp') }}<span class="sr-only"> {{ __('footer.new_tab') }}</span></a>@endisset
@isset($f['contact']['phone'])<a class="sf-ci" href="{{ $f['contact']['phone']['href'] }}">{!! $icon('phone') !!}<span><span class="sr-only">{{ __('footer.contact.phone') }}: </span>{{ $f['contact']['phone']['text'] }}</span></a>@endisset
@isset($f['contact']['email'])<a class="sf-ci" href="{{ $f['contact']['email']['href'] }}">{!! $icon('email') !!}<span><span class="sr-only">{{ __('footer.contact.email') }}: </span>{{ $f['contact']['email']['text'] }}</span></a>@endisset
@isset($f['contact']['address'])<p class="sf-ci">{!! $icon('address') !!}<span><span class="sr-only">{{ __('footer.contact.address') }}: </span>{{ $f['contact']['address'] }}</span></p>@endisset
@isset($f['contact']['hours'])<p class="sf-ci">{!! $icon('hours') !!}<span><span class="sr-only">{{ __('footer.contact.hours') }}: </span>{{ $f['contact']['hours'] }}</span></p>@endisset
</div>
@endif
</div>
<div>
@if ($f['columns'])
<div class="sf-cols" style="--sf-n:{{ count($f['columns']) }}">
@foreach ($f['columns'] as $c)
<details class="sf-col"><summary><h2>{{ $c['title'] }}</h2>{!! $icon('chevron') !!}</summary>
<ul>@foreach ($c['links'] as $l)<li><a href="{{ $l['href'] }}"@if ($l['external']) target="_blank" rel="noopener"@endif>{{ $l['label'] }}@if ($l['external']){!! $icon('external') !!}<span class="sr-only"> {{ __('footer.new_tab') }}</span>@endif</a></li>@endforeach</ul>
</details>
@endforeach
</div>
@endif
@if ($f['socials'])
<ul class="sf-social" aria-label="{{ __('footer.follow') }}">@foreach ($f['socials'] as $network => $href)<li><a href="{{ $href }}" target="_blank" rel="noopener me" aria-label="{{ __('footer.socials.'.$network) }} {{ __('footer.new_tab') }}" title="{{ __('footer.socials.'.$network) }}">{!! $icon($network) !!}</a></li>@endforeach</ul>
@endif
@if ($f['play'])
{{-- Google's own badge, unchanged (Google Play badge guidelines). --}}
<a class="sf-app" href="{{ $f['play'] }}" target="_blank" rel="noopener"><img src="/images/google-play-badge.png" alt="{{ __('footer.app_badge') }} {{ __('footer.new_tab') }}" width="145" height="56" loading="lazy"></a>
@endif
</div>
</div>
<div class="sf-bar">
@if ($logoUrl)<img src="{{ $logoUrl }}" alt="" height="24" loading="lazy">@else<span class="sf-mark" aria-hidden="true">{{ $mark }}</span>@endif
<span>{{ __('footer.copyright', ['year' => date('Y'), 'name' => $f['company']]) }}</span>
@if ($f['made_in'])<span>{{ $f['made_in'] }}</span>@endif
@if ($f['back_to_top'])<a class="sf-up" href="#top">{!! $icon('up') !!}{{ __('footer.back_to_top') }}</a>@endif
</div>
</div>
</footer>
{{-- Phones: sections start closed (tap to open). Wide screens show them open: by CSS where the browser can,
     else this sets "open" (only on wide screens, so nothing moves on a phone). --}}
<script nonce="{{ \App\Support\Security\Csp::nonce() }}">(function(){if(window.CSS&&CSS.supports&&CSS.supports('selector(::details-content)'))return;var m=matchMedia('(min-width:640px)'),s=function(){document.querySelectorAll('.sf-col').forEach(function(d){d.open=m.matches;});};s();m.addEventListener&&m.addEventListener('change',s);})();</script>
