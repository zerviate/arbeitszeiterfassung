import { reactive, watch } from 'vue'

export type Tone = 'success' | 'warning' | 'danger' | 'info' | 'muted'
export type RequestStatus = 'pending' | 'approved' | 'rejected' | 'cancelled'

export interface BreakEntry {
    start: string
    end: string | null
}

export interface WorkSession {
    start: string
    end: string | null
    breaks: BreakEntry[]
}

export interface Workday {
    userId: number
    date: string
    targetMinutes: number
    sessions: WorkSession[]
    status: string
    traffic: 'green' | 'yellow' | 'red' | 'gray'
    flags: string[]
    holiday?: string
    finalized: boolean
}

export interface Correction {
    id: number
    userId: number
    date: string
    status: RequestStatus
    requestedBy: string
    createdAt: string
    reason: string
    reviewNote: string
    sessions: WorkSession[]
}

export interface VacationRequest {
    id: number
    userId: number
    startDate: string
    endDate: string
    days: number
    status: RequestStatus
    requestedBy: string
    createdAt: string
    reason: string
    reviewNote: string
}

export interface SickLeave {
    key: string
    userId: number
    startDate: string
    endDate: string
    days: string[]
    note: string
    recordedBy: string
    createdAt: string
}

export interface Contract {
    id: number
    userId: number
    weeklyMinutes: number
    weekdays: number[]
    validFrom: string
    validTo: string | null
    active: boolean
}

export interface Holiday {
    id: number
    date: string
    name: string
    active: boolean
}

export interface VacationBalance {
    id: number
    userId: number
    year: number
    entitlement: number
    carryover: number
    adjustment: number
    note: string
}

export interface AuditEntry {
    id: number
    occurredAt: string
    actorId: number
    actor: string
    event: string
    subject: string
    before: Record<string, unknown> | null
    after: Record<string, unknown> | null
}

export interface DemoUser {
    id: number
    name: string
    email: string
    role: string
}

interface DemoState {
    version: number
    authenticated: boolean
    users: DemoUser[]
    workdays: Workday[]
    corrections: Correction[]
    vacations: VacationRequest[]
    sickLeaves: SickLeave[]
    contracts: Contract[]
    holidays: Holiday[]
    balances: VacationBalance[]
    audit: AuditEntry[]
}

const storageKey = 'arbeitszeiterfassung-exact-demo-v2'
const stateVersion = 4

function pad(value: number): string {
    return String(value).padStart(2, '0')
}

export function isoDate(value = new Date()): string {
    return `${value.getFullYear()}-${pad(value.getMonth() + 1)}-${pad(value.getDate())}`
}

export function isoMonth(value = new Date()): string {
    return isoDate(value).slice(0, 7)
}

export function shiftDate(days: number, source = new Date()): string {
    const value = new Date(source)
    value.setDate(value.getDate() + days)
    return isoDate(value)
}

export function localDateTime(date: string, time: string): string {
    return `${date}T${time}:00`
}

export function dateRange(start: string, end: string, weekdaysOnly = false): string[] {
    const dates: string[] = []
    const cursor = new Date(`${start}T12:00:00`)
    const limit = new Date(`${end}T12:00:00`)

    while (cursor <= limit) {
        if (!weekdaysOnly || (cursor.getDay() !== 0 && cursor.getDay() !== 6)) {
            dates.push(isoDate(cursor))
        }
        cursor.setDate(cursor.getDate() + 1)
    }

    return dates
}

function recentWeekday(offset: number): string {
    const cursor = new Date()
    let remaining = Math.abs(offset)
    const direction = offset < 0 ? -1 : 1

    while (remaining > 0) {
        cursor.setDate(cursor.getDate() + direction)
        if (cursor.getDay() !== 0 && cursor.getDay() !== 6) remaining -= 1
    }

    return isoDate(cursor)
}

