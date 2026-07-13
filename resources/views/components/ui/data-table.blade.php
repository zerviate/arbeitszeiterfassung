@props([
    'title' => null,
    'subtitle' => null,
])

<section {{ $attributes->class(['card', 'data-table-shell']) }}>
    @if($title || $subtitle || isset($actions))
        <div class="data-table-header">
            <div class="data-table-heading">
                @if($title)
                    <h3 class="data-table-title">{{ $title }}</h3>
                @endif

                @if($subtitle)
                    <p class="data-table-subtitle">{{ $subtitle }}</p>
                @endif
            </div>

            @if(isset($actions))
                <div class="data-table-actions">{{ $actions }}</div>
            @endif
        </div>
    @endif

    <div class="data-table-scroll">
        {{ $slot }}
    </div>
</section>
