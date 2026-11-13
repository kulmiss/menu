@extends('site.layout')

@section('title', $title.' · '.$platform)

@section('content')
    <h1>{{ $title }}</h1>
    @forelse ($paragraphs as $paragraph)
        <p>{!! nl2br(e($paragraph)) !!}</p>
    @empty
        <p class="muted">{{ __('site.not_written') }}</p>
    @endforelse
@endsection
