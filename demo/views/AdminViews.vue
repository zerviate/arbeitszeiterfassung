<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import StatusBadge from '../components/StatusBadge.vue'
import {
    deleteHoliday,
    demo,
    downloadCsv,
    finalizeWorkday,
    formatDate,
    formatDateTime,
    formatMinutes,
    getWorkday,
    isoDate,
    isoMonth,
    minutesBetween,
    saveBalance,
    saveContract,
    saveHoliday,
    toggleHoliday,
    userName,
    workdayMinutes,
    type Tone,
    type Workday,
} from '../store'
import { navigate } from '../router'
import { showToast } from '../toast'

const props = defineProps<{ path: string }>()
const cleanPath = computed(() => props.path.split('?')[0])
const query = computed(() => new URLSearchParams(props.path.split('?')[1] ?? ''))
const section = computed(() => cleanPath.value.split('/').filter(Boolean)[0] ?? 'management')
const action = computed(() => cleanPath.value.endsWith('/create') ? 'create' : cleanPath.value.endsWith('/edit') ? 'edit' : 'index')

function toneForLight(light: Workday['traffic']): Tone {
    return { green: 'success', yellow: 'warning', red: 'danger', gray: 'muted' }[light] as Tone
}

function trafficLabel(light: Workday['traffic']): string {
    return { green: 'Grün', yellow: 'Gelb', red: 'Rot', gray: 'Grau' }[light]
}

function gross(day?: Workday): number {
    return day?.sessions.reduce((sum, item) => sum + minutesBetween(item.start, item.end), 0) ?? 0
}

function breaks(day?: Workday): number {
    return gross(day) - (day ? workdayMinutes(day) : 0)
}

function targetForUserDate(userId: number, date: string): number {
    const weekday = new Date(`${date}T12:00:00`).getDay()
    const holiday = demo.holidays.some((item) => item.active && item.date === date)
    const contract = demo.contracts.find((item) => item.userId === userId
        && item.active
        && item.validFrom <= date
        && (!item.validTo || item.validTo >= date)
        && item.weekdays.includes(weekday))
    return holiday || !contract ? 0 : Math.round(contract.weeklyMinutes / contract.weekdays.length)
}

function teamEvaluation(userId: number, date: string) {
    const day = getWorkday(date, userId)
    const target = day?.targetMinutes ?? targetForUserDate(userId, date)
    const net = day ? workdayMinutes(day) : 0
    const vacation = demo.vacations.some((item) => item.userId === userId && item.status === 'approved' && item.startDate <= date && item.endDate >= date)
    const sick = demo.sickLeaves.some((item) => item.userId === userId && item.startDate <= date && item.endDate >= date)
    const credit = vacation || sick ? target : 0
    const balance = net + credit - target
    const status = day?.status ?? (vacation ? 'Urlaub' : sick ? 'Krank' : target > 0 ? 'Unter Soll' : 'Freier Tag')
    const light: Workday['traffic'] = day?.traffic ?? (vacation || sick ? 'green' : target > 0 ? 'red' : 'gray')
    return { day, target, net, balance, status, light }
}

// Team time
const teamDate = ref(query.value.get('date') ?? isoDate())
const teamParts = computed(() => cleanPath.value.split('/').filter(Boolean))
const teamDetail = computed(() => section.value === 'management' && teamParts.value.length >= 4)
const teamUserId = computed(() => Number(teamParts.value[2] ?? 1))
const teamDetailDate = computed(() => teamParts.value[3] ?? teamDate.value)
const teamDay = computed(() => getWorkday(teamDetailDate.value, teamUserId.value))
const teamResult = computed(() => teamEvaluation(teamUserId.value, teamDetailDate.value))

function exportTeam(kind: string): void {
    downloadCsv(`${kind}-${teamDate.value}.csv`, [
        ['Mitarbeiter', 'Brutto', 'Pausen', 'Netto', 'Soll', 'Saldo', 'Status'],
        ...demo.users.map((user) => {
            const result = teamEvaluation(user.id, teamDate.value)
            return [user.name, gross(result.day), breaks(result.day), result.net, result.target, result.balance, result.status]
        }),
    ])
    showToast('Der Export wurde mit synthetischen Demodaten erstellt.')
}

