<ul>
@foreach ($items as $item)
    <li>{{ \App\Support\Legal\LegalText::html($item['text']) }}@if ($item['children'] !== [])
@include('site.partials.legal-list', ['items' => $item['children']])
@endif</li>
@endforeach
</ul>
