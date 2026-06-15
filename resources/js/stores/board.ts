import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { Card, CardAssignee, Column } from '@/types/board'
import { boardApi, type ApiBoard, type ApiCard, type ApiColumn } from '@/api/board'
import { useLogger } from '@/composables/useLogger'

const log = useLogger('boardStore')

let seq = 0
const uid = (prefix: string): string => `${prefix}_${(seq += 1)}_${Math.round(performance.now())}`

function mockColumns(): Column[] {
  return [
    { id: 'col-ideas', title: 'Идеи', cards: [{ id: 'card-sea', title: 'Поездка к морю', assignee: 'together', labels: [], dueAt: null, done: false }] },
    { id: 'col-week', title: 'На неделе', cards: [
      { id: 'card-table', title: 'Забронировать столик', assignee: 'her', labels: [], dueAt: null, done: false },
      { id: 'card-flowers', title: 'Купить цветы', assignee: 'him', labels: [], dueAt: null, done: false },
    ] },
    { id: 'col-dates', title: 'Свидания', cards: [{ id: 'card-dinner', title: 'Ужин при свечах', assignee: 'together', labels: [], dueAt: null, done: false }] },
    { id: 'col-done', title: 'Готово', cards: [{ id: 'card-movie', title: 'Выбрать фильм на вечер', assignee: 'together', labels: [], dueAt: null, done: true }] },
  ]
}

function toCard(api: ApiCard): Card {
  return { id: String(api.id), title: api.title, description: api.description ?? undefined, assignee: api.assignee, labels: [], dueAt: api.dueAt, done: api.done }
}
function toColumn(api: ApiColumn): Column {
  return { id: String(api.id), title: api.title, cards: api.cards.map(toCard) }
}

export const useBoardStore = defineStore('board', () => {
  const columns = ref<Column[]>(mockColumns())
  const boardId = ref<number | null>(null)
  const backed = ref(false) // true = данные из API; false = мок (нет авторизованной пары)

  const totalCards = computed(() => columns.value.reduce((s, c) => s + c.cards.length, 0))

  function findCard(cardId: string): { column: Column; card: Card } | null {
    for (const column of columns.value) {
      const card = column.cards.find((c) => c.id === cardId)
      if (card) return { column, card }
    }
    return null
  }

  /** Загрузить доску с API; при 401/403 — остаёмся в мок-режиме. */
  async function fetchBoard(): Promise<void> {
    const board: ApiBoard | null = await boardApi.fetchBoard()
    if (board) {
      columns.value = board.columns.map(toColumn)
      boardId.value = board.id
      backed.value = true
      log.info('loaded from API', { boardId: board.id })
    } else {
      backed.value = false
      log.debug('not authenticated — mock mode')
    }
  }

  async function addCard(columnId: string, title: string, assignee: CardAssignee = 'together'): Promise<void> {
    const column = columns.value.find((c) => c.id === columnId)
    if (!column || title.trim() === '') return
    if (backed.value) {
      const api = await boardApi.createCard(Number(columnId), title.trim(), assignee)
      if (api) column.cards.push(toCard(api))
      else await fetchBoard()
    } else {
      column.cards.push({ id: uid('card'), title: title.trim(), assignee, labels: [], dueAt: null, done: false })
    }
    log.debug('addCard', { columnId })
  }

  async function toggleDone(cardId: string): Promise<void> {
    const found = findCard(cardId)
    if (!found) return
    found.card.done = !found.card.done // оптимистично
    if (backed.value) {
      const ok = await boardApi.toggleDone(Number(cardId))
      if (!ok) { found.card.done = !found.card.done; log.warn('toggleDone rollback', { cardId }) }
    }
    log.debug('toggleDone', { cardId, done: found.card.done })
  }

  async function removeCard(cardId: string): Promise<void> {
    const found = findCard(cardId)
    if (!found) return
    found.column.cards = found.column.cards.filter((c) => c.id !== cardId)
    if (backed.value) await boardApi.removeCard(Number(cardId))
  }

  /** Персист перемещения карточки (локально DnD уже подвинул). */
  async function persistCardMove(cardId: string, toColumnId: string, toIndex: number): Promise<void> {
    if (!backed.value) return
    const ok = await boardApi.moveCard(Number(cardId), Number(toColumnId), toIndex)
    if (!ok) await fetchBoard()
    log.debug('persistCardMove', { cardId, toColumnId, toIndex })
  }

  async function addColumn(title: string): Promise<void> {
    if (title.trim() === '') return
    if (backed.value && boardId.value !== null) {
      const api = await boardApi.addColumn(boardId.value, title.trim())
      if (api) columns.value.push(toColumn(api))
      else await fetchBoard()
    } else {
      columns.value.push({ id: uid('col'), title: title.trim(), cards: [] })
    }
    log.debug('addColumn')
  }

  async function renameColumn(columnId: string, title: string): Promise<void> {
    const column = columns.value.find((c) => c.id === columnId)
    if (!column || title.trim() === '') return
    column.title = title.trim()
    if (backed.value) await boardApi.renameColumn(Number(columnId), title.trim())
  }

  async function removeColumn(columnId: string): Promise<void> {
    columns.value = columns.value.filter((c) => c.id !== columnId)
    if (backed.value) await boardApi.removeColumn(Number(columnId))
  }

  /** Персист порядка колонок (локально DnD уже переставил). */
  async function persistColumnReorder(): Promise<void> {
    if (!backed.value) return
    await boardApi.reorderColumns(columns.value.map((c) => Number(c.id)))
    log.debug('persistColumnReorder')
  }

  return {
    columns, boardId, backed, totalCards, findCard,
    fetchBoard, addCard, toggleDone, removeCard, persistCardMove,
    addColumn, renameColumn, removeColumn, persistColumnReorder,
  }
})
