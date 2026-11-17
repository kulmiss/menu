{{-- A password field with a "Show password" button (the button appears only with JavaScript). --}}
@php $error = $errors->first($name); @endphp
<div class="pw">
    <input id="{{ $name }}" name="{{ $name }}" type="password" autocomplete="{{ $autocomplete }}" required
        @if ($error) aria-invalid="true" aria-describedby="{{ $name }}-error" @endif
        @isset($describedBy) @unless ($error) aria-describedby="{{ $describedBy }}" @endunless @endisset>
    <button type="button" aria-pressed="false" aria-label="{{ __('account.show_password') }}" data-show="{{ __('account.show_password') }}" data-hide="{{ __('account.hide_password') }}" aria-controls="{{ $name }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>
    </button>
</div>
@if ($error)
    <p class="error" id="{{ $name }}-error">{{ $error }}</p>
@endif