function session(date: string, start: string, end: string | null, breaks: Array<[string, string | null]> = []): WorkSession {
    return {
        start: localDateTime(date, start),
        end: end ? localDateTime(date, end) : null,
        breaks: breaks.map(([breakStart, breakEnd]) => ({
            start: localDateTime(date, breakStart),
            end: breakEnd ? localDateTime(date, breakEnd) : null,
        })),
    }
}

function createSeed(): DemoState {
    const today = isoDate()
    const yesterday = recentWeekday(-1)
    const twoDaysAgo = recentWeekday(-2)
    const threeDaysAgo = recentWeekday(-3)
    const fourDaysAgo = recentWeekday(-4)
    const vacationStart = shiftDate(12)
    const vacationEnd = shiftDate(16)
    const approvedStart = shiftDate(-35)
    const approvedEnd = shiftDate(-31)
    const sickStart = shiftDate(-10)
    const sickEnd = shiftDate(-8)
    const year = new Date().getFullYear()
    const activeSessionStart = new Date(Date.now() - 2.5 * 60 * 60 * 1000).toISOString()
    const completedBreakStart = new Date(Date.now() - 75 * 60 * 1000).toISOString()
    const completedBreakEnd = new Date(Date.now() - 45 * 60 * 1000).toISOString()

    return {
        version: stateVersion,
        authenticated: true,
        users: [
            { id: 1, name: 'Portfolio Demo', email: 'test@example.com', role: 'Administrator' },
            { id: 2, name: 'Lara Becker', email: 'lara.becker@example.com', role: 'Mitarbeiterin' },
            { id: 3, name: 'Jonas Wolf', email: 'jonas.wolf@example.com', role: 'Mitarbeiter' },
            { id: 4, name: 'Mina Kaya', email: 'mina.kaya@example.com', role: 'Managerin' },
        ],
        workdays: [
            {
                userId: 1,
                date: today,
                targetMinutes: 480,
                sessions: [{
                    start: activeSessionStart,
                    end: null,
                    breaks: [{ start: completedBreakStart, end: completedBreakEnd }],
                }],
                status: 'Unvollständig',
                traffic: 'yellow',
                flags: ['Offene Session'],
                finalized: false,
            },
            {
                userId: 1,
                date: yesterday,
                targetMinutes: 480,
                sessions: [session(yesterday, '08:01', '16:47', [['12:14', '12:44']])],
                status: 'Erfüllt',
                traffic: 'green',
                flags: [],
                finalized: true,
            },
            {
                userId: 1,
                date: twoDaysAgo,
                targetMinutes: 480,
                sessions: [session(twoDaysAgo, '08:18', '16:18', [['12:10', '12:40']])],
                status: 'Leicht unter Soll',
                traffic: 'yellow',
                flags: [],
                finalized: false,
            },
            {
                userId: 1,
                date: threeDaysAgo,
                targetMinutes: 480,
                sessions: [session(threeDaysAgo, '07:52', '18:21', [['12:15', '12:45']])],
                status: 'Mehrarbeit',
                traffic: 'red',
                flags: ['Tägliche Höchstarbeitszeit überschritten'],
                finalized: false,
            },
            {
                userId: 1,
                date: fourDaysAgo,
                targetMinutes: 480,
                sessions: [session(fourDaysAgo, '08:07', '12:03'), session(fourDaysAgo, '12:34', '16:46')],
                status: 'Erfüllt',
                traffic: 'green',
                flags: ['Manuelle Korrektur'],
                finalized: true,
            },
            {
                userId: 2,
                date: today,
                targetMinutes: 360,
                sessions: [{ start: new Date(Date.now() - 5 * 60 * 60 * 1000).toISOString(), end: null, breaks: [{ start: new Date(Date.now() - 25 * 60 * 1000).toISOString(), end: null }] }],
                status: 'Unvollständig',
                traffic: 'yellow',
                flags: ['Offene Session'],
                finalized: false,
            },
            {
                userId: 4,
                date: today,
                targetMinutes: 0,
                sessions: [session(today, '07:45', '16:33', [['12:02', '12:32']])],
                status: 'Arbeit ohne Vertrag',
                traffic: 'yellow',
                flags: ['Fehlender Vertrag'],
                finalized: false,
            },
        ],
        corrections: [
            {
                id: 204,
                userId: 2,
                date: twoDaysAgo,
                status: 'pending',
                requestedBy: 'Lara Becker',
                createdAt: localDateTime(today, '09:14'),
                reason: 'Fehlende Ausstempelung ergänzen.',
                reviewNote: '',
                sessions: [session(twoDaysAgo, '08:18', '16:48', [['12:10', '12:40']])],
            },
            {
                id: 203,
                userId: 1,
                date: fourDaysAgo,
                status: 'approved',
                requestedBy: 'Portfolio Demo',
                createdAt: localDateTime(fourDaysAgo, '17:05'),
                reason: 'Zweite Session nachgetragen.',
                reviewNote: 'Zeiten mit Kalender abgeglichen.',
                sessions: [session(fourDaysAgo, '08:07', '12:03'), session(fourDaysAgo, '12:34', '16:46')],
            },
            {
                id: 202,
                userId: 3,
                date: recentWeekday(-6),
                status: 'rejected',
                requestedBy: 'Jonas Wolf',
                createdAt: localDateTime(recentWeekday(-5), '08:22'),
                reason: 'Arbeitsbeginn ändern.',
                reviewNote: 'Bitte Beleg ergänzen.',
                sessions: [session(recentWeekday(-6), '07:30', '16:00', [['12:00', '12:30']])],
            },
        ],
        vacations: [
            {
                id: 1043,
                userId: 2,
                startDate: vacationStart,
                endDate: vacationEnd,
                days: dateRange(vacationStart, vacationEnd).length,
                status: 'pending',
                requestedBy: 'Lara Becker',
                createdAt: localDateTime(today, '08:45'),
                reason: 'Erholungsurlaub',
                reviewNote: '',
            },
            {
                id: 1042,
                userId: 1,
                startDate: approvedStart,
                endDate: approvedEnd,
                days: dateRange(approvedStart, approvedEnd).length,
                status: 'approved',
                requestedBy: 'Portfolio Demo',
                createdAt: localDateTime(shiftDate(-50), '12:08'),
                reason: 'Sommerurlaub',
                reviewNote: 'Vertretung ist geklärt.',
            },
            {
                id: 1041,
                userId: 3,
                startDate: shiftDate(25),
                endDate: shiftDate(26),
                days: 2,
                status: 'rejected',
                requestedBy: 'Jonas Wolf',
                createdAt: localDateTime(shiftDate(-2), '15:31'),
                reason: 'Kurzurlaub',
                reviewNote: 'Überschneidung im Team.',
            },
            {
                id: 1040,
                userId: 4,
                startDate: shiftDate(-60),
                endDate: shiftDate(-59),
                days: 2,
                status: 'cancelled',
                requestedBy: 'Mina Kaya',
                createdAt: localDateTime(shiftDate(-80), '10:10'),
                reason: 'Privater Termin',
                reviewNote: '',
            },
        ],
        sickLeaves: [
            {
                key: 'SL-2026-0031',
                userId: 3,
                startDate: sickStart,
                endDate: sickEnd,
                days: dateRange(sickStart, sickEnd),
                note: 'Arbeitsunfähigkeitsbescheinigung liegt vor.',
                recordedBy: 'Portfolio Demo',
                createdAt: localDateTime(sickStart, '08:12'),
            },
            {
                key: 'SL-2026-0030',
                userId: 2,
                startDate: shiftDate(-42),
                endDate: shiftDate(-41),
                days: dateRange(shiftDate(-42), shiftDate(-41)),
                note: '',
                recordedBy: 'Portfolio Demo',
                createdAt: localDateTime(shiftDate(-42), '07:55'),
            },
        ],
        contracts: [
            { id: 1, userId: 1, weeklyMinutes: 2400, weekdays: [1, 2, 3, 4, 5], validFrom: `${year}-01-01`, validTo: null, active: true },
            { id: 2, userId: 2, weeklyMinutes: 1800, weekdays: [1, 2, 3, 4, 5], validFrom: `${year}-01-01`, validTo: null, active: true },
            { id: 3, userId: 3, weeklyMinutes: 2400, weekdays: [1, 2, 3, 4, 5], validFrom: `${year - 1}-01-01`, validTo: `${year}-12-31`, active: true },
            { id: 4, userId: 4, weeklyMinutes: 1920, weekdays: [1, 2, 3, 4], validFrom: `${year - 1}-06-01`, validTo: `${year}-06-30`, active: false },
        ],
        holidays: [
            { id: 1, date: `${year}-01-01`, name: 'Neujahr', active: true },
            { id: 2, date: `${year}-05-01`, name: 'Tag der Arbeit', active: true },
            { id: 3, date: `${year}-10-03`, name: 'Tag der Deutschen Einheit', active: true },
            { id: 4, date: `${year}-12-25`, name: '1. Weihnachtstag', active: true },
            { id: 5, date: `${year}-12-26`, name: '2. Weihnachtstag', active: false },
        ],
        balances: [
            { id: 1, userId: 1, year, entitlement: 30, carryover: 2, adjustment: 0, note: 'Standardanspruch' },
            { id: 2, userId: 2, year, entitlement: 28, carryover: 1.5, adjustment: 0, note: '' },
            { id: 3, userId: 3, year, entitlement: 30, carryover: 0, adjustment: -1, note: 'Korrektur Vorjahr' },
        ],
        audit: [
            {
                id: 901,
                occurredAt: localDateTime(today, '10:42'),
                actorId: 1,
                actor: 'Portfolio Demo',
                event: 'time.break.ended',
                subject: 'WorkSession #331',
                before: { break_ended_at: null },
                after: { break_ended_at: localDateTime(today, '10:42') },
            },
            {
                id: 900,
                occurredAt: localDateTime(today, '10:12'),
                actorId: 1,
                actor: 'Portfolio Demo',
                event: 'time.break.started',
                subject: 'WorkSession #331',
                before: null,
                after: { break_started_at: localDateTime(today, '10:12') },
            },
            {
                id: 899,
                occurredAt: localDateTime(today, '08:03'),
                actorId: 1,
                actor: 'Portfolio Demo',
                event: 'time.clocked_in',
                subject: 'WorkSession #331',
                before: null,
                after: { started_at: localDateTime(today, '08:03') },
            },
            {
                id: 898,
                occurredAt: localDateTime(shiftDate(-1), '16:22'),
                actorId: 4,
                actor: 'Mina Kaya',
                event: 'vacation.approved',
                subject: 'AbsenceRequest #1042',
                before: { status: 'pending' },
                after: { status: 'approved' },
            },
        ],
    }
}

