<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { Badge } from '@/vue/components/ui/badge'

const props = defineProps<{
    sessionStartedAt?: string | null
    breakStartedAt?: string | null
}>()

const now = ref(Date.now())
let intervalId: number | undefined

const sessionStartMs = computed(() =>
    props.sessionStartedAt ? Date.parse(props.sessionStartedAt) : null
)
const breakStartMs = computed(() =>
    props.breakStartedAt ? Date.parse(props.breakStartedAt) : null
)
const hasSession = computed(() => sessionStartMs.value !== null)
const hasBreak = computed(() => breakStartMs.value !== null)

const sessionSeconds = computed(() => {
    if (!hasSession.value || sessionStartMs.value === null) {
        return 0
    }

    const end = hasBreak.value && breakStartMs.value !== null ? breakStartMs.value : now.value

    return Math.max(0, Math.floor((end - sessionStartMs.value) / 1000))
})

const breakSeconds = computed(() => {
    if (!hasBreak.value || breakStartMs.value === null) {
        return 0
    }

    return Math.max(0, Math.floor((now.value - breakStartMs.value) / 1000))
})

const statusLabel = computed(() => {
    if (!hasSession.value) {
        return 'Gestoppt'
    }

    return hasBreak.value ? 'Pause' : 'Läuft'
})

const statusVariant = computed(() => {
    if (!hasSession.value) {
        return 'outline'
    }

    return hasBreak.value ? 'secondary' : 'default'
})

const helperText = computed(() => {
    if (!hasSession.value) {
        return 'Keine laufende Session'
    }

    return hasBreak.value ? 'Arbeitszeit pausiert' : 'Arbeitszeit läuft'
})

function formatDuration(totalSeconds: number) {
    const hours = Math.floor(totalSeconds / 3600)
    const minutes = Math.floor((totalSeconds % 3600) / 60)
    const seconds = totalSeconds % 60

    return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`
}

const sessionTime = computed(() => formatDuration(sessionSeconds.value))
const breakTime = computed(() => formatDuration(breakSeconds.value))

onMounted(() => {
    intervalId = window.setInterval(() => {
        now.value = Date.now()
    }, 1000)
})

onUnmounted(() => {
    if (intervalId) {
        window.clearInterval(intervalId)
    }
})
</script>

<template>
    <div class="workday-live">
        <div class="workday-live-header">
            <Badge :variant="statusVariant">
                {{ statusLabel }}
            </Badge>
            <span class="workday-live-meta">{{ helperText }}</span>
        </div>

        <div class="workday-timer-row">
            <div class="workday-timer">{{ sessionTime }}</div>
            <div v-if="hasBreak" class="workday-break-mini">
                <span class="workday-break-mini-label">Pause</span>
                <span class="workday-break-mini-time">{{ breakTime }}</span>
            </div>
        </div>

        <div class="workday-live-footer">
            <div class="workday-live-label">Arbeitszeit</div>
        </div>
    </div>
</template>
