<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import StatusBadge from '../components/StatusBadge.vue'
import {
    dateRange,
    deleteSickLeave,
    demo,
    downloadCsv,
    formatDate,
    formatDateTime,
    isoDate,
    isoMonth,
    requestLabel,
    requestTone,
    reviewVacation,
    saveSickLeave,
    saveVacation,
} from '../store'
import { navigate } from '../router'
import { showToast } from '../toast'

const props = defineProps<{ path: string }>()
const cleanPath = computed(() => props.path.split('?')[0])
const isVacation = computed(() => cleanPath.value.startsWith('/vacations'))
const isSick = computed(() => cleanPath.value.startsWith('/sick-leaves'))
const action = computed(() => cleanPath.value.endsWith('/create') ? 'create' : cleanPath.value.endsWith('/edit') ? 'edit' : cleanPath.value.split('/').filter(Boolean).length > 1 ? 'show' : 'index')
const month = ref(new URLSearchParams(props.path.split('?')[1] ?? '').get('month') ?? isoMonth())
const reviewMode = ref<'approved' | 'rejected' | null>(null)
const reviewNote = ref('')

watch(() => props.path, () => {
    month.value = new URLSearchParams(props.path.split('?')[1] ?? '').get('month') ?? isoMonth()
    reviewMode.value = null
})

const vacationId = computed(() => Number(cleanPath.value.split('/').filter(Boolean)[1]))
const vacation = computed(() => demo.vacations.find((item) => item.id === vacationId.value))
const vacationForm = reactive({ userId: 1, startDate: isoDate(), endDate: isoDate(), reason: '' })

const visibleVacations = computed(() => demo.vacations.filter((item) => item.startDate.slice(0, 7) <= month.value && item.endDate.slice(0, 7) >= month.value))

function submitVacation(): void {
    if (!vacationForm.startDate || !vacationForm.endDate || vacationForm.endDate < vacationForm.startDate) {
        showToast('Das Enddatum muss am oder nach dem Startdatum liegen.', 'danger', 'Validierungsfehler')
        return
    }
    const item = saveVacation(vacationForm)
    navigate(`/vacations/${item.id}`)
    showToast('Urlaubsantrag wurde gespeichert.')
}

function reviewCurrentVacation(): void {
    if (!vacation.value || !reviewMode.value) return
    reviewVacation(vacation.value.id, reviewMode.value, reviewNote.value)
    showToast(reviewMode.value === 'approved' ? 'Urlaubsantrag wurde genehmigt.' : 'Urlaubsantrag wurde abgelehnt.', reviewMode.value === 'approved' ? 'success' : 'warning')
    reviewMode.value = null
    reviewNote.value = ''
}

function vacationDaysFor(userId: number, year: number, status: 'approved' | 'pending'): number {
    return demo.vacations.filter((item) => item.userId === userId && item.startDate.startsWith(String(year)) && item.status === status).reduce((sum, item) => sum + item.days, 0)
}

const sickKey = computed(() => cleanPath.value.split('/').filter(Boolean)[1] ?? '')
const sickLeave = computed(() => demo.sickLeaves.find((item) => item.key === sickKey.value))
const sickForm = reactive({ userId: 2, startDate: isoDate(), endDate: isoDate(), note: '' })

watch([sickLeave, action], ([item, currentAction]) => {
    if (item && currentAction === 'edit') Object.assign(sickForm, { userId: item.userId, startDate: item.startDate, endDate: item.endDate, note: item.note })
}, { immediate: true })

const visibleSickLeaves = computed(() => demo.sickLeaves.filter((item) => item.startDate.slice(0, 7) <= month.value && item.endDate.slice(0, 7) >= month.value))

function submitSickLeave(): void {
    if (!sickForm.startDate || !sickForm.endDate || sickForm.endDate < sickForm.startDate) {
        showToast('Das Enddatum muss am oder nach dem Startdatum liegen.', 'danger', 'Validierungsfehler')
        return
    }
    const item = saveSickLeave(sickForm, action.value === 'edit' ? sickKey.value : undefined)
    navigate(`/sick-leaves/${item.key}`)
    showToast(action.value === 'edit' ? 'Krankmeldung wurde aktualisiert.' : 'Krankmeldung wurde gespeichert.')
}

function removeSickLeave(): void {
    if (!sickLeave.value) return
    deleteSickLeave(sickLeave.value.key)
    navigate('/sick-leaves')
    showToast('Krankmeldung wurde gelöscht.', 'info', 'Krankmeldung')
}

