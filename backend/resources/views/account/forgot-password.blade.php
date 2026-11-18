@extends('account.layout')

@section('title', __('account.forgot.title'))

@section('header')
    <a class="btn btn-ghost" href="/signin">{{ __('account.sign_in') }}</a>
@endsection

@section('form')
    <h1>{{ __('account.forgot.title') }}</h1>
    <p class="intro">{{ __('account.forgot.intro') }}</p>
    @include('account.partials.status')
    <form method="POST" action="/forgot-password" class="stack" novalidate>
        @csrf
        @include('account.partials.field', ['name' => 'email', 'type' => 'email', 'label' => __('account.signin.email'), 'autocomplete' => 'email', 'inputmode' => 'email', 'placeholder' => __('account.signin.email_placeholder'), 'autofocus' => ! session('status')])
        <button class="btn btn-primary btn-block" type="submit">{{ __('account.forgot.submit') }}</button>
    </form>
@endsection

@section('after')
    <p class="after"><a href="/signin">{{ __('account.forgot.back') }}</a></p>
@endsection
