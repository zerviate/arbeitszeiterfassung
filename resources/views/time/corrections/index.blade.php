@extends('layouts.app')

@section('content')
    @php
        $format = \App\Support\DateTimeFormat::class;
        $timezone = auth()->user()?->timezone;
    @endphp

    <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h2>Korrekturen</h2>
            <a class="btn" href="{{ route('time.corrections.create') }}">Neue Korrektur</a>
        </div>

        <div class="data-table-scroll">
            <table>
                <thead>
                <tr>
                    <th>Mitarbeiter</th>
                    <th>Datum</th>
                    <th>Status</th>
                    <th>Beantragt von</th>
                    <th>Erstellt</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse($corrections as $correction)
                    <tr>
                        <td>{{ $correction->user->name ?? '-' }}</td>
                        <td>{{ $format::date($correction->work_date) }}</td>
                        <td><x-status-badge :label="$correction->status" kind="request-status" /></td>
                        <td>{{ $correction->requestedBy->name ?? '-' }}</td>
                        <td>{{ $format::dateTime($correction->created_at, $timezone) }}</td>
                        <td>
                            <a class="btn btn-secondary" href="{{ route('time.corrections.show', $correction) }}">Öffnen</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">Keine Korrekturen vorhanden.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $corrections->links() }}
        </div>
    </div>
@endsection