function exportSick(kind: string): void {
    downloadCsv(`krankmeldungen-${month.value}-${kind}.csv`, [
        ['Mitarbeiter', 'Von', 'Bis', 'Tage', 'Notiz'],
        ...visibleSickLeaves.value.map((item) => [demo.users.find((user) => user.id === item.userId)?.name ?? '-', item.startDate, item.endDate, item.days.length, item.note]),
    ])
    showToast('Der Export wurde mit synthetischen Demodaten erstellt.')
}
</script>

<template>
    <template v-if="isVacation">
        <template v-if="action === 'index'">
            <header class="page-header"><div class="page-header-copy"><h2>Urlaubsanträge</h2></div><div class="page-header-actions"><a class="btn" href="#/vacations/create">Urlaub beantragen</a></div></header>
            <section class="data-table-shell data-table-tall"><div class="data-table-header"><h3>Urlaubsanträge</h3><div class="data-table-actions"><form class="table-filter" @submit.prevent><label for="vacation-month" class="visually-hidden">Monat</label><input id="vacation-month" v-model="month" type="month" @change="navigate(`/vacations?month=${month}`)"><button type="button" class="table-filter-reset" @click="month = isoMonth(); navigate(`/vacations?month=${month}`)">Aktueller Monat</button></form></div></div><div class="data-table-scroll"><table><thead><tr><th>Mitarbeiter</th><th>Von</th><th>Bis</th><th>Tage</th><th>Status</th><th>Beantragt von</th><th>Erstellt</th><th></th></tr></thead><tbody>
                <tr v-for="item in visibleVacations" :key="item.id"><td>{{ demo.users.find((user) => user.id === item.userId)?.name ?? '-' }}</td><td>{{ formatDate(item.startDate) }}</td><td>{{ formatDate(item.endDate) }}</td><td>{{ item.days }}</td><td><StatusBadge :label="requestLabel(item.status)" :tone="requestTone(item.status)" /></td><td>{{ item.requestedBy }}</td><td>{{ formatDateTime(item.createdAt) }}</td><td><a class="btn btn-secondary" :href="`#/vacations/${item.id}`">Öffnen</a></td></tr>
                <tr v-if="!visibleVacations.length"><td colspan="8">Keine Urlaubsanträge vorhanden.</td></tr>
            </tbody></table></div></section>
        </template>

        <div v-else-if="action === 'create'" class="card">
            <h2>Urlaubsantrag anlegen</h2><form @submit.prevent="submitVacation">
                <div class="mb-3"><label>Mitarbeiter</label><select v-model.number="vacationForm.userId"><option :value="1">Ich selbst</option><option v-for="user in demo.users.filter((item) => item.id !== 1)" :key="user.id" :value="user.id">{{ user.name }}</option></select></div>
                <div class="grid"><div><label>Startdatum</label><input v-model="vacationForm.startDate" type="date" required></div><div><label>Enddatum</label><input v-model="vacationForm.endDate" type="date" required></div></div>
                <div class="mb-3"><label>Begründung / Notiz</label><textarea v-model="vacationForm.reason" rows="3"></textarea></div>
                <button class="btn btn-success">Urlaubsantrag speichern</button> <a class="btn btn-secondary" href="#/vacations">Abbrechen</a>
            </form>
        </div>

        <template v-else-if="vacation">
            <header class="page-header"><div class="page-header-copy"><h2>Urlaubsantrag #{{ vacation.id }}</h2></div><div class="page-header-actions"><a class="btn btn-secondary" href="#/vacations">Zur Liste</a><button class="btn btn-success" :disabled="vacation.status !== 'pending'" @click="reviewMode = 'approved'">Genehmigen</button><button class="btn btn-danger" :disabled="vacation.status !== 'pending'" @click="reviewMode = 'rejected'">Ablehnen</button></div></header>
            <div class="metric-grid">
                <div class="metric-card"><span class="metric-label">Status</span><span class="metric-value"><StatusBadge :label="requestLabel(vacation.status)" :tone="requestTone(vacation.status)" /></span></div>
                <div class="metric-card"><span class="metric-label">Zeitraum</span><span class="metric-value demo-metric-date">{{ formatDate(vacation.startDate) }} - {{ formatDate(vacation.endDate) }}</span><span class="metric-meta">{{ vacation.days }} Tage</span></div>
                <div class="metric-card"><span class="metric-label">Beantragt von</span><span class="metric-value demo-metric-date">{{ vacation.requestedBy }}</span><span class="metric-meta">{{ formatDateTime(vacation.createdAt) }}</span></div>
            </div>
            <div class="split-layout">
                <section class="ui-card"><div class="ui-card-header"><h3>Antragsdetails</h3></div><div class="ui-card-body"><div class="detail-list"><div class="detail-list-row"><span class="detail-list-label">Mitarbeiter</span><span class="detail-list-value">{{ demo.users.find((user) => user.id === vacation.userId)?.name }}</span></div><div class="detail-list-row"><span class="detail-list-label">Typ</span><span class="detail-list-value">Vacation</span></div><div class="detail-list-row"><span class="detail-list-label">Begründung</span><span class="detail-list-value">{{ vacation.reason || '-' }}</span></div><div v-if="vacation.reviewNote" class="detail-list-row"><span class="detail-list-label">Review-Notiz</span><span class="detail-list-value">{{ vacation.reviewNote }}</span></div></div></div></section>
                <section class="ui-card"><div class="ui-card-header"><h3>Urlaubskonto {{ vacation.startDate.slice(0, 4) }}</h3></div><div class="ui-card-body"><div class="detail-list"><div class="detail-list-row"><span class="detail-list-label">Verfügbar</span><span class="detail-list-value">30 Tage</span></div><div class="detail-list-row"><span class="detail-list-label">Offene Anträge</span><span class="detail-list-value">{{ vacationDaysFor(vacation.userId, Number(vacation.startDate.slice(0, 4)), 'pending') }} Tage</span></div><div class="detail-list-row"><span class="detail-list-label">Verbraucht</span><span class="detail-list-value">{{ vacationDaysFor(vacation.userId, Number(vacation.startDate.slice(0, 4)), 'approved') }} Tage</span></div><div class="detail-list-row"><span class="detail-list-label">Rest</span><span class="detail-list-value">{{ 30 - vacationDaysFor(vacation.userId, Number(vacation.startDate.slice(0, 4)), 'approved') }} Tage</span></div></div></div></section>
            </div>
            <section v-if="vacation.status === 'approved'" class="data-table-shell"><div class="data-table-header"><h3>Genehmigte Urlaubstage</h3></div><div class="data-table-scroll"><table><thead><tr><th>Datum</th></tr></thead><tbody><tr v-for="date in dateRange(vacation.startDate, vacation.endDate)" :key="date"><td>{{ formatDate(date) }}</td></tr></tbody></table></div></section>
            <div class="card"><h3>Stornieren</h3><button class="btn btn-secondary" :disabled="vacation.status !== 'pending'" @click="reviewVacation(vacation.id, 'cancelled'); showToast('Urlaubsantrag wurde storniert.', 'info')">Stornieren</button></div>
            <div v-if="reviewMode" class="demo-modal-backdrop" @click.self="reviewMode = null"><form class="card demo-review-popover" @submit.prevent="reviewCurrentVacation"><div class="demo-heading-row"><h3>Urlaubsantrag {{ reviewMode === 'approved' ? 'genehmigen' : 'ablehnen' }}</h3><button type="button" class="btn btn-ghost" @click="reviewMode = null">×</button></div><label>Review-Notiz</label><textarea v-model="reviewNote" rows="3" autofocus></textarea><div class="demo-form-actions"><button type="button" class="btn btn-secondary" @click="reviewMode = null">Abbrechen</button><button :class="['btn', reviewMode === 'approved' ? 'btn-success' : 'btn-danger']">{{ reviewMode === 'approved' ? 'Genehmigen' : 'Ablehnen' }}</button></div></form></div>
        </template>
        <div v-else class="card"><h2>Urlaubsantrag nicht gefunden</h2><a class="btn btn-secondary" href="#/vacations">Zur Liste</a></div>
    </template>

    <template v-else-if="isSick">
        <template v-if="action === 'index'">
            <header class="page-header"><div class="page-header-copy"><h2>Krankmeldungen</h2></div><div class="page-header-actions"><a class="btn" href="#/sick-leaves/create">Krankmeldung erfassen</a><button class="btn btn-secondary" @click="exportSick('csv')">CSV Export</button><button class="btn btn-secondary" @click="exportSick('excel')">Excel Export</button></div></header>
            <div class="metric-grid"><div class="metric-card"><span class="metric-label">Krankmeldungen im Monat</span><span class="metric-value">{{ visibleSickLeaves.length }}</span><span class="metric-meta">{{ month }}</span></div><div class="metric-card"><span class="metric-label">Krankmeldungen gesamt</span><span class="metric-value">{{ demo.sickLeaves.length }}</span></div></div>
            <section class="data-table-shell data-table-tall"><div class="data-table-header"><h3>Krankmeldungsserien</h3><div class="data-table-actions"><form class="table-filter" @submit.prevent><label class="visually-hidden">Monat</label><input v-model="month" type="month" @change="navigate(`/sick-leaves?month=${month}`)"><button type="button" class="table-filter-reset" @click="month = isoMonth(); navigate(`/sick-leaves?month=${month}`)">Aktueller Monat</button></form></div></div><div class="data-table-scroll"><table><thead><tr><th>Mitarbeiter</th><th>Von</th><th>Bis</th><th>Tage</th><th>Notiz</th><th>Erfasst von</th><th></th></tr></thead><tbody>
                <tr v-for="item in visibleSickLeaves" :key="item.key"><td>{{ demo.users.find((user) => user.id === item.userId)?.name }}</td><td>{{ formatDate(item.startDate) }}</td><td>{{ formatDate(item.endDate) }}</td><td>{{ item.days.length }}</td><td>{{ item.note || '-' }}</td><td>{{ item.recordedBy }}</td><td><a class="btn btn-secondary" :href="`#/sick-leaves/${item.key}`">Öffnen</a></td></tr><tr v-if="!visibleSickLeaves.length"><td colspan="7">Keine Krankmeldungen vorhanden.</td></tr>
            </tbody></table></div></section>
        </template>

        <div v-else-if="action === 'create' || (action === 'edit' && sickLeave)" class="card">
            <h2>{{ action === 'edit' ? 'Krankmeldung bearbeiten' : 'Krankmeldung erfassen' }}</h2><form @submit.prevent="submitSickLeave">
                <div class="mb-3"><label>Mitarbeiter</label><input v-if="action === 'edit'" :value="demo.users.find((user) => user.id === sickForm.userId)?.name" disabled><select v-else v-model.number="sickForm.userId" required><option disabled value="">Bitte auswählen</option><option v-for="user in demo.users" :key="user.id" :value="user.id">{{ user.name }}</option></select></div>
                <div class="grid"><div><label>Startdatum</label><input v-model="sickForm.startDate" type="date" required></div><div><label>Enddatum</label><input v-model="sickForm.endDate" type="date" required></div></div>
                <div class="mb-3"><label>Notiz</label><textarea v-model="sickForm.note" rows="3"></textarea></div>
                <button class="btn btn-success">{{ action === 'edit' ? 'Aktualisieren' : 'Speichern' }}</button> <a class="btn btn-secondary" :href="action === 'edit' ? `#/sick-leaves/${sickKey}` : '#/sick-leaves'">Abbrechen</a>
            </form>
        </div>

        <template v-else-if="sickLeave">
            <header class="page-header"><div class="page-header-copy"><h2>Krankmeldung {{ sickLeave.key }}</h2></div><div class="page-header-actions"><a class="btn btn-secondary" href="#/sick-leaves">Zur Liste</a><a class="btn btn-ghost" :href="`#/sick-leaves/${sickLeave.key}/edit`">Bearbeiten</a></div></header>
            <div class="metric-grid"><div class="metric-card"><span class="metric-label">Mitarbeiter</span><span class="metric-value demo-metric-date">{{ demo.users.find((user) => user.id === sickLeave.userId)?.name }}</span></div><div class="metric-card"><span class="metric-label">Zeitraum</span><span class="metric-value demo-metric-date">{{ formatDate(sickLeave.startDate) }} - {{ formatDate(sickLeave.endDate) }}</span><span class="metric-meta">{{ dateRange(sickLeave.startDate, sickLeave.endDate).length }} Kalendertage</span></div><div class="metric-card"><span class="metric-label">Krankheitstage</span><span class="metric-value">{{ sickLeave.days.length }}</span></div></div>
            <div class="split-layout"><section class="ui-card"><div class="ui-card-header"><h3>Krankmeldungsdetails</h3></div><div class="ui-card-body"><div class="detail-list"><div class="detail-list-row"><span class="detail-list-label">Notiz</span><span class="detail-list-value">{{ sickLeave.note || '-' }}</span></div><div class="detail-list-row"><span class="detail-list-label">Erfasst von</span><span class="detail-list-value">{{ sickLeave.recordedBy }}</span></div><div class="detail-list-row"><span class="detail-list-label">Erfasst am</span><span class="detail-list-value">{{ formatDateTime(sickLeave.createdAt) }}</span></div></div></div></section><section class="data-table-shell"><div class="data-table-header"><h3>Krankheitstage</h3></div><div class="data-table-scroll"><table><thead><tr><th>Datum</th></tr></thead><tbody><tr v-for="date in sickLeave.days" :key="date"><td>{{ formatDate(date) }}</td></tr></tbody></table></div></section></div>
            <div class="card"><h3>Aktionen</h3><button class="btn btn-danger" @click="removeSickLeave">Loeschen</button></div>
        </template>
        <div v-else class="card"><h2>Krankmeldung nicht gefunden</h2><a class="btn btn-secondary" href="#/sick-leaves">Zur Liste</a></div>
    </template>
</template>
