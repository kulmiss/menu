@extends('account.layout')

@section('title', __('account.two_factor.title'))

@section('form')
    <h1>{{ __('account.two_factor.title') }}</h1>
    <p class="intro">{{ __('account.two_factor.intro') }}</p>
    @php
        $codeError = $errors->first('code');
        $recoveryError = $errors->first('recovery_code');
    @endphp
    <form method="POST" action="/signin/two-factor" class="stack" novalidate>
        @csrf
        <div class="field">
            <label for="code">{{ __('account.two_factor.code') }}</label>
            <input id="code" name="code" type="text" inputmode="numeric" pattern="[0-9 ]*" autocomplete="one-time-code" maxlength="8" @unless ($recoveryError) autofocus @endunless
                @if ($codeError) aria-invalid="true" aria-describedby="code-error" @endif>
            @if ($codeError)
                <p class="error" id="code-error" role="alert">{{ $codeError }}</p>
            @endif
        </div>
        <button class="btn btn-primary btn-block" type="submit">{{ __('account.two_factor.submit') }}</button>
    </form>
    <details @if ($recoveryError) open @endif>
        <summary>{{ __('account.two_factor.use_recovery') }}</summary>
        <form method="POST" action="/signin/two-factor" class="stack" novalidate>
            @csrf
            <p class="intro" style="margin-top:0">{{ __('account.two_factor.recovery_intro') }}</p>
            <div class="field">
                <label for="recovery_code">{{ __('account.two_factor.recovery_code') }}</label>
                <input id="recovery_code" name="recovery_code" type="text" autocomplete="off" autocapitalize="none" spellcheck="false" @if ($recoveryError) autofocus aria-invalid="true" aria-describedby="recovery-error" @endif>
                @if ($recoveryError)
                    <p class="error" id="recovery-error" role="alert">{{ $recoveryError }}</p>
                @endif
            </div>
            <button class="btn btn-ghost btn-block" type="submit">{{ __('account.two_factor.submit') }}</button>
        </form>
    </details>
@endsection

@section('after')
    <p class="after"><a href="{{ $restart }}">{{ __('account.two_factor.start_again') }}</a></p>
@endsection
