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
        $defaultActiveDays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
    @endphp

    <div class="card">
        <h2>Vertrag anlegen</h2>

        <form method="post" action="{{ route('contracts.store') }}">
            @csrf

            <div class="mb-3">
                <label for="user_id">Mitarbeiter</label>
                <select id="user_id" name="user_id" required>
                    <option value="">Bitte auswählen</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}" @selected((int) old('user_id') === $user->id)>
                            {{ $user->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            @php
                $weeklyMinutes = (int) old('weekly_minutes', 2400);
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
                    <input id="valid_from" type="date" name="valid_from" value="{{ old('valid_from', now()->toDateString()) }}" required>
                </div>
                <div>
                    <label for="valid_to">Gültig bis</label>
                    <input id="valid_to" type="date" name="valid_to" value="{{ old('valid_to') }}">
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
                            @checked((int) old("workdays_pattern.$day", in_array($day, $defaultActiveDays, true) ? 1 : 0) === 1)
                        >
                        {{ $label }}
                    </label>
                @endforeach
            </div>

            <div class="mb-3">
                <label>
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked((int) old('is_active', 1) === 1)>
                    Aktiv
                </label>
            </div>

            <button class="btn btn-success">Speichern</button>
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
