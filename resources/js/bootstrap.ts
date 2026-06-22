// Echo-бутстрап под Laravel Reverb. Единственная точка инициализации real-time клиента.
// SPA авторизуется по Bearer-токену (Sanctum), а не по сессии → кастомный authorizer,
// который шлёт токен на /broadcasting/auth. Токен читаем напрямую из localStorage,
// чтобы НЕ импортировать api/auth (иначе цикл: api/auth тянет socketId() отсюда же).

import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { useLogger } from '@/composables/useLogger'

const log = useLogger('echo')
const TOKEN_KEY = 'cuploid.token'

// Структурно совпадает с pusher-js ChannelAuthorizationData (auth + опц. presence-данные).
type ChannelAuthData = { auth: string; channel_data?: string; shared_secret?: string }

let echo: Echo<'reverb'> | null = null

function token(): string | null {
  return localStorage.getItem(TOKEN_KEY)
}

/**
 * Инициализировать Echo (идемпотентно). Возвращает клиент или null, если нет токена
 * (гость) — подписка на couple-канал требует аутентификации.
 */
export function initEcho(): Echo<'reverb'> | null {
  if (echo) return echo
  if (!token()) return null

  // pusher-js нужен глобально как транспорт reverb-броадкастера.
  ;(window as unknown as { Pusher: typeof Pusher }).Pusher = Pusher

  echo = new Echo<'reverb'>({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
    wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
    enabledTransports: ['ws', 'wss'],
    // Кастомная авторизация приватного/presence-канала по Bearer-токену.
    authorizer: (channel: { name: string }) => ({
      authorize: (socketId: string, callback: (error: Error | null, data: ChannelAuthData | null) => void) => {
        fetch('/broadcasting/auth', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            ...(token() ? { Authorization: `Bearer ${token()}` } : {}),
          },
          body: JSON.stringify({ socket_id: socketId, channel_name: channel.name }),
        })
          .then((res) => (res.ok ? (res.json() as Promise<ChannelAuthData>) : Promise.reject(res)))
          .then((data) => callback(null, data))
          .catch(() => {
            log.warn('auth channel failed', channel.name)
            callback(new Error(`broadcast auth failed: ${channel.name}`), null)
          })
      },
    }),
  })

  log.debug('initialized')
  return echo
}

/** Текущий клиент Echo (или null, если не инициализирован). */
export function getEcho(): Echo<'reverb'> | null {
  return echo
}

/**
 * Socket-id текущего соединения — прокидывается заголовком X-Socket-ID в fetch-запросы,
 * чтобы broadcast()->toOthers() не возвращал событие инициатору (у него уже optimistic-update).
 */
export function socketId(): string | undefined {
  return echo?.socketId()
}

/** Разорвать соединение и сбросить клиент (на logout). */
export function teardownEcho(): void {
  if (echo) {
    echo.disconnect()
    echo = null
    log.debug('disconnected')
  }
}
