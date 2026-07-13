@php
    $toasts = [];

    if (session('toast')) {
        $toasts[] = session('toast');
    }

    if (session('success')) {
        $toasts[] = [
            'message' => session('success'),
            'variant' => 'success',
        ];
    }

    if ($errors->any()) {
        $toasts[] = [
            'message' => 'Bitte überprüfe die Eingaben.',
            'variant' => 'danger',
            'list' => $errors->all(),
        ];
    }
@endphp

@if(! empty($toasts))
    <div class="toast-stack" data-toast-stack>
        @foreach($toasts as $toast)
            @php
                $variant = $toast['variant'] ?? 'neutral';
                $message = $toast['message'] ?? '';
            @endphp
            <div class="toast toast-{{ $variant }}" data-toast data-timeout="3500">
                <button type="button" class="toast-close" aria-label="Benachrichtigung schließen" data-toast-dismiss>×</button>
                <div class="toast-message">{{ $message }}</div>
                @if(! empty($toast['list']) && is_array($toast['list']))
                    <ul class="toast-list">
                        @foreach($toast['list'] as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endforeach
    </div>
@endif
