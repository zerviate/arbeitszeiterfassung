<template>
    <div class="progress-widget">
        <h3>Tagesfortschritt</h3>

        <p class="meta">
            Netto: {{ netMinutes }} Min | Soll: {{ selectedTargetMinutes }} Min
        </p>

        <div class="progress-bar" role="progressbar" :aria-valuenow="progressPercent" aria-valuemin="0" aria-valuemax="100">
            <div class="progress-bar-fill" :style="{ width: `${progressPercent}%` }"></div>
        </div>

        <p class="meta">
            {{ progressPercent }}% erreicht
            <span v-if="remainingMinutes > 0">(noch {{ remainingMinutes }} Min)</span>
            <span v-else>(+{{ Math.abs(remainingMinutes) }} Min)</span>
        </p>

        <label class="target-label" for="target-minutes-input">Soll-Minuten anpassen</label>
        <input
            id="target-minutes-input"
            v-model.number="selectedTargetMinutes"
            type="number"
            min="1"
            step="15"
        >
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    netMinutes: {
        type: Number,
        default: 0,
    },
    targetMinutes: {
        type: Number,
        default: 480,
    },
});

const selectedTargetMinutes = ref(Number.isFinite(props.targetMinutes) && props.targetMinutes > 0 ? props.targetMinutes : 480);

const progressPercent = computed(() => {
    if (selectedTargetMinutes.value <= 0) {
        return 0;
    }

    const raw = Math.round((props.netMinutes / selectedTargetMinutes.value) * 100);

    return Math.max(0, Math.min(raw, 100));
});

const remainingMinutes = computed(() => selectedTargetMinutes.value - props.netMinutes);
</script>

<style scoped>
.progress-widget {
    margin-top: 16px;
    padding: 18px;
    border: 1px solid var(--color-border-subtle, #e8e8e8);
    border-radius: var(--radius-lg, 14px);
    background: linear-gradient(180deg, rgba(97, 31, 147, 0.04) 0%, rgba(255, 255, 255, 0.98) 100%);
    box-shadow: var(--shadow-sm, 0 1px 2px rgba(0, 0, 0, 0.05));
}

.meta {
    margin: 8px 0;
    color: var(--color-text-muted, #666666);
}

.progress-bar {
    width: 100%;
    height: 14px;
    border-radius: 999px;
    background: var(--color-surface-subtle, #f5f5f5);
    overflow: hidden;
    margin: 14px 0;
    border: 1px solid var(--color-border-subtle, #e8e8e8);
}

.progress-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--color-brand-secondary, #611f93), var(--color-brand-primary, #f59c00));
    transition: width 0.25s ease;
}

.target-label {
    display: block;
    margin-top: 14px;
    margin-bottom: 6px;
    font-weight: 600;
    color: var(--color-text-secondary, #3d3d3d);
}

input {
    width: 100%;
    min-height: 44px;
    padding: 10px 12px;
    border-radius: var(--radius-md, 10px);
    border: 1px solid var(--color-border, #cfcfcf);
    background: var(--color-surface, #ffffff);
    color: var(--color-text, #000000);
}

input:focus {
    outline: none;
    border-color: var(--color-brand-primary, #f59c00);
    box-shadow: 0 0 0 4px rgba(245, 156, 0, 0.16);
}
</style>
