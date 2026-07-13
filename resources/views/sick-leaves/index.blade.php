@extends('layouts.app')

@section('content')
    @php
        $format = \App\Support\DateTimeFormat::class;
    @endphp

    <x-ui.page-header title="Krankmeldungen" class="page-header-compact">
        <x-slot:actions>
            @if($canManage)
                <x-ui.button :href="route('sick-leaves.create')">Krankmeldung erfassen</x-ui.button>
            @endif
            @if($canExport)
                <x-ui.button :href="route('exports.sick-leaves.month.csv', ['month' => $exportMonth])" variant="secondary">CSV Export</x-ui.button>
                <x-ui.button :href="route('exports.sick-leaves.month.excel', ['month' => $exportMonth])" variant="secondary">Excel Export</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="metric-grid section-space">
        <div class="metric-card">
            <span class="metric-label">Krankmeldungen im Monat</span>
            <span class="metric-value">{{ $monthGroupCount }}</span>
            <span class="metric-meta">{{ $format::monthLabel($metricMonth) }}</span>
        </div>
        <div class="metric-card">
            <span class="metric-label">Krankmeldungen gesamt</span>
            <span class="metric-value">{{ $totalGroupCount }}</span>
        </div>
    </div>

    <x-ui.data-table title="Krankmeldungsserien" class="data-table-tall">
        <x-slot:actions>
            <form method="get" action="{{ route('sick-leaves.index') }}" class="table-filter" data-date-switcher>
                <label for="sick-leaves-month" class="visually-hidden">Monat</label>
                <input id="sick-leaves-month" type="month" name="month" value="{{ $selectedMonth ?? '' }}">
                <a class="table-filter-reset" href="{{ route('sick-leaves.index') }}">Alle anzeigen</a>
            </form>
        </x-slot:actions>
        <table>
            <thead>
            <tr>
                <th>Mitarbeiter</th>
                <th>Von</th>
                <th>Bis</th>
                <th>Tage</th>
                <th>Notiz</th>
                <th>Erfasst von</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse($groups as $group)
                <tr>
                    <td>{{ $group->user->name ?? '-' }}</td>
                    <td>{{ $format::date($group->start_date) }}</td>
                    <td>{{ $format::date($group->end_date) }}</td>
                    <td>{{ $group->records_count }}</td>
                    <td>{{ $group->note ?: '-' }}</td>
                    <td>{{ $group->recordedBy->name ?? '-' }}</td>
                    <td>
                        <x-ui.button :href="route('sick-leaves.show', $group)" variant="secondary">Öffnen</x-ui.button>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">Keine Krankmeldungen vorhanden.</td>
                </tr>
            @endforelse
            </tbody>
        </table>

        <div class="mt-4">
            {{ $groups->links() }}
        </div>
    </x-ui.data-table>
@endsection
