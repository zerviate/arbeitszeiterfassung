@props([
    'label' => '',
    'kind' => 'default',
    'variant' => null,
])

@php
    $normalizedLabel = strtolower((string) $label);
    $displayLabel = match ($kind) {
        'request-status' => match ($normalizedLabel) {
            'approved' => 'Genehmigt',
            'pending' => 'Offen',
            'rejected' => 'Abgelehnt',
            'cancelled' => 'Storniert',
            default => (string) $label,
        },
        'traffic-light' => match ($normalizedLabel) {
            'green' => 'Grün',
            'yellow' => 'Gelb',
            'red' => 'Rot',
            'grey' => 'Grau',
            default => (string) $label,
        },
        'day-status' => match ($normalizedLabel) {
            'fulfilled' => 'Erfüllt',
            'holiday_work' => 'Arbeit am Feiertag',
            'extra_work' => 'Mehrarbeit',
            'vacation' => 'Urlaub',
            'sick_leave' => 'Krank',
            'minor_under_target' => 'Leicht unter Soll',
            'incomplete' => 'Unvollständig',
            'missing_contract' => 'Fehlender Vertrag',
            'worked_without_contract' => 'Arbeit ohne Vertrag',
            'under_target' => 'Unter Soll',
            'holiday' => 'Feiertag',
            'off_day' => 'Freier Tag',
            default => (string) $label,
        },
        'entry-status' => match ($normalizedLabel) {
            'finalized', 'finalisiert' => 'Finalisiert',
            'open', 'offen' => 'Offen',
            default => (string) $label,
        },
        'flag' => match ($normalizedLabel) {
            'open_session' => 'Offene Session',
            'missing_contract' => 'Fehlender Vertrag',
            'holiday' => 'Feiertag',
            'absence_on_holiday' => 'Abwesenheit am Feiertag',
            'missing_break_30' => '30-Minuten-Pause fehlt',
            'missing_break_45' => '45-Minuten-Pause fehlt',
            'daily_limit_exceeded' => 'Tägliches Limit überschritten',
            'manual_correction_present' => 'Manuelle Korrektur vorhanden',
            default => ucfirst(str_replace('_', ' ', (string) $label)),
        },
        default => (string) $label,
    };

    if ($variant === null) {
        $variant = match ($kind) {
            'request-status' => match ($normalizedLabel) {
                'approved' => 'success',
                'pending' => 'warning',
                'rejected' => 'danger',
                'cancelled' => 'muted',
                default => 'default',
            },
            'traffic-light' => match ($normalizedLabel) {
                'green' => 'success',
                'yellow' => 'warning',
                'red' => 'danger',
                'grey' => 'muted',
                default => 'default',
            },
            'day-status' => match ($normalizedLabel) {
                'fulfilled', 'holiday_work', 'extra_work' => 'success',
                'vacation', 'sick_leave' => 'info',
                'minor_under_target', 'incomplete', 'missing_contract', 'worked_without_contract' => 'warning',
                'under_target' => 'danger',
                'holiday', 'off_day' => 'muted',
                default => 'default',
            },
            'entry-status' => match ($normalizedLabel) {
                'finalized', 'finalisiert' => 'success',
                'open', 'offen' => 'warning',
                default => 'default',
            },
            'flag' => 'info',
            default => 'default',
        };
    }
@endphp

<span {{ $attributes->class(['badge', 'badge-'.$variant]) }}>{{ $displayLabel }}</span>
