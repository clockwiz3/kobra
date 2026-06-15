// Typed-клиент вайтлиста. Без рефералов/лидерборда.

export interface JoinPayload {
  email: string
  name?: string
  locale?: string
  website?: string // honeypot
}

export interface JoinResult {
  ok: boolean
  message: string
  errors?: Record<string, string[]>
}

export interface WaitlistStatus {
  confirmed: boolean
  static_position: number | null
}

const JSON_HEADERS = { 'Content-Type': 'application/json', Accept: 'application/json' }

export async function joinWaitlist(payload: JoinPayload): Promise<JoinResult> {
  const res = await fetch('/api/waitlist/join', {
    method: 'POST',
    headers: JSON_HEADERS,
    body: JSON.stringify(payload),
  })
  const data = await res.json().catch(() => ({}))
  return { ok: res.ok, message: data.message ?? '', errors: data.errors }
}

export async function confirmWaitlist(token: string): Promise<{ ok: boolean; message: string }> {
  const res = await fetch(`/api/waitlist/confirm/${encodeURIComponent(token)}`, { headers: { Accept: 'application/json' } })
  const data = await res.json().catch(() => ({}))
  return { ok: res.ok, message: data.message ?? '' }
}

export async function getWaitlistStatus(token: string): Promise<WaitlistStatus | null> {
  const res = await fetch(`/api/waitlist/status/${encodeURIComponent(token)}`, { headers: { Accept: 'application/json' } })
  if (!res.ok) return null
  return res.json()
}
