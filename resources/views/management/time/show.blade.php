@extends('layouts.app')

@section('content')
    @php
        $format = \App\Support\DateTimeFormat::class;
        $timezone = $user->timezone ?: auth()->user()?->timezone ?: 'UTC';
    @endphp

    <div class="card">
        <h2>{{ $user->name }} - {{ $format::date($date) }}</h2>

        @if($summary || $evaluation)
            <p><strong>Brutto:</strong> {{ $format::minutes($summary?->gross_minutes ?? 0) }}</p>
            <p><strong>Pausen:</strong> {{ $format::minutes($summary?->break_minutes ?? 0) }}</p>
            <p><strong>Netto:</strong> {{ $format::minutes($summary?->net_minutes ?? 0) }}</p>
            <p><strong>Überzeit:</strong> {{ $format::minutes($summary?->overtime_minutes ?? 0) }}</p>

            @if($evaluation)
                <p><strong>Soll:</strong> {{ $format::minutes($evaluation->target_minutes) }}</p>
                <p><strong>Saldo:</strong> {{ $format::minutes($evaluation->balance_minutes) }}</p>
                <p><strong>Status:</strong> <x-status-badge :label="$evaluation->day_status ?? '-'" kind="day-status" /></p>
            <p><x-status-badge :label="$evaluation->traffic_light ?? '-'" kind="traffic-light" /></p>
                @if($evaluation->is_holiday)
                    <p><strong>Feiertag:</strong> {{ $evaluation->holiday_name ?? 'Ja' }}</p>
                @endif
            @endif

            <p>
                @if($summary?->finalized_at)
                    <x-status-badge label="finalisiert" kind="entry-status" />
                @endif
                @if($summary?->has_open_entries)
                    <x-status-badge label="open" kind="entry-status" />
                @endif
            </p>

            @if($canFinalize)
                <form method="post" action="{{ route('time.summaries.finalize', $summary) }}">
                    @csrf
                    <button class="btn btn-success">Tag finalisieren</button>
                </form>
            @endif
        @else
            <p>Keine Tageszusammenfassung vorhanden.</p>
        @endif
    </div>

    <div class="card">
        <h3>Sessions</h3>

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
                            Keine
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
    </div>
@endsection
