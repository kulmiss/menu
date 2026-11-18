@if ($notice)
    <p class="notice {{ $notice['ok'] ? 'good' : 'bad' }}" role="{{ $notice['ok'] ? 'status' : 'alert' }}">
        @if ($notice['ok'])
            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
        @else
            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
        @endif
        <span>{{ $notice['text'] }}</span>
    </p>
@endif
