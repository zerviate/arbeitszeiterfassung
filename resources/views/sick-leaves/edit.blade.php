@extends('layouts.app')

@section('content')
    <div class="card">
        <h2>Krankmeldung bearbeiten</h2>

        <form method="post" action="{{ route('sick-leaves.update', $sickLeaveGroup) }}">
            @csrf
            @method('PUT')

            <div class="mb-3">
                <label for="employee_name">Mitarbeiter</label>
                <input id="employee_name" type="text" value="{{ $sickLeaveGroup->user->name }}" disabled>
            </div>

            <div class="grid mb-3">
                <div>
                    <label for="start_date">Startdatum</label>
                    <input
                        id="start_date"
                        type="date"
                        name="start_date"
                        value="{{ old('start_date', $sickLeaveGroup->start_date?->toDateString()) }}"
                        required
                    >
                </div>
                <div>
                    <label for="end_date">Enddatum</label>
                    <input
                        id="end_date"
                        type="date"
                        name="end_date"
                        value="{{ old('end_date', $sickLeaveGroup->end_date?->toDateString()) }}"
                        required
                    >
                </div>
            </div>

            <div class="mb-3">
                <label for="note">Notiz</label>
                <textarea id="note" name="note" rows="4">{{ old('note', $sickLeaveGroup->note) }}</textarea>
            </div>

            <button class="btn btn-success">Aktualisieren</button>
            <a class="btn btn-secondary" href="{{ route('sick-leaves.show', $sickLeaveGroup) }}">Abbrechen</a>
        </form>
    </div>
@endsection
