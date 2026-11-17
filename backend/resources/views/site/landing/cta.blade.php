<section aria-labelledby="cta-title">
    <div class="wrap">
        <div class="band reveal">
            <h2 id="cta-title">{{ $t('cta.title') }}</h2>
            <p>{{ ! $signupOpen ? $t('cta.closed') : ($free ? $t('cta.text') : $t('cta.text_paid')) }}</p>
            @if ($cta)
                {!! $ctaButton('btn btn-lg') !!}
            @elseif ($demoUrl)
                <a class="btn btn-lg" href="{{ $demoUrl }}" target="_blank" rel="noopener" data-track="demo">{{ $t('hero.demo') }}</a>
            @endif
        </div>
    </div>
</section>
