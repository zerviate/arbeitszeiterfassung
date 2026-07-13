@extends('layouts.app')

@section('content')
    @php
        $yearStart = max(2000, $year - 5);
        $yearEnd = min(2100, $year + 5);
        $yearOptions = range($yearStart, $yearEnd);
    @endphp

    <x-ui.page-header title="Urlaubskonten · {{ $year }}" class="page-header-compact">
        <x-slot:meta>
            <form method="get" action="{{ route('vacation-balances.index') }}" class="inline" data-date-switcher>
                <div class="header-switcher" data-switcher="year">
                    <label class="header-switcher-field">
                        <select class="header-switcher-select" name="year">
                            @foreach($yearOptions as $option)
                                <option value="{{ $option }}" @selected($option === (int) $year)>{{ $option }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
            </form>
        </x-slot:meta>
        <x-slot:actions>
            @if($canManage)
                <x-ui.button :href="route('vacation-balances.create', ['year' => $year])">Urlaubskonto anlegen</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card title="Urlaubskonten im Jahr {{ $year }}">
        @if($summaries->isEmpty())
            <p class="text-muted">Keine Urlaubskonten vorhanden.</p>
        @else
            <div class="balance-grid">
                @foreach($summaries as $item)
                    @php
                        $summary = $item['summary'];
                        $balance = $summary['balance'];
                    @endphp
                    <section class="balance-card">
                        <div class="balance-card-header">
                            <div>
                                <div class="balance-card-title">{{ $item['user']->name }}</div>
                                <div class="balance-card-meta">{{ $year }}</div>
                            </div>
                            @if($canManage)
                                @if($balance)
                                    <x-ui.button :href="route('vacation-balances.edit', $balance)" variant="secondary">Bearbeiten</x-ui.button>
                                @else
                                    <x-ui.button :href="route('vacation-balances.create', ['user_id' => $item['user']->id, 'year' => $year])" variant="secondary">Anlegen</x-ui.button>
                                @endif
                            @endif
                        </div>

                        <div class="balance-metrics">
                            <div class="balance-metric">
                                <span class="balance-metric-label">Verfügbar</span>
                                <span class="balance-metric-value">{{ number_format((float) $summary['available_days'], 2, ',', '.') }}</span>
                            </div>
                            <div class="balance-metric">
                                <span class="balance-metric-label">Offen</span>
                                <span class="balance-metric-value">{{ number_format((float) $summary['pending_days'], 2, ',', '.') }}</span>
                            </div>
                            <div class="balance-metric">
                                <span class="balance-metric-label">Genehmigt</span>
                                <span class="balance-metric-value">{{ number_format((float) $summary['approved_days'], 2, ',', '.') }}</span>
                            </div>
                            <div class="balance-metric">
                                <span class="balance-metric-label">Rest</span>
                                <span class="balance-metric-value">{{ number_format((float) $summary['remaining_days'], 2, ',', '.') }}</span>
                            </div>
                        </div>
                    </section>
                @endforeach
            </div>
        @endif
    </x-ui.card>
@endsection
