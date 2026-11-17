<section class="{{ $soft ? 'soft' : '' }}" id="how" aria-labelledby="how-title">
    <div class="wrap">
        <div class="head reveal"><h2 id="how-title">{{ __('landing.how.title') }}</h2><p>{{ __('landing.how.subtitle') }}</p></div>
        <ol class="steps">
            @foreach (__('landing.how.steps') as $i => $step)
                <li class="step reveal">
                    <div class="tile">{!! $icon(['user', 'camera', 'printer'][$i] ?? 'check') !!}<span class="num" aria-hidden="true">{{ $i + 1 }}</span></div>
                    <h3><span class="sr-only">{{ $i + 1 }}. </span>{{ $step['title'] }}</h3>
                    <p>{{ $step['text'] }}</p>
                </li>
            @endforeach
        </ol>
        @if ($cta)<div class="more reveal">{!! $ctaButton() !!}</div>@endif
    </div>
</section>
