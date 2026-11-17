<section class="{{ $soft ? 'soft' : '' }}" id="faq" aria-labelledby="faq-title">
    <div class="wrap">
        <div class="head reveal"><h2 id="faq-title">{{ __('landing.faq.title') }}</h2><p>{{ __('landing.faq.subtitle') }}</p></div>
        <div class="faq">
            @foreach (__('landing.faq.items') as $item)
                <details class="reveal">
                    <summary>{{ $item['q'] }}</summary>
                    <p class="answer">{{ $item['a'] }}</p>
                </details>
            @endforeach
        </div>
        @if ($chat)
            <div class="more reveal"><a class="btn btn-ghost btn-lg" href="{{ $chat }}" target="_blank" rel="noopener">{!! $icon('message') !!} {{ __('landing.faq.help_whatsapp') }}<span class="sr-only"> {{ __('landing.new_tab') }}</span></a></div>
        @elseif ($supportEmail)
            <div class="more reveal"><a class="btn btn-ghost btn-lg" href="mailto:{{ $supportEmail }}">{!! $icon('mail') !!} {{ __('landing.faq.help_email') }}</a></div>
        @endif
    </div>
</section>