function loadState(): DemoState {
    try {
        const raw = localStorage.getItem(storageKey)
        if (!raw) return createSeed()
        const parsed = JSON.parse(raw) as DemoState
        return parsed.version === stateVersion ? parsed : createSeed()
    } catch {
        return createSeed()
    }
}

export const demo = reactive(loadState()) as DemoState

watch(demo, (value) => {
    try {
        localStorage.setItem(storageKey, JSON.stringify(value))
    } catch {
        // The demo remains usable in browsers that block persistent storage.
    }
}, { deep: true })

export function resetDemo(): void {
    Object.assign(demo, createSeed())
}

export function userName(userId: number): string {
    return demo.users.find((user) => user.id === userId)?.name ?? `Benutzer #${userId}`
}

export function getWorkday(date: string, userId = 1): Workday | undefined {
    return demo.workdays.find((day) => day.date === date && day.userId === userId)
}

export function requestLabel(status: RequestStatus): string {
    return {
        pending: 'Offen',
        approved: 'Genehmigt',
        rejected: 'Abgelehnt',
        cancelled: 'Storniert',
    }[status]
}

export function requestTone(status: RequestStatus): Tone {
    return {
        pending: 'warning',
        approved: 'success',
        rejected: 'danger',
        cancelled: 'muted',
    }[status] as Tone
}

