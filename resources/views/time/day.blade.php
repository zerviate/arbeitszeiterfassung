@extends('layouts.app')

@section('content')
    @php
        $format = \App\Support\DateTimeFormat::class;
        $timezone = auth()->user()?->timezone;
    @endphp

    <x-ui.page-header title="Tagesansicht · {{ $format::date($date) }}" class="page-header-compact">
        <x-slot:actions>
            <x-ui.button :href="route('time.index')" variant="secondary">Heute</x-ui.button>
            <x-ui.button :href="route('time.month', substr($date, 0, 7))" variant="ghost">Zur Monatsansicht</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if($summary || $evaluation)
        <div class="metric-grid">
            <div class="metric-card">
                <span class="metric-label">Brutto</span>
                <span class="metric-value">{{ $format::minutes($summary?->gross_minutes ?? 0) }}</span>
                <span class="metric-meta">Netto {{ $format::minutes($summary?->net_minutes ?? 0) }}</span>
            </div>
            <div class="metric-card">
                <span class="metric-label">Pause</span>
                <span class="metric-value">{{ $format::minutes($summary?->break_minutes ?? 0) }}</span>
            </div>
            <div class="metric-card">
                <span class="metric-label">Soll</span>
                <span class="metric-value">{{ $format::minutes($evaluation?->target_minutes ?? 0) }}</span>
            </div>
            <div class="metric-card">
                <span class="metric-label">Saldo</span>
                <span class="metric-value">{{ $format::minutes($evaluation?->balance_minutes ?? 0) }}</span>
            </div>
        </div>
    @endif

    <div class="split-layout">
        <x-ui.card title="Tageszusammenfassung">
            @if($summary || $evaluation)
                <div class="detail-list section-space">
                    <div class="detail-list-row">
                        <span class="detail-list-label">Netto</span>
                        <span class="detail-list-value">{{ $format::minutes($summary?->net_minutes ?? 0) }}</span>
                    </div>
                    <div class="detail-list-row">
                        <span class="detail-list-label">Überzeit</span>
                        <span class="detail-list-value">{{ $format::minutes($summary?->overtime_minutes ?? 0) }}</span>
                    </div>
                    <div class="detail-list-row">
                        <span class="detail-list-label">Status</span>
                        <span class="detail-list-value">
                            <x-status-badge :label="$evaluation?->day_status ?? '-'" kind="day-status" />
                            <x-status-badge :label="$evaluation?->traffic_light ?? 'grey'" kind="traffic-light" />
                        </span>
                    </div>
                    @if($summary?->finalized_at)
                        <div class="detail-list-row">
                            <span class="detail-list-label">Finalisierung</span>
                            <span class="detail-list-value"><x-status-badge label="finalisiert" kind="entry-status" /></span>
                        </div>
                    @endif
                    @if($evaluation?->is_holiday)
                        <div class="detail-list-row">
                            <span class="detail-list-label">Feiertag</span>
                            <span class="detail-list-value">{{ $evaluation->holiday_name ?? 'Ja' }}</span>
                        </div>
                    @endif
                </div>

                @if(! empty($summary?->violation_flags) || ! empty($evaluation?->flags))
                    <div class="section-space">
                        <p class="text-muted mb-2"><strong>Hinweise</strong></p>
                        <div class="inline-badge-list">
                            @foreach($summary?->violation_flags ?? [] as $flag)
                                <x-status-badge :label="$flag" kind="flag" />
                            @endforeach
                            @foreach($evaluation?->flags ?? [] as $flag)
                                <x-status-badge :label="$flag" kind="flag" />
                            @endforeach
                        </div>
                    </div>
                @endif
            @else
                <p class="text-muted">Für diesen Tag gibt es noch keine Zusammenfassung.</p>
            @endif
        </x-ui.card>

        <x-ui.data-table title="Sessions" class="data-table-tall">
            <table>
                <thead>
                <tr>
                    <th>Beginn</th>
                    <th>Ende</th>
                    <th>Brutto</th>
                    <th>Pausen</th>
                </tr>
                </thead>
                <tbody>
                @forelse($sessions as $session)
                    <tr>
                        <td>{{ $format::dateTime($session->started_at, $timezone) }}</td>
                        <td>{{ $format::dateTime($session->ended_at, $timezone) }}</td>
                        <td>{{ $format::minutes($session->gross_minutes) }}</td>
                        <td>
                            @forelse($session->breaks as $break)
                                <div>{{ $format::dateTime($break->started_at, $timezone) }} - {{ $format::dateTime($break->ended_at, $timezone) }} ({{ $format::minutes($break->minutes) }})</div>
                            @empty
                                <span class="text-muted">Keine</span>
                            @endforelse
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">Keine Sessions vorhanden.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </x-ui.data-table>
    </div>
@endsection
