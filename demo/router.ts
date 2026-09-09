import { ref } from 'vue'

function normalizeHash(): string {
    const value = window.location.hash.replace(/^#\/?/, '')
    return `/${value || 'time'}`.replace(/\/{2,}/g, '/')
}

export const currentPath = ref(normalizeHash())

window.addEventListener('hashchange', () => {
    currentPath.value = normalizeHash()
    document.querySelector('.app-main')?.scrollTo({ top: 0 })
})

export function navigate(path: string): void {
    const normalized = path.startsWith('/') ? path : `/${path}`
    if (currentPath.value === normalized) {
        document.querySelector('.app-main')?.scrollTo({ top: 0, behavior: 'smooth' })
        return
    }
    window.location.hash = normalized
}

export function pathPart(index: number): string {
    return currentPath.value.split('/').filter(Boolean)[index] ?? ''
}
