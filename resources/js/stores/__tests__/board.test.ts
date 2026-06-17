import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useBoardStore } from '@/stores/board'
import type { Card } from '@/types/board'

const card = (id: string): Card => ({ id, title: id, assignee: 'together', labels: [], dueAt: null, done: false })

describe('boardStore.removeColumn', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('keepCards переносит карточки в соседнюю колонку и удаляет колонку', async () => {
    const store = useBoardStore() // мок-режим: col-ideas, col-week(2), col-dates(1), col-done(1)
    const idx = store.columns.findIndex((c) => c.id === 'col-dates')
    const sibling = store.columns[idx - 1] // col-week
    const before = sibling.cards.length

    await store.removeColumn('col-dates', { keepCards: true })

    expect(store.columns.find((c) => c.id === 'col-dates')).toBeUndefined()
    expect(sibling.cards.length).toBe(before + 1)
    expect(sibling.cards.some((c) => c.id === 'card-dinner')).toBe(true)
  })

  it('без keepCards удаляет колонку вместе с карточками (каскад)', async () => {
    const store = useBoardStore()
    const totalBefore = store.totalCards // 5

    await store.removeColumn('col-week') // 2 карточки

    expect(store.columns.find((c) => c.id === 'col-week')).toBeUndefined()
    expect(store.totalCards).toBe(totalBefore - 2)
  })

  it('keepCards для единственной колонки откатывается к удалению', async () => {
    const store = useBoardStore()
    store.columns = [{ id: 'only', title: 'Одна', isTerminal: false, cards: [card('x')] }]

    await store.removeColumn('only', { keepCards: true })

    expect(store.columns).toHaveLength(0)
  })
})

// В мок-режиме готовность выводится локально из терминальности целевой колонки:
// перенос в «Готово» (terminal) → done, обратно → не done. Это роль бывшей галочки.
describe('boardStore.persistCardMove — done через терминальную колонку (мок)', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('перенос в терминальную колонку закрывает карточку', async () => {
    const store = useBoardStore()
    await store.persistCardMove('card-sea', 'col-done', 0)
    expect(store.findCard('card-sea')?.card.done).toBe(true)
  })

  it('перенос из терминальной в обычную возвращает в работу', async () => {
    const store = useBoardStore() // card-movie стартует в col-done (терминальной)
    await store.persistCardMove('card-movie', 'col-ideas', 0)
    expect(store.findCard('card-movie')?.card.done).toBe(false)
  })
})
