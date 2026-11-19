<section class="{{ $soft ? 'soft' : '' }}" id="demo" aria-labelledby="demo-title">
    <div class="wrap split">
        <div class="reveal">
            <span class="kicker">{{ $t('demo.eyebrow') }}</span>
            <h2 id="demo-title">{{ $t('demo.title') }}</h2>
            <p class="text">{{ $t('demo.text') }}</p>
            {{-- Desktop: scan the code with a phone. Phone: open it right here. --}}
            <div class="scan">
                <svg viewBox="0 0 {{ $qr['size'] }} {{ $qr['size'] }}" role="img" aria-label="{{ $t('hero.qr_alt') }}" shape-rendering="crispEdges"><path fill="#0b0f19" d="{{ $qr['path'] }}"/></svg>
                <div>
                    <strong>{{ $t('demo.scan') }}</strong>
                    <span>{{ $t('demo.scan_text') }}</span>
                    <a class="link" href="{{ $demoUrl }}" target="_blank" rel="noopener" data-track="demo">{{ $t('demo.open') }} {!! $icon('external') !!}<span class="sr-only"> {{ $t('new_tab') }}</span></a>
                </div>
            </div>
            <div class="open-demo">
                <a class="btn btn-primary btn-lg" href="{{ $demoUrl }}" target="_blank" rel="noopener" data-track="demo">{{ $t('demo.open') }} {!! $icon('external') !!}<span class="sr-only"> {{ $t('new_tab') }}</span></a>
            </div>
        </div>
        <a class="demo-phone reveal" href="{{ $demoUrl }}" target="_blank" rel="noopener" data-track="demo" tabindex="-1" aria-hidden="true">
            <div class="glow"></div>
            <div class="phone">
                {!! $picture('demo', '', true) !!}
            </div>
        </a>
    </div>
    @if ($styles)
        {{-- Menu styles (owner, 2026-11-19): a phone picture of each style's demo, a swipeable row on phones. --}}
        <div class="wrap styles reveal">
            <h3 id="styles-title">{{ $t('demo.styles') }}</h3>
            <ul class="style-row" aria-labelledby="styles-title">
                @foreach ($styles as $key => $style)
                    <li><a href="{{ $style['href'] }}" target="_blank" rel="noopener" data-track="demo" aria-label="{{ $t('demo.style_label', ['style' => $t("demo.style_names.{$key}")]) }}">
                        @if ($style['shot'])
                            <picture>@if ($style['shot']['avif'])<source type="image/avif" srcset="{{ $style['shot']['avif'] }}" sizes="150px">@endif<img src="{{ $style['shot']['src'] }}" srcset="{{ $style['shot']['webp'] }}" sizes="150px" width="150" height="304" alt="" loading="lazy" decoding="async"></picture>
                        @endif
                        <span>{{ $t("demo.style_names.{$key}") }}</span>
                    </a></li>
                @endforeach
            </ul>
        </div>
    @endif
</section>
