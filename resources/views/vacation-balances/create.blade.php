@extends('layouts.app')

@section('content')
    <div class="card">
        <h2>Urlaubskonto anlegen</h2>

        <form method="post" action="{{ route('vacation-balances.store') }}">
            @csrf

            <div class="mb-3">
                <label for="vacation-balance-user">Mitarbeiter</label>
                <select id="vacation-balance-user" name="user_id" required>
                    <option value="">Bitte auswählen</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}" @selected((int) old('user_id', (int) $prefillUserId) === $user->id)>
                            {{ $user->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="grid mb-3">
                <div>
                    <label for="vacation-balance-year">Jahr</label>
                    <input id="vacation-balance-year" type="number" name="year" value="{{ old('year', $prefillYear ?? now()->format('Y')) }}" min="2000" max="2100" required>
                </div>
                <div>
                    <label for="vacation-balance-entitlement">Urlaubsanspruch</label>
                    <input id="vacation-balance-entitlement" type="number" step="0.01" name="annual_entitlement_days" value="{{ old('annual_entitlement_days', 30) }}" min="0" max="366" required>
                </div>
            </div>

            <div class="grid mb-3">
                <div>
                    <label for="vacation-balance-carryover">Übertrag</label>
                    <input id="vacation-balance-carryover" type="number" step="0.01" name="carryover_days" value="{{ old('carryover_days', 0) }}" min="0" max="366">
                </div>
                <div>
                    <label for="vacation-balance-adjustment">Manuelle Anpassung</label>
                    <input id="vacation-balance-adjustment" type="number" step="0.01" name="manual_adjustment_days" value="{{ old('manual_adjustment_days', 0) }}" min="-366" max="366">
                </div>
            </div>

            <div class="mb-3">
                <label for="vacation-balance-note">Notiz</label>
                <textarea id="vacation-balance-note" name="note" rows="4">{{ old('note') }}</textarea>
            </div>

            <button class="btn btn-success">Speichern</button>
            <a class="btn btn-secondary" href="{{ route('vacation-balances.index', ['year' => old('year', $prefillYear ?? now()->format('Y'))]) }}">Abbrechen</a>
        </form>
    </div>
@endsection
