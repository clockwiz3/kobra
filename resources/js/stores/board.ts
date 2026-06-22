import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { CARD_TITLE_MAX, type AssigneeSlot, type BoardMember, type Card, type CardAssignee, type CardPatch, type Column, type UserAppearance } from '@/types/board'
import { boardApi, type ApiBoard, type ApiCard, type ApiColumn } from '@/api/board'
import { isOverdue } from '@/lib/date'
import { useLogger } from '@/composables/useLogger'

const log = useLogger('boardStore')

let seq = 0
const uid = (prefix: string): string => `${prefix}_${(seq += 1)}_${Math.round(performance.now())}`

function mockColumns(): Column[] {
  return [
    { id: 'col-ideas', title: 'Идеи', isTerminal: false, cards: [{ id: 'card-sea', title: 'Поездка к морю', assignee: 'together', labels: [], dueAt: null, done: false }] },
    { id: 'col-week', title: 'На неделе', isTerminal: false, cards: [
      { id: 'card-table', title: 'Забронировать столик', assignee: 'her', labels: [], dueAt: null, done: false },
      { id: 'card-flowers', title: 'Купить цветы', assignee: 'him', labels: [], dueAt: null, done: false },
    ] },
    { id: 'col-dates', title: 'Свидания', isTerminal: false, cards: [{ id: 'card-dinner', title: 'Ужин при свечах', assignee: 'together', labels: [], dueAt: null, done: false }] },
    { id: 'col-done', title: 'Готово', isTerminal: true, cards: [{ id: 'card-movie', title: 'Выбрать фильм на вечер', assignee: 'together', labels: [], dueAt: null, done: true }] },
  ]
}

// Участники мок-пары (демо/неавторизованный режим) — подписи исполнителя соответствуют мок-доске.
// В backed-режиме перетираются реальными аккаунтами из ответа fetchBoard.
function mockMembers(): BoardMember[] {
  return [
    { slot: 'her', id: 'm-her', name: 'Леся', appearance: { card: { paint: { kind: 'solid', color: '#e84aa6' }, style: 'solid', width: 2, animated: false } } },
    { slot: 'him', id: 'm-him', name: 'Серёжа', appearance: { card: { paint: { kind: 'gradient', from: '#5b8cff', to: '#9b6bff', angle: 135 }, style: 'solid', width: 2, animated: true } } },
  ]
}

function toCard(api: ApiCard): Card {
  return { id: String(api.id), columnId: String(api.columnId), title: api.title, description: api.description ?? undefined, assignee: api.assignee, priority: api.priority, labels: [], dueAt: api.dueAt, dueEndAt: api.dueEndAt, dueTime: api.dueTime, done: api.done }
}
function toColumn(api: ApiColumn): Column {
  return { id: String(api.id), title: api.title, isTerminal: api.isTerminal, cards: api.cards.map(toCard) }
}

