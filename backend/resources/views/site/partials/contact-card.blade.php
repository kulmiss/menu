{{-- Contact us page: the same details as the footer's contact block (Super Admin → Footer). --}}
@push('styles')
    <style>
        .cc { margin: 1.5rem 0; padding: 1.25rem; border: 1px solid var(--line); border-radius: 1rem; background: var(--soft); display: grid; gap: .25rem; }
        .cc h2 { font-size: 1.1rem; margin: 0 0 .5rem; }
        .cc a, .cc p { display: flex; align-items: center; min-height: 44px; margin: 0; color: inherit; overflow-wrap: anywhere; }
        .cc .wa { display: inline-flex; justify-self: start; padding: 0 1.25rem; border-radius: 999px; background: var(--brand); color: var(--on-brand); font-weight: 700; text-decoration: none; margin-bottom: .5rem; }
    </style>
@endpush
<section class="cc" aria-label="{{ $card['title'] }}">
    <h2>{{ $card['title'] }}</h2>
    @isset($card['whatsapp'])<a class="wa" href="{{ $card['whatsapp'] }}" target="_blank" rel="noopener">{{ __('footer.contact.whatsapp') }}</a>@endisset
    @isset($card['phone'])<a href="{{ $card['phone']['href'] }}">{{ __('footer.contact.phone') }}: {{ $card['phone']['text'] }}</a>@endisset
    @isset($card['email'])<a href="{{ $card['email']['href'] }}">{{ __('footer.contact.email') }}: {{ $card['email']['text'] }}</a>@endisset
    @isset($card['address'])<p>{{ __('footer.contact.address') }}: {{ $card['address'] }}</p>@endisset
    @isset($card['hours'])<p>{{ __('footer.contact.hours') }}: {{ $card['hours'] }}</p>@endisset
</section>
