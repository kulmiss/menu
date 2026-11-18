@extends('account.layout')

@section('title', $invitation ? __('account.invite.title', ['restaurant' => $invitation->tenant->name]) : __('account.invite.invalid_title'))

@section('header')
    @unless ($user)
        <a class="btn btn-ghost" href="/signin?redirect={{ rawurlencode('/invite/'.$token) }}">{{ __('account.sign_in') }}</a>
    @endunless
@endsection

@section('form')
    @if (! $invitation)
        <h1>{{ __('account.invite.invalid_title') }}</h1>
        <p class="intro">{{ __('account.invite.invalid_text') }}</p>
        <div class="stack">
            <a class="btn btn-primary btn-block" href="{{ $user ? '/admin' : '/signin' }}">{{ $user ? __('account.home') : __('account.sign_in') }}</a>
        </div>
    @else
        @php
            $restaurant = $invitation->tenant->name;
            $role = __('account.invite.roles.'.$invitation->role->value);
        @endphp
        <h1>{{ __('account.invite.title', ['restaurant' => $restaurant]) }}</h1>
        <p class="intro">{{ __('account.invite.intro', ['restaurant' => $restaurant, 'email' => $invitation->email, 'role' => $role]) }}</p>
        @include('account.partials.status')
        @if ($user && $mine)
            <p class="intro">{{ __('account.invite.signed_in_as', ['email' => $user->email]) }}</p>
            <form method="POST" action="/invite/{{ $token }}/accept" class="stack">
                @csrf
                <button class="btn btn-primary btn-block" type="submit">{{ __('account.invite.accept', ['restaurant' => $restaurant]) }}</button>
            </form>
        @elseif ($user)
            @include('account.partials.notice', ['notice' => ['ok' => false, 'text' => __('account.invite.wrong_account', ['invited' => $invitation->email, 'email' => $user->email])]])
            <form method="POST" action="/signout" class="stack">
                @csrf
                <input type="hidden" name="redirect" value="/invite/{{ $token }}">
                <button class="btn btn-primary btn-block" type="submit">{{ __('account.invite.switch') }}</button>
            </form>
        @elseif ($hasAccount)
            <div class="stack">
                <a class="btn btn-primary btn-block" href="/signin?redirect={{ rawurlencode('/invite/'.$token) }}">{{ __('account.sign_in') }}</a>
            </div>
        @else
            <form method="POST" action="/invite/{{ $token }}/join" class="stack" novalidate>
                @csrf
                <h2 class="sub">{{ __('account.invite.create') }}</h2>
                @include('account.partials.field', ['name' => 'name', 'label' => __('account.invite.name'), 'autocomplete' => 'name', 'maxlength' => 120, 'autofocus' => true])
                <input type="text" name="username" value="{{ $invitation->email }}" autocomplete="username" hidden>
                <div class="field">
                    <label for="password">{{ __('account.invite.password') }}</label>
                    @include('account.partials.password', ['name' => 'password', 'autocomplete' => 'new-password', 'describedBy' => 'password-hint', 'strength' => true])
                    @unless ($errors->has('password'))
                        <p class="hint" id="password-hint">{{ __('account.signup.password_hint') }}</p>
                    @endunless
                </div>
                @foreach (['email', 'invitation_token'] as $other)
                    @if ($errors->has($other))
                        <p class="error" role="alert">{{ $errors->first($other) }}</p>
                    @endif
                @endforeach
                <button class="btn btn-primary btn-block" type="submit">{{ __('account.invite.submit', ['restaurant' => $restaurant]) }}</button>
            </form>
        @endif
    @endif
@endsection

@section('after')
    @if ($invitation && ! $user && ! $hasAccount)
        <p class="after">{{ __('account.invite.have_account', ['email' => $invitation->email]) }} <a href="/signin?redirect={{ rawurlencode('/invite/'.$token) }}">{{ __('account.sign_in') }}</a></p>
    @endif
@endsection
