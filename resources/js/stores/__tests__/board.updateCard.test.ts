import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

// Мокаем typed-клиент: проверяем оптимистичный апдейт, откат и camel→snake-контракт вызова.
vi.mock('@/api/board', () => ({
  boardApi: {
    fetchBoard: vi.fn().mockResolvedValue(null),
    updateCard: vi.fn(),
    removeCard: vi.fn().mockResolvedValue(true),
  },
}))

import { useBoardStore } from '@/stores/board'
import { boardApi } from '@/api/board'
import type { Card } from '@/types/board'

const card = (id: string): Card => ({
  id, title: 'A', assignee: 'together', labels: [], dueAt: null, dueEndAt: null, done: false,
})

beforeEach(() => {
  setActivePinia(createPinia())
  vi.mocked(boardApi.updateCard).mockReset()
})

describe('boardStore.updateCard', () => {
  it('мок-режим: мутирует локально, API не дёргает', async () => {
    const store = useBoardStore() // backed=false по умолчанию
    await store.updateCard('card-sea', { title: 'Море', assignee: 'him' })

    const found = store.findCard('card-sea')!
    expect(found.card.title).toBe('Море')
    expect(found.card.assignee).toBe('him')
    expect(boardApi.updateCard).not.toHaveBeenCalled()
  })

  it('backed: вызывает API с числовым id и тем же патчем (camel-ключи)', async () => {
    const store = useBoardStore()
    store.columns = [{ id: 'c', title: 'C', isTerminal: false, cards: [card('5')] }]
    store.backed = true
    vi.mocked(boardApi.updateCard).mockResolvedValue({
      id: 5, columnId: 1, title: 'A', description: null, assignee: 'together',
      dueAt: '2026-06-20', dueEndAt: '2026-06-24', done: false, position: 0,
    })

    await store.updateCard('5', { dueAt: '2026-06-20', dueEndAt: '2026-06-24' })

    expect(boardApi.updateCard).toHaveBeenCalledWith(5, { dueAt: '2026-06-20', dueEndAt: '2026-06-24' })
    const updated = store.findCard('5')!.card
    expect(updated.dueAt).toBe('2026-06-20')
    expect(updated.dueEndAt).toBe('2026-06-24')
  })

  it('backed: при провале PATCH откатывает изменения', async () => {
    const store = useBoardStore()
    store.columns = [{ id: 'c', title: 'C', isTerminal: false, cards: [card('5')] }]
    store.backed = true
    vi.mocked(boardApi.updateCard).mockResolvedValue(null) // провал

    await store.updateCard('5', { title: 'Изменено' })

    expect(store.findCard('5')!.card.title).toBe('A') // откатилось к снапшоту
  })
})

describe('boardStore selectedCard', () => {
  it('openCard/closeCard управляют выбранной карточкой', () => {
    const store = useBoardStore()
    expect(store.selectedCard).toBeNull()

    store.openCard('card-sea')
    expect(store.selectedCardId).toBe('card-sea')
    expect(store.selectedCard?.id).toBe('card-sea')

    store.closeCard()
    expect(store.selectedCardId).toBeNull()
    expect(store.selectedCard).toBeNull()
  })

  it('removeCard сбрасывает выбор удалённой карточки', async () => {
    const store = useBoardStore()
    store.openCard('card-sea')
    await store.removeCard('card-sea')
    expect(store.selectedCardId).toBeNull()
  })
})
