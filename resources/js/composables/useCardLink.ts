import { ref } from 'vue'
import { useLogger } from '@/composables/useLogger'

/**
 * Прямая ссылка на задачу (отдельная страница /task/:id) + копирование в буфер.
 * Используется в шапке drawer (клик по «#id») и на странице задачи (кнопка «поделиться»).
 * copied — реактивный флаг успеха (сам гаснет через ~1.6с) для UI-фидбэка «скопировано».
 */
export function useCardLink() {
  const log = useLogger('cardLink')
  const copied = ref(false)
  let timer: ReturnType<typeof setTimeout> | null = null

  /** Абсолютная ссылка на страницу задачи (origin берём из текущего окружения). */
  function linkFor(cardId: string): string {
    const origin = typeof window !== 'undefined' ? window.location.origin : ''
    return `${origin}/task/${cardId}`
  }

  /** Скопировать ссылку на задачу. Тихо логируем неуспех (нет clipboard / отказ доступа). */
  async function copy(cardId: string): Promise<void> {
    const url = linkFor(cardId)
    try {
      await navigator.clipboard.writeText(url)
      copied.value = true
      if (timer) clearTimeout(timer)
      timer = setTimeout(() => { copied.value = false }, 1600)
      log.debug('copied', { cardId })
    } catch {
      log.warn('copy failed — clipboard недоступен', { cardId })
    }
  }

  return { copied, copy, linkFor }
}
