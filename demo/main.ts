import { createApp, type App } from 'vue'
import WorkdayCommandCenter from '@/vue/components/WorkdayCommandCenter.vue'
import '../resources/css/app.css'
import './demo.css'
import {
    auditEvents,
    createDefaultState,
    teamMembers,
    workdays,
    type DemoRole,
    type DemoState,
    type VacationRequest,
    type VacationStatus,
} from './mock-data'

const storageKey = 'arbeitszeiterfassung-recruiter-demo-v1'
const validRoutes = new Set(['today', 'month', 'corrections', 'vacation', 'team', 'reports', 'architecture'])
const routeTitles: Record<string, string> = {
    today: 'Heute',
    month: 'Monat',
    corrections: 'Korrekturen',
    vacation: 'Urlaub',
    team: 'Teamzeiten',
    reports: 'Auswertungen',
    architecture: 'Architektur & Stack',
}

let state = loadState()
let timerApp: App<Element> | null = null
let toastTimer: number | undefined

function query<T extends Element>(selector: string): T {
    const element = document.querySelector<T>(selector)

    if (!element) {
        throw new Error(`Demo element not found: ${selector}`)
    }

    return element
}

function loadState(): DemoState {
    try {
        const raw = window.localStorage.getItem(storageKey)

        if (!raw) {
            return createDefaultState()
        }

        const parsed = JSON.parse(raw) as Partial<DemoState>
        const fallback = createDefaultState()

        return {
            role: parsed.role === 'employee' || parsed.role === 'manager' ? parsed.role : fallback.role,
            sessionStartedAt: typeof parsed.sessionStartedAt === 'string' ? parsed.sessionStartedAt : null,
            breakStartedAt: typeof parsed.breakStartedAt === 'string' ? parsed.breakStartedAt : null,
            vacationRequests: Array.isArray(parsed.vacationRequests) ? parsed.vacationRequests : fallback.vacationRequests,
            lastAction: typeof parsed.lastAction === 'string' ? parsed.lastAction : fallback.lastAction,
        }
    } catch {
        return createDefaultState()
    }
}

function saveState(): void {
    try {
        window.localStorage.setItem(storageKey, JSON.stringify(state))
    } catch {
        // The demo still works when storage is unavailable; state simply resets on reload.
    }
}

function showToast(message: string): void {
    const toast = query<HTMLElement>('#demo-toast')
    window.clearTimeout(toastTimer)
    toast.textContent = message
    toast.classList.add('is-visible')
    toastTimer = window.setTimeout(() => toast.classList.remove('is-visible'), 3200)
}

function setAction(message: string): void {
    state.lastAction = message
    saveState()
    showToast(message)
}

function getRoute(): string {
    const candidate = window.location.hash.replace('#', '') || 'today'

    if (!validRoutes.has(candidate)) {
        return 'today'
    }

    if (state.role !== 'manager' && (candidate === 'team' || candidate === 'reports')) {
        return 'today'
    }

    return candidate
}

function applyRoute(): void {
    const route = getRoute()

    document.querySelectorAll<HTMLElement>('[data-screen]').forEach((screen) => {
        const isActive = screen.dataset.screen === route
        screen.classList.toggle('is-active', isActive)
        screen.toggleAttribute('hidden', !isActive)
    })

    document.querySelectorAll<HTMLAnchorElement>('[data-route]').forEach((link) => {
        const isActive = link.dataset.route === route
        link.classList.toggle('is-active', isActive)
        if (isActive) link.setAttribute('aria-current', 'page')
        else link.removeAttribute('aria-current')
    })

    query<HTMLElement>('#topbar-title').textContent = routeTitles[route]
    document.title = `${routeTitles[route]} · Arbeitszeiterfassung Demo`
    closeSidebar()
}

function applyRole(): void {
    const isManager = state.role === 'manager'
    query<HTMLSelectElement>('#role-select').value = state.role

    document.querySelectorAll<HTMLElement>('.manager-only').forEach((element) => {
        element.classList.toggle('is-role-hidden', !isManager)
    })

    applyRoute()
    renderApprovals()
}

