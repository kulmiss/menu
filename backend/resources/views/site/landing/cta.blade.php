<section aria-labelledby="cta-title">
    <div class="wrap">
        <div class="band reveal">
            <h2 id="cta-title">{{ __('landing.cta.title') }}</h2>
            <p>{{ ! $signupOpen ? __('landing.cta.closed') : ($free ? __('landing.cta.text') : __('landing.cta.text_paid')) }}</p>
            @if ($cta)
                {!! $ctaButton('btn btn-lg') !!}
            @elseif ($demoUrl)
                <a class="btn btn-lg" href="{{ $demoUrl }}" target="_blank" rel="noopener" data-track="demo">{{ __('landing.hero.demo') }}</a>
            @endif
        </div>
    </div>
</section>
