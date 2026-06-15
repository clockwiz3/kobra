import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { Card, CardAssignee, Column } from '@/types/board'
import { useLogger } from '@/composables/useLogger'

const log = useLogger('boardStore')

let seq = 0
const uid = (prefix: string): string => `${prefix}_${(seq += 1)}_${Math.round(performance.now())}`

function mockColumns(): Column[] {
  return [
    {
      id: 'col-ideas',
      title: 'Идеи',
      cards: [
        { id: 'card-sea', title: 'Поездка к морю', assignee: 'together', labels: [], dueAt: null, done: false },
      ],
    },
    {
      id: 'col-week',
      title: 'На неделе',
      cards: [
        { id: 'card-table', title: 'Забронировать столик', assignee: 'her', labels: [], dueAt: null, done: false },
        { id: 'card-flowers', title: 'Купить цветы', assignee: 'him', labels: [], dueAt: null, done: false },
      ],
    },
    {
      id: 'col-dates',
      title: 'Свидания',
      cards: [
        { id: 'card-dinner', title: 'Ужин при свечах', assignee: 'together', labels: [], dueAt: null, done: false },
      ],
    },
    {
      id: 'col-done',
      title: 'Готово',
      cards: [
        { id: 'card-movie', title: 'Выбрать фильм на вечер', assignee: 'together', labels: [], dueAt: null, done: true },
      ],
    },
  ]
}

export const useBoardStore = defineStore('board', () => {
  // Источник правды на этом этапе — мок-данные в памяти. Позже заменим на API (задача #23).
  const columns = ref<Column[]>(mockColumns())

  const totalCards = computed(() => columns.value.reduce((sum, c) => sum + c.cards.length, 0))

  function findCard(cardId: string): { column: Column; card: Card } | null {
    for (const column of columns.value) {
      const card = column.cards.find((c) => c.id === cardId)
      if (card) return { column, card }
    }
    return null
  }

  // ---- карточки ----
  function addCard(columnId: string, title: string, assignee: CardAssignee = 'together'): void {
    const column = columns.value.find((c) => c.id === columnId)
    if (!column || title.trim() === '') return
    column.cards.push({ id: uid('card'), title: title.trim(), assignee, labels: [], dueAt: null, done: false })
    log.debug('addCard', { columnId })
  }

  function updateCard(cardId: string, patch: Partial<Card>): void {
    const found = findCard(cardId)
    if (found) Object.assign(found.card, patch)
    log.debug('updateCard', { cardId })
  }

  function toggleDone(cardId: string): void {
    const found = findCard(cardId)
    if (!found) return
    found.card.done = !found.card.done
    log.debug('toggleDone', { cardId, done: found.card.done })
  }

  function removeCard(cardId: string): void {
    const found = findCard(cardId)
    if (!found) return
    found.column.cards = found.column.cards.filter((c) => c.id !== cardId)
    log.debug('removeCard', { cardId })
  }

  // ---- колонки ----
  function addColumn(title: string): void {
    if (title.trim() === '') return
    columns.value.push({ id: uid('col'), title: title.trim(), cards: [] })
    log.debug('addColumn')
  }

  function renameColumn(columnId: string, title: string): void {
    const column = columns.value.find((c) => c.id === columnId)
    if (column && title.trim() !== '') column.title = title.trim()
    log.debug('renameColumn', { columnId })
  }

  function removeColumn(columnId: string): void {
    columns.value = columns.value.filter((c) => c.id !== columnId)
    log.debug('removeColumn', { columnId })
  }

  return {
    columns,
    totalCards,
    findCard,
    addCard,
    updateCard,
    toggleDone,
    removeCard,
    addColumn,
    renameColumn,
    removeColumn,
  }
})
