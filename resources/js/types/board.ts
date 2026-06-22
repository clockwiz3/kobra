// Типы канбана (фронт). Вложенная форма (column.cards) удобна для vuedraggable;
// при синхронизации с API маппится в плоскую (columnId/position).

// Лимит длины названия карточки. Держать в синхроне с бэкендом
// (StoreCardRequest/UpdateCardRequest: title max:100).
export const CARD_TITLE_MAX = 100

export type CardAssignee = 'him' | 'her' | 'together'

// Слот исполнителя, привязанный к реальному аккаунту пары (без 'together' — это «оба»).
export type AssigneeSlot = 'him' | 'her'

// — «Внешний вид» пользователя в задачах (appearance) — рамка карточки —
export type BorderStyle = 'solid' | 'dashed' | 'dotted'

// Заливка рамки: сплошной цвет либо 2-точечный градиент с углом.
export type FramePaint =
  | { kind: 'solid'; color: string }
  | { kind: 'gradient'; from: string; to: string; angle: number }

// Полное описание рамки карточки. animated — «мерцание»: заливка (сплошная ИЛИ градиент)
// превращается в анимированный движущийся блик (см. useCardFrame).
export interface CardFrame {
  paint: FramePaint
  style: BorderStyle
  width: 1 | 2 | 3
  animated: boolean
}

// Per-user appearance. Пока единственная грань — рамка карточки; структура расширяемая.
export interface UserAppearance {
  card: CardFrame
}

// Участник пары: слот ↔ имя аккаунта + его appearance. Источник имён исполнителей (вместо «Он/Она»)
// и рамок карточек по исполнителю.
export interface BoardMember {
  slot: AssigneeSlot
  id: string
  name: string
  appearance?: UserAppearance | null
}

export type CardPriority = 'low' | 'normal' | 'high' | 'critical'

export interface Label {
  id: string
  name: string
  color?: string
}

export interface Card {
  id: string
  columnId?: string // исходная/текущая колонка (нужна бэклогу: показ и дефолт возврата). На доске вложенность даёт её неявно.
  title: string
  description?: string
  assignee: CardAssignee
  priority?: CardPriority // приоритет задачи (по умолчанию 'normal')
  labels: string[] // label ids
  dueAt?: string | null // одиночная дата либо начало диапазона ('YYYY-MM-DD' / ISO)
  dueEndAt?: string | null // конец диапазона (null = одиночная дата)
  dueTime?: string | null // время суток дедлайна 'HH:MM' (null = без времени)
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
  priority: CardPriority
  dueAt: string | null
  dueEndAt: string | null
  dueTime: string | null
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
