@extends('layouts.app')

@section('content')
    @php
        $format = \App\Support\DateTimeFormat::class;
        $timezone = auth()->user()?->timezone;
    @endphp

    @php
        $exportParams = request()->only(['actor_id', 'event']);
    @endphp

    <x-ui.page-header title="Audit-Logs" class="page-header-compact">
        <x-slot:actions>
            <x-ui.button :href="route('exports.audit.csv', $exportParams)" variant="secondary">CSV Export</x-ui.button>
            <x-ui.button :href="route('exports.audit.excel', $exportParams)" variant="secondary">Excel Export</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.filter-panel title="Filter" class="filter-panel-compact">
        <form method="get" action="{{ route('audit.index') }}" class="filter-grid">
            <div>
                <label for="actor_id">Akteur</label>
                <input id="actor_id" type="number" name="actor_id" value="{{ request('actor_id') }}">
            </div>
            <div>
                <label for="event">Event</label>
                <select id="event" name="event">
                    <option value="">Alle</option>
                    @foreach($eventOptions as $eventOption)
                        <option value="{{ $eventOption }}" @selected(request('event') === $eventOption)>{{ $eventOption }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-actions">
                <x-ui.button type="submit">Filtern</x-ui.button>
                <x-ui.button :href="route('audit.index')" variant="secondary">Reset</x-ui.button>
            </div>
        </form>
    </x-ui.filter-panel>

    <x-ui.data-table title="Protokoll" class="data-table-tall">
        <table>
            <thead>
            <tr>
                <th>Zeitpunkt</th>
                <th>Actor</th>
                <th>Event</th>
                <th>Objekt</th>
                <th>Vorher</th>
                <th>Nachher</th>
            </tr>
            </thead>
            <tbody>
            @forelse($logs as $log)
                <tr>
                    <td>{{ $format::dateTime($log->created_at, $timezone) }}</td>
                    <td>{{ $log->actor?->name ? $log->actor->name.' (#'.$log->actor_id.')' : $log->actor_id }}</td>
                    <td>{{ $log->event }}</td>
                    <td>{{ $log->auditable_type }} #{{ $log->auditable_id }}</td>
                    <td><pre class="audit-json">{{ json_encode($log->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></td>
                    <td><pre class="audit-json">{{ json_encode($log->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">Keine Audit-Einträge vorhanden.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </x-ui.data-table>

    {{ $logs->links() }}
@endsection
