@extends('layouts.app')

@section('content')
    @php
        $format = \App\Support\DateTimeFormat::class;
        $currentMonth = \Carbon\Carbon::createFromFormat('Y-m', $month, 'UTC');
        $previousMonth = $currentMonth->copy()->subMonth()->format('Y-m');
        $nextMonth = $currentMonth->copy()->addMonth()->format('Y-m');
        $canExport = auth()->user()->hasAnyPermission(['time.export.own', 'time.export.team', 'time.export.all']);
    @endphp

    <section class="month-shell">
        <x-ui.page-header title="Monatsansicht · {{ $format::monthLabel($month) }}" class="page-header-compact">
            <x-slot:actions>
                <x-ui.button :href="route('time.month', $previousMonth)" variant="secondary">Vorheriger Monat</x-ui.button>
                <x-ui.button :href="route('time.index')" variant="ghost">Heute</x-ui.button>
                <x-ui.button :href="route('time.month', $nextMonth)" variant="secondary">Nächster Monat</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        @if(isset($monthBalance))
            <div class="month-overview">
                <x-ui.card title="Monatsübersicht" class="month-overview-summary">
                    <div class="month-summary-grid">
                        <div class="month-summary-item">
                            <span class="month-summary-label">Abwesenheiten</span>
                            <span class="month-summary-value">
                                {{ $format::minutes($monthBalance['vacation_minutes'] + $monthBalance['sick_leave_minutes']) }}
                            </span>
                            <span class="month-summary-meta">
                                Urlaub {{ $format::minutes($monthBalance['vacation_minutes']) }} | Krank {{ $format::minutes($monthBalance['sick_leave_minutes']) }}
                            </span>
                        </div>
                        <div class="month-summary-item">
                            <span class="month-summary-label">Saldo</span>
                            <span class="month-summary-value">{{ $format::minutes($monthBalance['balance_minutes']) }}</span>
                            <span class="month-summary-meta">
                                Soll {{ $format::minutes($monthBalance['target_minutes']) }} | Ist {{ $format::minutes($monthBalance['actual_minutes']) }}
                            </span>
                            <div class="month-summary-badge">
                                <x-status-badge :label="$monthBalance['traffic_light']" kind="traffic-light" />
                            </div>
                        </div>
                    </div>
                </x-ui.card>

                <x-ui.card title="Monatsverlauf" class="month-overview-heatmap">
                    <x-slot:actions>
                        @if($canExport)
                            <x-ui.button :href="route('exports.time.month.csv', ['month' => $month])" variant="secondary">CSV Export</x-ui.button>
                            <x-ui.button :href="route('exports.time.month.excel', ['month' => $month])" variant="secondary">Excel Export</x-ui.button>
                            <x-ui.button :href="route('exports.compliance.month.csv', ['month' => $month])" variant="secondary">Compliance CSV</x-ui.button>
                            <x-ui.button :href="route('exports.compliance.month.excel', ['month' => $month])" variant="secondary">Compliance Excel</x-ui.button>
                        @endif
                    </x-slot:actions>
                    @include('time.partials.month-heatmap', [
                        'month' => $month,
                        'evaluations' => $evaluations,
                        'summariesByDate' => $summariesByDate,
                        'format' => $format,
                    ])
                </x-ui.card>
            </div>
        @endif
    </section>
@endsection
