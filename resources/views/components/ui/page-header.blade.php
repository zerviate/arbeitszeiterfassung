@props([
    'title',
    'subtitle' => null,
    'kicker' => null,
])

<section {{ $attributes->class(['page-header']) }}>
    <div class="page-header-main">
        @if($kicker)
            <span class="page-header-kicker">{{ $kicker }}</span>
        @endif

        <div class="page-header-title-row">
            <h2 class="page-header-title">{{ $title }}</h2>
            @if(isset($meta))
                <div class="page-header-meta">{{ $meta }}</div>
            @endif
        </div>

        @if($subtitle)
            <p class="page-header-subtitle">{{ $subtitle }}</p>
        @endif
    </div>

    @if(isset($actions))
        <div class="page-header-actions">{{ $actions }}</div>
    @endif
</section>
