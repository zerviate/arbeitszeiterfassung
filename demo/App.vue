<script setup lang="ts">
import { computed } from 'vue'
import { currentPath, navigate } from './router'
import { demo, isoDate, isoMonth, resetDemo } from './store'
import { showToast } from './toast'
import ToastStack from './components/ToastStack.vue'
import LoginView from './views/LoginView.vue'
import TimeViews from './views/TimeViews.vue'
import CorrectionViews from './views/CorrectionViews.vue'
import AbsenceViews from './views/AbsenceViews.vue'
import AdminViews from './views/AdminViews.vue'
import EvaluationViews from './views/EvaluationViews.vue'
import AuditView from './views/AuditView.vue'

const activeView = computed(() => {
    if (currentPath.value.startsWith('/time/corrections')) return CorrectionViews
    if (currentPath.value.startsWith('/time')) return TimeViews
    if (currentPath.value.startsWith('/vacations') || currentPath.value.startsWith('/sick-leaves')) return AbsenceViews
    if (
        currentPath.value.startsWith('/management')
        || currentPath.value.startsWith('/contracts')
        || currentPath.value.startsWith('/holidays')
        || currentPath.value.startsWith('/vacation-balances')
    ) return AdminViews
    if (currentPath.value.startsWith('/evaluations')) return EvaluationViews
    if (currentPath.value.startsWith('/audit-logs')) return AuditView
    return TimeViews
})

const today = isoDate()
const month = isoMonth()

function active(prefix: string): boolean {
    return currentPath.value === prefix || currentPath.value.startsWith(`${prefix}/`)
}

function logout(): void {
    demo.authenticated = false
    navigate('/login')
    showToast('Sie wurden abgemeldet.', 'info', 'Abmeldung')
}

function reset(): void {
    resetDemo()
    navigate('/time')
    showToast('Die synthetischen Demodaten wurden zurückgesetzt.', 'info', 'Demo zurückgesetzt')
}
</script>

<template>
    <ToastStack />
    <LoginView v-if="!demo.authenticated || currentPath === '/login'" />

    <div v-else class="app-shell">
        <aside class="app-sidebar" aria-label="Hauptnavigation">
            <div class="app-sidebar-inner">
                <div class="app-brand">
                    <span class="app-brand-mark">AZ</span>
                    <span><span class="app-brand-title">Arbeitszeiterfassung</span></span>
                </div>

                <details class="app-nav-group" open>
                    <summary class="app-nav-group-title">Arbeitszeit</summary>
                    <div class="app-nav-list">
                        <a href="#/time" :class="['app-nav-link', { 'is-active': currentPath === '/time' }]">
                            <span class="app-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></span>
                            <span class="app-nav-label">Heute</span>
                        </a>
                        <a :href="`#/time/month/${month}`" :class="['app-nav-link', { 'is-active': active('/time/month') }]">
                            <span class="app-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span>
                            <span class="app-nav-label">Monat</span>
                        </a>
                        <a href="#/time/corrections" :class="['app-nav-link', { 'is-active': active('/time/corrections') }]">
                            <span class="app-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg></span>
                            <span class="app-nav-label">Korrekturen</span>
                        </a>
                    </div>
                </details>

                <details class="app-nav-group" open>
                    <summary class="app-nav-group-title">Abwesenheit</summary>
                    <div class="app-nav-list">
                        <a :href="`#/vacations?month=${month}`" :class="['app-nav-link', { 'is-active': active('/vacations') }]">
                            <span class="app-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><line x1="12" y1="2" x2="12" y2="4"/><line x1="12" y1="20" x2="12" y2="22"/><line x1="2" y1="12" x2="4" y2="12"/><line x1="20" y1="12" x2="22" y2="12"/></svg></span>
                            <span class="app-nav-label">Urlaub</span>
                        </a>
                        <a :href="`#/sick-leaves?month=${month}`" :class="['app-nav-link', { 'is-active': active('/sick-leaves') }]">
                            <span class="app-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21.2l8.8-8.8a5.5 5.5 0 0 0 0-7.8Z"/></svg></span>
                            <span class="app-nav-label">Krankmeldungen</span>
                        </a>
                    </div>
                </details>

                <details class="app-nav-group" open>
                    <summary class="app-nav-group-title">Verwaltung</summary>
                    <div class="app-nav-list">
                        <a :href="`#/management/time?date=${today}`" :class="['app-nav-link', { 'is-active': active('/management/time') }]">
                            <span class="app-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.9"/></svg></span>
                            <span class="app-nav-label">Teamzeiten</span>
                        </a>
                        <a href="#/contracts" :class="['app-nav-link', { 'is-active': active('/contracts') }]">
                            <span class="app-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><polyline points="14 2 14 8 20 8"/></svg></span>
                            <span class="app-nav-label">Verträge</span>
                        </a>
                        <a :href="`#/holidays?month=${month}`" :class="['app-nav-link', { 'is-active': active('/holidays') }]">
                            <span class="app-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15 8.5 22 9.3 17 14 18.5 21 12 17.8 5.5 21 7 14 2 9.3 9 8.5"/></svg></span>
                            <span class="app-nav-label">Feiertage</span>
                        </a>
                        <a :href="`#/vacation-balances?year=${new Date().getFullYear()}`" :class="['app-nav-link', { 'is-active': active('/vacation-balances') }]">
                            <span class="app-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-5"/><path d="M18 12h4"/></svg></span>
                            <span class="app-nav-label">Urlaubskonten</span>
                        </a>
                    </div>
                </details>
            </div>
        </aside>

        <div class="app-content">
            <header class="app-topbar">
                <div class="app-topbar-tools">
                    <details class="app-topbar-menu">
                        <summary class="app-topbar-menu-trigger">Mehr <span aria-hidden="true">▾</span></summary>
                        <div class="app-topbar-menu-panel">
                            <span class="app-topbar-menu-label">Bewertungen</span>
                            <a class="app-topbar-menu-link" :href="`#/evaluations/users/1/day/${today}`">Tagesbewertung</a>
                            <a class="app-topbar-menu-link" :href="`#/evaluations/users/1/week/${today}`">Wochenbewertung</a>
                            <a class="app-topbar-menu-link" :href="`#/evaluations/users/1/month/${month}`">Monatsbewertung</a>
                            <div class="app-topbar-menu-divider"></div>
                            <span class="app-topbar-menu-label">System</span>
                            <a class="app-topbar-menu-link" href="#/audit-logs">Audit-Logs</a>
                            <button class="app-topbar-menu-link demo-menu-button" type="button" @click="reset">Demodaten zurücksetzen</button>
                        </div>
                    </details>
                    <div class="app-user-chip">
                        <span class="app-user-avatar">PD</span>
                        <span class="app-user-name">Portfolio Demo</span>
                    </div>
                    <button type="button" class="btn btn-secondary" @click="logout">Logout</button>
                </div>
            </header>

            <main class="app-main">
                <div class="app-main-inner">
                    <component :is="activeView" :path="currentPath" />
                    <p class="demo-disclosure">Interaktive Portfolio-Demo mit synthetischen Daten. Änderungen werden nur in diesem Browser gespeichert.</p>
                </div>
            </main>
        </div>
    </div>
</template>
