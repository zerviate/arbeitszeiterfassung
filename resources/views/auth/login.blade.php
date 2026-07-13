@extends('layouts.app')

@section('content')
    <div class="card" style="max-width:480px; margin:0 auto;">
        <h2>Anmeldung</h2>

        <form method="post" action="{{ route('login.store') }}">
            @csrf

            <div class="mb-3">
                <label for="email">E-Mail</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
            </div>

            <div class="mb-3">
                <label for="password">Passwort</label>
                <input id="password" type="password" name="password" required>
            </div>

            <div class="mb-3">
                <label style="display:flex; align-items:center; gap:8px; font-weight:400;">
                    <input type="checkbox" name="remember" value="1" style="width:auto;">
                    Angemeldet bleiben
                </label>
            </div>

            <button type="submit" class="btn btn-success">Einloggen</button>
        </form>
    </div>
@endsection
