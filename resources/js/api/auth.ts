// Typed-клиент авторизации (Sanctum personal access token). Паттерн как api/board.ts.
// Токен живёт в localStorage('cuploid.token') — его же читает boardApi.

import { useLogger } from '@/composables/useLogger'

const log = useLogger('authApi')
const TOKEN_KEY = 'cuploid.token'

export interface AuthUser {
  id: number
  name: string | null
  email: string | null
  avatar: string | null
}

export interface AuthSession {
  token: string
  user: AuthUser
}

const JSON_HEADERS = { 'Content-Type': 'application/json', Accept: 'application/json' }

export function getToken(): string | null {
  return localStorage.getItem(TOKEN_KEY)
}

function authHeaders(): HeadersInit {
  const token = getToken()
  return { ...JSON_HEADERS, ...(token ? { Authorization: `Bearer ${token}` } : {}) }
}

/** Логин. При успехе кладёт токен в localStorage и возвращает сессию; иначе null. */
export async function login(email: string, password: string): Promise<AuthSession | null> {
  log.debug('login')
  const res = await fetch('/api/login', { method: 'POST', headers: JSON_HEADERS, body: JSON.stringify({ email, password }) })
  if (!res.ok) {
    log.warn('login rejected', res.status)
    return null
  }
  const session = (await res.json()) as AuthSession
  localStorage.setItem(TOKEN_KEY, session.token)
  return session
}

/** Логаут: отозвать токен на сервере, в любом случае очистить локальный. */
export async function logout(): Promise<void> {
  try {
    await fetch('/api/logout', { method: 'POST', headers: authHeaders() })
  } catch (e) {
    log.warn('logout network', e)
  } finally {
    localStorage.removeItem(TOKEN_KEY)
  }
}

/** Текущий юзер по сохранённому токену; null если токена нет или он невалиден (401). */
export async function me(): Promise<AuthUser | null> {
  if (!getToken()) return null
  const res = await fetch('/api/user', { headers: authHeaders() })
  if (!res.ok) {
    log.debug('me unauthenticated', res.status)
    return null
  }
  return (await res.json()) as AuthUser
}
