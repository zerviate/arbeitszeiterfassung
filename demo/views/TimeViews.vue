<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import WorkdayCommandCenter from '@/vue/components/WorkdayCommandCenter.vue'
import StatusBadge from '../components/StatusBadge.vue'
import {
    breakMinutes,
    clockIn,
    clockOut,
    demo,
    downloadCsv,
    formatClockMinutes,
    formatDate,
    formatDateTime,
    formatMinutes,
    formatTime,
    getWorkday,
    isoDate,
    isoMonth,
    minutesBetween,
    toggleBreak,
    workdayMinutes,
    type Tone,
    type Workday,
} from '../store'
import { navigate } from '../router'
import { showToast } from '../toast'

const props = defineProps<{ path: string }>()
const tick = ref(Date.now())
const selectedDate = ref<string | null>(null)
let timer: number | undefined

onMounted(() => {
    timer = window.setInterval(() => { tick.value = Date.now() }, 1000)
})

onUnmounted(() => window.clearInterval(timer))

const cleanPath = computed(() => props.path.split('?')[0])
const mode = computed(() => cleanPath.value.startsWith('/time/day/') ? 'day' : cleanPath.value.startsWith('/time/month/') ? 'month' : 'today')
const routeDate = computed(() => mode.value === 'day' ? cleanPath.value.split('/').at(-1) ?? isoDate() : isoDate())
const routeMonth = computed(() => mode.value === 'month' ? cleanPath.value.split('/').at(-1) ?? isoMonth() : isoMonth())
const day = computed(() => getWorkday(routeDate.value))
const todayDay = computed(() => getWorkday(isoDate()))
const openSession = computed(() => todayDay.value?.sessions.find((entry) => entry.end === null))
const openBreak = computed(() => openSession.value?.breaks.find((entry) => entry.end === null))

const liveNetMinutes = computed(() => {
    tick.value
    return todayDay.value ? workdayMinutes(todayDay.value) : 0
})

function grossMinutes(item: Workday): number {
    tick.value
    return item.sessions.reduce((total, entry) => total + minutesBetween(entry.start, entry.end), 0)
}

function statusTone(item?: Workday): Tone {
    if (!item) return 'muted'
    return trafficTone(item.traffic)
}

function trafficTone(traffic: Workday['traffic'] = 'gray'): Tone {
    return { green: 'success', yellow: 'warning', red: 'danger', gray: 'muted' }[traffic] as Tone
}

function statusLabel(traffic?: Workday['traffic']): string {
    return { green: 'Grün', yellow: 'Gelb', red: 'Rot', gray: 'Grau' }[traffic ?? 'gray']
}

function dayStatusTone(status?: string): Tone {
    if (!status) return 'muted'
    if (['Erfüllt', 'Mehrarbeit', 'Arbeit am Feiertag'].includes(status)) return 'success'
    if (['Unter Soll'].includes(status)) return 'danger'
    if (['Urlaub', 'Krank'].includes(status)) return 'info'
    if (['Freier Tag', 'Feiertag'].includes(status)) return 'muted'
    return 'warning'
}

function runClockAction(): void {
    if (openSession.value) {
        clockOut()
        showToast('Arbeitszeit wurde beendet.', 'info', 'Arbeitszeit')
    } else {
        clockIn()
        showToast('Arbeitszeit wurde gestartet.')
    }
}

function runBreakAction(): void {
    const result = toggleBreak()
    if (result === 'started') showToast('Pause wurde gestartet.', 'warning', 'Pause')
    if (result === 'ended') showToast('Pause wurde beendet.')
}

