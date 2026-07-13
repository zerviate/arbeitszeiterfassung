@extends('layouts.app')

@section('content')
    @php $format = \App\Support\DateTimeFormat::class; @endphp

    <div class="card">
        <h2>Verwaltung - Tagesübersicht {{ $format::date($date) }}</h2>

        <form method="get" action="{{ route('management.time.index') }}" class="mb-3" style="display:flex; gap:8px; align-items:flex-end; flex-wrap:wrap;">
            <div>
                <label for="management-time-date">Datum</label>
                <input id="management-time-date" type="date" name="date" value="{{ $date }}">
            </div>
            <button class="btn">Filtern</button>
            <a class="btn btn-secondary" href="{{ route('management.time.index') }}">Reset</a>
        </form>

        @if(auth()->user()->hasAnyPermission(['time.export.own', 'time.export.team', 'time.export.all']))
            <div class="mb-3" style="display:flex; gap:8px; flex-wrap:wrap;">
                <a class="btn btn-secondary" href="{{ route('exports.time.day.csv', ['date' => $date]) }}">CSV Export</a>
                <a class="btn btn-secondary" href="{{ route('exports.time.day.excel', ['date' => $date]) }}">Excel Export</a>
                <a class="btn btn-secondary" href="{{ route('exports.compliance.day.csv', ['date' => $date]) }}">Compliance CSV</a>
                <a class="btn btn-secondary" href="{{ route('exports.compliance.day.excel', ['date' => $date]) }}">Compliance Excel</a>
            </div>
        @endif

        <div class="data-table-scroll">
            <table>
                <thead>
                <tr>
                    <th>Mitarbeiter</th>
                    <th>Brutto</th>
                    <th>Pausen</th>
                    <th>Netto</th>
                    <th>Soll</th>
                    <th>Saldo</th>
                    <th>Status</th>
                    <th><span class="visually-hidden">Status</span></th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse($users as $user)
                    @php $summary = $summaries->get($user->id); @endphp
                    @php $evaluation = $evaluations->get($user->id); @endphp
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td>{{ $summary ? $format::minutes($summary->gross_minutes) : '-' }}</td>
                        <td>{{ $summary ? $format::minutes($summary->break_minutes) : '-' }}</td>
                        <td>{{ $summary ? $format::minutes($summary->net_minutes) : '-' }}</td>
                        <td>{{ $evaluation ? $format::minutes($evaluation->target_minutes) : '-' }}</td>
                        <td>{{ $evaluation ? $format::minutes($evaluation->balance_minutes) : '-' }}</td>
                        <td>@if($evaluation)<x-status-badge :label="$evaluation->day_status" kind="day-status" />@else-@endif</td>
                        <td>
                            @if($evaluation)
                                <x-status-badge :label="$evaluation->traffic_light" kind="traffic-light" />
                            @else
                                -
                            @endif
                        </td>
                        <td>
                            <a class="btn btn-secondary" href="{{ route('management.time.show', [$user, $date]) }}">Öffnen</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9">Keine Mitarbeiter gefunden.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
