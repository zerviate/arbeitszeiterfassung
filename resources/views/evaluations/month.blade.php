@extends('layouts.app')

@section('content')
    @php $format = \App\Support\DateTimeFormat::class; @endphp

    <x-ui.page-header
        title="Monatsbewertung · {{ $user->name }} · {{ $format::monthLabel($month) }}"
        class="page-header-compact"
    />

    <div class="metric-grid section-space">
        <div class="metric-card">
            <span class="metric-label">Soll</span>
            <span class="metric-value">{{ $format::minutes($summary['target_minutes']) }}</span>
        </div>
        <div class="metric-card">
            <span class="metric-label">Ist</span>
            <span class="metric-value">{{ $format::minutes($summary['actual_minutes']) }}</span>
        </div>
        <div class="metric-card">
            <span class="metric-label">Urlaub</span>
            <span class="metric-value">{{ $format::minutes($summary['vacation_minutes']) }}</span>
        </div>
        <div class="metric-card">
            <span class="metric-label">Krank</span>
            <span class="metric-value">{{ $format::minutes($summary['sick_leave_minutes']) }}</span>
        </div>
        <div class="metric-card">
            <span class="metric-label">Saldo</span>
            <span class="metric-value">{{ $format::minutes($summary['balance_minutes']) }}</span>
        </div>
        <div class="metric-card">
            <span class="metric-label">Status</span>
            <span class="metric-value metric-value-badge"><x-status-badge :label="$summary['traffic_light']" kind="traffic-light" /></span>
        </div>
    </div>

    <x-ui.data-table title="Tage" class="data-table-tall">
        <table>
            <thead>
            <tr>
                <th>Datum</th>
                <th>Status</th>
                <th><span class="visually-hidden">Status</span></th>
                <th>Soll</th>
                <th>Ist</th>
                <th>Urlaub</th>
                <th>Krank</th>
                <th>Saldo</th>
                <th>Feiertag</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse($summary['evaluations'] as $evaluation)
                <tr>
                    <td>{{ $format::date($evaluation->work_date) }}</td>
                    <td><x-status-badge :label="$evaluation->day_status" kind="day-status" /></td>
                    <td><x-status-badge :label="$evaluation->traffic_light" kind="traffic-light" /></td>
                    <td>{{ $format::minutes($evaluation->target_minutes) }}</td>
                    <td>{{ $format::minutes($evaluation->actual_minutes) }}</td>
                    <td>{{ $format::minutes($evaluation->vacation_minutes) }}</td>
                    <td>{{ $format::minutes($evaluation->sick_leave_minutes) }}</td>
                    <td>{{ $format::minutes($evaluation->balance_minutes) }}</td>
                    <td>{{ $evaluation->is_holiday ? ($evaluation->holiday_name ?? 'Ja') : '-' }}</td>
                    <td>
                        <a class="btn btn-secondary" href="{{ route('evaluations.day', [$user, $evaluation->work_date?->toDateString()]) }}">Tag</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10">Keine Bewertungen vorhanden.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </x-ui.data-table>
@endsection
