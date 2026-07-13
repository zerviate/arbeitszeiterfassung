@extends('layouts.app')

@section('content')
    @php
        $format = \App\Support\DateTimeFormat::class;
        $timezone = auth()->user()?->timezone;
        $openBreak = $openSession?->breaks?->firstWhere('status', 'open');
        $sessionStartedAt = $openSession?->started_at?->toIso8601String();
        $breakStartedAt = $openBreak?->started_at?->toIso8601String();
        $hasOpenSession = (bool) $openSession;
        $hasOpenBreak = (bool) $openBreak;
    @endphp

    <section class="workday-shell">
        <x-ui.page-header title="Heute · {{ $format::date($today) }}" class="page-header-compact">
        <x-slot:actions>
            <x-ui.button :href="route('time.month')" variant="secondary">Monatsansicht</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

        <div class="workday-grid">
            <x-ui.card title="Live-Status">
                <div
                    data-vue-component="workday-command-center"
                    data-vue-props='@json([
                        "sessionStartedAt" => $sessionStartedAt,
                        "breakStartedAt" => $breakStartedAt,
                    ])'
                >
                    <p class="text-muted">Live-Timer wird geladen...</p>
                </div>
            </x-ui.card>

            <x-ui.card title="Aktionen">
                <div class="workday-actions">
                    @if($hasOpenSession)
                        <form method="post" action="{{ route('time.clock-out') }}">
                            @csrf
                            <x-ui.button type="submit" class="workday-action-button">Arbeitszeit beenden</x-ui.button>
                        </form>
                    @else
                        <form method="post" action="{{ route('time.clock-in') }}">
                            @csrf
                            <x-ui.button type="submit" class="workday-action-button">Arbeitszeit starten</x-ui.button>
                        </form>
                    @endif

                    @if($hasOpenBreak)
                        <form method="post" action="{{ route('time.break-end') }}">
                            @csrf
                            <x-ui.button type="submit" variant="secondary" class="workday-action-button">Pause beenden</x-ui.button>
                        </form>
                    @else
                        <form method="post" action="{{ route('time.break-start') }}">
                            @csrf
                            <x-ui.button type="submit" variant="secondary" class="workday-action-button" :disabled="! $hasOpenSession">
                                Pause starten
                            </x-ui.button>
                        </form>
                    @endif
                </div>

                <div class="workday-actions-meta text-muted">
                    @if($hasOpenSession)
                        <span>Session läuft.</span>
                    @else
                        <span>Keine laufende Session.</span>
                    @endif
                </div>

                @if($openSession)
                    <div class="workday-meta">
                        <div>
                            <span class="detail-list-label">Session gestartet</span>
                            <span class="detail-list-value">{{ $format::dateTime($openSession->started_at, $timezone) }}</span>
                        </div>
                        @if($openBreak)
                            <div>
                                <span class="detail-list-label">Pause seit</span>
                                <span class="detail-list-value">{{ $format::dateTime($openBreak->started_at, $timezone) }}</span>
                            </div>
                        @endif
                    </div>
                @endif
            </x-ui.card>
        </div>

        <x-ui.card title="Tageswerte">
            @if($summary || $evaluation)
                <div class="workday-metrics">
                    <div class="workday-metric">
                        <span class="workday-metric-label">Netto</span>
                        <span class="workday-metric-value">{{ $format::minutes($summary?->net_minutes ?? 0) }}</span>
                    </div>
                    <div class="workday-metric">
                        <span class="workday-metric-label">Soll</span>
                        <span class="workday-metric-value">{{ $format::minutes($evaluation?->target_minutes ?? 0) }}</span>
                    </div>
                    <div class="workday-metric">
                        <span class="workday-metric-label">Saldo</span>
                        <span class="workday-metric-value">{{ $format::minutes($evaluation?->balance_minutes ?? 0) }}</span>
                    </div>
                    <div class="workday-metric">
                        <span class="workday-metric-label">Status</span>
                        <span class="workday-metric-value"><x-status-badge :label="$evaluation?->traffic_light ?? 'grey'" kind="traffic-light" /></span>
                    </div>
                </div>

                <div class="workday-status-row">
                    <span class="detail-list-label">Status</span>
                    <span class="detail-list-value"><x-status-badge :label="$evaluation?->day_status ?? '-'" kind="day-status" /></span>
                </div>

                @if($evaluation?->is_holiday)
                    <div class="workday-status-row">
                        <span class="detail-list-label">Feiertag</span>
                        <span class="detail-list-value">{{ $evaluation->holiday_name ?? 'Ja' }}</span>
                    </div>
                @endif

                @if(! empty($summary?->violation_flags) || ! empty($evaluation?->flags))
                    <div class="workday-flags">
                        <span class="detail-list-label">Hinweise</span>
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
                <p class="text-muted">Noch keine Daten für heute vorhanden.</p>
            @endif
        </x-ui.card>
    </section>
@endsection
