<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Arbeitszeiterfassung</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @if(! app()->runningUnitTests() && (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot'))))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body>
@if(auth()->check())
    <x-app-shell :user="auth()->user()">
        @yield('content')
    </x-app-shell>
@else
    <div class="guest-shell">
        <main class="guest-main">
            <x-feedback-stack />
            @yield('content')
        </main>
    </div>
@endif
</body>
</html>
