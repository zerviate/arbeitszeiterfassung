@props([
    'title' => null,
    'subtitle' => null,
])

<section {{ $attributes->class(['card', 'filter-panel']) }}>
    @if($title || $subtitle)
        <div class="filter-panel-header">
            @if($title)
                <h3 class="filter-panel-title">{{ $title }}</h3>
            @endif

            @if($subtitle)
                <p class="filter-panel-subtitle">{{ $subtitle }}</p>
            @endif
        </div>
    @endif

    {{ $slot }}
</section>
