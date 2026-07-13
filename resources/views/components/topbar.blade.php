@props(['user'])

@php
    $timezone = $user->timezone ?: 'UTC';
    $today = now()->setTimezone($timezone)->format('Y-m-d');
    $month = now()->setTimezone($timezone)->format('Y-m');

    $canSeeEvaluationLinks = $user->hasAnyPermission([
        'time.view.own',
        'time.view.team',
        'time.view.all',
    ]);

    $canSeeAuditLink = $user->hasPermission('time.audit.view');
    $showMoreMenu = $canSeeEvaluationLinks || $canSeeAuditLink;

    $initials = collect(explode(' ', (string) $user->name))
        ->filter()
        ->take(2)
        ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');
@endphp

<header class="app-topbar">
    <div class="app-topbar-tools">
        @if($showMoreMenu)
            <details class="app-topbar-menu">
                <summary class="app-topbar-menu-trigger">Mehr
                    <span aria-hidden="true">▾</span>
                </summary>
                <div class="app-topbar-menu-panel">
                    @if($canSeeEvaluationLinks)
                        <span class="app-topbar-menu-label">Bewertungen</span>
                        <a class="app-topbar-menu-link" href="{{ route('evaluations.day', [$user, $today]) }}">Tagesbewertung</a>
                        <a class="app-topbar-menu-link" href="{{ route('evaluations.week', [$user, $today]) }}">Wochenbewertung</a>
                        <a class="app-topbar-menu-link" href="{{ route('evaluations.month', [$user, $month]) }}">Monatsbewertung</a>
                    @endif

                    @if($canSeeEvaluationLinks && $canSeeAuditLink)
                        <div class="app-topbar-menu-divider"></div>
                    @endif

                    @if($canSeeAuditLink)
                        <span class="app-topbar-menu-label">System</span>
                        <a class="app-topbar-menu-link" href="{{ route('audit.index') }}">Audit-Logs</a>
                    @endif
                </div>
            </details>
        @endif

        <div class="app-user-chip">
            <span class="app-user-avatar">{{ $initials !== '' ? $initials : 'AZ' }}</span>
            <span class="app-user-name">{{ $user->name }}</span>
        </div>

        <form method="post" action="{{ route('logout') }}" class="inline">
            @csrf
            <button type="submit" class="btn btn-secondary">Logout</button>
        </form>
    </div>
</header>
