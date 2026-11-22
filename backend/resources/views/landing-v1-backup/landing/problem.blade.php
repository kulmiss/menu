<section class="{{ $soft ? 'soft' : '' }}" id="problem" aria-labelledby="problem-title">
    <div class="wrap">
        <div class="head reveal"><h2 id="problem-title">{{ $t('problem.title') }}</h2></div>
        <div class="grid3">
            @foreach (array_keys(__('landing.problem.items')) as $key)
                <div class="card pain reveal">
                    <div class="tile">{!! $icon($key) !!}</div>
                    <h3>{{ $t("problem.items.{$key}.title") }}</h3>
                    <p>{{ $t("problem.items.{$key}.text") }}</p>
                </div>
            @endforeach
        </div>
        <p class="fix reveal">{!! $icon('check') !!}<span>{{ $t('problem.text') }}</span></p>
        @if ($cta)<div class="more reveal">{!! $ctaButton() !!}</div>@endif
    </div>
</section>
