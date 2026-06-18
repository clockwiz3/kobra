import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import KanbanColumn from '@/components/kanban/KanbanColumn.vue'
import { useBoardStore } from '@/stores/board'
import type { Column } from '@/types/board'

// Лёгкий стаб vuedraggable: рендерит slot-item и даёт эмитить start/end вручную.
const DraggableStub = {
  name: 'draggable',
  template: '<div><slot name="item" :element="{ id: 1, title: \'x\', assignee: \'together\', labels: [], dueAt: null, done: false }" /></div>',
}

const column = (over: Partial<Column> = {}): Column => ({ id: 'c1', title: 'Колонка', isTerminal: false, cards: [], ...over })

function mountColumn(over: Partial<Column> = {}) {
  return mount(KanbanColumn, {
    props: { column: column(over) },
    global: { stubs: { draggable: DraggableStub } },
  })
}

describe('KanbanColumn drag lifecycle', () => {
  beforeEach(() => setActivePinia(createPinia()))

  // Регрессия: при переносе карточки мигали скроллбары в принимающей колонке.
  // Гасит их флаг store.dragging (CSS .is-dragging клиппит overflow на время drag) —
  // тест охраняет проводку @start/@end → store.dragging.
  it('start/end переключают общий флаг store.dragging', () => {
    const store = useBoardStore()
    const wrapper = mountColumn()
    const dr = wrapper.findComponent(DraggableStub)

    expect(store.dragging).toBe(false)

    dr.vm.$emit('start')
    expect(store.dragging).toBe(true)

    dr.vm.$emit('end')
    expect(store.dragging).toBe(false)
  })
})

describe('KanbanColumn — терминальная колонка («Готово»)', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('обычная колонка: кнопка удаления активна, есть форма добавления', () => {
    const w = mountColumn({ isTerminal: false })
    const del = w.find('[aria-label="Удалить колонку"]')
    expect(del.exists()).toBe(true)
    expect(del.classes()).not.toContain('is-invisible')
    expect(w.find('.kanban-col__add').exists()).toBe(true)
  })

  it('терминальная колонка: кнопка удаления скрыта (место сохранено), формы добавления нет', () => {
    const w = mountColumn({ isTerminal: true })
    const del = w.find('[aria-label="Удалить колонку"]')
    expect(del.exists()).toBe(true) // место под кнопку сохранено для выравнивания
    expect(del.classes()).toContain('is-invisible') // но скрыта
    expect(w.find('.kanban-col__add').exists()).toBe(false)
  })
})
