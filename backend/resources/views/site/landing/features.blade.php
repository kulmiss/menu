<section class="{{ $soft ? 'soft' : '' }}" id="features" aria-labelledby="features-title">
    <div class="wrap">
        <div class="head reveal"><h2 id="features-title">{{ __('landing.features.title') }}</h2><p>{{ __('landing.features.subtitle') }}</p></div>
        <div class="grid-features">
            @foreach (__('landing.features.items') as $key => $feature)
                <div class="card hover reveal">
                    <div class="tile">{!! $icon($key) !!}</div>
                    <h3>{{ $feature['title'] }}@if ($paidOnly[$key] ?? false) <span class="tag">{{ __('landing.features.paid') }}</span>@endif</h3>
                    <p>{{ $feature['text'] }}</p>
                </div>
            @endforeach
        </div>
        @if ($cta)<div class="more reveal">{!! $ctaButton() !!}</div>@endif
    </div>
</section>