function renderTimer(): void {
    const container = query<HTMLElement>('#workday-command-center')

    if (timerApp) {
        timerApp.unmount()
        timerApp = null
    }

    container.replaceChildren()
    timerApp = createApp(WorkdayCommandCenter, {
        sessionStartedAt: state.sessionStartedAt,
        breakStartedAt: state.breakStartedAt,
    })
    timerApp.mount(container)
}

function renderWorkday(): void {
    const hasSession = Boolean(state.sessionStartedAt)
    const hasBreak = Boolean(state.breakStartedAt)
    const clockButton = query<HTMLButtonElement>('#clock-toggle')
    const breakButton = query<HTMLButtonElement>('#break-toggle')
    const note = query<HTMLElement>('#workday-note')

    clockButton.textContent = hasSession ? 'Arbeitszeit beenden' : 'Arbeitszeit starten'
    breakButton.textContent = hasBreak ? 'Pause beenden' : 'Pause starten'
    breakButton.disabled = !hasSession
    note.textContent = hasBreak
        ? 'Pause läuft. Beenden setzt den Arbeitstimer fort.'
        : hasSession
            ? 'Session läuft. Änderungen werden lokal simuliert.'
            : 'Keine laufende Session. Starte eine neue Arbeitszeit.'

    renderTimer()
}

function createStatus(status: string): HTMLSpanElement {
    const element = document.createElement('span')
    element.className = `demo-table-status is-${status.toLowerCase().replace('ä', 'a').replace(' ', '-')}`
    element.textContent = status
    return element
}

function renderWorkdayTable(): void {
    const body = query<HTMLTableSectionElement>('#workday-table')
    body.replaceChildren()

    workdays.forEach((row) => {
        const tr = document.createElement('tr')
        const values = [`${row.weekday}, ${row.date}`, row.net, row.target, row.balance]

        values.forEach((value, index) => {
            const cell = document.createElement('td')
            cell.textContent = value
            if (index === 3) cell.className = value.startsWith('+') ? 'demo-positive' : 'demo-negative'
            tr.append(cell)
        })

        const statusCell = document.createElement('td')
        statusCell.append(createStatus(row.status))
        tr.append(statusCell)
        body.append(tr)
    })
}

function renderHeatmap(): void {
    const heatmap = query<HTMLElement>('#month-heatmap')
    heatmap.replaceChildren()

    for (let day = 1; day <= 31; day += 1) {
        const date = new Date(2026, 7, day)
        const cell = document.createElement('div')
        const weekday = date.getDay()
        const isWeekend = weekday === 0 || weekday === 6
        const isFuture = day > 26
        const isWarning = day === 12 || day === 26

        cell.className = 'demo-heatmap-day'
        if (isWeekend) cell.classList.add('is-weekend')
        else if (isFuture) cell.classList.add('is-future')
        else if (isWarning) cell.classList.add('is-warning')
        else cell.classList.add('is-complete')
        cell.innerHTML = `<span>${day}</span><small>${isWeekend ? 'Frei' : isFuture ? '–' : isWarning ? 'Hinweis' : 'OK'}</small>`
        heatmap.append(cell)
    }
}

function renderTeam(): void {
    const body = query<HTMLTableSectionElement>('#team-table')
    body.replaceChildren()

    teamMembers.forEach((member) => {
        const row = document.createElement('tr')
        const identity = document.createElement('td')
        const identityWrap = document.createElement('div')
        identityWrap.className = 'demo-team-identity'
        const avatar = document.createElement('span')
        avatar.textContent = member.initials
        const name = document.createElement('strong')
        name.textContent = member.name
        identityWrap.append(avatar, name)
        identity.append(identityWrap)
        row.append(identity)

        ;[member.role, member.today, member.balance].forEach((value) => {
            const cell = document.createElement('td')
            cell.textContent = value
            row.append(cell)
        })

        const statusCell = document.createElement('td')
        statusCell.append(createStatus(member.status))
        row.append(statusCell)
        body.append(row)
    })
}

function formatDate(value: string): string {
    return new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' })
        .format(new Date(`${value}T12:00:00`))
}

function calculateDays(from: string, to: string): number {
    const start = new Date(`${from}T12:00:00`)
    const end = new Date(`${to}T12:00:00`)
    let days = 0

    for (const cursor = new Date(start); cursor <= end; cursor.setDate(cursor.getDate() + 1)) {
        if (cursor.getDay() !== 0 && cursor.getDay() !== 6) days += 1
    }

    return Math.max(0, days)
}

