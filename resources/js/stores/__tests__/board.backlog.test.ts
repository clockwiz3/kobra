import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

// Мокаем typed-клиент: проверяем загрузку бэклога, оптимистичный возврат на доску и удаление.
vi.mock('@/api/board', () => ({
  boardApi: {
    fetchBoard: vi.fn().mockResolvedValue(null),
    fetchBacklog: vi.fn(),
    createBacklogCard: vi.fn(),
    returnToBoard: vi.fn(),
    removeCard: vi.fn().mockResolvedValue(true),
  },
}))

import { useBoardStore } from '@/stores/board'
import { boardApi } from '@/api/board'
import type { ApiCard } from '@/api/board'

const apiCard = (id: number, columnId: number, title = 'A'): ApiCard => ({
  id, columnId, title, description: null, assignee: 'together',
  priority: 'normal', dueAt: null, dueEndAt: null, dueTime: null, done: false, position: 0,
})

beforeEach(() => {
  setActivePinia(createPinia())
  vi.mocked(boardApi.fetchBacklog).mockReset()
  vi.mocked(boardApi.createBacklogCard).mockReset()
  vi.mocked(boardApi.returnToBoard).mockReset()
})

describe('boardStore.fetchBacklog', () => {
  it('backed: наполняет backlogCards из API (с columnId)', async () => {
    const store = useBoardStore()
    store.backed = true
    vi.mocked(boardApi.fetchBacklog).mockResolvedValue([apiCard(7, 3, 'Убрана')])

    await store.fetchBacklog()

    expect(store.backlogCards).toHaveLength(1)
    expect(store.backlogCards[0]).toMatchObject({ id: '7', columnId: '3', title: 'Убрана' })
  })

  it('мок-режим (backed=false): бэклог пуст, API не дёргается', async () => {
    const store = useBoardStore()
    await store.fetchBacklog()

    expect(store.backlogCards).toEqual([])
    expect(boardApi.fetchBacklog).not.toHaveBeenCalled()
  })
})

describe('boardStore.addBacklogCard', () => {
  it('backed: создаёт карточку через API и кладёт её в начало бэклога', async () => {
    const store = useBoardStore()
    store.backed = true
    vi.mocked(boardApi.createBacklogCard).mockResolvedValue(apiCard(12, 1, 'Новая'))

    await store.addBacklogCard('Новая')

    expect(boardApi.createBacklogCard).toHaveBeenCalledWith('Новая', 'together')
    expect(store.backlogCards.map((c) => c.id)).toEqual(['12'])
  })

  it('мок-режим: добавляет локально, API не дёргает', async () => {
    const store = useBoardStore()
    await store.addBacklogCard('Локальная')

    expect(store.backlogCards).toHaveLength(1)
    expect(store.backlogCards[0].title).toBe('Локальная')
    expect(boardApi.createBacklogCard).not.toHaveBeenCalled()
  })

  it('пустой/пробельный заголовок игнорируется', async () => {
    const store = useBoardStore()
    await store.addBacklogCard('   ')
    expect(store.backlogCards).toHaveLength(0)
  })
})

describe('boardStore.findCard видит бэклог', () => {
  it('находит карточку из backlogCards (column=null) — для редактирования через drawer', () => {
    const store = useBoardStore()
    store.backlogCards = [{ id: '9', columnId: '1', title: 'Из бэклога', assignee: 'her', labels: [], dueAt: null, done: false }]

    const found = store.findCard('9')
    expect(found).not.toBeNull()
    expect(found!.card.id).toBe('9')
    expect(found!.column).toBeNull()
  })
})

describe('boardStore.returnToBoard', () => {
  it('backed: переносит карточку из бэклога в конец колонки и зовёт API', async () => {
    const store = useBoardStore()
    store.backed = true
    store.columns = [{ id: '1', title: 'Идеи', isTerminal: false, cards: [] }]
    store.backlogCards = [{ id: '9', columnId: '1', title: 'Вернётся', assignee: 'together', labels: [], dueAt: null, done: false }]
    vi.mocked(boardApi.returnToBoard).mockResolvedValue(apiCard(9, 1, 'Вернётся'))

    await store.returnToBoard('9', '1')

    expect(boardApi.returnToBoard).toHaveBeenCalledWith(9, 1)
    expect(store.backlogCards).toHaveLength(0)
    expect(store.columns[0].cards.map((c) => c.id)).toEqual(['9'])
  })

  it('возврат в терминальную колонку выставляет done=true (оптимистично)', async () => {
    const store = useBoardStore() // backed=false → без API, чистый оптимизм
    store.columns = [{ id: '4', title: 'Готово', isTerminal: true, cards: [] }]
    store.backlogCards = [{ id: '9', columnId: '1', title: 'В готово', assignee: 'together', labels: [], dueAt: null, done: false }]

    await store.returnToBoard('9', '4')

    expect(store.columns[0].cards[0].done).toBe(true)
  })
})

describe('boardStore.removeFromBacklog', () => {
  it('backed: убирает карточку из бэклога и зовёт DELETE', async () => {
    const store = useBoardStore()
    store.backed = true
    store.backlogCards = [{ id: '9', columnId: '1', title: 'Удалить', assignee: 'together', labels: [], dueAt: null, done: false }]

    await store.removeFromBacklog('9')

    expect(store.backlogCards).toHaveLength(0)
    expect(boardApi.removeCard).toHaveBeenCalledWith(9)
  })
})
