@extends('layouts.app')

@section('content')
    @php $format = \App\Support\DateTimeFormat::class; @endphp

    <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:12px;">
        <h2>Verträge</h2>
            <a class="btn" href="{{ route('contracts.create') }}">Vertrag anlegen</a>
        </div>

        <table>
            <thead>
            <tr>
                <th>Mitarbeiter</th>
                <th>Wochenzeit</th>
                <th>Arbeitstage</th>
                <th>Tages-Soll</th>
                <th>Gültig von</th>
                <th>Gültig bis</th>
                <th>Aktiv</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse($contracts as $contract)
                @php
                    $activeDays = collect($contract->workdays_pattern ?? [])->filter(fn ($active) => (bool) $active)->count();
                    $dailyTarget = $activeDays > 0 ? (int) floor($contract->weekly_minutes / $activeDays) : 0;
                @endphp
                <tr>
                    <td>{{ $contract->user->name ?? '-' }}</td>
                    <td>{{ $format::minutes($contract->weekly_minutes) }}</td>
                    <td>{{ $activeDays }}</td>
                    <td>{{ $format::minutes($dailyTarget) }}</td>
                    <td>{{ $format::date($contract->valid_from) }}</td>
                    <td>{{ $contract->valid_to ? $format::date($contract->valid_to) : 'offen' }}</td>
                    <td>
                        <x-status-badge :label="$contract->is_active ? 'Aktiv' : 'Inaktiv'" :variant="$contract->is_active ? 'success' : 'muted'" />
                    </td>
                    <td>
                        <a class="btn btn-secondary" href="{{ route('contracts.edit', $contract) }}">Bearbeiten</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">Keine Verträge vorhanden.</td>
                </tr>
            @endforelse
            </tbody>
        </table>

        {{ $contracts->links() }}
    </div>
@endsection