function requestCard(request: VacationRequest, includeActions = false): HTMLElement {
    const card = document.createElement('div')
    card.className = 'demo-request-card'
    const dates = document.createElement('div')
    dates.className = 'demo-request-dates'
    const strong = document.createElement('strong')
    strong.textContent = `${formatDate(request.from)} – ${formatDate(request.to)}`
    const detail = document.createElement('small')
    detail.textContent = `${request.days} Arbeitstage · ${request.reason || 'Keine Notiz'}`
    dates.append(strong, detail)
    card.append(dates, createStatus(request.status))

    if (includeActions && request.status === 'Ausstehend') {
        const actions = document.createElement('div')
        actions.className = 'demo-approval-actions'
        const approve = document.createElement('button')
        approve.className = 'btn btn-success'
        approve.type = 'button'
        approve.textContent = 'Genehmigen'
        approve.addEventListener('click', () => updateVacationStatus(request.id, 'Genehmigt'))
        const reject = document.createElement('button')
        reject.className = 'btn btn-secondary'
        reject.type = 'button'
        reject.textContent = 'Ablehnen'
        reject.addEventListener('click', () => updateVacationStatus(request.id, 'Abgelehnt'))
        actions.append(approve, reject)
        card.append(actions)
    }

    return card
}

function renderVacations(): void {
    const list = query<HTMLElement>('#vacation-list')
    const pending = state.vacationRequests.filter((request) => request.status === 'Ausstehend')
    const pendingDays = pending.reduce((total, request) => total + request.days, 0)
    list.replaceChildren(...state.vacationRequests.map((request) => requestCard(request)))
    query<HTMLElement>('#pending-days').textContent = String(pendingDays)
    query<HTMLElement>('#available-days').textContent = String(Math.max(0, 18 - pendingDays))
    query<HTMLElement>('#request-total').textContent = `${state.vacationRequests.length} Einträge`
    query<HTMLElement>('#vacation-count').textContent = String(pending.length)
    renderApprovals()
}

function renderApprovals(): void {
    const list = query<HTMLElement>('#approval-list')
    const pending = state.vacationRequests.filter((request) => request.status === 'Ausstehend')
    list.replaceChildren()

    if (pending.length === 0) {
        const empty = document.createElement('p')
        empty.className = 'demo-empty-state'
        empty.textContent = 'Keine offenen Freigaben.'
        list.append(empty)
        return
    }

    pending.forEach((request) => list.append(requestCard(request, true)))
}

function updateVacationStatus(id: number, status: VacationStatus): void {
    state.vacationRequests = state.vacationRequests.map((request) => request.id === id ? { ...request, status } : request)
    saveState()
    renderVacations()
    showToast(`Antrag #${id} wurde auf „${status}“ gesetzt.`)
}

function renderAudit(): void {
    const list = query<HTMLElement>('#audit-list')
    list.replaceChildren()

    auditEvents.forEach((event) => {
        const row = document.createElement('div')
        const time = document.createElement('time')
        time.textContent = event.time
        const content = document.createElement('p')
        const action = document.createElement('strong')
        action.textContent = event.action
        const context = document.createElement('small')
        context.textContent = `${event.actor} · ${event.context}`
        content.append(action, context)
        const marker = document.createElement('span')
        marker.textContent = 'gespeichert'
        row.append(time, content, marker)
        list.append(row)
    })
}

