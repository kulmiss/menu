<section class="{{ $soft ? 'soft' : '' }}" id="faq" aria-labelledby="faq-title">
    <div class="wrap">
        <div class="head reveal"><h2 id="faq-title">{{ $t('faq.title') }}</h2><p>{{ $t('faq.subtitle') }}</p></div>
        <div class="faq">
            @foreach (array_keys(__('landing.faq.items')) as $key)
                <details class="reveal">
                    <summary>{{ $t("faq.items.{$key}.q") }}</summary>
                    <p class="answer">{{ $t("faq.items.{$key}.a") }}</p>
                </details>
            @endforeach
        </div>
        @if ($chat)
            <div class="more reveal"><a class="btn btn-ghost btn-lg" href="{{ $chat }}" target="_blank" rel="noopener">{!! $icon('message') !!} {{ $t('faq.help_whatsapp') }}<span class="sr-only"> {{ $t('new_tab') }}</span></a></div>
        @elseif ($supportEmail)
            <div class="more reveal"><a class="btn btn-ghost btn-lg" href="mailto:{{ $supportEmail }}">{!! $icon('mail') !!} {{ $t('faq.help_email') }}</a></div>
        @endif
    </div>
</section>
