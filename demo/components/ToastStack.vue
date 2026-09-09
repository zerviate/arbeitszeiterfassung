<script setup lang="ts">
import { dismissToast, toast } from '../toast'
</script>

<template>
    <div v-if="toast" class="toast-stack" aria-live="polite" aria-atomic="true">
        <div :class="['toast', `toast-${toast.tone === 'info' ? 'neutral' : toast.tone}`]" role="status">
            <button class="toast-close" type="button" aria-label="Benachrichtigung schließen" @click="dismissToast">×</button>
            <div v-if="toast.title" class="toast-title">{{ toast.title }}</div>
            <div class="toast-message">{{ toast.message }}</div>
        </div>
    </div>
</template>
