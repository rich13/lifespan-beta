@props(['items'])

<nav aria-label="breadcrumb">
    <ol class="breadcrumb mb-0">
        @foreach($items as $index => $item)
            @if($index === count($items) - 1)
                {{-- Last item (current page) --}}
                <li class="breadcrumb-item active" aria-current="page">
                    {{ $item['text'] }}
                </li>
            @else
                {{-- Navigation items --}}
                <li class="breadcrumb-item">
                    @if(isset($item['url']))
                        <a href="{{ $item['url'] }}" class="text-decoration-none">
                            {{ $item['text'] }}
                        </a>
                    @else
                        {{ $item['text'] }}
                    @endif
                </li>
            @endif
        @endforeach
    </ol>
</nav> 