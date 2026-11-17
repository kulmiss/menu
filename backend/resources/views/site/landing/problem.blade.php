<section class="{{ $soft ? 'soft' : '' }}" id="problem" aria-labelledby="problem-title">
    <div class="wrap">
        <div class="head reveal"><h2 id="problem-title">{{ __('landing.problem.title') }}</h2></div>
        <div class="grid3">
            @foreach (__('landing.problem.items') as $key => $item)
                <div class="card pain reveal">
                    <div class="tile">{!! $icon($key) !!}</div>
                    <h3>{{ $item['title'] }}</h3>
                    <p>{{ $item['text'] }}</p>
                </div>
            @endforeach
        </div>
        <p class="fix reveal">{!! $icon('check') !!}<span>{{ __('landing.problem.text') }}</span></p>
        @if ($cta)<div class="more reveal">{!! $ctaButton() !!}</div>@endif
    </div>
</section>
