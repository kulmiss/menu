<section class="{{ $soft ? 'soft' : '' }}" id="compare" aria-labelledby="compare-title">
    <div class="wrap">
        <div class="head reveal"><h2 id="compare-title">{{ $t('compare.title') }}</h2><p>{{ $t('compare.subtitle') }}</p></div>
        <div class="compare">
            <div class="card before reveal">
                <h3>{!! $icon('receipt') !!} {{ $t('compare.before') }}</h3>
                <ul>
                    @foreach (array_keys(__('landing.compare.rows')) as $i)
                        <li>{!! $icon('x') !!}<span>{{ $t("compare.rows.{$i}.0") }}</span></li>
                    @endforeach
                </ul>
            </div>
            <div class="card after reveal">
                <h3>{!! $icon('qr') !!} {{ $t('compare.after') }}</h3>
                <ul>
                    @foreach (array_keys(__('landing.compare.rows')) as $i)
                        <li>{!! $icon('check') !!}<span>{{ $t("compare.rows.{$i}.1") }}</span></li>
                    @endforeach
                </ul>
            </div>
        </div>
        @if ($cta)<div class="more reveal">{!! $ctaButton() !!}</div>@endif
    </div>
</section>
