@extends('layouts.app')

@section('content')
    @php
        $format = \App\Support\DateTimeFormat::class;
        $timezone = auth()->user()?->timezone;
        $canRequestVacation = auth()->user()->hasAnyPermission(['absence.request.own', 'absence.request.for_others']);
    @endphp

    <x-ui.page-header title="Urlaubsanträge" class="page-header-compact">
        <x-slot:actions>
            @if($canRequestVacation)
                <x-ui.button :href="route('vacations.create')">Urlaub beantragen</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @if(($showVacationSummary ?? false) && isset($vacationSummary))
        <div class="metric-grid">
            <div class="metric-card">
                <span class="metric-label">Verfügbar</span>
                <span class="metric-value">{{ number_format((float) $vacationSummary['available_days'], 2, ',', '.') }}</span>
            </div>
            <div class="metric-card">
                <span class="metric-label">Offene Anträge</span>
                <span class="metric-value">{{ number_format((float) $vacationSummary['pending_days'], 2, ',', '.') }}</span>
            </div>
            <div class="metric-card">
                <span class="metric-label">Verbraucht</span>
                <span class="metric-value">{{ number_format((float) $vacationSummary['used_days'], 2, ',', '.') }}</span>
            </div>
            <div class="metric-card">
                <span class="metric-label">Rest</span>
                <span class="metric-value">{{ number_format((float) $vacationSummary['remaining_days'], 2, ',', '.') }}</span>
            </div>
        </div>
    @endif

    <x-ui.data-table title="Urlaubsanträge" class="data-table-tall">
        <table>
            <thead>
            <tr>
                <th>Mitarbeiter</th>
                <th>Von</th>
                <th>Bis</th>
                <th>Tage</th>
                <th>Status</th>
                <th>Beantragt von</th>
                <th>Erstellt</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse($requests as $requestItem)
                <tr>
                    <td>{{ $requestItem->user->name ?? '-' }}</td>
                    <td>{{ $format::date($requestItem->start_date) }}</td>
                    <td>{{ $format::date($requestItem->end_date) }}</td>
                    <td>{{ $requestItem->days_requested }}</td>
                    <td><x-status-badge :label="$requestItem->status" kind="request-status" /></td>
                    <td>{{ $requestItem->requestedBy->name ?? '-' }}</td>
                    <td>{{ $format::dateTime($requestItem->created_at, $timezone) }}</td>
                    <td>
                        <x-ui.button :href="route('vacations.show', $requestItem)" variant="secondary">Öffnen</x-ui.button>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">Keine Urlaubsanträge vorhanden.</td>
                </tr>
            @endforelse
            </tbody>
        </table>

        <div class="mt-4">
            {{ $requests->links() }}
        </div>
    </x-ui.data-table>

@endsection
