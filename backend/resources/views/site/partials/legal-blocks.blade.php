@foreach ($blocks as $block)
    @switch($block['type'])
        @case('h2')
            <h2 id="{{ $block['id'] }}">{{ $block['text'] }}</h2>
            @break
        @case('h3')
            <h3 id="{{ $block['id'] }}">{{ $block['text'] }}</h3>
            @break
        @case('ul')
            @include('site.partials.legal-list', ['items' => $block['items']])
            @break
        @default
            <p>{!! implode('<br>', array_map(fn ($line) => \App\Support\Legal\LegalText::html($line)->toHtml(), $block['lines'])) !!}</p>
    @endswitch
@endforeach