function finalize(): void {
    if (finalizeWorkday(teamDetailDate.value, teamUserId.value)) showToast('Tag wurde finalisiert.')
    else showToast('Ein Tag mit offener Session kann nicht finalisiert werden.', 'danger', 'Finalisierung fehlgeschlagen')
}

// Contracts
const contractId = computed(() => Number(cleanPath.value.split('/').filter(Boolean)[1]))
const contract = computed(() => demo.contracts.find((item) => item.id === contractId.value))
const contractForm = reactive({ userId: 1, weeklyHours: 40, validFrom: isoDate(), validTo: '', weekdays: [1, 2, 3, 4, 5] as number[], active: true })

watch(contract, (item) => {
    if (item && action.value === 'edit') Object.assign(contractForm, { userId: item.userId, weeklyHours: item.weeklyMinutes / 60, validFrom: item.validFrom, validTo: item.validTo ?? '', weekdays: [...item.weekdays], active: item.active })
}, { immediate: true })

function submitContract(): void {
    if (!contractForm.validFrom || contractForm.weeklyHours <= 0 || !contractForm.weekdays.length || (contractForm.validTo && contractForm.validTo < contractForm.validFrom)) {
        showToast('Bitte überprüfe die Vertragsdaten.', 'danger', 'Validierungsfehler')
        return
    }
    saveContract({ userId: contractForm.userId, weeklyMinutes: Math.round(contractForm.weeklyHours * 60), validFrom: contractForm.validFrom, validTo: contractForm.validTo || null, weekdays: [...contractForm.weekdays], active: contractForm.active }, action.value === 'edit' ? contractId.value : undefined)
    navigate('/contracts')
    showToast(action.value === 'edit' ? 'Vertrag wurde aktualisiert.' : 'Vertrag wurde angelegt.')
}

const weekdayNames = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag']

// Holidays
const holidayMonth = ref(query.value.get('month') ?? isoMonth())
const holidayId = computed(() => Number(cleanPath.value.split('/').filter(Boolean)[1]))
const holiday = computed(() => demo.holidays.find((item) => item.id === holidayId.value))
const holidayForm = reactive({ date: query.value.get('date') ?? isoDate(), name: '', active: true })

watch(holiday, (item) => {
    if (item && action.value === 'edit') Object.assign(holidayForm, { date: item.date, name: item.name, active: item.active })
}, { immediate: true })

const holidayMonthLabel = computed(() => {
    const [year, month] = holidayMonth.value.split('-').map(Number)
    return new Intl.DateTimeFormat('de-DE', { month: 'long', year: 'numeric' }).format(new Date(year, month - 1, 1))
})

