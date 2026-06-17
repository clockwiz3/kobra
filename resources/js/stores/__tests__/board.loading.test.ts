import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

// Мокаем API-клиент: эти тесты про флаг первичной загрузки, не про сеть.
// vi.hoisted — чтобы ссылку на мок можно было использовать внутри vi.mock-фабрики.
const { fetchBoard } = vi.hoisted(() => ({ fetchBoard: vi.fn() }))
vi.mock('@/api/board', () => ({ boardApi: { fetchBoard } }))

import { useBoardStore } from '@/stores/board'

describe('boardStore.loading (скелетон первичной загрузки)', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    fetchBoard.mockReset()
  })

  // Регрессия: стор инициализируется мок-карточками (graceful-фолбэк). Без флага loading
  // они мигали на долю секунды до ответа fetchBoard — Board.vue теперь рендерит скелетон,
  // пока loading === true.
  it('стартует в true — до первого ответа показываем скелетон', () => {
    expect(useBoardStore().loading).toBe(true)
  })

  it('после успешного fetchBoard — false, данные из API', async () => {
    fetchBoard.mockResolvedValue({ id: 1, name: 'Доска', columns: [] })
    const store = useBoardStore()

    await store.fetchBoard()

    expect(store.loading).toBe(false)
    expect(store.backed).toBe(true)
  })

  it('после null (неавторизован/ошибка) — тоже false, остаёмся в мок-режиме', async () => {
    fetchBoard.mockResolvedValue(null)
    const store = useBoardStore()

    await store.fetchBoard()

    expect(store.loading).toBe(false)
    expect(store.backed).toBe(false)
  })
})
