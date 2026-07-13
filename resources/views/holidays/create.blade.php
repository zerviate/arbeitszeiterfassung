@extends('layouts.app')

@section('content')
    <div class="card">
        <h2>Feiertag anlegen</h2>

        <form method="post" action="{{ route('holidays.store') }}">
            @csrf

            <div class="grid mb-3">
                <div>
                    <label for="holiday_date">Datum</label>
                <input id="holiday_date" type="date" name="holiday_date" value="{{ old('holiday_date', request('date', now()->toDateString())) }}" required>
                </div>
                <div>
                    <label for="name">Bezeichnung</label>
                    <input id="name" type="text" name="name" value="{{ old('name') }}" maxlength="160" required>
                </div>
            </div>

            <div class="mb-3">
                <label>
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked((int) old('is_active', 1) === 1)>
                    Aktiv
                </label>
            </div>

            <button class="btn btn-success">Speichern</button>
            <a class="btn btn-secondary" href="{{ route('holidays.index') }}">Abbrechen</a>
        </form>
    </div>
@endsection
