<section class="{{ $soft ? 'soft' : '' }}" id="compare" aria-labelledby="compare-title">
    <div class="wrap">
        <div class="head reveal"><h2 id="compare-title">{{ __('landing.compare.title') }}</h2><p>{{ __('landing.compare.subtitle') }}</p></div>
        <div class="compare">
            <div class="card before reveal">
                <h3>{!! $icon('receipt') !!} {{ __('landing.compare.before') }}</h3>
                <ul>
                    @foreach (__('landing.compare.rows') as [$before, $after])
                        <li>{!! $icon('x') !!}<span>{{ $before }}</span></li>
                    @endforeach
                </ul>
            </div>
            <div class="card after reveal">
                <h3>{!! $icon('qr') !!} {{ __('landing.compare.after') }}</h3>
                <ul>
                    @foreach (__('landing.compare.rows') as [$before, $after])
                        <li>{!! $icon('check') !!}<span>{{ $after }}</span></li>
                    @endforeach
                </ul>
            </div>
        </div>
        @if ($cta)<div class="more reveal">{!! $ctaButton() !!}</div>@endif
    </div>
</section>
