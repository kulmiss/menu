<section class="{{ $soft ? 'soft' : '' }}" id="testimonials" aria-labelledby="testimonials-title">
    <div class="wrap">
        <div class="head reveal"><h2 id="testimonials-title">{{ __('landing.testimonials.title') }}</h2></div>
        <div class="quotes">
            @foreach ($testimonials as $quote)
                <figure class="card reveal" style="margin:0">
                    <blockquote><p>“{{ $quote['quote'] }}”</p></blockquote>
                    <figcaption>— {{ $quote['name'] }}@if (! empty($quote['role'])), {{ $quote['role'] }}@endif</figcaption>
                </figure>
            @endforeach
        </div>
        @if ($cta)<div class="more reveal">{!! $ctaButton() !!}</div>@endif
    </div>
</section>
