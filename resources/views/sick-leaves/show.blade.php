@extends('layouts.app')

@section('content')
    @php
        $format = \App\Support\DateTimeFormat::class;
        $timezone = auth()->user()?->timezone;
        $dayCount = $records->count();
        $rangeDays = $sickLeaveGroup->start_date && $sickLeaveGroup->end_date
            ? $sickLeaveGroup->start_date->diffInDays($sickLeaveGroup->end_date) + 1
            : $dayCount;
    @endphp

    <x-ui.page-header title="Krankmeldung {{ $sickLeaveGroup->group_key }}" class="page-header-compact">
        <x-slot:actions>
            <x-ui.button :href="route('sick-leaves.index')" variant="secondary">Zur Liste</x-ui.button>
            @if($canManage)
                <x-ui.button :href="route('sick-leaves.edit', $sickLeaveGroup)" variant="ghost">Bearbeiten</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="metric-grid">
        <div class="metric-card">
            <span class="metric-label">Mitarbeiter</span>
            <span class="metric-value">{{ $sickLeaveGroup->user->name ?? '-' }}</span>
        </div>
        <div class="metric-card">
            <span class="metric-label">Zeitraum</span>
            <span class="metric-value">{{ $format::date($sickLeaveGroup->start_date) }} - {{ $format::date($sickLeaveGroup->end_date) }}</span>
            <span class="metric-meta">{{ $rangeDays }} Tage</span>
        </div>
        <div class="metric-card">
            <span class="metric-label">Krankheitstage</span>
            <span class="metric-value">{{ $dayCount }}</span>
        </div>
    </div>

    <div class="split-layout">
        <x-ui.card title="Krankmeldungsdetails">
            <div class="detail-list">
                @if($sickLeaveGroup->note)
                    <div class="detail-list-row">
                        <span class="detail-list-label">Notiz</span>
                        <span class="detail-list-value">{{ $sickLeaveGroup->note }}</span>
                    </div>
                @endif
                <div class="detail-list-row">
                    <span class="detail-list-label">Erfasst von</span>
                    <span class="detail-list-value">{{ $sickLeaveGroup->recordedBy->name ?? '-' }}</span>
                </div>
                <div class="detail-list-row">
                    <span class="detail-list-label">Erfasst am</span>
                    <span class="detail-list-value">{{ $format::dateTime($sickLeaveGroup->created_at, $timezone) }}</span>
                </div>
            </div>
        </x-ui.card>

        <x-ui.data-table title="Krankheitstage">
            <table>
                <thead>
                <tr>
                    <th>Datum</th>
                </tr>
                </thead>
                <tbody>
                @foreach($records as $record)
                    <tr>
                        <td>{{ $format::date($record->absence_date) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </x-ui.data-table>
    </div>

    @if($canManage)
        <x-ui.card title="Aktionen">
            <form method="post" action="{{ route('sick-leaves.destroy', $sickLeaveGroup) }}" class="inline">
                @csrf
                @method('DELETE')
                <x-ui.button type="submit" variant="danger">Loeschen</x-ui.button>
            </form>
        </x-ui.card>
    @endif
@endsection
