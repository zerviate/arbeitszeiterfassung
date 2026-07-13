@extends('layouts.app')

@section('content')
    <div class="card">
        <h2>Korrekturantrag anlegen</h2>

        <form method="post" action="{{ route('time.corrections.store') }}">
            @csrf

            @if($users->isNotEmpty())
                <div class="mb-3">
                    <label>Mitarbeiter</label>
                    <select name="user_id">
                        <option value="">Ich selbst</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" @selected(old('user_id') == $user->id)>
                                {{ $user->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="mb-3">
                <label>Arbeitsdatum</label>
                <input type="date" name="work_date" value="{{ old('work_date', now()->toDateString()) }}">
            </div>

            <div class="mb-3">
                <label>Begründung</label>
                <textarea name="reason" rows="3">{{ old('reason') }}</textarea>
            </div>

            <div class="card">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <h3>Schneller Eintrag</h3>
                    <button type="button" class="btn btn-secondary" id="toggle-advanced-btn">Erweiterte Sessions</button>
                </div>

                <div class="grid">
                    <div>
                        <label for="quick-started-at">Arbeitsbeginn</label>
                        <input id="quick-started-at" type="datetime-local" name="quick_started_at" value="{{ old('quick_started_at') }}" required>
                    </div>
                    <div>
                        <label for="quick-ended-at">Arbeitsende</label>
                        <input id="quick-ended-at" type="datetime-local" name="quick_ended_at" value="{{ old('quick_ended_at') }}" required>
                    </div>
                    <div>
                        <label for="quick-break-started-at">Pause (Start)</label>
                        <input id="quick-break-started-at" type="datetime-local" name="quick_break_started_at" value="{{ old('quick_break_started_at') }}">
                    </div>
                    <div>
                        <label for="quick-break-ended-at">Pause (Ende)</label>
                        <input id="quick-break-ended-at" type="datetime-local" name="quick_break_ended_at" value="{{ old('quick_break_ended_at') }}">
                    </div>
                </div>
            </div>

            <div id="advanced-sessions" class="card" hidden>
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <h3>Sessions</h3>
                    <button type="button" class="btn btn-secondary" id="add-session-btn">Session hinzufügen</button>
                </div>

                <div id="sessions-container"></div>
            </div>

            <div id="quick-session-inputs" class="visually-hidden"></div>

            <div class="mb-3">
                <button class="btn btn-success">Antrag speichern</button>
            </div>
        </form>
    </div>

    <template id="session-template">
        <div class="card session-item" data-session-index="__SESSION_INDEX__">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <h3>Session <span class="session-number">__SESSION_NUMBER__</span></h3>
                <button type="button" class="btn btn-danger remove-session-btn">Session entfernen</button>
            </div>

            <div class="grid">
                <div>
                    <label>Arbeitsbeginn</label>
                    <input type="datetime-local" name="sessions[__SESSION_INDEX__][started_at]">
                </div>
                <div>
                    <label>Arbeitsende</label>
                    <input type="datetime-local" name="sessions[__SESSION_INDEX__][ended_at]">
                </div>
            </div>

            <div class="card">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <h4>Pausen</h4>
                    <button type="button" class="btn btn-secondary add-break-btn">Pause hinzufügen</button>
                </div>

                <div class="breaks-container"></div>
            </div>
        </div>
    </template>

    <template id="break-template">
        <div class="grid break-item" data-break-index="__BREAK_INDEX__" style="margin-bottom:12px;">
            <div>
                <label>Pausenbeginn</label>
                <input type="datetime-local" name="sessions[__SESSION_INDEX__][breaks][__BREAK_INDEX__][started_at]">
            </div>
            <div>
                <label>Pausenende</label>
                <input type="datetime-local" name="sessions[__SESSION_INDEX__][breaks][__BREAK_INDEX__][ended_at]">
            </div>
            <div style="display:flex; align-items:end;">
                <button type="button" class="btn btn-danger remove-break-btn">Pause entfernen</button>
            </div>
        </div>
    </template>

    <script type="application/json" id="old-sessions-data">
        @json(old('sessions', []))
    </script>

    <script>
        (() => {
            const form = document.querySelector('form[action="{{ route("time.corrections.store") }}"]');
            const sessionsContainer = document.getElementById('sessions-container');
            const addSessionBtn = document.getElementById('add-session-btn');
            const advancedPanel = document.getElementById('advanced-sessions');
            const toggleAdvancedBtn = document.getElementById('toggle-advanced-btn');
            const quickInputsContainer = document.getElementById('quick-session-inputs');
            const quickStartedAt = document.getElementById('quick-started-at');
            const quickEndedAt = document.getElementById('quick-ended-at');
            const quickBreakStartedAt = document.getElementById('quick-break-started-at');
            const quickBreakEndedAt = document.getElementById('quick-break-ended-at');
            const sessionTemplate = document.getElementById('session-template').innerHTML;
            const breakTemplate = document.getElementById('break-template').innerHTML;
            const oldSessions = JSON.parse(
                document.getElementById('old-sessions-data')?.textContent?.trim() || '[]'
            );

            function createSession(sessionData = null) {
                const sessionIndex = sessionsContainer.querySelectorAll('.session-item').length;
                const sessionNumber = sessionIndex + 1;

                let html = sessionTemplate
                    .replaceAll('__SESSION_INDEX__', sessionIndex)
                    .replaceAll('__SESSION_NUMBER__', sessionNumber);

                const wrapper = document.createElement('div');
                wrapper.innerHTML = html.trim();

                const sessionElement = wrapper.firstElementChild;

                const startedAtInput = sessionElement.querySelector(
                    `input[name="sessions[${sessionIndex}][started_at]"]`
                );
                const endedAtInput = sessionElement.querySelector(
                    `input[name="sessions[${sessionIndex}][ended_at]"]`
                );

                if (sessionData) {
                    startedAtInput.value = sessionData.started_at ?? '';
                    endedAtInput.value = sessionData.ended_at ?? '';
                }

                sessionElement.querySelector('.remove-session-btn').addEventListener('click', () => {
                    sessionElement.remove();
                    reindexSessions();
                });

                sessionElement.querySelector('.add-break-btn').addEventListener('click', () => {
                    createBreak(sessionElement, null);
                });

                sessionsContainer.appendChild(sessionElement);

                if (sessionData?.breaks?.length) {
                    sessionData.breaks.forEach((breakData) => createBreak(sessionElement, breakData));
                }
            }

            function createBreak(sessionElement, breakData = null) {
                const sessionIndex = sessionElement.dataset.sessionIndex;
                const breaksContainer = sessionElement.querySelector('.breaks-container');
                const breakIndex = breaksContainer.querySelectorAll('.break-item').length;

                let html = breakTemplate
                    .replaceAll('__SESSION_INDEX__', sessionIndex)
                    .replaceAll('__BREAK_INDEX__', breakIndex);

                const wrapper = document.createElement('div');
                wrapper.innerHTML = html.trim();

                const breakElement = wrapper.firstElementChild;

                const startedAtInput = breakElement.querySelector(
                    `input[name="sessions[${sessionIndex}][breaks][${breakIndex}][started_at]"]`
                );
                const endedAtInput = breakElement.querySelector(
                    `input[name="sessions[${sessionIndex}][breaks][${breakIndex}][ended_at]"]`
                );

                if (breakData) {
                    startedAtInput.value = breakData.started_at ?? '';
                    endedAtInput.value = breakData.ended_at ?? '';
                }

                breakElement.querySelector('.remove-break-btn').addEventListener('click', () => {
                    breakElement.remove();
                    reindexBreaks(sessionElement);
                });

                breaksContainer.appendChild(breakElement);
            }

            function reindexSessions() {
                const sessionItems = [...sessionsContainer.querySelectorAll('.session-item')];

                sessionItems.forEach((sessionElement, newSessionIndex) => {
                    sessionElement.dataset.sessionIndex = newSessionIndex;
                    sessionElement.querySelector('.session-number').textContent = newSessionIndex + 1;

                    sessionElement.querySelectorAll('input').forEach((input) => {
                        input.name = input.name.replace(/sessions\[\d+]/, `sessions[${newSessionIndex}]`);
                    });

                    reindexBreaks(sessionElement);
                });
            }

            function reindexBreaks(sessionElement) {
                const sessionIndex = sessionElement.dataset.sessionIndex;
                const breakItems = [...sessionElement.querySelectorAll('.break-item')];

                breakItems.forEach((breakElement, newBreakIndex) => {
                    breakElement.dataset.breakIndex = newBreakIndex;

                    breakElement.querySelectorAll('input').forEach((input) => {
                        input.name = input.name
                            .replace(/sessions\[\d+]/, `sessions[${sessionIndex}]`)
                            .replace(/breaks\[\d+]/, `breaks[${newBreakIndex}]`);
                    });
                });
            }

            const openAdvanced = () => {
                advancedPanel.hidden = false;
                toggleAdvancedBtn.textContent = 'Erweitert ausblenden';
                quickStartedAt.removeAttribute('required');
                quickEndedAt.removeAttribute('required');
            };

            const closeAdvanced = () => {
                advancedPanel.hidden = true;
                toggleAdvancedBtn.textContent = 'Erweiterte Sessions';
                quickStartedAt.setAttribute('required', 'required');
                quickEndedAt.setAttribute('required', 'required');
            };

            toggleAdvancedBtn.addEventListener('click', () => {
                if (advancedPanel.hidden) {
                    openAdvanced();
                    if (sessionsContainer.children.length === 0) {
                        createSession();
                    }
                } else {
                    closeAdvanced();
                }
            });

            addSessionBtn.addEventListener('click', () => createSession());

            if (oldSessions.length > 0) {
                openAdvanced();
                oldSessions.forEach((sessionData) => createSession(sessionData));
            } else {
                closeAdvanced();
            }

            form.addEventListener('submit', () => {
                if (!advancedPanel.hidden && sessionsContainer.children.length > 0) {
                    return;
                }

                quickInputsContainer.innerHTML = '';

                const startedAt = quickStartedAt.value;
                const endedAt = quickEndedAt.value;

                if (!startedAt || !endedAt) {
                    return;
                }

                const sessionInputs = [
                    { name: 'sessions[0][started_at]', value: startedAt },
                    { name: 'sessions[0][ended_at]', value: endedAt },
                ];

                if (quickBreakStartedAt.value && quickBreakEndedAt.value) {
                    sessionInputs.push(
                        { name: 'sessions[0][breaks][0][started_at]', value: quickBreakStartedAt.value },
                        { name: 'sessions[0][breaks][0][ended_at]', value: quickBreakEndedAt.value }
                    );
                }

                sessionInputs.forEach((inputData) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = inputData.name;
                    input.value = inputData.value;
                    quickInputsContainer.appendChild(input);
                });
            });
        })();
    </script>
@endsection
