<x-mail::message>
{!! $html !!}

@if ($unsubscribe)
<x-mail::subcopy>
{{ __('campaign.footer', ['platform' => $platform]) }} [{{ __('campaign.unsubscribe') }}]({{ $unsubscribe }})
</x-mail::subcopy>
@endif
</x-mail::message>
