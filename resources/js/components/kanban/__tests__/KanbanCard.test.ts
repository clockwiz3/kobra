import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import KanbanCard from '@/components/kanban/KanbanCard.vue'
import { useBoardStore } from '@/stores/board'
import type { Card } from '@/types/board'

const makeCard = (over: Partial<Card> = {}): Card => ({
  id: 'c1', title: 'Задача', assignee: 'together', labels: [], dueAt: null, dueEndAt: null, done: false, ...over,
})

let store: ReturnType<typeof useBoardStore>

beforeEach(() => {
  vi.useFakeTimers({ toFake: ['Date'] })
  vi.setSystemTime(new Date('2026-06-17T12:00:00'))
  setActivePinia(createPinia())
  store = useBoardStore()
})
afterEach(() => vi.useRealTimers())

const mountCard = (card: Card) => mount(KanbanCard, { props: { card } })

describe('KanbanCard — контракт vuedraggable', () => {
  // Регрессия: ведущий комментарий в <template> делал компонент мульти-рутовым (fragment) в dev —
  // vuedraggable терял контекст элемента (getUnderlyingVm → null), дроп ломался. Корень — РОВНО
  // один элемент .task, без комментариев/текстовых узлов перед ним.
  it('рендерит единственный корневой элемент .task (без ведущих узлов)', () => {
    const w = mountCard(makeCard())
    expect(w.element.nodeType).toBe(1) // ELEMENT_NODE, не комментарий (8) и не фрагмент
    expect(w.element.tagName).toBe('DIV')
    expect(w.classes()).toContain('task')
  })
})

describe('KanbanCard — открытие панели', () => {
  it('клик по телу эмитит open', async () => {
    const w = mountCard(makeCard())
    await w.find('.task__body').trigger('click')
    expect(w.emitted('open')).toHaveLength(1)
  })

  it('чекбокс завершения убран (готовность — через колонку «Готово»)', () => {
    const w = mountCard(makeCard())
    expect(w.find('.check').exists()).toBe(false)
    expect(w.emitted('toggle-done')).toBeUndefined()
  })

  it('во время drag (store.dragging) клик по телу не открывает', async () => {
    const w = mountCard(makeCard())
    store.dragging = true
    await w.find('.task__body').trigger('click')
    expect(w.emitted('open')).toBeUndefined()
  })

  it('сдвиг указателя (перетаскивание) подавляет open', async () => {
    const w = mountCard(makeCard())
    const body = w.find('.task__body')
    await body.trigger('pointerdown', { clientX: 0, clientY: 0 })
    await body.trigger('pointermove', { clientX: 40, clientY: 0 })
    await body.trigger('click')
    expect(w.emitted('open')).toBeUndefined()
  })
})

describe('KanbanCard — дедлайн на лице карточки', () => {
  // дедлайн теперь единый чип tone="warn" (.chip.warn), а не отдельный .badge
  it('одиночная дата форматируется ru', () => {
    const w = mountCard(makeCard({ dueAt: '2026-06-20' }))
    expect(w.find('.chip.warn').text()).toBe('20 июня')
  })

  it('диапазон форматируется ru', () => {
    const w = mountCard(makeCard({ dueAt: '2026-06-17', dueEndAt: '2026-06-24' }))
    expect(w.find('.chip.warn').text()).toBe('17–24 июня')
  })

  it('без дедлайна warn-чип не рендерится', () => {
    const w = mountCard(makeCard())
    expect(w.find('.chip.warn').exists()).toBe(false)
  })
})
