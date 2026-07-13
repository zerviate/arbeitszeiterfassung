@extends('layouts.app')

@section('content')
    <div class="card">
        <h2>Urlaubskonto bearbeiten</h2>

        <form method="post" action="{{ route('vacation-balances.update', $vacationBalance) }}">
            @csrf
            @method('PUT')

            <div class="mb-3">
                <label for="vacation-balance-user">Mitarbeiter</label>
                <input id="vacation-balance-user" type="text" value="{{ $vacationBalance->user->name }}" disabled>
            </div>

            <div class="mb-3">
                <label for="vacation-balance-year">Jahr</label>
                <input id="vacation-balance-year" type="number" value="{{ $vacationBalance->year }}" disabled>
            </div>

            <div class="grid mb-3">
                <div>
                    <label for="vacation-balance-entitlement">Urlaubsanspruch</label>
                    <input id="vacation-balance-entitlement" type="number" step="0.01" name="annual_entitlement_days" value="{{ old('annual_entitlement_days', $vacationBalance->annual_entitlement_days) }}" min="0" max="366" required>
                </div>
                <div>
                    <label for="vacation-balance-carryover">Übertrag</label>
                    <input id="vacation-balance-carryover" type="number" step="0.01" name="carryover_days" value="{{ old('carryover_days', $vacationBalance->carryover_days) }}" min="0" max="366">
                </div>
            </div>

            <div class="mb-3">
                <label for="vacation-balance-adjustment">Manuelle Anpassung</label>
                <input id="vacation-balance-adjustment" type="number" step="0.01" name="manual_adjustment_days" value="{{ old('manual_adjustment_days', $vacationBalance->manual_adjustment_days) }}" min="-366" max="366">
            </div>

            <div class="mb-3">
                <label for="vacation-balance-note">Notiz</label>
                <textarea id="vacation-balance-note" name="note" rows="4">{{ old('note', $vacationBalance->note) }}</textarea>
            </div>

            <button class="btn btn-success">Aktualisieren</button>
            <a class="btn btn-secondary" href="{{ route('vacation-balances.index', ['year' => $vacationBalance->year]) }}">Abbrechen</a>
        </form>
    </div>
@endsection
