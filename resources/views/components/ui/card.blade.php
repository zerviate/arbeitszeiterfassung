@props([
    'title' => null,
    'subtitle' => null,
])

<section {{ $attributes->class(['card', 'ui-card']) }}>
    @if($title || $subtitle || isset($actions))
        <div class="ui-card-header">
            <div class="ui-card-heading">
                @if($title)
                    <h3 class="ui-card-title">{{ $title }}</h3>
                @endif

                @if($subtitle)
                    <p class="ui-card-subtitle">{{ $subtitle }}</p>
                @endif
            </div>

            @if(isset($actions))
                <div class="ui-card-actions">{{ $actions }}</div>
            @endif
        </div>
    @endif

    {{ $slot }}
</section>
