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

// Фильтры доски: по исполнителю и по дедлайну (далёкие даты — без зависимости от часов).
describe('boardStore.cardMatchesFilter', () => {
  beforeEach(() => setActivePinia(createPinia()))
  const mk = (over: Partial<Card> = {}): Card => ({ id: 'x', title: 't', assignee: 'together', labels: [], dueAt: null, dueEndAt: null, done: false, ...over })

  it('фильтр по исполнителю', () => {
    const store = useBoardStore()
    store.filterAssignee = 'him'
    expect(store.cardMatchesFilter(mk({ assignee: 'him' }))).toBe(true)
    expect(store.cardMatchesFilter(mk({ assignee: 'her' }))).toBe(false)
  })

  it('фильтр по дедлайну: с дедлайном / без / просроченные', () => {
    const store = useBoardStore()
    store.filterDeadline = 'has'
    expect(store.cardMatchesFilter(mk({ dueAt: '2999-01-01' }))).toBe(true)
    expect(store.cardMatchesFilter(mk({ dueAt: null }))).toBe(false)

    store.filterDeadline = 'none'
    expect(store.cardMatchesFilter(mk({ dueAt: null }))).toBe(true)

    store.filterDeadline = 'overdue'
    expect(store.cardMatchesFilter(mk({ dueAt: '2000-01-01' }))).toBe(true)
    expect(store.cardMatchesFilter(mk({ dueAt: '2999-01-01' }))).toBe(false)
    expect(store.cardMatchesFilter(mk({ dueAt: '2000-01-01', done: true }))).toBe(false) // выполненная не «просрочена»
  })

  it('исполнитель И дедлайн комбинируются', () => {
    const store = useBoardStore()
    store.filterAssignee = 'her'
    store.filterDeadline = 'has'
    expect(store.cardMatchesFilter(mk({ assignee: 'her', dueAt: '2999-01-01' }))).toBe(true)
    expect(store.cardMatchesFilter(mk({ assignee: 'her', dueAt: null }))).toBe(false)
    expect(store.cardMatchesFilter(mk({ assignee: 'him', dueAt: '2999-01-01' }))).toBe(false)
  })
})

// «Готово» всегда последняя: новая колонка встаёт перед терминальной.
describe('boardStore.addColumn — вставка перед терминальной', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('новая колонка — предпоследняя, «Готово» остаётся последней', async () => {
    const store = useBoardStore() // мок: …, col-done (isTerminal) последняя
    await store.addColumn('Новая')
    const cols = store.columns
    expect(cols[cols.length - 1].isTerminal).toBe(true) // «Готово» последняя
    expect(cols[cols.length - 2].title).toBe('Новая')   // новая — перед ней
  })
})
