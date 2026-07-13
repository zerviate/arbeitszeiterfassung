@extends('layouts.app')

@section('content')
    @php
        $dayLabels = [
            'monday' => 'Montag',
            'tuesday' => 'Dienstag',
            'wednesday' => 'Mittwoch',
            'thursday' => 'Donnerstag',
            'friday' => 'Freitag',
            'saturday' => 'Samstag',
            'sunday' => 'Sonntag',
        ];
    @endphp

    <div class="card">
        <h2>Vertrag bearbeiten</h2>

        <form method="post" action="{{ route('contracts.update', $contract) }}">
            @csrf
            @method('PUT')

            <div class="mb-3">
                <label for="employee_name">Mitarbeiter</label>
                <input id="employee_name" type="text" value="{{ $contract->user->name ?? '-' }}" disabled>
            </div>

            @php
                $weeklyMinutes = (int) old('weekly_minutes', $contract->weekly_minutes);
                $weeklyHours = number_format($weeklyMinutes / 60, 2, '.', '');
            @endphp

            <div class="mb-3">
                <label for="weekly_hours">Wochenstunden</label>
                <input id="weekly_hours" type="number" step="0.25" min="1" max="168" value="{{ $weeklyHours }}" required>
                <input id="weekly_minutes" type="hidden" name="weekly_minutes" value="{{ $weeklyMinutes }}">
            </div>

            <div class="grid mb-3">
                <div>
                    <label for="valid_from">Gültig von</label>
                    <input id="valid_from" type="date" name="valid_from" value="{{ old('valid_from', $contract->valid_from?->toDateString()) }}" required>
                </div>
                <div>
                    <label for="valid_to">Gültig bis</label>
                    <input id="valid_to" type="date" name="valid_to" value="{{ old('valid_to', $contract->valid_to?->toDateString()) }}">
                </div>
            </div>

            <div class="card">
                <h3>Arbeitstage</h3>

                @foreach($dayLabels as $day => $label)
                    <label style="display:block; margin-bottom:8px;">
                        <input type="hidden" name="workdays_pattern[{{ $day }}]" value="0">
                        <input
                            type="checkbox"
                            name="workdays_pattern[{{ $day }}]"
                            value="1"
                            @checked((int) old("workdays_pattern.$day", ($contract->workdays_pattern[$day] ?? false) ? 1 : 0) === 1)
                        >
                        {{ $label }}
                    </label>
                @endforeach
            </div>

            <div class="mb-3">
                <label>
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked((int) old('is_active', $contract->is_active ? 1 : 0) === 1)>
                    Aktiv
                </label>
            </div>

            <button class="btn btn-success">Aktualisieren</button>
            <a class="btn btn-secondary" href="{{ route('contracts.index') }}">Abbrechen</a>
        </form>
    </div>

    <script>
        (() => {
            const hoursInput = document.getElementById('weekly_hours');
            const minutesInput = document.getElementById('weekly_minutes');

            if (!hoursInput || !minutesInput) {
                return;
            }

            const syncMinutes = () => {
                const hours = Number.parseFloat(hoursInput.value.replace(',', '.'));
                const minutes = Number.isFinite(hours) ? Math.round(hours * 60) : 0;
                minutesInput.value = String(Math.max(minutes, 0));
            };

            hoursInput.addEventListener('input', syncMinutes);
            syncMinutes();
        })();
    </script>
@endsection
