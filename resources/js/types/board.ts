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
  dueAt?: string | null // одиночная дата либо начало диапазона ('YYYY-MM-DD' / ISO)
  dueEndAt?: string | null // конец диапазона (null = одиночная дата)
  done: boolean
}

/**
 * Частичный патч редактируемых параметров карточки — единый контракт для
 * стора и API-клиента. Новый параметр карточки добавляется ЗДЕСЬ (масштабирование).
 */
export type CardPatch = Partial<{
  title: string
  description: string | null
  assignee: CardAssignee
  dueAt: string | null
  dueEndAt: string | null
}>

export interface Column {
  id: string
  title: string
  isTerminal: boolean // терминальная («Готово») колонка: попадание карточки = «выполнено», удалять нельзя
  cards: Card[]
}

export interface Board {
  id: string
  name: string
  columns: Column[]
}
