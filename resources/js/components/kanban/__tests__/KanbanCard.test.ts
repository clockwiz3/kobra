import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import KanbanCard from '@/components/kanban/KanbanCard.vue'
import { useBoardStore } from '@/stores/board'
import type { Card, CardPriority } from '@/types/board'

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

// teleport: true — меню карточки телепортится в .ds-root; стаб рендерит его инлайн (findable, без warn)
const mountCard = (card: Card) =>
  mount(KanbanCard, { props: { card }, global: { stubs: { teleport: true } } })

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

describe('KanbanCard — приоритет шевроном', () => {
  it('обычный приоритет (по умолчанию) → минус (.prio.is-normal)', () => {
    const w = mountCard(makeCard())
    expect(w.find('.prio').classes()).toContain('is-normal')
    expect(w.find('.prio').attributes('aria-label')).toBe('Обычный приоритет')
  })

  const cases: Array<[CardPriority, string, string]> = [
    ['low', 'is-low', 'Низкий приоритет'],
    ['high', 'is-high', 'Высокий приоритет'],
    ['critical', 'is-critical', 'Критический приоритет'],
  ]
  it.each(cases)('приоритет %s → класс .prio.%s', (priority, cls, label) => {
    const w = mountCard(makeCard({ priority }))
    expect(w.find('.prio').classes()).toContain(cls)
    expect(w.find('.prio').attributes('aria-label')).toBe(label)
  })
})

describe('KanbanCard — время на лице карточки', () => {
  it('время суток рендерится отдельным чипом', () => {
    const w = mountCard(makeCard({ dueTime: '19:30' }))
    const chips = w.findAll('.chip').map((c) => c.text())
    expect(chips).toContain('19:30')
  })

  it('без времени чип времени отсутствует', () => {
    const w = mountCard(makeCard())
    const chips = w.findAll('.chip').map((c) => c.text())
    expect(chips).not.toContain('19:30')
  })

  it('дата + время объединяются в один чип', () => {
    const w = mountCard(makeCard({ dueAt: '2026-06-20', dueTime: '19:30' }))
    expect(w.find('.chip.warn').text()).toBe('20 июня 19:30')
    // отдельного чипа только со временем больше нет
    expect(w.findAll('.chip').map((c) => c.text())).not.toContain('19:30')
  })
})

describe('KanbanCard — меню карточки (бейдж приоритета)', () => {
  it('клик по бейджу открывает меню с 3 пунктами и не открывает карточку', async () => {
    const w = mountCard(makeCard())
    expect(w.find('.card-menu').exists()).toBe(false)

    await w.find('.prio').trigger('click')

    expect(w.find('.card-menu').exists()).toBe(true)
    expect(w.findAll('.card-menu__item')).toHaveLength(3)
    expect(w.emitted('open')).toBeUndefined() // клик по бейджу не пробрасывается в открытие карточки
  })

  it('пункты вызывают экшены стора (вверх/вниз/убрать с доски)', async () => {
    const w = mountCard(makeCard({ id: 'c9' }))
    const edge = vi.spyOn(store, 'moveCardToEdge').mockResolvedValue()
    const off = vi.spyOn(store, 'removeFromBoard').mockResolvedValue()

    await w.find('.prio').trigger('click')
    await w.findAll('.card-menu__item')[0].trigger('click')
    expect(edge).toHaveBeenCalledWith('c9', 'top')

    await w.find('.prio').trigger('click') // меню закрылось после выбора — открыть снова
    await w.findAll('.card-menu__item')[1].trigger('click')
    expect(edge).toHaveBeenCalledWith('c9', 'bottom')

    await w.find('.prio').trigger('click')
    await w.findAll('.card-menu__item')[2].trigger('click')
    expect(off).toHaveBeenCalledWith('c9')
  })
})
