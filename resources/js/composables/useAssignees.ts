// Единый источник подписей исполнителя: имена реальных аккаунтов пары вместо обезличенных
// «Он/Она». Имена тянутся из стора доски (board.members, наполняется из API). 'together' —
// виртуальный слот «оба», подписан сердечком. Все места рендера исполнителя (карточка, бэклог,
// форма, фильтр доски) ходят сюда — единственная точка правды для лейблов.

import { computed } from 'vue'
import { useBoardStore } from '@/stores/board'
import type { CardAssignee } from '@/types/board'

export const TOGETHER_LABEL = '♥ Вместе'

// Фолбэк, пока участники пары не загружены (теоретический — стор инициализирован мок-парой).
const FALLBACK: Record<CardAssignee, string> = { him: 'Партнёр 1', her: 'Партнёр 2', together: TOGETHER_LABEL }

export function useAssignees() {
  const store = useBoardStore()

  // Слот (him/her/together) → подпись. Имена аккаунтов перекрывают фолбэк.
  const labelBySlot = computed<Record<CardAssignee, string>>(() => {
    const map = { ...FALLBACK }
    for (const m of store.members) map[m.slot] = m.name?.trim() || FALLBACK[m.slot]
    return map
  })

  /** Подпись исполнителя карточки (имя аккаунта или «♥ Вместе»). */
  const assigneeLabel = (a: CardAssignee): string => labelBySlot.value[a]

  /** Опции выбора исполнителя (радио/селект): him/her/together. Порядок стабилен (тесты по индексу). */
  const assigneeOptions = computed<Array<{ value: CardAssignee; label: string }>>(() => [
    { value: 'him', label: labelBySlot.value.him },
    { value: 'her', label: labelBySlot.value.her },
    { value: 'together', label: TOGETHER_LABEL },
  ])

  /** Опции фильтра доски по исполнителю (+ «Кто угодно»). */
  const assigneeFilterOptions = computed<Array<{ value: string; label: string }>>(() => [
    { value: 'all', label: 'Кто угодно' },
    ...assigneeOptions.value,
  ])

  return { assigneeLabel, assigneeOptions, assigneeFilterOptions }
}
