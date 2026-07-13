@extends('layouts.app')

@section('content')
    @php
        $format = \App\Support\DateTimeFormat::class;
        $timezone = auth()->user()?->timezone;
    @endphp

    <x-ui.page-header :title="'Urlaubsantrag #' . $vacation->id" class="page-header-compact">
        <x-slot:actions>
            <x-ui.button :href="route('vacations.index')" variant="secondary">Zur Liste</x-ui.button>
            @if($canReview)
                <x-ui.button type="button" variant="success" data-review-trigger="approve" :disabled="! $isPending">Genehmigen</x-ui.button>
                <x-ui.button type="button" variant="danger" data-review-trigger="reject" :disabled="! $isPending">Ablehnen</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="metric-grid">
        <div class="metric-card">
            <span class="metric-label">Status</span>
            <span class="metric-value"><x-status-badge :label="$vacation->status" kind="request-status" /></span>
        </div>
        <div class="metric-card">
            <span class="metric-label">Zeitraum</span>
            <span class="metric-value">{{ $format::date($vacation->start_date) }} - {{ $format::date($vacation->end_date) }}</span>
            <span class="metric-meta">{{ $vacation->days_requested }} Tage</span>
        </div>
        <div class="metric-card">
            <span class="metric-label">Beantragt von</span>
            <span class="metric-value">{{ $vacation->requestedBy->name ?? '-' }}</span>
            <span class="metric-meta">{{ $format::dateTime($vacation->created_at, $timezone) }}</span>
        </div>
    </div>

    <div class="split-layout">
        <x-ui.card title="Antragsdetails">
            <div class="detail-list">
                <div class="detail-list-row">
                    <span class="detail-list-label">Mitarbeiter</span>
                    <span class="detail-list-value">{{ $vacation->user->name ?? '-' }}</span>
                </div>
                <div class="detail-list-row">
                    <span class="detail-list-label">Typ</span>
                    <span class="detail-list-value">{{ ucfirst($vacation->type) }}</span>
                </div>
                <div class="detail-list-row">
                    <span class="detail-list-label">Begründung</span>
                    <span class="detail-list-value">{{ $vacation->reason ?: '-' }}</span>
                </div>
                @if($vacation->review_note)
                    <div class="detail-list-row">
                        <span class="detail-list-label">Review-Notiz</span>
                        <span class="detail-list-value">{{ $vacation->review_note }}</span>
                    </div>
                @endif
            </div>
        </x-ui.card>

        @if(isset($vacationSummary))
            <x-ui.card :title="'Urlaubskonto ' . $vacationSummary['year']">
                <div class="detail-list">
                    <div class="detail-list-row">
                        <span class="detail-list-label">Verfügbar</span>
                        <span class="detail-list-value">{{ number_format((float) $vacationSummary['available_days'], 2, ',', '.') }}</span>
                    </div>
                    <div class="detail-list-row">
                        <span class="detail-list-label">Offene Anträge</span>
                        <span class="detail-list-value">{{ number_format((float) $vacationSummary['pending_days'], 2, ',', '.') }}</span>
                    </div>
                    <div class="detail-list-row">
                        <span class="detail-list-label">Verbraucht</span>
                        <span class="detail-list-value">{{ number_format((float) $vacationSummary['used_days'], 2, ',', '.') }}</span>
                    </div>
                    <div class="detail-list-row">
                        <span class="detail-list-label">Rest</span>
                        <span class="detail-list-value">{{ number_format((float) $vacationSummary['remaining_days'], 2, ',', '.') }}</span>
                    </div>
                </div>
            </x-ui.card>
        @endif
    </div>

    @if($vacation->records->isNotEmpty())
        <x-ui.data-table title="Genehmigte Urlaubstage">
            <table>
                <thead>
                <tr>
                    <th>Datum</th>
                </tr>
                </thead>
                <tbody>
                @foreach($vacation->records->sortBy('absence_date') as $record)
                    <tr>
                        <td>{{ $format::date($record->absence_date) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </x-ui.data-table>
    @endif

    @if($canSeeCancelAction)
        <x-ui.card title="Stornieren">
            <form method="post" action="{{ route('vacations.cancel', $vacation) }}">
                @csrf
                <x-ui.button type="submit" variant="secondary" :disabled="! $canCancel">Stornieren</x-ui.button>
            </form>
        </x-ui.card>
    @endif

    @if($canReview)
        <div class="review-popover" data-review-popover="approve" hidden>
            <form method="post" action="{{ route('vacations.approve', $vacation) }}" class="review-popover-form">
                @csrf
                <h3 class="review-popover-title">Urlaubsantrag genehmigen</h3>
                <label for="approve-review-note">Review-Notiz</label>
                <textarea id="approve-review-note" name="review_note" rows="3">{{ old('review_note') }}</textarea>
                <div class="review-popover-actions">
                    <x-ui.button type="button" variant="secondary" data-review-close>Abbrechen</x-ui.button>
                    <x-ui.button type="submit" variant="success" :disabled="! $isPending">Genehmigen</x-ui.button>
                </div>
            </form>
        </div>

        <div class="review-popover" data-review-popover="reject" hidden>
            <form method="post" action="{{ route('vacations.reject', $vacation) }}" class="review-popover-form">
                @csrf
                <h3 class="review-popover-title">Urlaubsantrag ablehnen</h3>
                <label for="reject-review-note">Review-Notiz</label>
                <textarea id="reject-review-note" name="review_note" rows="3">{{ old('review_note') }}</textarea>
                <div class="review-popover-actions">
                    <x-ui.button type="button" variant="secondary" data-review-close>Abbrechen</x-ui.button>
                    <x-ui.button type="submit" variant="danger" :disabled="! $isPending">Ablehnen</x-ui.button>
                </div>
            </form>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const triggers = Array.from(document.querySelectorAll('[data-review-trigger]'));
                const popovers = Array.from(document.querySelectorAll('[data-review-popover]'));

                const byName = new Map(popovers.map((popover) => [popover.dataset.reviewPopover, popover]));

                const closeAll = () => {
                    popovers.forEach((popover) => {
                        popover.hidden = true;
                    });
                };

                const positionPopover = (popover, trigger) => {
                    const margin = 12;
                    const offset = 10;
                    const triggerRect = trigger.getBoundingClientRect();

                    popover.style.left = '0px';
                    popover.style.top = '0px';
                    popover.hidden = false;

                    const rect = popover.getBoundingClientRect();

                    let left = triggerRect.right - rect.width;
                    let top = triggerRect.bottom + offset;

                    if (left < margin) {
                        left = margin;
                    }

                    if (left + rect.width > window.innerWidth - margin) {
                        left = window.innerWidth - rect.width - margin;
                    }

                    if (top + rect.height > window.innerHeight - margin) {
                        top = triggerRect.top - rect.height - offset;
                    }

                    if (top < margin) {
                        top = margin;
                    }

                    popover.style.left = `${left}px`;
                    popover.style.top = `${top}px`;
                };

                triggers.forEach((trigger) => {
                    trigger.addEventListener('click', (event) => {
                        event.stopPropagation();

                        const name = trigger.dataset.reviewTrigger;
                        const popover = byName.get(name);

                        if (!popover) {
                            return;
                        }

                        const wasOpen = !popover.hidden;
                        closeAll();

                        if (wasOpen) {
                            return;
                        }

                        positionPopover(popover, trigger);

                        const textarea = popover.querySelector('textarea');
                        if (textarea) {
                            textarea.focus();
                        }
                    });
                });

                document.querySelectorAll('[data-review-close]').forEach((button) => {
                    button.addEventListener('click', () => {
                        const popover = button.closest('[data-review-popover]');
                        if (popover) {
                            popover.hidden = true;
                        }
                    });
                });

                document.addEventListener('click', (event) => {
                    if (event.target.closest('[data-review-popover]')) {
                        return;
                    }

                    if (event.target.closest('[data-review-trigger]')) {
                        return;
                    }

                    closeAll();
                });

                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape') {
                        closeAll();
                    }
                });

                window.addEventListener('resize', () => {
                    closeAll();
                });
            });
        </script>
    @endif
@endsection
