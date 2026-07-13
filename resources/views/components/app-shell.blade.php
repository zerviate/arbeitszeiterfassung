@props(['user'])

<div class="app-shell">
    <aside class="app-sidebar" aria-label="Hauptnavigation">
        <x-sidebar-nav :user="$user" />
    </aside>

    <div class="app-content">
        <x-topbar :user="$user" />

        <main class="app-main">
            <div class="app-main-inner">
                <x-feedback-stack />
                {{ $slot }}
            </div>
        </main>
    </div>

</div>
