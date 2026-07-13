@extends('layouts.app')

@section('content')
    <div class="card">
        <h2>Feiertag bearbeiten</h2>

        <form method="post" action="{{ route('holidays.update', $holiday) }}">
            @csrf
            @method('PUT')

            <div class="grid mb-3">
                <div>
                    <label for="holiday_date">Datum</label>
                    <input id="holiday_date" type="date" name="holiday_date" value="{{ old('holiday_date', $holiday->holiday_date?->toDateString()) }}" required>
                </div>
                <div>
                    <label for="name">Bezeichnung</label>
                    <input id="name" type="text" name="name" value="{{ old('name', $holiday->name) }}" maxlength="160" required>
                </div>
            </div>

            <div class="mb-3">
                <label>
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked((int) old('is_active', $holiday->is_active ? 1 : 0) === 1)>
                    Aktiv
                </label>
            </div>

            <button class="btn btn-success">Aktualisieren</button>
            <a class="btn btn-secondary" href="{{ route('holidays.index') }}">Abbrechen</a>
        </form>
    </div>
@endsection
