// Typed-клиент профиля текущего юзера (Identity). Паттерн как api/auth.ts: Bearer-токен из
// localStorage, при провале → null (стор остаётся в прежнем состоянии). PATCH /api/user обновляет
// имя и/или appearance (рамку карточек) и возвращает свежий AuthUser.

import type { UserAppearance } from '@/types/board'
import type { AuthUser } from '@/api/auth'
import { useLogger } from '@/composables/useLogger'
import { socketId } from '@/bootstrap'

const log = useLogger('profileApi')
const TOKEN_KEY = 'cuploid.token'

const JSON_HEADERS = { 'Content-Type': 'application/json', Accept: 'application/json' }

function authHeaders(): HeadersInit {
  const token = localStorage.getItem(TOKEN_KEY)
  const sid = socketId() // X-Socket-ID → апдейт профиля не вернётся инициатору
  return {
    ...JSON_HEADERS,
    ...(token ? { Authorization: `Bearer ${token}` } : {}),
    ...(sid ? { 'X-Socket-Id': sid } : {}),
  }
}

export interface ProfilePatch {
  name?: string
  appearance?: UserAppearance
}

/** Обновить профиль (частично). При успехе возвращает обновлённого юзера; иначе null. */
export async function updateProfile(patch: ProfilePatch): Promise<AuthUser | null> {
  log.debug('updateProfile', { keys: Object.keys(patch) })
  const res = await fetch('/api/user', { method: 'PATCH', headers: authHeaders(), body: JSON.stringify(patch) })
  if (!res.ok) {
    log.warn('updateProfile failed', res.status)
    return null
  }
  return (await res.json()) as AuthUser
}
