<script setup lang="ts">
import { computed } from 'vue'
import StatusBadge from '../components/StatusBadge.vue'
import { dateRange, demo, formatDate, formatMinutes, getWorkday, isoDate, isoMonth, userName, workdayMinutes, type Tone } from '../store'

const props = defineProps<{ path: string }>()
const parts = computed(() => props.path.split('?')[0].split('/').filter(Boolean))
const userId = computed(() => Number(parts.value[2] ?? 1))
const mode = computed(() => parts.value[3] ?? 'day')
const routeValue = computed(() => parts.value[4] ?? (mode.value === 'month' ? isoMonth() : isoDate()))

interface Evaluation {
    date: string
    target: number
    actual: number
    vacation: number
    sick: number
    balance: number
    status: string
    traffic: 'green' | 'yellow' | 'red' | 'gray'
    holiday: string | null
    flags: string[]
}

function targetForDate(date: string): number {
    const weekday = new Date(`${date}T12:00:00`).getDay()
    const contract = demo.contracts.find((item) => item.userId === userId.value
        && item.active
        && item.validFrom <= date
        && (!item.validTo || item.validTo >= date)
        && item.weekdays.includes(weekday))
    return contract ? Math.round(contract.weeklyMinutes / contract.weekdays.length) : 0
}

function evaluate(date: string): Evaluation {
    const native = new Date(`${date}T12:00:00`)
    const weekend = native.getDay() === 0 || native.getDay() === 6
    const holiday = demo.holidays.find((item) => item.date === date && item.active)?.name ?? null
    const vacation = demo.vacations.some((item) => item.userId === userId.value && item.status === 'approved' && item.startDate <= date && item.endDate >= date)
    const sick = demo.sickLeaves.some((item) => item.userId === userId.value && item.startDate <= date && item.endDate >= date)
    const day = getWorkday(date, userId.value)
    const target = weekend || holiday ? 0 : targetForDate(date)
    const actual = day ? workdayMinutes(day) : 0
    const vacationMinutes = vacation ? target : 0
    const sickMinutes = sick ? target : 0
    const credited = actual + vacationMinutes + sickMinutes
    const balance = credited - target
    let status = day?.status ?? '-'
    let traffic: Evaluation['traffic'] = day?.traffic ?? 'gray'
    if (!day && vacation) { status = 'Urlaub'; traffic = 'green' }
    else if (!day && sick) { status = 'Krank'; traffic = 'green' }
    else if (!day && holiday) { status = 'Feiertag'; traffic = 'gray' }
    else if (!day && weekend) { status = 'Freier Tag'; traffic = 'gray' }
    else if (!day && date <= isoDate()) { status = 'Unter Soll'; traffic = 'red' }
    return { date, target, actual, vacation: vacationMinutes, sick: sickMinutes, balance, status, traffic, holiday, flags: day?.flags ?? [] }
}

function startOfWeek(date: string): Date {
    const value = new Date(`${date}T12:00:00`)
    value.setDate(value.getDate() - ((value.getDay() + 6) % 7))
    return value
}

const evaluations = computed(() => {
    if (mode.value === 'day') return [evaluate(routeValue.value)]
    if (mode.value === 'week') {
        const start = startOfWeek(routeValue.value)
        const end = new Date(start)
        end.setDate(end.getDate() + 6)
        const toIso = (value: Date) => `${value.getFullYear()}-${String(value.getMonth() + 1).padStart(2, '0')}-${String(value.getDate()).padStart(2, '0')}`
        return dateRange(toIso(start), toIso(end)).map(evaluate)
    }
    const [year, month] = routeValue.value.split('-').map(Number)
    const end = new Date(year, month, 0).getDate()
    return Array.from({ length: end }, (_, index) => evaluate(`${year}-${String(month).padStart(2, '0')}-${String(index + 1).padStart(2, '0')}`))
})

const totals = computed(() => evaluations.value.reduce((result, item) => ({
    target: result.target + item.target,
    actual: result.actual + item.actual,
    vacation: result.vacation + item.vacation,
    sick: result.sick + item.sick,
    balance: result.balance + item.balance,
}), { target: 0, actual: 0, vacation: 0, sick: 0, balance: 0 }))

const overallTraffic = computed<Evaluation['traffic']>(() => {
    const values = evaluations.value.map((item) => item.traffic)
    if (values.includes('red')) return 'red'
    if (values.includes('yellow')) return 'yellow'
    if (values.includes('green')) return 'green'
    return 'gray'
})

function badgeTone(value: Evaluation['traffic']): Tone {
    return { green: 'success', yellow: 'warning', red: 'danger', gray: 'muted' }[value] as Tone
}

function trafficLabel(value: Evaluation['traffic']): string {
    return { green: 'Grün', yellow: 'Gelb', red: 'Rot', gray: 'Grau' }[value]
}

