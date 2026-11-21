@extends('account.layout')

@section('title', __('account.verify.title'))

@section('header')
    @unless ($user)
        <a class="btn btn-ghost" href="/signin">{{ __('account.sign_in') }}</a>
    @endunless
@endsection

@section('form')
    <svg class="big-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
    <h1>{{ __('account.verify.title') }}</h1>
    @if ($user)
        <p class="intro">{{ __('account.verify.text', ['email' => $user->email]) }}</p>
    @else
        <p class="intro">{{ __('account.verify.text_public') }}</p>
    @endif
    <p class="intro">{{ __('account.verify.expires') }}</p>
    @include('account.partials.status')
    @if ($user)
        <p class="intro">{{ __('account.verify.menu_off') }}</p>
        <div class="stack">
            <form method="POST" action="/verify-email/check">
                @csrf
                <button class="btn btn-primary btn-block" type="submit">{{ __('account.verify.done') }}</button>
            </form>
            <form method="POST" action="/verify-email/resend">
                @csrf
                <button class="btn btn-ghost btn-block" type="submit">{{ __('account.verify.resend') }}</button>
            </form>
        </div>
        <details @if ($errors->has('email')) open @endif>
            <summary>{{ __('account.verify.change') }}</summary>
            <form method="POST" action="/verify-email/change" class="stack" novalidate>
                @csrf
                @include('account.partials.field', ['name' => 'email', 'type' => 'email', 'label' => __('account.verify.new_email'), 'autocomplete' => 'email', 'inputmode' => 'email', 'value' => old('email', '')])
                <button class="btn btn-ghost btn-block" type="submit">{{ __('account.verify.save_email') }}</button>
            </form>
        </details>
    @else
        <div class="stack">
            <a class="btn btn-primary btn-block" href="/signin">{{ __('account.verify.back_to_sign_in') }}</a>
        </div>
    @endif
@endsection

@section('after')
    @if ($user)
        <form method="POST" action="/signout" class="after">
            @csrf
            <button class="text-btn" type="submit">{{ __('account.verify.sign_out') }}</button>
        </form>
    @endif
@endsection
