@php
    $monthDate = \Carbon\Carbon::createFromFormat('Y-m', $month, 'UTC');
    $startOfMonth = $monthDate->copy()->startOfMonth();
    $daysInMonth = $monthDate->daysInMonth;
    $evaluationsByDate = $evaluations->keyBy(fn ($item) => $item->work_date?->toDateString());
    $emptyCells = max(0, $startOfMonth->dayOfWeekIso - 1);

    $heatClassForMinutes = static function (int $minutes): string {
        return match (true) {
            $minutes >= 360 => 'heat-high',
            $minutes >= 240 => 'heat-mid',
            $minutes > 0 => 'heat-low',
            default => 'heat-0',
        };
    };
@endphp

<div class="month-heatmap" data-heatmap>
    <div class="heatmap-grid">
        @for($i = 0; $i < $emptyCells; $i++)
            <div class="heatmap-cell heatmap-empty"></div>
        @endfor

        @for($day = 1; $day <= $daysInMonth; $day++)
            @php
                $date = $startOfMonth->copy()->day($day);
                $dateKey = $date->toDateString();
                $evaluation = $evaluationsByDate->get($dateKey);
                $summary = $summariesByDate->get($dateKey);
                $minutes = (int) ($summary?->net_minutes ?? $evaluation?->actual_minutes ?? 0);
                $heatClass = $heatClassForMinutes($minutes);
                $isWeekend = $date->isWeekend();
                $classes = trim('heatmap-cell heatmap-day ' . $heatClass . ($isWeekend ? ' heat-weekend' : ''));
                $targetMinutes = (int) ($evaluation?->target_minutes ?? 0);
                $balanceMinutes = (int) ($evaluation?->balance_minutes ?? 0);
                $title = $format::date($date)
                    . ' · Ist ' . $format::minutes($minutes)
                    . ' · Soll ' . $format::minutes($targetMinutes)
                    . ' · Saldo ' . $format::minutes($balanceMinutes);
                $dayStatus = $evaluation?->day_status ?? '-';
                $trafficLight = $evaluation?->traffic_light ?? '-';
                $holidayLabel = $evaluation?->is_holiday
                    ? ($evaluation?->holiday_name ?? 'Ja')
                    : '-';
            @endphp

            <button
                type="button"
                class="{{ $classes }}"
                title="{{ $title }}"
                data-date="{{ $format::date($date) }}"
                data-raw-date="{{ $dateKey }}"
                data-target="{{ $format::minutes($targetMinutes) }}"
                data-gross="{{ $format::minutes($summary?->gross_minutes ?? 0) }}"
                data-breaks="{{ $format::minutes($summary?->break_minutes ?? 0) }}"
                data-net="{{ $format::minutes($summary?->net_minutes ?? $evaluation?->actual_minutes ?? 0) }}"
                data-balance="{{ $format::minutes($balanceMinutes) }}"
                data-status="{{ $dayStatus }}"
                data-traffic="{{ $trafficLight }}"
                data-holiday="{{ $holidayLabel }}"
                data-day-url="{{ route('time.day', $dateKey) }}"
            >
                {{ $day }}
            </button>
        @endfor
    </div>

    <div class="heatmap-legend">
        <span>Ist-Zeit</span>
        <div class="heatmap-legend-scale" aria-hidden="true">
            <span class="heatmap-legend-chip heat-0"></span>
            <span class="heatmap-legend-chip heat-low"></span>
            <span class="heatmap-legend-chip heat-mid"></span>
            <span class="heatmap-legend-chip heat-high"></span>
        </div>
    </div>

    <div class="heatmap-popover" data-heatmap-popover hidden>
        <div class="heatmap-popover-header">
            <div class="heatmap-popover-title" data-heatmap-popover-title>Tag</div>
            <a class="heatmap-popover-link" data-heatmap-popover-link href="#" hidden>Tag oeffnen</a>
        </div>
        <div class="heatmap-popover-grid">
            <div>
                <span class="heatmap-popover-label">Soll</span>
                <span class="heatmap-popover-value" data-heatmap-popover-target>—</span>
            </div>
            <div>
                <span class="heatmap-popover-label">Brutto</span>
                <span class="heatmap-popover-value" data-heatmap-popover-gross>—</span>
            </div>
            <div>
                <span class="heatmap-popover-label">Pausen</span>
                <span class="heatmap-popover-value" data-heatmap-popover-breaks>—</span>
            </div>
            <div>
                <span class="heatmap-popover-label">Netto</span>
                <span class="heatmap-popover-value" data-heatmap-popover-net>—</span>
            </div>
            <div>
                <span class="heatmap-popover-label">Saldo</span>
                <span class="heatmap-popover-value" data-heatmap-popover-balance>—</span>
            </div>
            <div>
                <span class="heatmap-popover-label">Status</span>
                <span class="badge badge-muted heatmap-badge" data-heatmap-popover-status>—</span>
            </div>
            <div>
                <span class="heatmap-popover-label">Ampel</span>
                <span class="badge badge-muted heatmap-badge" data-heatmap-popover-traffic>—</span>
            </div>
            <div>
                <span class="heatmap-popover-label">Feiertag</span>
                <span class="heatmap-popover-value" data-heatmap-popover-holiday>—</span>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const heatmap = document.querySelector('[data-heatmap]');
        if (!heatmap) {
            return;
        }

        const popover = heatmap.querySelector('[data-heatmap-popover]');
        const title = heatmap.querySelector('[data-heatmap-popover-title]');
        const link = heatmap.querySelector('[data-heatmap-popover-link]');
        const fields = {
            target: heatmap.querySelector('[data-heatmap-popover-target]'),
            gross: heatmap.querySelector('[data-heatmap-popover-gross]'),
            breaks: heatmap.querySelector('[data-heatmap-popover-breaks]'),
            net: heatmap.querySelector('[data-heatmap-popover-net]'),
            balance: heatmap.querySelector('[data-heatmap-popover-balance]'),
            statusBadge: heatmap.querySelector('[data-heatmap-popover-status]'),
            trafficBadge: heatmap.querySelector('[data-heatmap-popover-traffic]'),
            holiday: heatmap.querySelector('[data-heatmap-popover-holiday]'),
        };

        const buttons = heatmap.querySelectorAll('.heatmap-day');

        const statusLabels = {
            fulfilled: 'Erfuellt',
            holiday_work: 'Arbeit am Feiertag',
            extra_work: 'Mehrarbeit',
            vacation: 'Urlaub',
            sick_leave: 'Krank',
            minor_under_target: 'Leicht unter Soll',
            incomplete: 'Unvollstaendig',
            missing_contract: 'Fehlender Vertrag',
            worked_without_contract: 'Arbeit ohne Vertrag',
            under_target: 'Unter Soll',
            holiday: 'Feiertag',
            off_day: 'Freier Tag',
        };

        const trafficLabels = {
            green: 'Gruen',
            yellow: 'Gelb',
            red: 'Rot',
            grey: 'Grau',
        };

        const statusVariants = {
            fulfilled: 'success',
            holiday_work: 'success',
            extra_work: 'success',
            vacation: 'info',
            sick_leave: 'info',
            minor_under_target: 'warning',
            incomplete: 'warning',
            missing_contract: 'warning',
            worked_without_contract: 'warning',
            under_target: 'danger',
            holiday: 'muted',
            off_day: 'muted',
        };

        const trafficVariants = {
            green: 'success',
            yellow: 'warning',
            red: 'danger',
            grey: 'muted',
        };

        const setBadge = (badge, value, labels, variants) => {
            if (!badge) {
                return;
            }

            const normalized = String(value || '').toLowerCase();
            const label = labels[normalized] || value || '—';
            const variant = variants[normalized] || 'muted';

            badge.textContent = label;
            badge.className = `badge badge-${variant} heatmap-badge`;
        };

        const positionPopover = (anchorX, anchorY) => {
            if (!popover) {
                return;
            }

            const margin = 12;
            const offset = 12;
            const rect = popover.getBoundingClientRect();
            let left = anchorX + offset;
            let top = anchorY + offset;

            if (left + rect.width > window.innerWidth - margin) {
                left = window.innerWidth - rect.width - margin;
            }

            if (top + rect.height > window.innerHeight - margin) {
                top = anchorY - rect.height - offset;
            }

            if (left < margin) {
                left = margin;
            }

            if (top < margin) {
                top = margin;
            }

            popover.style.left = `${left}px`;
            popover.style.top = `${top}px`;
        };

        const showPopover = (button, event) => {
            if (!popover) {
                return;
            }

            popover.hidden = false;
            popover.style.left = '0px';
            popover.style.top = '0px';

            const rect = button.getBoundingClientRect();
            const anchorX = event?.clientX ?? rect.left + rect.width / 2;
            const anchorY = event?.clientY ?? rect.top + rect.height / 2;

            positionPopover(anchorX, anchorY);
        };

        const hidePopover = () => {
            if (popover) {
                popover.hidden = true;
            }
        };

        const setActive = (button, event) => {
            buttons.forEach((item) => item.classList.remove('is-selected'));
            button.classList.add('is-selected');

            title.textContent = button.dataset.date || 'Tag';
            fields.target.textContent = button.dataset.target || '—';
            fields.gross.textContent = button.dataset.gross || '—';
            fields.breaks.textContent = button.dataset.breaks || '—';
            fields.net.textContent = button.dataset.net || '—';
            fields.balance.textContent = button.dataset.balance || '—';
            setBadge(fields.statusBadge, button.dataset.status, statusLabels, statusVariants);
            setBadge(fields.trafficBadge, button.dataset.traffic, trafficLabels, trafficVariants);
            fields.holiday.textContent = button.dataset.holiday || '—';

            if (button.dataset.dayUrl) {
                link.href = button.dataset.dayUrl;
                link.hidden = false;
            } else if (link) {
                link.hidden = true;
            }

            showPopover(button, event);
        };

        buttons.forEach((button) => {
            button.addEventListener('click', (event) => {
                event.stopPropagation();
                setActive(button, event);
            });
        });

        document.addEventListener('click', (event) => {
            if (!popover || popover.hidden) {
                return;
            }

            if (popover.contains(event.target)) {
                return;
            }

            if (event.target.closest('.heatmap-day')) {
                return;
            }

            hidePopover();
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                hidePopover();
            }
        });
    });
</script>
