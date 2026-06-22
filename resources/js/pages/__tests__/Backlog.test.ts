import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'

// Управляем через API-моки (как реально живёт страница: onMounted → fetchBoard + fetchBacklog).
// useConfirm мокаем (подтверждаем удаление без UI).
vi.mock('@/api/board', () => ({
  boardApi: {
    fetchBoard: vi.fn(),
    fetchBacklog: vi.fn(),
    createBacklogCard: vi.fn().mockResolvedValue(null),
    returnToBoard: vi.fn().mockResolvedValue(null),
    removeCard: vi.fn().mockResolvedValue(true),
  },
}))
const confirmMock = vi.fn().mockResolvedValue({ confirmed: true })
vi.mock('@/composables/useConfirm', () => ({ useConfirm: () => ({ confirm: confirmMock }) }))

import Backlog from '@/pages/Backlog.vue'
import { useBoardStore } from '@/stores/board'
import { boardApi, type ApiBoard, type ApiCard } from '@/api/board'

const apiCard = (id: number, columnId: number, title: string): ApiCard => ({
  id, columnId, title, description: null, assignee: 'together',
  priority: 'normal', dueAt: null, dueEndAt: null, dueTime: null, done: false, position: 0,
})

const boardWith = (columns: ApiBoard['columns']): ApiBoard => ({ id: 1, name: 'Доска', columns })
const ideasBoard = boardWith([{ id: 1, title: 'Идеи', position: 0, isTerminal: false, cards: [] }])

async function mountBacklog(backlog: ApiCard[], board: ApiBoard = ideasBoard) {
  setActivePinia(createPinia())
  vi.mocked(boardApi.fetchBoard).mockResolvedValue(board)
  vi.mocked(boardApi.fetchBacklog).mockResolvedValue(backlog)
  const store = useBoardStore()
  const w = mount(Backlog, {
    global: { plugins: [], stubs: { CardDetailDrawer: true, teleport: true } },
  })
  await flushPromises() // onMounted: fetchBoard + fetchBacklog
  return { w, store }
}

beforeEach(() => confirmMock.mockClear())

describe('Backlog.vue', () => {
  it('пустой бэклог → empty-state, без списка', async () => {
    const { w } = await mountBacklog([])
    expect(w.find('.backlog').exists()).toBe(false)
    expect(w.find('.stub-text').exists()).toBe(true)
  })

  it('рендерит список убранных карточек с исходной колонкой', async () => {
    const { w } = await mountBacklog([apiCard(9, 1, 'Поездка к морю')])
    const items = w.findAll('.bl-card')
    expect(items).toHaveLength(1)
    expect(items[0].text()).toContain('Поездка к морю')
  })

  it('форма добавления видна при пустом бэклоге и submit зовёт store.addBacklogCard', async () => {
    const { w, store } = await mountBacklog([])
    const spy = vi.spyOn(store, 'addBacklogCard').mockResolvedValue()

    const input = w.find('.bl-add input')
    expect(input.exists()).toBe(true)
    await input.setValue('Идея на потом')
    await w.find('.bl-add').trigger('submit')

    expect(spy).toHaveBeenCalledWith('Идея на потом')
  })

  it('«На доску» зовёт store.returnToBoard с дефолтной (исходной) колонкой', async () => {
    const { w, store } = await mountBacklog([apiCard(9, 1, 'Вернуть')])
    const spy = vi.spyOn(store, 'returnToBoard').mockResolvedValue()

    await w.findAll('button').find((b) => b.text() === 'На доску')!.trigger('click')

    expect(spy).toHaveBeenCalledWith('9', '1')
  })

  it('«Удалить» после подтверждения зовёт store.removeFromBacklog', async () => {
    const { w, store } = await mountBacklog([apiCard(9, 1, 'Удалить')])
    const spy = vi.spyOn(store, 'removeFromBacklog').mockResolvedValue()

    await w.findAll('button').find((b) => b.text() === 'Удалить')!.trigger('click')
    await flushPromises()

    expect(confirmMock).toHaveBeenCalled()
    expect(spy).toHaveBeenCalledWith('9')
  })
})