const holidayCalendar = computed(() => {
    const [year, month] = holidayMonth.value.split('-').map(Number)
    const first = new Date(year, month - 1, 1)
    const count = new Date(year, month, 0).getDate()
    const values: Array<{ blank?: boolean; day?: number; date?: string; holiday?: (typeof demo.holidays)[number] }> = Array.from({ length: (first.getDay() + 6) % 7 }, () => ({ blank: true }))
    for (let day = 1; day <= count; day += 1) {
        const date = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`
        values.push({ day, date, holiday: demo.holidays.find((item) => item.date === date) })
    }
    return values
})

function openHolidayDate(date: string): void {
    if (window.confirm('Feiertag eintragen?')) navigate(`/holidays/create?date=${date}`)
}

function submitHoliday(): void {
    if (!holidayForm.date || !holidayForm.name.trim()) {
        showToast('Datum und Bezeichnung sind erforderlich.', 'danger', 'Validierungsfehler')
        return
    }
    saveHoliday({ ...holidayForm }, action.value === 'edit' ? holidayId.value : undefined)
    navigate(`/holidays?month=${holidayForm.date.slice(0, 7)}`)
    showToast(action.value === 'edit' ? 'Feiertag wurde aktualisiert.' : 'Feiertag wurde angelegt.')
}

function removeHoliday(id: number): void {
    if (!window.confirm('Feiertag wirklich löschen?')) return
    deleteHoliday(id)
    showToast('Feiertag wurde gelöscht.', 'info', 'Feiertag')
}

// Vacation balances
const balanceYear = ref(Number(query.value.get('year') ?? new Date().getFullYear()))
const balanceId = computed(() => Number(cleanPath.value.split('/').filter(Boolean)[1]))
const balance = computed(() => demo.balances.find((item) => item.id === balanceId.value))
const balanceForm = reactive({ userId: Number(query.value.get('user_id') ?? 1), year: Number(query.value.get('year') ?? new Date().getFullYear()), entitlement: 30, carryover: 0, adjustment: 0, note: '' })

watch(balance, (item) => {
    if (item && action.value === 'edit') Object.assign(balanceForm, { userId: item.userId, year: item.year, entitlement: item.entitlement, carryover: item.carryover, adjustment: item.adjustment, note: item.note })
}, { immediate: true })

const balanceYears = computed(() => Array.from({ length: 11 }, (_, index) => balanceYear.value - 5 + index))

watch(() => props.path, () => {
    if (section.value === 'management' && !teamDetail.value) {
        teamDate.value = query.value.get('date') ?? isoDate()
    }
    if (section.value === 'contracts' && action.value === 'create') {
        Object.assign(contractForm, { userId: 1, weeklyHours: 40, validFrom: isoDate(), validTo: '', weekdays: [1, 2, 3, 4, 5], active: true })
    }
    if (section.value === 'holidays' && action.value === 'index') {
        holidayMonth.value = query.value.get('month') ?? isoMonth()
    }
    if (section.value === 'holidays' && action.value === 'create') {
        Object.assign(holidayForm, { date: query.value.get('date') ?? isoDate(), name: '', active: true })
    }
    if (section.value === 'vacation-balances' && action.value === 'index') {
        balanceYear.value = Number(query.value.get('year') ?? new Date().getFullYear())
    }
    if (section.value === 'vacation-balances' && action.value === 'create') {
        Object.assign(balanceForm, {
            userId: Number(query.value.get('user_id') ?? 1),
            year: Number(query.value.get('year') ?? new Date().getFullYear()),
            entitlement: 30,
            carryover: 0,
            adjustment: 0,
            note: '',
        })
    }
}, { immediate: true })

function usedVacation(userId: number, year: number, status: 'approved' | 'pending'): number {
    return demo.vacations.filter((item) => item.userId === userId && item.status === status && item.startDate.startsWith(String(year))).reduce((sum, item) => sum + item.days, 0)
}

function availableBalance(userId: number, year: number): number {
    const account = demo.balances.find((item) => item.userId === userId && item.year === year)
    return account ? account.entitlement + account.carryover + account.adjustment : 0
}

function submitBalance(): void {
    saveBalance({ ...balanceForm }, action.value === 'edit' ? balanceId.value : undefined)
    navigate(`/vacation-balances?year=${balanceForm.year}`)
    showToast(action.value === 'edit' ? 'Urlaubskonto wurde aktualisiert.' : 'Urlaubskonto wurde angelegt.')
}
</script>

<template>
    <template v-if="section === 'management'">
        <template v-if="!teamDetail">
            <div class="card">
                <h2>Verwaltung - Tagesübersicht {{ formatDate(teamDate) }}</h2>
                <form class="demo-inline-form" @submit.prevent="navigate(`/management/time?date=${teamDate}`)"><label>Datum</label><input v-model="teamDate" type="date"><button class="btn">Filtern</button><button type="button" class="btn btn-secondary" @click="teamDate = isoDate(); navigate(`/management/time?date=${teamDate}`)">Reset</button></form>
                <div class="demo-export-row"><button class="btn btn-secondary" @click="exportTeam('teamzeiten-csv')">CSV Export</button><button class="btn btn-secondary" @click="exportTeam('teamzeiten-excel')">Excel Export</button><button class="btn btn-secondary" @click="exportTeam('compliance-csv')">Compliance CSV</button><button class="btn btn-secondary" @click="exportTeam('compliance-excel')">Compliance Excel</button></div>
                <div class="data-table-scroll"><table><thead><tr><th>Mitarbeiter</th><th>Brutto</th><th>Pausen</th><th>Netto</th><th>Soll</th><th>Saldo</th><th>Status</th><th class="visually-hidden">Ampel</th><th></th></tr></thead><tbody>
                    <tr v-for="user in demo.users" :key="user.id"><td>{{ user.name }}</td><td>{{ formatMinutes(gross(teamEvaluation(user.id, teamDate).day)) }}</td><td>{{ formatMinutes(breaks(teamEvaluation(user.id, teamDate).day)) }}</td><td>{{ formatMinutes(teamEvaluation(user.id, teamDate).net) }}</td><td>{{ formatMinutes(teamEvaluation(user.id, teamDate).target) }}</td><td>{{ formatMinutes(teamEvaluation(user.id, teamDate).balance, true) }}</td><td><StatusBadge :label="teamEvaluation(user.id, teamDate).status" :tone="toneForLight(teamEvaluation(user.id, teamDate).light)" /></td><td><StatusBadge :label="trafficLabel(teamEvaluation(user.id, teamDate).light)" :tone="toneForLight(teamEvaluation(user.id, teamDate).light)" /></td><td><a class="btn btn-secondary" :href="`#/management/time/${user.id}/${teamDate}`">Öffnen</a></td></tr>
                </tbody></table></div>
            </div>
        </template>
        <template v-else>
            <div class="card"><h2>{{ userName(teamUserId) }} - {{ formatDate(teamDetailDate) }}</h2>
                <p><strong>Brutto:</strong> {{ formatMinutes(gross(teamDay)) }}</p><p><strong>Pausen:</strong> {{ formatMinutes(breaks(teamDay)) }}</p><p><strong>Netto:</strong> {{ formatMinutes(teamResult.net) }}</p><p><strong>Überzeit:</strong> {{ formatMinutes(teamResult.balance, true) }}</p><p><strong>Soll:</strong> {{ formatMinutes(teamResult.target) }}</p><p><strong>Saldo:</strong> {{ formatMinutes(teamResult.balance, true) }}</p><p><strong>Status:</strong> <StatusBadge :label="teamResult.status" :tone="toneForLight(teamResult.light)" /> <StatusBadge :label="trafficLabel(teamResult.light)" :tone="toneForLight(teamResult.light)" /></p><div v-if="teamDay" class="inline-badge-list"><StatusBadge v-if="teamDay.finalized" label="Finalisiert" tone="success"/><StatusBadge v-if="teamDay.sessions.some((item) => !item.end)" label="Offen" tone="warning"/></div><button v-if="teamDay && !teamDay.finalized" class="btn btn-success demo-section-button" @click="finalize">Tag finalisieren</button>
            </div>
            <section class="data-table-shell"><div class="data-table-header"><h3>Sessions</h3></div><div class="data-table-scroll"><table><thead><tr><th>Beginn</th><th>Ende</th><th>Brutto</th><th>Pausen</th></tr></thead><tbody><tr v-for="entry in teamDay?.sessions ?? []" :key="entry.start"><td>{{ formatDateTime(entry.start) }}</td><td>{{ entry.end ? formatDateTime(entry.end) : '-' }}</td><td>{{ formatMinutes(minutesBetween(entry.start, entry.end)) }}</td><td><div v-for="pause in entry.breaks" :key="pause.start">{{ formatDateTime(pause.start) }} - {{ pause.end ? formatDateTime(pause.end) : '-' }} ({{ formatMinutes(minutesBetween(pause.start, pause.end)) }})</div><span v-if="!entry.breaks.length">Keine</span></td></tr><tr v-if="!teamDay?.sessions.length"><td colspan="4">Keine Sessions vorhanden.</td></tr></tbody></table></div></section>
        </template>
    </template>

    <template v-else-if="section === 'contracts'">
        <div v-if="action === 'index'" class="card"><div class="demo-heading-row"><h2>Verträge</h2><a class="btn" href="#/contracts/create">Vertrag anlegen</a></div><table><thead><tr><th>Mitarbeiter</th><th>Wochenzeit</th><th>Arbeitstage</th><th>Tages-Soll</th><th>Gültig von</th><th>Gültig bis</th><th>Aktiv</th><th></th></tr></thead><tbody><tr v-for="item in demo.contracts" :key="item.id"><td>{{ userName(item.userId) }}</td><td>{{ formatMinutes(item.weeklyMinutes) }}</td><td>{{ item.weekdays.length }}</td><td>{{ formatMinutes(item.weeklyMinutes / item.weekdays.length) }}</td><td>{{ formatDate(item.validFrom) }}</td><td>{{ item.validTo ? formatDate(item.validTo) : 'offen' }}</td><td><StatusBadge :label="item.active ? 'Aktiv' : 'Inaktiv'" :tone="item.active ? 'success' : 'muted'" /></td><td><a class="btn btn-secondary" :href="`#/contracts/${item.id}/edit`">Bearbeiten</a></td></tr><tr v-if="!demo.contracts.length"><td colspan="8">Keine Verträge vorhanden.</td></tr></tbody></table></div>
        <div v-else class="card"><h2>{{ action === 'edit' ? 'Vertrag bearbeiten' : 'Vertrag anlegen' }}</h2><form @submit.prevent="submitContract"><div class="mb-3"><label>Mitarbeiter</label><input v-if="action === 'edit'" :value="userName(contractForm.userId)" disabled><select v-else v-model.number="contractForm.userId" required><option disabled value="">Bitte auswählen</option><option v-for="user in demo.users" :key="user.id" :value="user.id">{{ user.name }}</option></select></div><div class="grid"><div><label>Wochenstunden</label><input v-model.number="contractForm.weeklyHours" type="number" step="0.25" min="1" max="168" required><small class="text-muted">{{ Math.round(contractForm.weeklyHours * 60) }} Minuten</small></div><div><label>Gültig von</label><input v-model="contractForm.validFrom" type="date" required></div><div><label>Gültig bis</label><input v-model="contractForm.validTo" type="date"></div></div><div class="card"><h3>Arbeitstage</h3><label v-for="(name, index) in weekdayNames" :key="name" class="inline-checkbox demo-weekday"><input v-model="contractForm.weekdays" type="checkbox" :value="index + 1"> {{ name }}</label></div><label class="inline-checkbox"><input v-model="contractForm.active" type="checkbox"> Aktiv</label><div class="demo-form-actions"><button class="btn btn-success">{{ action === 'edit' ? 'Aktualisieren' : 'Speichern' }}</button><a class="btn btn-secondary" href="#/contracts">Abbrechen</a></div></form></div>
    </template>

    <template v-else-if="section === 'holidays'">
        <template v-if="action === 'index'">
            <header class="page-header"><div class="page-header-copy"><h2>Feiertage · {{ holidayMonthLabel }}</h2><form class="header-switcher" @submit.prevent><input v-model="holidayMonth" type="month" @change="navigate(`/holidays?month=${holidayMonth}`)"></form></div><div class="page-header-actions"><a class="btn btn-secondary" href="#/holidays/create">Feiertag anlegen</a></div></header>
            <div class="holiday-calendar"><section class="holiday-month"><div class="holiday-month-header"><h3 class="holiday-month-title">{{ holidayMonthLabel }}</h3><span class="holiday-month-meta">{{ holidayMonth.split('-').reverse().join('.') }}</span></div><div class="holiday-grid"><div v-for="(cell, index) in holidayCalendar" :key="cell.date ?? index" :class="['holiday-day', { 'holiday-day-empty': cell.blank, 'holiday-active': cell.holiday?.active, 'holiday-inactive': cell.holiday && !cell.holiday.active, 'holiday-day-editable': cell.holiday }]">
                <template v-if="!cell.blank && !cell.holiday"><button type="button" class="holiday-day-create" @click="openHolidayDate(cell.date!)"><span class="holiday-day-number">{{ cell.day }}</span></button></template>
                <template v-else-if="cell.holiday"><button type="button" class="demo-holiday-main" @click="navigate(`/holidays/${cell.holiday.id}/edit`)"><strong class="holiday-day-number">{{ cell.day }}</strong><span class="holiday-day-name">{{ cell.holiday.name }}</span></button><div class="holiday-day-actions"><button class="holiday-toggle" @click.stop="toggleHoliday(cell.holiday.id)">{{ cell.holiday.active ? 'Deaktivieren' : 'Aktivieren' }}</button><a class="holiday-edit" :href="`#/holidays/${cell.holiday.id}/edit`">Bearbeiten</a><button class="holiday-edit" @click.stop="removeHoliday(cell.holiday.id)">Löschen</button></div></template>
            </div></div></section></div>
        </template>
        <div v-else class="card"><h2>{{ action === 'edit' ? 'Feiertag bearbeiten' : 'Feiertag anlegen' }}</h2><form @submit.prevent="submitHoliday"><div class="grid"><div><label>Datum</label><input v-model="holidayForm.date" type="date" required></div><div><label>Bezeichnung</label><input v-model="holidayForm.name" type="text" maxlength="160" required></div></div><label class="inline-checkbox"><input v-model="holidayForm.active" type="checkbox"> Aktiv</label><div class="demo-form-actions"><button class="btn btn-success">{{ action === 'edit' ? 'Aktualisieren' : 'Speichern' }}</button><a class="btn btn-secondary" :href="`#/holidays?month=${holidayForm.date.slice(0, 7)}`">Abbrechen</a></div></form></div>
    </template>

    <template v-else-if="section === 'vacation-balances'">
        <template v-if="action === 'index'">
            <header class="page-header"><div class="page-header-copy"><h2>Urlaubskonten · {{ balanceYear }}</h2><form class="header-switcher"><select v-model.number="balanceYear" @change="navigate(`/vacation-balances?year=${balanceYear}`)"><option v-for="year in balanceYears" :key="year" :value="year">{{ year }}</option></select></form></div><div class="page-header-actions"><a class="btn" :href="`#/vacation-balances/create?year=${balanceYear}`">Urlaubskonto anlegen</a></div></header>
            <section class="ui-card"><div class="ui-card-header"><h3>Urlaubskonten im Jahr {{ balanceYear }}</h3></div><div class="ui-card-body"><div class="balance-grid"><section v-for="user in demo.users" :key="user.id" class="balance-card"><div class="demo-heading-row"><div><h3>{{ user.name }}</h3><span class="text-muted">{{ balanceYear }}</span></div><a v-if="demo.balances.find((item) => item.userId === user.id && item.year === balanceYear)" class="btn btn-secondary" :href="`#/vacation-balances/${demo.balances.find((item) => item.userId === user.id && item.year === balanceYear)?.id}/edit`">Bearbeiten</a><a v-else class="btn btn-secondary" :href="`#/vacation-balances/create?user_id=${user.id}&year=${balanceYear}`">Anlegen</a></div><div class="balance-stats"><div><span>Verfügbar</span><strong>{{ availableBalance(user.id, balanceYear).toFixed(2) }}</strong></div><div><span>Offen</span><strong>{{ usedVacation(user.id, balanceYear, 'pending').toFixed(2) }}</strong></div><div><span>Genehmigt</span><strong>{{ usedVacation(user.id, balanceYear, 'approved').toFixed(2) }}</strong></div><div><span>Rest</span><strong>{{ (availableBalance(user.id, balanceYear) - usedVacation(user.id, balanceYear, 'approved')).toFixed(2) }}</strong></div></div></section></div></div></section>
        </template>
        <div v-else class="card"><h2>{{ action === 'edit' ? 'Urlaubskonto bearbeiten' : 'Urlaubskonto anlegen' }}</h2><form @submit.prevent="submitBalance"><div class="mb-3"><label>Mitarbeiter</label><input v-if="action === 'edit'" :value="userName(balanceForm.userId)" disabled><select v-else v-model.number="balanceForm.userId" required><option disabled value="">Bitte auswählen</option><option v-for="user in demo.users" :key="user.id" :value="user.id">{{ user.name }}</option></select></div><div class="grid"><div><label>Jahr</label><input v-model.number="balanceForm.year" type="number" min="2000" max="2100" :disabled="action === 'edit'"></div><div><label>Urlaubsanspruch</label><input v-model.number="balanceForm.entitlement" type="number" step="0.01" min="0" max="366"></div></div><div class="grid"><div><label>Übertrag</label><input v-model.number="balanceForm.carryover" type="number" step="0.01" min="0" max="366"></div><div><label>Manuelle Anpassung</label><input v-model.number="balanceForm.adjustment" type="number" step="0.01" min="-366" max="366"></div></div><div class="mb-3"><label>Notiz</label><textarea v-model="balanceForm.note" rows="3"></textarea></div><div class="demo-form-actions"><button class="btn btn-success">{{ action === 'edit' ? 'Aktualisieren' : 'Speichern' }}</button><a class="btn btn-secondary" :href="`#/vacation-balances?year=${balanceForm.year}`">Abbrechen</a></div></form></div>
    </template>
</template>
