@extends('layouts.app')

@section('content')
    @php
        $month = $month ?? now('UTC')->format('Y-m');
        $monthDate = \Carbon\Carbon::createFromFormat('Y-m', $month, 'UTC');
        $year = (int) $monthDate->format('Y');
        $monthNumber = (int) $monthDate->format('n');
        $entriesByDate = $entries->groupBy(fn ($entry) => $entry->holiday_date?->toDateString());
        $monthNames = [
            1 => 'Januar',
            2 => 'Februar',
            3 => 'März',
            4 => 'April',
            5 => 'Mai',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'August',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Dezember',
        ];
        $monthLabel = $monthNames[$monthNumber] ?? $monthDate->format('F');
        $daysInMonth = $monthDate->daysInMonth;
        $startOffset = $monthDate->dayOfWeekIso - 1;
    @endphp

    <x-ui.page-header title="Feiertage · {{ $monthLabel }} {{ $year }}" class="page-header-compact">
        <x-slot:meta>
            <form method="get" action="{{ route('holidays.index') }}" class="inline" data-date-switcher>
                <div class="header-switcher" data-switcher="month">
                    <label class="header-switcher-field">
                        <input class="header-switcher-input" type="month" name="month" value="{{ $month }}">
                    </label>
                </div>
            </form>
        </x-slot:meta>
        <x-slot:actions>
            <x-ui.button :href="route('holidays.create')" variant="secondary">Feiertag anlegen</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="holiday-calendar">
        <section class="holiday-month">
            <div class="holiday-month-header">
                <h3 class="holiday-month-title">{{ $monthLabel }}</h3>
                <span class="holiday-month-meta">{{ $monthDate->format('m.Y') }}</span>
            </div>

            <div class="holiday-grid">
                @for($i = 0; $i < $startOffset; $i++)
                    <div class="holiday-day holiday-day-empty"></div>
                @endfor

                @for($day = 1; $day <= $daysInMonth; $day++)
                    @php
                        $date = $monthDate->copy()->day($day);
                        $dateKey = $date->toDateString();
                        $entry = $entriesByDate->get($dateKey)?->first();
                        $isActive = $entry?->is_active;
                        $classes = $entry
                            ? ($isActive ? 'holiday-day holiday-active holiday-day-editable' : 'holiday-day holiday-inactive holiday-day-editable')
                            : 'holiday-day holiday-day-create';
                    @endphp
                    @if($entry)
                        <div class="{{ $classes }}" data-edit-url="{{ route('holidays.edit', $entry) }}">
                            <div class="holiday-day-number">{{ $day }}</div>
                            <div class="holiday-day-name">{{ $entry->name }}</div>
                            <div class="holiday-day-actions">
                                <form method="post" action="{{ route('holidays.toggle', $entry) }}">
                                    @csrf
                                    <button type="submit" class="holiday-toggle">
                                        {{ $isActive ? 'Deaktivieren' : 'Aktivieren' }}
                                    </button>
                                </form>
                                <a class="holiday-edit" href="{{ route('holidays.edit', $entry) }}">Bearbeiten</a>
                                <form method="post" action="{{ route('holidays.destroy', $entry) }}" onsubmit="return confirm('Feiertag wirklich löschen?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="holiday-edit">Löschen</button>
                                </form>
                            </div>
                        </div>
                    @else
                        <button
                            type="button"
                            class="{{ $classes }}"
                            data-create-url="{{ route('holidays.create', ['date' => $dateKey]) }}"
                            aria-label="Feiertag am {{ $date->format('d.m.Y') }} anlegen"
                        >
                            <div class="holiday-day-number">{{ $day }}</div>
                        </button>
                    @endif
                @endfor
            </div>
        </section>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.holiday-day-create').forEach((button) => {
                button.addEventListener('click', () => {
                    const targetUrl = button.dataset.createUrl;
                    if (!targetUrl) {
                        return;
                    }

                    const confirmed = window.confirm('Feiertag eintragen?');
                    if (confirmed) {
                        window.location.href = targetUrl;
                    }
                });
            });

            document.querySelectorAll('.holiday-day-editable').forEach((card) => {
                card.addEventListener('click', (event) => {
                    if (event.target.closest('button, a, form')) {
                        return;
                    }

                    const targetUrl = card.dataset.editUrl;
                    if (targetUrl) {
                        window.location.href = targetUrl;
                    }
                });
            });
        });
    </script>
@endsection
