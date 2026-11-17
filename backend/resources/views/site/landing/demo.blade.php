<section class="{{ $soft ? 'soft' : '' }}" id="demo" aria-labelledby="demo-title">
    <div class="wrap split">
        <div class="reveal">
            <span class="kicker">{{ __('landing.demo.eyebrow') }}</span>
            <h2 id="demo-title">{{ __('landing.demo.title') }}</h2>
            <p class="text">{{ __('landing.demo.text') }}</p>
            {{-- Desktop: scan the code with a phone. Phone: open it right here. --}}
            <div class="scan">
                <svg viewBox="0 0 {{ $qr['size'] }} {{ $qr['size'] }}" role="img" aria-label="{{ __('landing.hero.qr_alt') }}" shape-rendering="crispEdges"><path fill="#0b0f19" d="{{ $qr['path'] }}"/></svg>
                <div>
                    <strong>{{ __('landing.demo.scan') }}</strong>
                    <span>{{ __('landing.demo.scan_text') }}</span>
                    <a class="link" href="{{ $demoUrl }}" target="_blank" rel="noopener" data-track="demo">{{ __('landing.demo.open') }} {!! $icon('external') !!}<span class="sr-only"> {{ __('landing.new_tab') }}</span></a>
                </div>
            </div>
            <div class="open-demo">
                <a class="btn btn-primary btn-lg" href="{{ $demoUrl }}" target="_blank" rel="noopener" data-track="demo">{{ __('landing.demo.open') }} {!! $icon('external') !!}<span class="sr-only"> {{ __('landing.new_tab') }}</span></a>
            </div>
            @if ($styles)
                <p class="looks">
                    <span>{{ __('landing.demo.styles') }}</span>
                    @foreach ($styles as $key => $href)
                        <a class="link" href="{{ $href }}" target="_blank" rel="noopener" data-track="demo" aria-label="{{ __('landing.demo.style_label', ['style' => __("landing.demo.style_names.{$key}")]) }}">{{ __("landing.demo.style_names.{$key}") }}</a>
                    @endforeach
                </p>
            @endif
        </div>
        <a class="demo-phone reveal" href="{{ $demoUrl }}" target="_blank" rel="noopener" data-track="demo" tabindex="-1" aria-hidden="true">
            <div class="glow"></div>
            <div class="phone">
                <picture>
                    <source type="image/avif" media="(prefers-color-scheme: dark)" srcset="{{ $set('demo-dark', 'avif') }}" sizes="240px">
                    <source type="image/webp" media="(prefers-color-scheme: dark)" srcset="{{ $set('demo-dark', 'webp') }}" sizes="240px">
                    <source type="image/avif" srcset="{{ $set('demo', 'avif') }}" sizes="240px">
                    <img src="{{ asset('landing/demo-600.webp') }}" srcset="{{ $set('demo', 'webp') }}" sizes="240px" width="300" height="608" alt="" loading="lazy" decoding="async">
                </picture>
            </div>
        </a>
    </div>
</section>
