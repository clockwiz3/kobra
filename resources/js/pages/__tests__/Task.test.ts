import { describe, it, expect, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { setActivePinia, createPinia } from 'pinia'

// Карточку резолвим через стор (наполняется API-моками: fetchBoard + fetchBacklog).
vi.mock('@/api/board', () => ({
  boardApi: {
    fetchBoard: vi.fn(),
    fetchBacklog: vi.fn(),
  },
}))

import Task from '@/pages/Task.vue'
import { boardApi, type ApiBoard, type ApiCard } from '@/api/board'

const apiCard = (id: number, columnId: number, title: string, done = false): ApiCard => ({
  id, columnId, title, description: null, assignee: 'together',
  priority: 'normal', dueAt: null, dueEndAt: null, dueTime: null, done, position: 0,
})

const Blank = { template: '<div />' }

async function mountTask(opts: { board: ApiBoard; backlog?: ApiCard[]; id: string }) {
  setActivePinia(createPinia())
  vi.mocked(boardApi.fetchBoard).mockResolvedValue(opts.board)
  vi.mocked(boardApi.fetchBacklog).mockResolvedValue(opts.backlog ?? [])

  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'dashboard', component: Blank },
      { path: '/board', name: 'board', component: Blank },
      { path: '/task/:id', name: 'task', component: Task },
    ],
  })
  router.push(`/task/${opts.id}`)
  await router.isReady()

  const w = mount(Task, {
    global: { plugins: [router], stubs: { CardDetailForm: true } },
  })
  await flushPromises() // onMounted: fetchBoard + fetchBacklog
  return { w }
}

const boardWithCard = (card: ApiCard, isTerminal = false): ApiBoard => ({
  id: 1, coupleId: 1, name: 'Доска', members: [],
  columns: [{ id: card.columnId, title: 'Идеи', position: 0, isTerminal, cards: [card] }],
})

describe('Task.vue', () => {
  it('карточка на доске → показывает название, #id и статус-колонку', async () => {
    const { w } = await mountTask({ board: boardWithCard(apiCard(9, 1, 'Поездка к морю')), id: '9' })

    expect(w.find('.task-page__title').text()).toBe('Поездка к морю')
    expect(w.find('.task-page__id').text()).toContain('#9')
    expect(w.find('.task-page__status').text()).toContain('Идеи')
    expect(w.findComponent({ name: 'CardDetailForm' }).exists()).toBe(true)
  })

  it('карточка done в терминальной колонке → бейдж «Выполнено»', async () => {
    const { w } = await mountTask({ board: boardWithCard(apiCard(9, 4, 'Готовая', true), true), id: '9' })
    expect(w.find('.task-page__status').text()).toContain('Выполнено')
  })

  it('карточка в бэклоге → статус «В бэклоге»', async () => {
    const emptyBoard: ApiBoard = { id: 1, coupleId: 1, name: 'Доска', members: [], columns: [{ id: 1, title: 'Идеи', position: 0, isTerminal: false, cards: [] }] }
    const { w } = await mountTask({ board: emptyBoard, backlog: [apiCard(9, 1, 'Из бэклога')], id: '9' })
    expect(w.find('.task-page__status').text()).toContain('В бэклоге')
  })

  it('нет такой карточки → сообщение «не найдена»', async () => {
    const emptyBoard: ApiBoard = { id: 1, coupleId: 1, name: 'Доска', members: [], columns: [] }
    const { w } = await mountTask({ board: emptyBoard, id: '777' })
    expect(w.text()).toContain('не найдена')
  })
})
