<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import StatusBadge from '../components/StatusBadge.vue'
import { demo, formatDate, formatDateTime, isoDate, localDateTime, requestLabel, requestTone, reviewCorrection, saveCorrection, type WorkSession } from '../store'
import { navigate } from '../router'
import { showToast } from '../toast'

const props = defineProps<{ path: string }>()
const cleanPath = computed(() => props.path.split('?')[0])
const mode = computed(() => cleanPath.value.endsWith('/create') ? 'create' : cleanPath.value === '/time/corrections' ? 'index' : 'show')
const selected = computed(() => demo.corrections.find((item) => item.id === Number(cleanPath.value.split('/').at(-1))))
const advanced = ref(false)
const reviewApprove = ref('')
const reviewReject = ref('')

interface BreakDraft { start: string; end: string }
interface SessionDraft { start: string; end: string; breaks: BreakDraft[] }

const form = reactive({
    userId: 1,
    date: isoDate(),
    reason: '',
    quickStart: `${isoDate()}T08:00`,
    quickEnd: `${isoDate()}T16:30`,
    quickBreakStart: `${isoDate()}T12:00`,
    quickBreakEnd: `${isoDate()}T12:30`,
    sessions: [{ start: `${isoDate()}T08:00`, end: `${isoDate()}T16:30`, breaks: [{ start: `${isoDate()}T12:00`, end: `${isoDate()}T12:30` }] }] as SessionDraft[],
})

watch(() => form.date, (date) => {
    const useDate = (value: string): string => value ? `${date}${value.slice(10)}` : value
    form.quickStart = useDate(form.quickStart)
    form.quickEnd = useDate(form.quickEnd)
    form.quickBreakStart = useDate(form.quickBreakStart)
    form.quickBreakEnd = useDate(form.quickBreakEnd)
    form.sessions.forEach((entry) => {
        entry.start = useDate(entry.start)
        entry.end = useDate(entry.end)
        entry.breaks.forEach((pause) => {
            pause.start = useDate(pause.start)
            pause.end = useDate(pause.end)
        })
    })
})

function addSession(): void {
    form.sessions.push({ start: `${form.date}T08:00`, end: `${form.date}T16:30`, breaks: [] })
}

function addBreak(item: SessionDraft): void {
    item.breaks.push({ start: `${form.date}T12:00`, end: `${form.date}T12:30` })
}

function toSession(item: SessionDraft): WorkSession {
    return {
        start: `${item.start}:00`,
        end: item.end ? `${item.end}:00` : null,
        breaks: item.breaks.map((entry) => ({ start: `${entry.start}:00`, end: entry.end ? `${entry.end}:00` : null })),
    }
}

function submit(): void {
    if (!form.date || !form.reason.trim()) {
        showToast('Bitte überprüfe die Eingaben.', 'danger', 'Validierungsfehler')
        return
    }
    const sessions = advanced.value
        ? form.sessions.map(toSession)
        : [{
            start: `${form.quickStart}:00`,
            end: `${form.quickEnd}:00`,
            breaks: form.quickBreakStart && form.quickBreakEnd ? [{ start: `${form.quickBreakStart}:00`, end: `${form.quickBreakEnd}:00` }] : [],
        }]
    if (sessions.some((item) => !item.start || !item.end || new Date(item.end) <= new Date(item.start))) {
        showToast('Arbeitsende muss nach dem Arbeitsbeginn liegen.', 'danger', 'Validierungsfehler')
        return
    }
    const correction = saveCorrection({ userId: form.userId, date: form.date, reason: form.reason, sessions })
    navigate(`/time/corrections/${correction.id}`)
    showToast('Korrekturantrag wurde gespeichert.')
}

function review(status: 'approved' | 'rejected'): void {
    if (!selected.value) return
    const note = status === 'approved' ? reviewApprove.value : reviewReject.value
    reviewCorrection(selected.value.id, status, note)
    showToast(status === 'approved' ? 'Korrekturantrag wurde genehmigt.' : 'Korrekturantrag wurde abgelehnt.', status === 'approved' ? 'success' : 'warning')
}
</script>