function downloadCsv(filename: string, rows: string[][]): void {
    const csv = rows.map((row) => row.map((value) => `"${value.replaceAll('"', '""')}"`).join(';')).join('\n')
    const blob = new Blob([`\uFEFF${csv}`], { type: 'text/csv;charset=utf-8' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = filename
    link.click()
    URL.revokeObjectURL(url)
    showToast(`${filename} wurde mit synthetischen Demodaten erstellt.`)
}

function openSidebar(): void {
    document.body.classList.add('demo-sidebar-open')
    query<HTMLButtonElement>('#mobile-menu').setAttribute('aria-expanded', 'true')
}

function closeSidebar(): void {
    document.body.classList.remove('demo-sidebar-open')
    query<HTMLButtonElement>('#mobile-menu').setAttribute('aria-expanded', 'false')
}

function bindEvents(): void {
    window.addEventListener('hashchange', applyRoute)
    query<HTMLButtonElement>('#mobile-menu').addEventListener('click', openSidebar)
    query<HTMLButtonElement>('#sidebar-backdrop').addEventListener('click', closeSidebar)

    query<HTMLSelectElement>('#role-select').addEventListener('change', (event) => {
        state.role = (event.currentTarget as HTMLSelectElement).value as DemoRole
        saveState()
        applyRole()
        showToast(`Ansicht auf ${state.role === 'manager' ? 'Manager' : 'Mitarbeiter'} gewechselt.`)
    })

    query<HTMLButtonElement>('#clock-toggle').addEventListener('click', () => {
        if (state.sessionStartedAt) {
            state.sessionStartedAt = null
            state.breakStartedAt = null
            setAction('Arbeitszeit wurde beendet.')
        } else {
            state.sessionStartedAt = new Date().toISOString()
            setAction('Arbeitszeit wurde gestartet.')
        }
        renderWorkday()
    })

    query<HTMLButtonElement>('#break-toggle').addEventListener('click', () => {
        if (!state.sessionStartedAt) return
        state.breakStartedAt = state.breakStartedAt ? null : new Date().toISOString()
        setAction(state.breakStartedAt ? 'Pause wurde gestartet.' : 'Pause wurde beendet.')
        renderWorkday()
    })

    query<HTMLFormElement>('#correction-form').addEventListener('submit', (event) => {
        event.preventDefault()
        setAction('Korrekturanfrage #K-204 wurde zur Prüfung eingereicht.')
        ;(event.currentTarget as HTMLFormElement).reset()
    })

    query<HTMLFormElement>('#vacation-form').addEventListener('submit', (event) => {
        event.preventDefault()
        const form = event.currentTarget as HTMLFormElement
        const data = new FormData(form)
        const from = String(data.get('from'))
        const to = String(data.get('to'))
        const days = calculateDays(from, to)

        if (!from || !to || days < 1 || new Date(from) > new Date(to)) {
            showToast('Bitte einen gültigen Urlaubszeitraum auswählen.')
            return
        }

        state.vacationRequests.unshift({
            id: Math.max(...state.vacationRequests.map((request) => request.id), 1043) + 1,
            from,
            to,
            days,
            reason: String(data.get('reason') || 'Erholungsurlaub'),
            status: 'Ausstehend',
        })
        saveState()
        renderVacations()
        showToast('Urlaubsantrag wurde lokal zur Demo hinzugefügt.')
        form.reset()
    })

    query<HTMLButtonElement>('#focus-vacation-form').addEventListener('click', () => {
        query<HTMLFormElement>('#vacation-form').scrollIntoView({ behavior: 'smooth', block: 'center' })
        query<HTMLInputElement>('#vacation-form input').focus({ preventScroll: true })
    })

    query<HTMLButtonElement>('#reset-demo').addEventListener('click', () => {
        state = createDefaultState()
        saveState()
        renderAll()
        showToast('Demo wurde auf den Ausgangszustand zurückgesetzt.')
    })

    query<HTMLButtonElement>('#export-time').addEventListener('click', () => {
        downloadCsv('arbeitszeit-demo-august-2026.csv', [
            ['Datum', 'Wochentag', 'Netto', 'Soll', 'Saldo', 'Status'],
            ...workdays.map((row) => [row.date, row.weekday, row.net, row.target, row.balance, row.status]),
        ])
    })

    query<HTMLButtonElement>('#export-compliance').addEventListener('click', () => {
        downloadCsv('compliance-demo-august-2026.csv', [
            ['Regel', 'Status', 'Hinweise'],
            ['Tägliche Höchstarbeitszeit', 'OK', '0'],
            ['Pausenregelung', 'Prüfen', '1'],
            ['Ruhezeit', 'OK', '0'],
        ])
    })
}

function renderAll(): void {
    applyRole()
    renderWorkday()
    renderWorkdayTable()
    renderHeatmap()
    renderTeam()
    renderVacations()
    renderAudit()
}

bindEvents()
renderAll()
