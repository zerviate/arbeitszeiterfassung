<script setup lang="ts">
import { computed, ref } from 'vue'
import { demo, downloadCsv, formatDateTime } from '../store'
import { showToast } from '../toast'

defineProps<{ path: string }>()

const actorId = ref('')
const event = ref('')
const appliedActor = ref('')
const appliedEvent = ref('')
const events = computed(() => [...new Set(demo.audit.map((item) => item.event))].sort())
const entries = computed(() => demo.audit.filter((item) => (!appliedActor.value || item.actorId === Number(appliedActor.value)) && (!appliedEvent.value || item.event === appliedEvent.value)))

function applyFilters(): void {
    appliedActor.value = actorId.value
    appliedEvent.value = event.value
}

function resetFilters(): void {
    actorId.value = ''
    event.value = ''
    applyFilters()
}

function exportAudit(kind: string): void {
    downloadCsv(`audit-logs-${kind}.csv`, [
        ['Zeitpunkt', 'Actor', 'Event', 'Objekt', 'Vorher', 'Nachher'],
        ...entries.value.map((item) => [item.occurredAt, `${item.actor} (#${item.actorId})`, item.event, item.subject, JSON.stringify(item.before), JSON.stringify(item.after)]),
    ])
    showToast('Der Export wurde mit synthetischen Demodaten erstellt.')
}
</script>

<template>
    <header class="page-header"><div class="page-header-copy"><h2>Audit-Logs</h2></div><div class="page-header-actions"><button class="btn btn-secondary" @click="exportAudit('csv')">CSV Export</button><button class="btn btn-secondary" @click="exportAudit('excel')">Excel Export</button></div></header>
    <section class="filter-panel filter-panel-compact"><div class="filter-panel-header"><h3>Filter</h3></div><form class="filter-panel-body demo-audit-filter" @submit.prevent="applyFilters"><div><label>Akteur</label><input v-model="actorId" type="number" min="1"></div><div><label>Event</label><select v-model="event"><option value="">Alle</option><option v-for="name in events" :key="name" :value="name">{{ name }}</option></select></div><button class="btn">Filtern</button><button type="button" class="btn btn-secondary" @click="resetFilters">Reset</button></form></section>
    <section class="data-table-shell data-table-tall"><div class="data-table-header"><h3>Protokoll</h3></div><div class="data-table-scroll"><table><thead><tr><th>Zeitpunkt</th><th>Actor</th><th>Event</th><th>Objekt</th><th>Vorher</th><th>Nachher</th></tr></thead><tbody>
        <tr v-for="item in entries" :key="item.id"><td>{{ formatDateTime(item.occurredAt) }}</td><td>{{ item.actor }} (#{{ item.actorId }})</td><td>{{ item.event }}</td><td>{{ item.subject }}</td><td><pre class="audit-json">{{ JSON.stringify(item.before, null, 2) }}</pre></td><td><pre class="audit-json">{{ JSON.stringify(item.after, null, 2) }}</pre></td></tr>
        <tr v-if="!entries.length"><td colspan="6">Keine Audit-Einträge vorhanden.</td></tr>
    </tbody></table></div></section>
</template>
