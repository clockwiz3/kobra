// Клиентский логгер, уровень через VITE_LOG_LEVEL (в prod дефолт warn).

const LEVELS = { debug: 0, info: 1, warn: 2, error: 3 } as const
type Level = keyof typeof LEVELS

const configured = (import.meta.env.VITE_LOG_LEVEL as Level | undefined)
  ?? (import.meta.env.PROD ? 'warn' : 'debug')

export function useLogger(scope: string) {
  const emit = (level: Level, message: string, data?: unknown) => {
    if (LEVELS[level] < LEVELS[configured]) return
    const fn = console[level] ?? console.log
    fn(`[${scope}] ${message}`, data ?? '')
  }

  return {
    debug: (m: string, d?: unknown) => emit('debug', m, d),
    info: (m: string, d?: unknown) => emit('info', m, d),
    warn: (m: string, d?: unknown) => emit('warn', m, d),
    error: (m: string, d?: unknown) => emit('error', m, d),
  }
}