export const useBoardStore = defineStore('board', () => {
  const columns = ref<Column[]>(mockColumns())
  // Участники пары (him/her) — источник имён исполнителей (вместо «Он/Она»), см. useAssignees.
  const members = ref<BoardMember[]>(mockMembers())
  // Бэклог: карточки, убранные с доски (on_board=false). Живут отдельно от columns.
  // Наполняется fetchBacklog (только в backed-режиме — в моке бэклога нет).
  const backlogCards = ref<Card[]>([])
  const boardId = ref<number | null>(null)
  const backed = ref(false) // true = данные из API; false = мок (нет авторизованной пары)
  // первичная загрузка доски: true до первого ответа fetchBoard. Пока true — Board.vue
  // показывает скелетон вместо мок-карточек (иначе на долю секунды мигает мок-доска).
  // Сбрасывается один раз и навсегда → при возврате на доску повторный fetch идёт тихо.
  const loading = ref(true)
  // активный drag (карточка/колонка) — пока true, списки карточек клиппят overflow,
  // чтобы FLIP-анимация vuedraggable не порождала мигающие скроллбары в принимающей колонке
  const dragging = ref(false)
  // id карточки, открытой в выезжающей панели параметров (null — панель закрыта)
  const selectedCardId = ref<string | null>(null)
  // id карточки, ТОЛЬКО ЧТО закрытой переносом в терминал (переход done false→true).
  // Триггер ретро-анимации закрытия (pixel): KanbanCard вешает .just-closed по совпадению id.
  // Сам сбрасывается через ANIM_MS — НЕ играет на загрузке/перерисовке (на старте null).
  // Класс выводится из стора (computed), а не из локального состояния компонента — так он
  // переживает пересоздание инстанса карточки при cross-column DnD (vuedraggable).
  const justClosedCardId = ref<string | null>(null)
  let justClosedTimer: ReturnType<typeof setTimeout> | null = null
  const JUST_CLOSED_MS = 700 // вся секвенция: левая полоса → правая → тряска

  function markJustClosed(cardId: string): void {
    justClosedCardId.value = cardId
    if (justClosedTimer !== null) clearTimeout(justClosedTimer)
    justClosedTimer = setTimeout(() => { justClosedCardId.value = null; justClosedTimer = null }, JUST_CLOSED_MS)
  }

  // Фильтры доски (клиентские, in-memory): по исполнителю и по дедлайну.
  // Карточки, не прошедшие фильтр, скрываются (v-show в KanbanColumn), но остаются в
  // :list драггабла — индексы DnD не плывут.
  const filterAssignee = ref<'all' | CardAssignee>('all')
  const filterDeadline = ref<'all' | 'has' | 'none' | 'overdue'>('all')

  /** Проходит ли карточка текущие фильтры (исполнитель И дедлайн). */
  function cardMatchesFilter(card: Card): boolean {
    if (filterAssignee.value !== 'all' && card.assignee !== filterAssignee.value) return false
    switch (filterDeadline.value) {
      case 'has': return card.dueAt != null
      case 'none': return card.dueAt == null
      case 'overdue': return card.dueAt != null && !card.done && isOverdue(card.dueAt, card.dueEndAt)
      default: return true
    }
  }

  const totalCards = computed(() => columns.value.reduce((s, c) => s + c.cards.length, 0))

  // column=null → карточка найдена в бэклоге (вне доски). Редактирование через CardDetailDrawer
  // использует только .card, поэтому null-колонка корректна; board-операции (move/remove с доски)
  // backlog-карточек не получают.
  function findCard(cardId: string): { column: Column | null; card: Card } | null {
    for (const column of columns.value) {
      const card = column.cards.find((c) => c.id === cardId)
      if (card) return { column, card }
    }
    const backlogCard = backlogCards.value.find((c) => c.id === cardId)
    if (backlogCard) return { column: null, card: backlogCard }
    return null
  }

  /** Карточка, открытая в панели (резолвится из текущего состояния доски). */
  const selectedCard = computed<Card | null>(() =>
    selectedCardId.value === null ? null : (findCard(selectedCardId.value)?.card ?? null),
  )

  function openCard(cardId: string): void {
    selectedCardId.value = cardId
    log.debug('openCard', { cardId })
  }

  function closeCard(): void {
    log.debug('closeCard', { cardId: selectedCardId.value })
    selectedCardId.value = null
  }

  /** Загрузить доску с API; при 401/403 — остаёмся в мок-режиме. */
  async function fetchBoard(): Promise<void> {
    try {
      const board: ApiBoard | null = await boardApi.fetchBoard()
      if (board) {
        columns.value = board.columns.map(toColumn)
        if (board.members?.length) members.value = board.members.map((m) => ({ slot: m.slot, id: String(m.id), name: m.name ?? '', appearance: m.appearance ?? null }))
        boardId.value = board.id
        backed.value = true
        log.info('loaded from API', { boardId: board.id })
      } else {
        backed.value = false
        log.debug('not authenticated — mock mode')
      }
    } finally {
      loading.value = false // [FIX] первичная загрузка завершена — скрыть скелетон (реальная доска или мок-фолбэк)
    }
  }

  async function addCard(columnId: string, title: string, assignee: CardAssignee = 'together'): Promise<void> {
    const column = columns.value.find((c) => c.id === columnId)
    // лимит названия (синхрон с бэкендом max:100) — бэкстоп к maxlength инпута
    const clean = title.trim().slice(0, CARD_TITLE_MAX)
    if (!column || clean === '') return
    if (backed.value) {
      const api = await boardApi.createCard(Number(columnId), clean, assignee)
      if (api) column.cards.push(toCard(api))
      else await fetchBoard()
    } else {
      column.cards.push({ id: uid('card'), title: clean, assignee, labels: [], dueAt: null, done: false })
    }
    log.debug('addCard', { columnId })
  }

  /**
   * Обновить параметры карточки оптимистично; при провале PATCH — откат к снапшоту.
   * В мок-режиме (backed=false) — только локальная мутация. Дебаунс живёт в drawer-компоненте.
   */
  async function updateCard(cardId: string, patch: CardPatch): Promise<void> {
    const found = findCard(cardId)
    if (!found) return
    // лимит названия (синхрон с бэкендом max:100) — бэкстоп к maxlength инпута
    if (typeof patch.title === 'string') patch = { ...patch, title: patch.title.slice(0, CARD_TITLE_MAX) }
    const { card } = found
    const snapshot = { ...card } // для отката
    Object.assign(card, patch) // оптимистично
    log.debug('updateCard', { cardId, keys: Object.keys(patch) })
    if (backed.value) {
      const api = await boardApi.updateCard(Number(cardId), patch)
      if (!api) {
        Object.assign(card, snapshot)
        log.warn('updateCard rollback', { cardId })
      }
    }
  }

  async function removeCard(cardId: string): Promise<void> {
    const found = findCard(cardId)
    if (!found || !found.column) return // board-only операция; backlog-карточки удаляет removeFromBacklog
    found.column.cards = found.column.cards.filter((c) => c.id !== cardId)
    if (selectedCardId.value === cardId) selectedCardId.value = null // закрыть панель удалённой карточки
    if (backed.value) await boardApi.removeCard(Number(cardId))
  }

  /** Перенести карточку в начало/конец её колонки (пункт меню карточки). */
  async function moveCardToEdge(cardId: string, edge: 'top' | 'bottom'): Promise<void> {
    const found = findCard(cardId)
    if (!found || !found.column) return // только для карточек на доске
    const { column, card } = found
    const i = column.cards.indexOf(card)
    if (i === -1) return
    column.cards.splice(i, 1)
    const toIndex = edge === 'top' ? 0 : column.cards.length
    column.cards.splice(toIndex, 0, card)
    if (backed.value) {
      const api = await boardApi.moveCard(Number(cardId), Number(column.id), toIndex)
      if (!api) await fetchBoard()
    }
    log.debug('moveCardToEdge', { cardId, edge, toIndex })
  }

  /** Убрать карточку с доски (в бэклог) — НЕ удаление: остаётся в БД (on_board=false). */
  async function removeFromBoard(cardId: string): Promise<void> {
    const found = findCard(cardId)
    if (!found || !found.column) return
    const card = found.card
    found.column.cards = found.column.cards.filter((c) => c.id !== cardId)
    if (selectedCardId.value === cardId) selectedCardId.value = null
    backlogCards.value.unshift(card) // живая синхронизация бэклога (свежие — сверху, как в API)
    if (backed.value) {
      const ok = await boardApi.removeFromBoard(Number(cardId))
      if (!ok) { await fetchBoard(); await fetchBacklog() } // откат оптимистичного перемещения при провале
    }
    log.debug('removeFromBoard', { cardId })
  }

  /**
   * Локально обновить appearance участника по слоту (him/her) — чтобы доска перекрасилась
   * сразу после сохранения профиля, без полного fetchBoard. Бэкенд — источник правды при
   * следующей загрузке доски.
   */
  function setMemberAppearance(slot: AssigneeSlot, appearance: UserAppearance | null): void {
    const member = members.value.find((m) => m.slot === slot)
    if (member) member.appearance = appearance
    log.debug('setMemberAppearance', { slot })
  }

  /** Загрузить бэклог (карточки, убранные с доски). В мок-режиме бэклога нет. */
  async function fetchBacklog(): Promise<void> {
    if (!backed.value) { backlogCards.value = []; return }
    const api = await boardApi.fetchBacklog()
    backlogCards.value = api ? api.map(toCard) : []
    log.debug('fetchBacklog', { count: backlogCards.value.length })
  }

  /** Создать карточку сразу в бэклоге (on_board=false). Свежая — сверху списка (как в API). */
  async function addBacklogCard(title: string, assignee: CardAssignee = 'together'): Promise<void> {
    const clean = title.trim().slice(0, CARD_TITLE_MAX) // синхрон с бэкендом max:100
    if (clean === '') return
    if (backed.value) {
      const api = await boardApi.createBacklogCard(clean, assignee)
      if (api) backlogCards.value.unshift(toCard(api))
      else await fetchBacklog()
    } else {
      backlogCards.value.unshift({ id: uid('card'), title: clean, assignee, labels: [], dueAt: null, done: false })
    }
    log.debug('addBacklogCard')
  }

  /**
   * Вернуть карточку из бэклога на доску в конец выбранной колонки (оптимистично).
   * Готовность выводится из терминальности целевой колонки (как при move). При провале PATCH —
   * откат через перезагрузку доски и бэклога.
   */
  async function returnToBoard(cardId: string, toColumnId: string): Promise<void> {
    const i = backlogCards.value.findIndex((c) => c.id === cardId)
    if (i === -1) return
    const [card] = backlogCards.value.splice(i, 1)
    const target = columns.value.find((c) => c.id === toColumnId)
    if (target) {
      card.done = target.isTerminal // оптимистично
      target.cards.push(card)
    }
    if (selectedCardId.value === cardId) selectedCardId.value = null // карточка ушла на доску — закрыть панель
    if (backed.value) {
      const api = await boardApi.returnToBoard(Number(cardId), Number(toColumnId))
      if (!api) { await fetchBoard(); await fetchBacklog() } // откат
      else if (target) { const moved = target.cards.find((c) => c.id === cardId); if (moved) moved.done = api.done }
    }
    log.debug('returnToBoard', { cardId, toColumnId })
  }

  /** Удалить карточку из бэклога окончательно (DELETE) — без возврата на доску. */
  async function removeFromBacklog(cardId: string): Promise<void> {
    const i = backlogCards.value.findIndex((c) => c.id === cardId)
    if (i === -1) return
    backlogCards.value.splice(i, 1)
    if (selectedCardId.value === cardId) selectedCardId.value = null
    if (backed.value) await boardApi.removeCard(Number(cardId))
    log.debug('removeFromBacklog', { cardId })
  }

  /**
   * Персист перемещения карточки (локально DnD уже подвинул). Готовность выводится из
   * терминальности целевой колонки (роль галочки): в «Готово» → done=true, обратно → false.
   * Делается и в мок-режиме; в backed бэкенд — источник правды (сверяем done из ответа).
   */
  async function persistCardMove(cardId: string, toColumnId: string, toIndex: number): Promise<void> {
    const found = findCard(cardId)
    const target = columns.value.find((c) => c.id === toColumnId)
    const wasDone = found?.card.done ?? false
    if (found && target) found.card.done = target.isTerminal // оптимистично, в т.ч. мок
    // переход «в работе» → «выполнено»: триггер анимации закрытия (только false→true)
    if (found && target?.isTerminal && !wasDone) markJustClosed(cardId)
    if (backed.value) {
      const api = await boardApi.moveCard(Number(cardId), Number(toColumnId), toIndex)
      if (!api) await fetchBoard()
      else if (found) found.card.done = api.done // бэкенд — источник правды
    }
    log.debug('persistCardMove', { cardId, toColumnId, toIndex, done: found?.card.done })
  }

  /** Вставить колонку ПЕРЕД терминальной («Готово» всегда последняя). */
  function insertBeforeTerminal(col: Column): void {
    const i = columns.value.findIndex((c) => c.isTerminal)
    if (i === -1) columns.value.push(col)
    else columns.value.splice(i, 0, col)
  }

  async function addColumn(title: string): Promise<void> {
    if (title.trim() === '') return
    if (backed.value && boardId.value !== null) {
      const api = await boardApi.addColumn(boardId.value, title.trim())
      if (api) insertBeforeTerminal(toColumn(api))
      else await fetchBoard()
    } else {
      insertBeforeTerminal({ id: uid('col'), title: title.trim(), isTerminal: false, cards: [] })
    }
    log.debug('addColumn')
  }

  async function renameColumn(columnId: string, title: string): Promise<void> {
    const column = columns.value.find((c) => c.id === columnId)
    if (!column || title.trim() === '') return
    column.title = title.trim()
    if (backed.value) await boardApi.renameColumn(Number(columnId), title.trim())
  }

  /**
   * Удалить колонку. По умолчанию каскадом (карточки удаляются вместе с колонкой).
   * При keepCards=true и наличии соседней колонки карточки переносятся в неё
   * (relocate через moveCard), а удаляется уже пустая колонка — полноценный архив
   * (soft-delete) появится отдельно (веха Privacy & Lifecycle Hardening).
   */
  async function removeColumn(columnId: string, opts: { keepCards?: boolean } = {}): Promise<void> {
    const idx = columns.value.findIndex((c) => c.id === columnId)
    if (idx === -1) return
    const column = columns.value[idx]
    // соседняя колонка-приёмник: предпочитаем предыдущую, иначе следующую
    const sibling = columns.value[idx - 1] ?? columns.value[idx + 1] ?? null
    let movedCount = 0

    if (opts.keepCards && sibling && column.cards.length > 0) {
      for (const card of [...column.cards]) {
        const toIndex = sibling.cards.length
        sibling.cards.push(card) // локальный перенос (append в конец соседней)
        if (backed.value) await boardApi.moveCard(Number(card.id), Number(sibling.id), toIndex)
        movedCount += 1
      }
      column.cards = []
    }

    columns.value = columns.value.filter((c) => c.id !== columnId)
    if (backed.value) await boardApi.removeColumn(Number(columnId))
    log.debug('removeColumn', { columnId, keepCards: !!opts.keepCards, movedCount })
  }

  /** Персист порядка колонок (локально DnD уже переставил). */
  async function persistColumnReorder(): Promise<void> {
    if (!backed.value) return
    await boardApi.reorderColumns(columns.value.map((c) => Number(c.id)))
    log.debug('persistColumnReorder')
  }

  return {
    columns, members, setMemberAppearance, backlogCards, boardId, backed, loading, dragging, totalCards, findCard,
    selectedCardId, selectedCard, openCard, closeCard,
    justClosedCardId,
    filterAssignee, filterDeadline, cardMatchesFilter,
    fetchBoard, addCard, updateCard, removeCard, persistCardMove, moveCardToEdge, removeFromBoard,
    fetchBacklog, addBacklogCard, returnToBoard, removeFromBacklog,
    addColumn, renameColumn, removeColumn, persistColumnReorder,
  }
})
