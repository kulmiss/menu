<section class="{{ $soft ? 'soft' : '' }}" id="features" aria-labelledby="features-title">
    <div class="wrap">
        <div class="head reveal"><h2 id="features-title">{{ $t('features.title') }}</h2><p>{{ $t('features.subtitle') }}</p></div>
        <div class="grid-features">
            @foreach (array_keys(__('landing.features.items')) as $key)
                <div class="card hover reveal">
                    <div class="tile">{!! $icon($key) !!}</div>
                    <h3>{{ $t("features.items.{$key}.title") }}@if ($paidOnly[$key] ?? false) <span class="tag">{{ $t('features.paid') }}</span>@endif</h3>
                    <p>{{ $t("features.items.{$key}.text") }}</p>
                </div>
            @endforeach
        </div>
        @if ($cta)<div class="more reveal">{!! $ctaButton() !!}</div>@endif
    </div>
</section>
