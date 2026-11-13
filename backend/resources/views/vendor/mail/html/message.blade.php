{{-- Every e-mail: platform logo (or name) on top, platform name in the footer (owner, 2026-11-13). --}}
@php($platform = (string) \App\Support\PlatformSettings::get('name'))
<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
@if ($logo = \App\Support\EmailLogo::url())
<img src="{{ $logo }}" alt="{{ $platform }}" style="max-height:60px;max-width:240px;border:0;">
@else
{{ $platform }}
@endif
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ $platform }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