function shiftMonth(value: string, offset: number): string {
    const [year, month] = value.split('-').map(Number)
    const date = new Date(year, month - 1 + offset, 1)
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`
}

const monthLabel = computed(() => {
    const [year, month] = routeMonth.value.split('-').map(Number)
    return new Intl.DateTimeFormat('de-DE', { month: '2-digit', year: 'numeric' }).format(new Date(year, month - 1, 1))
})

interface CalendarCell {
    blank?: true
    date?: string
    day?: number
    weekend?: boolean
    minutes?: number
    target?: number
    status?: string
    traffic?: Workday['traffic']
    holiday?: string
    vacation?: boolean
    sick?: boolean
    workday?: Workday
}

const calendar = computed<CalendarCell[]>(() => {
    const [year, month] = routeMonth.value.split('-').map(Number)
    const first = new Date(year, month - 1, 1)
    const count = new Date(year, month, 0).getDate()
    const cells: CalendarCell[] = Array.from({ length: (first.getDay() + 6) % 7 }, () => ({ blank: true }))

    for (let number = 1; number <= count; number += 1) {
        const date = `${year}-${String(month).padStart(2, '0')}-${String(number).padStart(2, '0')}`
        const native = new Date(`${date}T12:00:00`)
        const item = getWorkday(date)
        const vacation = demo.vacations.some((entry) => entry.userId === 1 && entry.status === 'approved' && entry.startDate <= date && entry.endDate >= date)
        const sick = demo.sickLeaves.some((entry) => entry.userId === 1 && entry.startDate <= date && entry.endDate >= date)
        const holiday = demo.holidays.find((entry) => entry.date === date && entry.active)
        const weekend = native.getDay() === 0 || native.getDay() === 6
        const minutes = item ? workdayMinutes(item) : 0
        cells.push({
            date,
            day: number,
            weekend,
            minutes,
            target: weekend || holiday ? 0 : 480,
            status: item?.status ?? (holiday ? 'Feiertag' : vacation ? 'Urlaub' : sick ? 'Krank' : weekend ? 'Freier Tag' : date <= isoDate() ? 'Unter Soll' : '-'),
            traffic: item?.traffic ?? (holiday || weekend ? 'gray' : vacation || sick ? 'green' : date <= isoDate() ? 'red' : 'gray'),
            holiday: holiday?.name,
            vacation,
            sick,
            workday: item,
        })
    }
    return cells
})

const selectedCell = computed(() => calendar.value.find((cell) => cell.date === selectedDate.value))
const monthWorkdays = computed(() => demo.workdays.filter((item) => item.userId === 1 && item.date.startsWith(routeMonth.value)))
const monthActual = computed(() => monthWorkdays.value.reduce((total, item) => total + workdayMinutes(item), 0))
const monthTarget = computed(() => calendar.value.reduce((total, item) => total + (item.target ?? 0), 0))
const monthVacation = computed(() => calendar.value.reduce((total, item) => total + (item.vacation ? item.target ?? 0 : 0), 0))
const monthSick = computed(() => calendar.value.reduce((total, item) => total + (item.sick ? item.target ?? 0 : 0), 0))
const monthTraffic = computed<Workday['traffic']>(() => {
    const values = calendar.value.map((item) => item.traffic)
    if (values.includes('red')) return 'red'
    if (values.includes('yellow')) return 'yellow'
    if (values.includes('green')) return 'green'
    return 'gray'
})

watch(routeMonth, () => { selectedDate.value = null })

function heatClass(minutes = 0): string {
    if (minutes >= 360) return 'heat-high'
    if (minutes >= 240) return 'heat-mid'
    if (minutes > 0) return 'heat-low'
    return 'heat-0'
}

function calendarBalance(cell: CalendarCell): number {
    const absenceCredit = cell.vacation || cell.sick ? cell.target ?? 0 : 0
    return (cell.minutes ?? 0) + absenceCredit - (cell.target ?? 0)
}

function exportMonth(kind: string): void {
    downloadCsv(`${kind}-${routeMonth.value}.csv`, [
        ['Datum', 'Netto', 'Soll', 'Saldo', 'Status'],
        ...calendar.value.filter((cell) => cell.date).map((cell) => [
            cell.date ?? '',
            cell.minutes ?? 0,
            cell.target ?? 0,
            calendarBalance(cell),
            cell.status ?? '-',
        ]),
    ])
    showToast('Der Export wurde mit synthetischen Demodaten erstellt.')
}
</script>

<template>
    <section v-if="mode === 'today'" class="workday-shell">
        <header class="page-header page-header-compact">
            <div class="page-header-copy"><h2>Heute · {{ formatDate(isoDate()) }}</h2></div>
            <div class="page-header-actions"><a :href="`#/time/month/${isoMonth()}`" class="btn btn-secondary">Monatsansicht</a></div>
        </header>

        <div class="workday-grid">
            <section class="ui-card"><div class="ui-card-header"><h3>Live-Status</h3></div><div class="ui-card-body">
                <WorkdayCommandCenter :session-started-at="openSession?.start" :break-started-at="openBreak?.start" />
            </div></section>
            <section class="ui-card"><div class="ui-card-header"><h3>Aktionen</h3></div><div class="ui-card-body">
                <div class="workday-actions">
                    <button type="button" class="btn workday-action-button" @click="runClockAction">{{ openSession ? 'Arbeitszeit beenden' : 'Arbeitszeit starten' }}</button>
                    <button type="button" class="btn btn-secondary workday-action-button" :disabled="!openSession" @click="runBreakAction">{{ openBreak ? 'Pause beenden' : 'Pause starten' }}</button>
                </div>
                <div class="workday-actions-meta text-muted"><span>{{ openSession ? 'Session läuft.' : 'Keine laufende Session.' }}</span></div>
                <div v-if="openSession" class="workday-meta">
                    <div><span class="detail-list-label">Session gestartet</span><span class="detail-list-value">{{ formatDateTime(openSession.start) }}</span></div>
                    <div v-if="openBreak"><span class="detail-list-label">Pause seit</span><span class="detail-list-value">{{ formatDateTime(openBreak.start) }}</span></div>
                </div>
            </div></section>
        </div>

        <section class="ui-card"><div class="ui-card-header"><h3>Tageswerte</h3></div><div class="ui-card-body">
            <template v-if="todayDay">
                <div class="workday-metrics">
                    <div class="workday-metric"><span class="workday-metric-label">Netto</span><span class="workday-metric-value">{{ formatMinutes(liveNetMinutes) }}</span></div>
                    <div class="workday-metric"><span class="workday-metric-label">Soll</span><span class="workday-metric-value">{{ formatMinutes(todayDay.targetMinutes) }}</span></div>
                    <div class="workday-metric"><span class="workday-metric-label">Saldo</span><span class="workday-metric-value">{{ formatMinutes(liveNetMinutes - todayDay.targetMinutes, true) }}</span></div>
                    <div class="workday-metric"><span class="workday-metric-label">Status</span><span class="workday-metric-value"><StatusBadge :label="statusLabel(todayDay.traffic)" :tone="statusTone(todayDay)" /></span></div>
                </div>
                <div class="workday-status-row"><span class="detail-list-label">Status</span><span class="detail-list-value"><StatusBadge :label="todayDay.status" :tone="dayStatusTone(todayDay.status)" /></span></div>
                <div v-if="todayDay.flags.length" class="workday-flags"><span class="detail-list-label">Hinweise</span><div class="inline-badge-list"><StatusBadge v-for="flag in todayDay.flags" :key="flag" :label="flag" tone="info" /></div></div>
            </template>
            <p v-else class="text-muted">Noch keine Daten für heute vorhanden.</p>
        </div></section>
    </section>

    <template v-else-if="mode === 'day'">
        <header class="page-header page-header-compact">
            <div class="page-header-copy"><h2>Tagesansicht · {{ formatDate(routeDate) }}</h2></div>
            <div class="page-header-actions"><a href="#/time" class="btn btn-secondary">Heute</a><a :href="`#/time/month/${routeDate.slice(0, 7)}`" class="btn btn-ghost">Zur Monatsansicht</a></div>
        </header>
        <div v-if="day" class="metric-grid">
            <div class="metric-card"><span class="metric-label">Brutto</span><span class="metric-value">{{ formatMinutes(grossMinutes(day)) }}</span><span class="metric-meta">Netto {{ formatMinutes(workdayMinutes(day)) }}</span></div>
            <div class="metric-card"><span class="metric-label">Pause</span><span class="metric-value">{{ formatMinutes(day.sessions.reduce((sum, item) => sum + breakMinutes(item), 0)) }}</span></div>
            <div class="metric-card"><span class="metric-label">Soll</span><span class="metric-value">{{ formatMinutes(day.targetMinutes) }}</span></div>
            <div class="metric-card"><span class="metric-label">Saldo</span><span class="metric-value">{{ formatMinutes(workdayMinutes(day) - day.targetMinutes, true) }}</span></div>
        </div>
        <div class="split-layout">
            <section class="ui-card"><div class="ui-card-header"><h3>Tageszusammenfassung</h3></div><div class="ui-card-body">
                <template v-if="day">
                    <div class="detail-list section-space">
                        <div class="detail-list-row"><span class="detail-list-label">Netto</span><span class="detail-list-value">{{ formatMinutes(workdayMinutes(day)) }}</span></div>
                        <div class="detail-list-row"><span class="detail-list-label">Überzeit</span><span class="detail-list-value">{{ formatMinutes(workdayMinutes(day) - day.targetMinutes, true) }}</span></div>
                        <div class="detail-list-row"><span class="detail-list-label">Status</span><span class="detail-list-value"><StatusBadge :label="day.status" :tone="dayStatusTone(day.status)" /> <StatusBadge :label="statusLabel(day.traffic)" :tone="statusTone(day)" /></span></div>
                        <div v-if="day.finalized" class="detail-list-row"><span class="detail-list-label">Finalisierung</span><span class="detail-list-value"><StatusBadge label="Finalisiert" tone="success" /></span></div>
                        <div v-if="day.holiday" class="detail-list-row"><span class="detail-list-label">Feiertag</span><span class="detail-list-value">{{ day.holiday }}</span></div>
                    </div>
                    <div v-if="day.flags.length" class="section-space"><p class="text-muted mb-2"><strong>Hinweise</strong></p><div class="inline-badge-list"><StatusBadge v-for="flag in day.flags" :key="flag" :label="flag" tone="info" /></div></div>
                </template>
                <p v-else class="text-muted">Für diesen Tag gibt es noch keine Zusammenfassung.</p>
            </div></section>
            <section class="data-table-shell data-table-tall"><div class="data-table-header"><h3>Sessions</h3></div><div class="data-table-scroll"><table><thead><tr><th>Beginn</th><th>Ende</th><th>Brutto</th><th>Pausen</th></tr></thead><tbody>
                <tr v-for="entry in day?.sessions ?? []" :key="entry.start"><td>{{ formatDateTime(entry.start) }}</td><td>{{ entry.end ? formatDateTime(entry.end) : '-' }}</td><td>{{ formatMinutes(minutesBetween(entry.start, entry.end)) }}</td><td><div v-for="pause in entry.breaks" :key="pause.start">{{ formatDateTime(pause.start) }} - {{ pause.end ? formatDateTime(pause.end) : '-' }} ({{ formatMinutes(minutesBetween(pause.start, pause.end)) }})</div><span v-if="!entry.breaks.length" class="text-muted">Keine</span></td></tr>
                <tr v-if="!day?.sessions.length"><td colspan="4">Keine Sessions vorhanden.</td></tr>
            </tbody></table></div></section>
        </div>
    </template>

    <section v-else class="month-shell">
        <header class="page-header page-header-compact"><div class="page-header-copy"><h2>Monatsansicht · {{ monthLabel }}</h2></div><div class="page-header-actions">
            <button class="btn btn-secondary" type="button" @click="navigate(`/time/month/${shiftMonth(routeMonth, -1)}`)">Vorheriger Monat</button>
            <a href="#/time" class="btn btn-ghost">Heute</a>
            <button class="btn btn-secondary" type="button" @click="navigate(`/time/month/${shiftMonth(routeMonth, 1)}`)">Nächster Monat</button>
        </div></header>
        <div class="month-overview">
            <section class="ui-card month-overview-summary"><div class="ui-card-header"><h3>Monatsübersicht</h3></div><div class="ui-card-body"><div class="month-summary-grid">
                <div class="month-summary-item"><span class="month-summary-label">Abwesenheiten</span><span class="month-summary-value">{{ formatMinutes(monthVacation + monthSick) }}</span><span class="month-summary-meta">Urlaub {{ formatMinutes(monthVacation) }} | Krank {{ formatMinutes(monthSick) }}</span></div>
                <div class="month-summary-item"><span class="month-summary-label">Saldo</span><span class="month-summary-value">{{ formatMinutes(monthActual + monthVacation + monthSick - monthTarget, true) }}</span><span class="month-summary-meta">Soll {{ formatMinutes(monthTarget) }} | Ist {{ formatMinutes(monthActual) }}</span><div class="month-summary-badge"><StatusBadge :label="statusLabel(monthTraffic)" :tone="trafficTone(monthTraffic)" /></div></div>
            </div></div></section>
            <section class="ui-card month-overview-heatmap"><div class="ui-card-header"><h3>Monatsverlauf</h3><div class="ui-card-actions demo-export-actions">
                <button class="btn btn-secondary" @click="exportMonth('arbeitszeit')">CSV Export</button><button class="btn btn-secondary" @click="exportMonth('arbeitszeit-excel')">Excel Export</button><button class="btn btn-secondary" @click="exportMonth('compliance')">Compliance CSV</button><button class="btn btn-secondary" @click="exportMonth('compliance-excel')">Compliance Excel</button>
            </div></div><div class="ui-card-body">
                <div class="month-heatmap"><div class="heatmap-grid">
                    <component :is="cell.blank ? 'div' : 'button'" v-for="(cell, index) in calendar" :key="cell.date ?? `blank-${index}`" :type="cell.blank ? undefined : 'button'" :class="['heatmap-cell', cell.blank ? 'heatmap-empty' : 'heatmap-day', !cell.blank ? heatClass(cell.minutes) : '', { 'heat-weekend': cell.weekend, 'is-selected': cell.date === selectedDate }]" @click="cell.date && (selectedDate = cell.date)">{{ cell.day }}</component>
                </div><div class="heatmap-legend"><span>Ist-Zeit</span><div class="heatmap-legend-scale"><span class="heatmap-legend-chip heat-0"></span><span class="heatmap-legend-chip heat-low"></span><span class="heatmap-legend-chip heat-mid"></span><span class="heatmap-legend-chip heat-high"></span></div></div>
                <div v-if="selectedCell?.date" class="heatmap-popover demo-heatmap-popover"><div class="heatmap-popover-header"><div class="heatmap-popover-title">{{ formatDate(selectedCell.date) }}</div><a class="heatmap-popover-link" :href="`#/time/day/${selectedCell.date}`">Tag oeffnen</a></div><div class="heatmap-popover-grid">
                    <div><span class="heatmap-popover-label">Soll</span><span class="heatmap-popover-value">{{ formatMinutes(selectedCell.target ?? 0) }}</span></div><div><span class="heatmap-popover-label">Brutto</span><span class="heatmap-popover-value">{{ formatMinutes(selectedCell.workday ? grossMinutes(selectedCell.workday) : 0) }}</span></div><div><span class="heatmap-popover-label">Pausen</span><span class="heatmap-popover-value">{{ formatMinutes(selectedCell.workday?.sessions.reduce((sum, item) => sum + breakMinutes(item), 0) ?? 0) }}</span></div><div><span class="heatmap-popover-label">Netto</span><span class="heatmap-popover-value">{{ formatMinutes(selectedCell.minutes ?? 0) }}</span></div><div><span class="heatmap-popover-label">Saldo</span><span class="heatmap-popover-value">{{ formatMinutes(calendarBalance(selectedCell), true) }}</span></div><div><span class="heatmap-popover-label">Status</span><StatusBadge :label="selectedCell.status ?? '-'" :tone="dayStatusTone(selectedCell.status)" /></div><div><span class="heatmap-popover-label">Ampel</span><StatusBadge :label="statusLabel(selectedCell.traffic)" :tone="trafficTone(selectedCell.traffic)" /></div><div><span class="heatmap-popover-label">Feiertag</span><span class="heatmap-popover-value">{{ selectedCell.holiday ?? '-' }}</span></div>
                </div></div>
                </div>
            </div></section>
        </div>
    </section>
</template>
