@extends('layouts.app')

@section('content')
    <div class="card">
        <h2>Urlaubsantrag anlegen</h2>

        <form method="post" action="{{ route('vacations.store') }}">
            @csrf

            @if($users->isNotEmpty())
                <div class="mb-3">
                    <label for="user_id">Mitarbeiter</label>
                    <select id="user_id" name="user_id">
                        <option value="">Ich selbst</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" @selected(old('user_id') == $user->id)>
                                {{ $user->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

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
                <label for="reason">Begründung / Notiz</label>
                <textarea id="reason" name="reason" rows="4">{{ old('reason') }}</textarea>
            </div>

            <button class="btn btn-success">Urlaubsantrag speichern</button>
            <a class="btn btn-secondary" href="{{ route('vacations.index') }}">Abbrechen</a>
        </form>
    </div>
@endsection
