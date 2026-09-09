import { ref } from 'vue'

export interface ToastMessage {
    id: number
    tone: 'success' | 'warning' | 'danger' | 'info'
    title: string
    message: string
}

export const toast = ref<ToastMessage | null>(null)
let timer: number | undefined

export function showToast(message: string, tone: ToastMessage['tone'] = 'success', title = 'Erfolg'): void {
    window.clearTimeout(timer)
    toast.value = { id: Date.now(), tone, title, message }
    timer = window.setTimeout(() => {
        toast.value = null
    }, 3500)
}

export function dismissToast(): void {
    window.clearTimeout(timer)
    toast.value = null
}