export function minutesBetween(start: string, end: string | null): number {
    const startTime = new Date(start).getTime()
    const endTime = end ? new Date(end).getTime() : Date.now()
    return Math.max(0, Math.floor((endTime - startTime) / 60000))
}

export function breakMinutes(entry: WorkSession): number {
    return entry.breaks.reduce((total, item) => total + minutesBetween(item.start, item.end), 0)
}

export function workdayMinutes(day: Workday): number {
    return day.sessions.reduce((total, entry) => total + Math.max(0, minutesBetween(entry.start, entry.end) - breakMinutes(entry)), 0)
}

export function formatMinutes(value: number, signed = false): string {
    const sign = value < 0 ? '-' : signed && value > 0 ? '+' : ''
    const absolute = Math.abs(Math.round(value))
    return `${sign}${Math.floor(absolute / 60)}h ${absolute % 60}m`
}

export function formatClockMinutes(value: number): string {
    const absolute = Math.abs(Math.round(value))
    return `${value < 0 ? '-' : ''}${pad(Math.floor(absolute / 60))}:${pad(absolute % 60)}`
}

export function formatDate(value: string): string {
    return new Intl.DateTimeFormat('de-DE').format(new Date(`${value.slice(0, 10)}T12:00:00`))
}

export function formatDateTime(value: string): string {
    return new Intl.DateTimeFormat('de-DE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value))
}

export function formatTime(value: string | null): string {
    if (!value) return '-'
    return new Intl.DateTimeFormat('de-DE', { hour: '2-digit', minute: '2-digit' }).format(new Date(value))
}

function nextId(values: Array<{ id: number }>): number {
    return Math.max(0, ...values.map((value) => value.id)) + 1
}

export function appendAudit(event: string, subject: string, before: Record<string, unknown> | null, after: Record<string, unknown> | null): void {
    demo.audit.unshift({
        id: nextId(demo.audit),
        occurredAt: new Date().toISOString(),
        actorId: 1,
        actor: 'Portfolio Demo',
        event,
        subject,
        before,
        after,
    })
}

export function clockIn(): boolean {
    const today = isoDate()
    let day = getWorkday(today)
    if (!day) {
        day = { userId: 1, date: today, targetMinutes: 480, sessions: [], status: 'Unvollständig', traffic: 'yellow', flags: [], finalized: false }
        demo.workdays.unshift(day)
    }
    if (day.sessions.some((entry) => entry.end === null)) return false
    day.sessions.push({ start: new Date().toISOString(), end: null, breaks: [] })
    day.status = 'Unvollständig'
    day.traffic = 'yellow'
    if (!day.flags.includes('Offene Session')) day.flags.push('Offene Session')
    appendAudit('time.clocked_in', `Workday ${today}`, null, { started_at: day.sessions.at(-1)?.start })
    return true
}

export function clockOut(): boolean {
    const day = getWorkday(isoDate())
    const current = day?.sessions.find((entry) => entry.end === null)
    if (!day || !current) return false
    const openBreak = current.breaks.find((entry) => entry.end === null)
    if (openBreak) openBreak.end = new Date().toISOString()
    current.end = new Date().toISOString()
    day.flags = day.flags.filter((flag) => flag !== 'Offene Session')
    const balance = workdayMinutes(day) - day.targetMinutes
    day.status = balance >= 0 ? 'Erfüllt' : balance >= -30 ? 'Leicht unter Soll' : 'Unter Soll'
    day.traffic = balance >= 0 ? 'green' : balance >= -30 ? 'yellow' : 'red'
    appendAudit('time.clocked_out', `Workday ${day.date}`, { ended_at: null }, { ended_at: current.end })
    return true
}

export function toggleBreak(): 'started' | 'ended' | null {
    const day = getWorkday(isoDate())
    const current = day?.sessions.find((entry) => entry.end === null)
    if (!current) return null
    const openBreak = current.breaks.find((entry) => entry.end === null)
    if (openBreak) {
        openBreak.end = new Date().toISOString()
        appendAudit('time.break.ended', `Workday ${day?.date}`, { ended_at: null }, { ended_at: openBreak.end })
        return 'ended'
    }
    current.breaks.push({ start: new Date().toISOString(), end: null })
    appendAudit('time.break.started', `Workday ${day?.date}`, null, { started_at: current.breaks.at(-1)?.start })
    return 'started'
}

export function finalizeWorkday(date: string, userId = 1): boolean {
    const day = getWorkday(date, userId)
    if (!day || day.sessions.some((entry) => entry.end === null)) return false
    day.finalized = true
    appendAudit('workday.finalized', `Workday ${date}`, { finalized: false }, { finalized: true })
    return true
}

export function saveCorrection(input: Omit<Correction, 'id' | 'status' | 'requestedBy' | 'createdAt' | 'reviewNote'>): Correction {
    const correction: Correction = {
        ...input,
        id: nextId(demo.corrections),
        status: 'pending',
        requestedBy: 'Portfolio Demo',
        createdAt: new Date().toISOString(),
        reviewNote: '',
    }
    demo.corrections.unshift(correction)
    appendAudit('correction.created', `TimeCorrection #${correction.id}`, null, { status: correction.status, date: correction.date })
    return correction
}

export function reviewCorrection(id: number, status: 'approved' | 'rejected', note: string): void {
    const correction = demo.corrections.find((item) => item.id === id)
    if (!correction || correction.status !== 'pending') return
    const before = correction.status
    correction.status = status
    correction.reviewNote = note
    appendAudit(`correction.${status}`, `TimeCorrection #${id}`, { status: before }, { status, review_note: note })
}

export function saveVacation(input: Omit<VacationRequest, 'id' | 'days' | 'status' | 'requestedBy' | 'createdAt' | 'reviewNote'>): VacationRequest {
    const request: VacationRequest = {
        ...input,
        id: nextId(demo.vacations),
        days: dateRange(input.startDate, input.endDate).length,
        status: 'pending',
        requestedBy: 'Portfolio Demo',
        createdAt: new Date().toISOString(),
        reviewNote: '',
    }
    demo.vacations.unshift(request)
    appendAudit('vacation.created', `AbsenceRequest #${request.id}`, null, { status: request.status })
    return request
}

export function reviewVacation(id: number, status: 'approved' | 'rejected' | 'cancelled', note = ''): void {
    const request = demo.vacations.find((item) => item.id === id)
    if (!request || request.status !== 'pending') return
    const before = request.status
    request.status = status
    request.reviewNote = note
    appendAudit(`vacation.${status}`, `AbsenceRequest #${id}`, { status: before }, { status, review_note: note })
}

export function saveSickLeave(input: Pick<SickLeave, 'userId' | 'startDate' | 'endDate' | 'note'>, key?: string): SickLeave {
    const existing = key ? demo.sickLeaves.find((item) => item.key === key) : undefined
    if (existing) {
        const before = { start_date: existing.startDate, end_date: existing.endDate, note: existing.note }
        existing.startDate = input.startDate
        existing.endDate = input.endDate
        existing.note = input.note
        existing.days = dateRange(input.startDate, input.endDate)
        appendAudit('sick_leave.updated', `SickLeaveGroup ${key}`, before, { start_date: input.startDate, end_date: input.endDate, note: input.note })
        return existing
    }
    const item: SickLeave = {
        ...input,
        key: `SL-${new Date().getFullYear()}-${String(demo.sickLeaves.length + 31).padStart(4, '0')}`,
        days: dateRange(input.startDate, input.endDate),
        recordedBy: 'Portfolio Demo',
        createdAt: new Date().toISOString(),
    }
    demo.sickLeaves.unshift(item)
    appendAudit('sick_leave.created', `SickLeaveGroup ${item.key}`, null, { start_date: item.startDate, end_date: item.endDate })
    return item
}

export function deleteSickLeave(key: string): void {
    const index = demo.sickLeaves.findIndex((item) => item.key === key)
    if (index < 0) return
    const [item] = demo.sickLeaves.splice(index, 1)
    appendAudit('sick_leave.deleted', `SickLeaveGroup ${key}`, { start_date: item.startDate, end_date: item.endDate }, null)
}

export function saveContract(input: Omit<Contract, 'id'>, id?: number): Contract {
    const existing = id ? demo.contracts.find((item) => item.id === id) : undefined
    if (existing) {
        const before = { weekly_minutes: existing.weeklyMinutes, active: existing.active }
        Object.assign(existing, input)
        appendAudit('contract.updated', `Contract #${id}`, before, { weekly_minutes: input.weeklyMinutes, active: input.active })
        return existing
    }
    const item = { ...input, id: nextId(demo.contracts) }
    demo.contracts.push(item)
    appendAudit('contract.created', `Contract #${item.id}`, null, { weekly_minutes: item.weeklyMinutes, active: item.active })
    return item
}

export function saveHoliday(input: Omit<Holiday, 'id'>, id?: number): Holiday {
    const existing = id ? demo.holidays.find((item) => item.id === id) : undefined
    if (existing) {
        const before = { date: existing.date, name: existing.name, active: existing.active }
        Object.assign(existing, input)
        appendAudit('holiday.updated', `Holiday #${id}`, before, input)
        return existing
    }
    const item = { ...input, id: nextId(demo.holidays) }
    demo.holidays.push(item)
    appendAudit('holiday.created', `Holiday #${item.id}`, null, input)
    return item
}

export function toggleHoliday(id: number): void {
    const item = demo.holidays.find((holiday) => holiday.id === id)
    if (!item) return
    const before = item.active
    item.active = !item.active
    appendAudit('holiday.updated', `Holiday #${id}`, { active: before }, { active: item.active })
}

export function deleteHoliday(id: number): void {
    const index = demo.holidays.findIndex((item) => item.id === id)
    if (index < 0) return
    const [item] = demo.holidays.splice(index, 1)
    appendAudit('holiday.deleted', `Holiday #${id}`, { date: item.date, name: item.name }, null)
}

export function saveBalance(input: Omit<VacationBalance, 'id'>, id?: number): VacationBalance {
    const existing = id ? demo.balances.find((item) => item.id === id) : undefined
    if (existing) {
        const before = { entitlement: existing.entitlement, carryover: existing.carryover, adjustment: existing.adjustment }
        Object.assign(existing, input)
        appendAudit('vacation_balance.updated', `VacationBalance #${id}`, before, input)
        return existing
    }
    const item = { ...input, id: nextId(demo.balances) }
    demo.balances.push(item)
    appendAudit('vacation_balance.created', `VacationBalance #${item.id}`, null, input)
    return item
}

export function downloadCsv(filename: string, rows: Array<Array<string | number>>): void {
    const csv = rows
        .map((row) => row.map((value) => `"${String(value).replaceAll('"', '""')}"`).join(';'))
        .join('\n')
    const blob = new Blob([`\uFEFF${csv}`], { type: 'text/csv;charset=utf-8' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = filename
    link.click()
    URL.revokeObjectURL(url)
}
