{{-- A text field with its label, hint and error. --}}
@php $error = $errors->first($name); $hintId = isset($hint) ? $name.'-hint' : null; @endphp
<div class="field">
    <label for="{{ $name }}">{{ $label }}</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type ?? 'text' }}" value="{{ $value ?? old($name) }}"
        @isset($autocomplete) autocomplete="{{ $autocomplete }}" @endisset
        @isset($inputmode) inputmode="{{ $inputmode }}" @endisset
        @isset($placeholder) placeholder="{{ $placeholder }}" @endisset
        @if ($readonly ?? false) readonly @endif
        @if ($autofocus ?? false) autofocus @endif
        @if ($required ?? true) required @endif
        maxlength="{{ $maxlength ?? 255 }}"
        @if ($error) aria-invalid="true" aria-describedby="{{ $name }}-error" @elseif ($hintId) aria-describedby="{{ $hintId }}" @endif>
    @if ($error)
        <p class="error" id="{{ $name }}-error" role="alert">{{ $error }}</p>
    @elseif ($hintId)
        <p class="hint" id="{{ $hintId }}">{{ $hint }}</p>
    @endif
</div>