function statusTone(status: string): Tone {
    if (['Erfüllt', 'Mehrarbeit'].includes(status)) return 'success'
    if (['Urlaub', 'Krank'].includes(status)) return 'info'
    if (['Unter Soll'].includes(status)) return 'danger'
    if (['Feiertag', 'Freier Tag', '-'].includes(status)) return 'muted'
    return 'warning'
}

const periodLabel = computed(() => {
    if (mode.value === 'day') return `Tagesbewertung · ${userName(userId.value)} · ${formatDate(routeValue.value)}`
    if (mode.value === 'week') return `Wochenbewertung · ${userName(userId.value)} · ${formatDate(evaluations.value[0].date)} – ${formatDate(evaluations.value.at(-1)?.date ?? evaluations.value[0].date)}`
    return `Monatsbewertung · ${userName(userId.value)} · ${routeValue.value.split('-').reverse().join('.')}`
})
</script>

<template>
    <header class="page-header"><div class="page-header-copy"><h2>{{ periodLabel }}</h2></div><div v-if="mode !== 'month'" class="page-header-actions">
        <a v-if="mode === 'day'" class="btn btn-secondary" :href="`#/evaluations/users/${userId}/week/${routeValue}`">Woche</a>
        <a class="btn btn-secondary" :href="`#/evaluations/users/${userId}/month/${routeValue.slice(0, 7)}`">Monat</a>
        <a v-if="mode === 'week'" class="btn btn-secondary" :href="`#/time/month/${routeValue.slice(0, 7)}`">Zeitkonto</a>
    </div></header>

    <div class="metric-grid">
        <div class="metric-card"><span class="metric-label">Soll</span><span class="metric-value">{{ formatMinutes(mode === 'day' ? evaluations[0].target : totals.target) }}</span></div>
        <div class="metric-card"><span class="metric-label">Ist</span><span class="metric-value">{{ formatMinutes(mode === 'day' ? evaluations[0].actual : totals.actual) }}</span></div>
        <div class="metric-card"><span class="metric-label">Urlaub</span><span class="metric-value">{{ formatMinutes(mode === 'day' ? evaluations[0].vacation : totals.vacation) }}</span></div>
        <div class="metric-card"><span class="metric-label">Krank</span><span class="metric-value">{{ formatMinutes(mode === 'day' ? evaluations[0].sick : totals.sick) }}</span></div>
        <div class="metric-card"><span class="metric-label">Saldo</span><span class="metric-value">{{ formatMinutes(mode === 'day' ? evaluations[0].balance : totals.balance, true) }}</span></div>
        <div class="metric-card"><span class="metric-label">Status</span><span class="metric-value"><template v-if="mode === 'day'"><StatusBadge :label="evaluations[0].status" :tone="statusTone(evaluations[0].status)" /> <StatusBadge :label="trafficLabel(evaluations[0].traffic)" :tone="badgeTone(evaluations[0].traffic)" /></template><StatusBadge v-else :label="trafficLabel(overallTraffic)" :tone="badgeTone(overallTraffic)" /></span></div>
    </div>

    <template v-if="mode === 'day'">
        <section v-if="evaluations[0].holiday" class="ui-card"><div class="ui-card-header"><h3>Feiertag</h3></div><div class="ui-card-body"><p>{{ evaluations[0].holiday }}</p></div></section>
        <section v-if="evaluations[0].flags.length" class="ui-card"><div class="ui-card-header"><h3>Hinweise</h3></div><div class="ui-card-body"><div class="inline-badge-list"><StatusBadge v-for="flag in evaluations[0].flags" :key="flag" :label="flag" tone="info" /></div></div></section>
    </template>

    <section v-else class="data-table-shell data-table-tall"><div class="data-table-header"><h3>Tage</h3></div><div class="data-table-scroll"><table><thead><tr><th>Datum</th><th>Status</th><th class="visually-hidden">Ampel</th><th>Soll</th><th>Ist</th><th>Urlaub</th><th>Krank</th><th>Saldo</th><th>Feiertag</th><th></th></tr></thead><tbody>
        <tr v-for="item in evaluations" :key="item.date"><td>{{ formatDate(item.date) }}</td><td><StatusBadge :label="item.status" :tone="statusTone(item.status)" /></td><td><StatusBadge :label="trafficLabel(item.traffic)" :tone="badgeTone(item.traffic)" /></td><td>{{ formatMinutes(item.target) }}</td><td>{{ formatMinutes(item.actual) }}</td><td>{{ formatMinutes(item.vacation) }}</td><td>{{ formatMinutes(item.sick) }}</td><td>{{ formatMinutes(item.balance, true) }}</td><td>{{ item.holiday ?? '-' }}</td><td><a class="btn btn-secondary" :href="`#/evaluations/users/${userId}/day/${item.date}`">Tag</a></td></tr>
    </tbody></table></div></section>
</template>
