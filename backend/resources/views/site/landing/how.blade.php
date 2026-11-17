<section class="{{ $soft ? 'soft' : '' }}" id="how" aria-labelledby="how-title">
    <div class="wrap">
        <div class="head reveal"><h2 id="how-title">{{ $t('how.title') }}</h2><p>{{ $t('how.subtitle') }}</p></div>
        <ol class="steps">
            @foreach (array_keys(__('landing.how.steps')) as $i)
                <li class="step reveal">
                    <div class="tile">{!! $icon(['user', 'camera', 'printer'][$i] ?? 'check') !!}<span class="num" aria-hidden="true">{{ $i + 1 }}</span></div>
                    <h3><span class="sr-only">{{ $i + 1 }}. </span>{{ $t("how.steps.{$i}.title") }}</h3>
                    <p>{{ $t("how.steps.{$i}.text") }}</p>
                </li>
            @endforeach
        </ol>
        @if ($cta)<div class="more reveal">{!! $ctaButton() !!}</div>@endif
    </div>
</section>