<template>
    <div v-if="mode === 'index'" class="card">
        <div class="demo-heading-row"><h2>Korrekturen</h2><a class="btn" href="#/time/corrections/create">Neue Korrektur</a></div>
        <div class="data-table-scroll"><table><thead><tr><th>Mitarbeiter</th><th>Datum</th><th>Status</th><th>Beantragt von</th><th>Erstellt</th><th></th></tr></thead><tbody>
            <tr v-for="item in demo.corrections" :key="item.id"><td>{{ demo.users.find((user) => user.id === item.userId)?.name ?? '-' }}</td><td>{{ formatDate(item.date) }}</td><td><StatusBadge :label="requestLabel(item.status)" :tone="requestTone(item.status)" /></td><td>{{ item.requestedBy }}</td><td>{{ formatDateTime(item.createdAt) }}</td><td><a class="btn btn-secondary" :href="`#/time/corrections/${item.id}`">Öffnen</a></td></tr>
            <tr v-if="!demo.corrections.length"><td colspan="6">Keine Korrekturen vorhanden.</td></tr>
        </tbody></table></div>
    </div>

    <div v-else-if="mode === 'create'" class="card">
        <h2>Korrekturantrag anlegen</h2>
        <form @submit.prevent="submit">
            <div class="mb-3"><label>Mitarbeiter</label><select v-model.number="form.userId"><option :value="1">Ich selbst</option><option v-for="user in demo.users.filter((item) => item.id !== 1)" :key="user.id" :value="user.id">{{ user.name }}</option></select></div>
            <div class="mb-3"><label>Arbeitsdatum</label><input v-model="form.date" type="date"></div>
            <div class="mb-3"><label>Begründung</label><textarea v-model="form.reason" rows="3"></textarea></div>
            <div class="card">
                <div class="demo-heading-row"><h3>Schneller Eintrag</h3><button type="button" class="btn btn-secondary" @click="advanced = !advanced">{{ advanced ? 'Schneller Eintrag' : 'Erweiterte Sessions' }}</button></div>
                <div class="grid" :hidden="advanced">
                    <div><label>Arbeitsbeginn</label><input v-model="form.quickStart" type="datetime-local" :required="!advanced"></div><div><label>Arbeitsende</label><input v-model="form.quickEnd" type="datetime-local" :required="!advanced"></div><div><label>Pause (Start)</label><input v-model="form.quickBreakStart" type="datetime-local"></div><div><label>Pause (Ende)</label><input v-model="form.quickBreakEnd" type="datetime-local"></div>
                </div>
            </div>
            <div v-if="advanced" class="card">
                <div class="demo-heading-row"><h3>Sessions</h3><button type="button" class="btn btn-secondary" @click="addSession">Session hinzufügen</button></div>
                <div v-for="(entry, sessionIndex) in form.sessions" :key="sessionIndex" class="card session-item">
                    <div class="demo-heading-row"><h3>Session {{ sessionIndex + 1 }}</h3><button v-if="form.sessions.length > 1" type="button" class="btn btn-danger" @click="form.sessions.splice(sessionIndex, 1)">Session entfernen</button></div>
                    <div class="grid"><div><label>Arbeitsbeginn</label><input v-model="entry.start" type="datetime-local" required></div><div><label>Arbeitsende</label><input v-model="entry.end" type="datetime-local" required></div></div>
                    <div class="card"><div class="demo-heading-row"><h4>Pausen</h4><button type="button" class="btn btn-secondary" @click="addBreak(entry)">Pause hinzufügen</button></div>
                        <div v-for="(pause, breakIndex) in entry.breaks" :key="breakIndex" class="grid break-item demo-break-row"><div><label>Pausenbeginn</label><input v-model="pause.start" type="datetime-local"></div><div><label>Pausenende</label><input v-model="pause.end" type="datetime-local"></div><div class="demo-align-end"><button type="button" class="btn btn-danger" @click="entry.breaks.splice(breakIndex, 1)">Pause entfernen</button></div></div>
                    </div>
                </div>
            </div>
            <div class="mb-3"><button class="btn btn-success">Antrag speichern</button></div>
        </form>
    </div>

    <div v-else-if="selected" class="card">
        <h2>Korrekturantrag #{{ selected.id }}</h2>
        <p><strong>Mitarbeiter:</strong> {{ demo.users.find((user) => user.id === selected.userId)?.name ?? '-' }}</p><p><strong>Datum:</strong> {{ formatDate(selected.date) }}</p><p><strong>Status:</strong> <StatusBadge :label="requestLabel(selected.status)" :tone="requestTone(selected.status)" /></p><p><strong>Beantragt von:</strong> {{ selected.requestedBy }}</p><p><strong>Begründung:</strong> {{ selected.reason }}</p>
        <h3>Neue Werte</h3><pre>{{ JSON.stringify({ sessions: selected.sessions }, null, 2) }}</pre>
        <template v-if="selected.reviewNote"><h3>Review-Notiz</h3><p>{{ selected.reviewNote }}</p></template>
        <div v-if="selected.status === 'pending'" class="grid">
            <div class="card"><h3>Genehmigen</h3><form @submit.prevent="review('approved')"><div class="mb-3"><label>Review-Notiz</label><textarea v-model="reviewApprove" rows="3"></textarea></div><button class="btn btn-success">Genehmigen</button></form></div>
            <div class="card"><h3>Ablehnen</h3><form @submit.prevent="review('rejected')"><div class="mb-3"><label>Review-Notiz</label><textarea v-model="reviewReject" rows="3"></textarea></div><button class="btn btn-danger">Ablehnen</button></form></div>
        </div>
    </div>
    <div v-else class="card"><h2>Korrektur nicht gefunden</h2><a class="btn btn-secondary" href="#/time/corrections">Zur Liste</a></div>
</template>
