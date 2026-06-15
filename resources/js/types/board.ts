// Типы канбана (фронт). Вложенная форма (column.cards) удобна для vuedraggable;
// при синхронизации с API маппится в плоскую (columnId/position).

export type CardAssignee = 'him' | 'her' | 'together'

export interface Label {
  id: string
  name: string
  color?: string
}

export interface Card {
  id: string
  title: string
  description?: string
  assignee: CardAssignee
  labels: string[] // label ids
  dueAt?: string | null
  done: boolean
}

export interface Column {
  id: string
  title: string
  cards: Card[]
}

export interface Board {
  id: string
  name: string
  columns: Column[]
}
