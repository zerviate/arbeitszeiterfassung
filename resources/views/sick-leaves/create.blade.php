@extends('layouts.app')

@section('content')
    <div class="card">
        <h2>Krankmeldung erfassen</h2>

        <form method="post" action="{{ route('sick-leaves.store') }}">
            @csrf

            <div class="mb-3">
                <label for="user_id">Mitarbeiter</label>
                <select id="user_id" name="user_id" required>
                    <option value="">Bitte auswählen</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}" @selected(old('user_id') == $user->id)>
                            {{ $user->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="grid mb-3">
                <div>
                    <label for="start_date">Startdatum</label>
                    <input id="start_date" type="date" name="start_date" value="{{ old('start_date') }}" required>
                </div>
                <div>
                    <label for="end_date">Enddatum</label>
                    <input id="end_date" type="date" name="end_date" value="{{ old('end_date') }}" required>
                </div>
            </div>

            <div class="mb-3">
                <label for="note">Notiz</label>
                <textarea id="note" name="note" rows="4">{{ old('note') }}</textarea>
            </div>

            <button class="btn btn-success">Speichern</button>
            <a class="btn btn-secondary" href="{{ route('sick-leaves.index') }}">Abbrechen</a>
        </form>
    </div>
@endsection
