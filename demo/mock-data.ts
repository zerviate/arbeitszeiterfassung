export type DemoRole = 'employee' | 'manager'

export type VacationStatus = 'Ausstehend' | 'Genehmigt' | 'Abgelehnt'

export interface VacationRequest {
    id: number
    from: string
    to: string
    days: number
    reason: string
    status: VacationStatus
}

export interface DemoState {
    role: DemoRole
    sessionStartedAt: string | null
    breakStartedAt: string | null
    vacationRequests: VacationRequest[]
    lastAction: string
}

export interface WorkdayRow {
    date: string
    weekday: string
    net: string
    target: string
    balance: string
    status: 'OK' | 'Hinweis' | 'Fehltag'
}

export interface TeamMember {
    initials: string
    name: string
    role: string
    today: string
    balance: string
    status: 'Aktiv' | 'Pause' | 'Abwesend'
}

export const workdays: WorkdayRow[] = [
    { date: '26.08.2026', weekday: 'Mi', net: '07:42', target: '08:00', balance: '-00:18', status: 'Hinweis' },
    { date: '25.08.2026', weekday: 'Di', net: '08:16', target: '08:00', balance: '+00:16', status: 'OK' },
    { date: '24.08.2026', weekday: 'Mo', net: '08:04', target: '08:00', balance: '+00:04', status: 'OK' },
    { date: '21.08.2026', weekday: 'Fr', net: '07:58', target: '08:00', balance: '-00:02', status: 'OK' },
    { date: '20.08.2026', weekday: 'Do', net: '08:31', target: '08:00', balance: '+00:31', status: 'OK' },
]

export const teamMembers: TeamMember[] = [
    { initials: 'PD', name: 'Portfolio Demo', role: 'Entwicklung', today: '06:24', balance: '+04:18', status: 'Aktiv' },
    { initials: 'LB', name: 'Lara Becker', role: 'Design', today: '05:51', balance: '+01:42', status: 'Pause' },
    { initials: 'JW', name: 'Jonas Wolf', role: 'Support', today: '00:00', balance: '-00:36', status: 'Abwesend' },
    { initials: 'MK', name: 'Mina Kaya', role: 'Projektleitung', today: '07:03', balance: '+06:09', status: 'Aktiv' },
]

export const auditEvents = [
    { time: '10:42', actor: 'Portfolio Demo', action: 'Pause beendet', context: 'Arbeitszeit · Heute' },
    { time: '10:12', actor: 'Portfolio Demo', action: 'Pause gestartet', context: 'Arbeitszeit · Heute' },
    { time: '08:03', actor: 'Portfolio Demo', action: 'Eingestempelt', context: 'Arbeitszeit · Heute' },
    { time: 'Gestern', actor: 'Mina Kaya', action: 'Urlaub genehmigt', context: 'Antrag #1042' },
]

export function createDefaultState(): DemoState {
    return {
        role: 'manager',
        sessionStartedAt: new Date(Date.now() - 2 * 60 * 60 * 1000 - 37 * 60 * 1000).toISOString(),
        breakStartedAt: null,
        vacationRequests: [
            {
                id: 1043,
                from: '2026-09-14',
                to: '2026-09-18',
                days: 5,
                reason: 'Erholungsurlaub',
                status: 'Ausstehend',
            },
            {
                id: 1042,
                from: '2026-07-20',
                to: '2026-07-24',
                days: 5,
                reason: 'Sommerurlaub',
                status: 'Genehmigt',
            },
        ],
        lastAction: 'Demo mit synthetischen Daten geladen.',
    }
}
