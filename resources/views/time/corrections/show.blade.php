@extends('layouts.app')

@section('content')
    @php $format = \App\Support\DateTimeFormat::class; @endphp

    <div class="card">
        <h2>Korrekturantrag #{{ $correction->id }}</h2>

        <p><strong>Mitarbeiter:</strong> {{ $correction->user->name ?? '-' }}</p>
        <p><strong>Datum:</strong> {{ $format::date($correction->work_date) }}</p>
        <p><strong>Status:</strong> <x-status-badge :label="$correction->status" kind="request-status" /></p>
        <p><strong>Beantragt von:</strong> {{ $correction->requestedBy->name ?? '-' }}</p>
        <p><strong>Begründung:</strong> {{ $correction->reason }}</p>

        <h3>Neue Werte</h3>
        <pre>{{ json_encode($correction->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>

        @if($correction->review_note)
            <h3>Review-Notiz</h3>
            <p>{{ $correction->review_note }}</p>
        @endif

        @can('review', $correction)
            @if($correction->status === 'pending')
            <div class="grid">
                <div class="card">
                    <h3>Genehmigen</h3>
                    <form method="post" action="{{ route('time.corrections.approve', $correction) }}">
                        @csrf
                        <div class="mb-3">
                            <label>Review-Notiz</label>
                            <textarea name="review_note" rows="3"></textarea>
                        </div>
                        <button class="btn btn-success">Genehmigen</button>
                    </form>
                </div>

                <div class="card">
                    <h3>Ablehnen</h3>
                    <form method="post" action="{{ route('time.corrections.reject', $correction) }}">
                        @csrf
                        <div class="mb-3">
                            <label>Review-Notiz</label>
                            <textarea name="review_note" rows="3"></textarea>
                        </div>
                        <button class="btn btn-danger">Ablehnen</button>
                    </form>
                </div>
            </div>
            @endif
        @endcan
    </div>
@endsection
