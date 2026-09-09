<script setup lang="ts">
import type { Tone } from '../store'

defineProps<{
    label: string
    tone?: Tone
}>()
</script>

<template>
    <span :class="['badge', `badge-${tone ?? 'default'}`]">{{ label }}</span>
</template>
