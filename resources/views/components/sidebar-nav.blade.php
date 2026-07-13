@props(['user'])

@php
    $timezone = $user->timezone ?: 'UTC';
    $today = now()->setTimezone($timezone)->format('Y-m-d');
    $month = now()->setTimezone($timezone)->format('Y-m');
    $matchesRoute = static fn (array $patterns): bool => collect($patterns)->contains(fn (string $pattern): bool => request()->routeIs($pattern));

    $canSeeTimeLinks = $user->hasAnyPermission([
        'time.view.own',
        'time.view.team',
        'time.view.all',
    ]);

    $canSeeVacationLinks = $user->hasAnyPermission([
        'absence.view.own',
        'absence.view.team',
        'absence.view.all',
        'absence.request.own',
        'absence.request.for_others',
    ]);

    $canSeeSickLeaveLinks = $user->hasAnyPermission([
        'absence.view.own',
        'absence.view.team',
        'absence.view.all',
    ]);

    $canSeeManagementLinks = $user->hasAnyPermission([
        'time.view.team',
        'time.view.all',
        'time.contract.manage',
        'time.holiday.manage',
        'absence.view.team',
        'absence.view.all',
        'absence.vacation.balance.manage',
    ]);

    $icon = static function (string $name): string {
        return match ($name) {
            'clock' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
            'calendar' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
            'edit' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>',
            'sun' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><line x1="12" y1="2" x2="12" y2="4"/><line x1="12" y1="20" x2="12" y2="22"/><line x1="4.93" y1="4.93" x2="6.34" y2="6.34"/><line x1="17.66" y1="17.66" x2="19.07" y2="19.07"/><line x1="2" y1="12" x2="4" y2="12"/><line x1="20" y1="12" x2="22" y2="12"/><line x1="4.93" y1="19.07" x2="6.34" y2="17.66"/><line x1="17.66" y1="6.34" x2="19.07" y2="4.93"/></svg>',
            'heart' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>',
            'users' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
            'file' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>',
            'star' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15 8.5 22 9.3 17 14 18.5 21 12 17.8 5.5 21 7 14 2 9.3 9 8.5 12 2"/></svg>',
            'wallet' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-5"/><path d="M18 12h4"/></svg>',
            default => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/></svg>',
        };
    };
@endphp

<div class="app-sidebar-inner">
    <div class="app-brand">
        <span class="app-brand-mark">AZ</span>
        <span>
            <span class="app-brand-title">Arbeitszeiterfassung</span>
        </span>
    </div>

    @if($canSeeTimeLinks)
        <details class="app-nav-group" open>
            <summary class="app-nav-group-title">Arbeitszeit</summary>
            <div class="app-nav-list">
                <a href="{{ route('time.index') }}" @class(['app-nav-link', 'is-active' => $matchesRoute(['time.index'])])>
                    <span class="app-nav-icon" aria-hidden="true">{!! $icon('clock') !!}</span>
                    <span class="app-nav-label">Heute</span>
                </a>
                <a href="{{ route('time.month') }}" @class(['app-nav-link', 'is-active' => $matchesRoute(['time.month'])])>
                    <span class="app-nav-icon" aria-hidden="true">{!! $icon('calendar') !!}</span>
                    <span class="app-nav-label">Monat</span>
                </a>
                <a href="{{ route('time.corrections.index') }}" @class(['app-nav-link', 'is-active' => $matchesRoute(['time.corrections.*'])])>
                    <span class="app-nav-icon" aria-hidden="true">{!! $icon('edit') !!}</span>
                    <span class="app-nav-label">Korrekturen</span>
                </a>
            </div>
        </details>
    @endif

    @if($canSeeVacationLinks || $canSeeSickLeaveLinks)
        <details class="app-nav-group" open>
            <summary class="app-nav-group-title">Abwesenheit</summary>
            <div class="app-nav-list">
                @if($canSeeVacationLinks)
                    <a href="{{ route('vacations.index', ['month' => $month]) }}" @class(['app-nav-link', 'is-active' => $matchesRoute(['vacations.*'])])>
                        <span class="app-nav-icon" aria-hidden="true">{!! $icon('sun') !!}</span>
                        <span class="app-nav-label">Urlaub</span>
                    </a>
                @endif

                @if($canSeeSickLeaveLinks)
                    <a href="{{ route('sick-leaves.index', ['month' => $month]) }}" @class(['app-nav-link', 'is-active' => $matchesRoute(['sick-leaves.*'])])>
                        <span class="app-nav-icon" aria-hidden="true">{!! $icon('heart') !!}</span>
                        <span class="app-nav-label">Krankmeldungen</span>
                    </a>
                @endif
            </div>
        </details>
    @endif

    @if($canSeeManagementLinks)
        <details class="app-nav-group" open>
            <summary class="app-nav-group-title">Verwaltung</summary>
            <div class="app-nav-list">
                @if($user->hasAnyPermission(['time.view.team', 'time.view.all']))
                    <a href="{{ route('management.time.index') }}" @class(['app-nav-link', 'is-active' => $matchesRoute(['management.time.*'])])>
                        <span class="app-nav-icon" aria-hidden="true">{!! $icon('users') !!}</span>
                        <span class="app-nav-label">Teamzeiten</span>
                    </a>
                @endif

                @if($user->hasPermission('time.contract.manage'))
                    <a href="{{ route('contracts.index') }}" @class(['app-nav-link', 'is-active' => $matchesRoute(['contracts.*'])])>
                        <span class="app-nav-icon" aria-hidden="true">{!! $icon('file') !!}</span>
                        <span class="app-nav-label">Verträge</span>
                    </a>
                @endif

                @if($user->hasPermission('time.holiday.manage'))
                    <a href="{{ route('holidays.index') }}" @class(['app-nav-link', 'is-active' => $matchesRoute(['holidays.*'])])>
                        <span class="app-nav-icon" aria-hidden="true">{!! $icon('star') !!}</span>
                        <span class="app-nav-label">Feiertage</span>
                    </a>
                @endif

                @if($user->hasAnyPermission(['absence.view.team', 'absence.view.all', 'absence.vacation.balance.manage']))
                    <a href="{{ route('vacation-balances.index') }}" @class(['app-nav-link', 'is-active' => $matchesRoute(['vacation-balances.*'])])>
                        <span class="app-nav-icon" aria-hidden="true">{!! $icon('wallet') !!}</span>
                        <span class="app-nav-label">Urlaubskonten</span>
                    </a>
                @endif
            </div>
        </details>
    @endif
</div>
