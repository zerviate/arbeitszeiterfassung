@extends('layouts.app')

@section('content')
    @php $format = \App\Support\DateTimeFormat::class; @endphp

    <x-ui.page-header title="Tagesbewertung · {{ $user->name }} · {{ $format::date($date) }}" class="page-header-compact">
        <x-slot:actions>
            <x-ui.button :href="route('evaluations.week', [$user, $date])" variant="secondary">Woche</x-ui.button>
            <x-ui.button :href="route('evaluations.month', [$user, \Carbon\Carbon::parse($date)->format('Y-m')])" variant="secondary">Monat</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="metric-grid section-space">
        <div class="metric-card">
            <span class="metric-label">Soll</span>
            <span class="metric-value">{{ $format::minutes($evaluation->target_minutes) }}</span>
        </div>
        <div class="metric-card">
            <span class="metric-label">Ist</span>
            <span class="metric-value">{{ $format::minutes($evaluation->actual_minutes) }}</span>
        </div>
        <div class="metric-card">
            <span class="metric-label">Urlaub</span>
            <span class="metric-value">{{ $format::minutes($evaluation->vacation_minutes) }}</span>
        </div>
        <div class="metric-card">
            <span class="metric-label">Krank</span>
            <span class="metric-value">{{ $format::minutes($evaluation->sick_leave_minutes) }}</span>
        </div>
        <div class="metric-card">
            <span class="metric-label">Saldo</span>
            <span class="metric-value">{{ $format::minutes($evaluation->balance_minutes) }}</span>
        </div>
        <div class="metric-card">
            <span class="metric-label">Status</span>
            <span class="metric-value metric-value-badge">
                <x-status-badge :label="$evaluation->day_status ?? '-'" kind="day-status" />
                <x-status-badge :label="$evaluation->traffic_light ?? '-'" kind="traffic-light" />
            </span>
        </div>
    </div>

    @if($evaluation->is_holiday)
        <x-ui.card title="Feiertag" subtitle="{{ $evaluation->holiday_name ?? 'Ja' }}"></x-ui.card>
    @endif

    @if(! empty($evaluation->flags))
        <x-ui.card title="Hinweise">
            <div class="inline-badge-list">
                @foreach($evaluation->flags as $flag)
                    <x-status-badge :label="$flag" kind="flag" />
                @endforeach
            </div>
        </x-ui.card>
    @endif
@endsection
