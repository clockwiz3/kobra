// Typed-клиент couple-API канбана. Bearer-токен из localStorage (до появления полноценного auth).
// При 401/403 функции возвращают null/false — store остаётся в мок-режиме.

import type { AssigneeSlot, CardAssignee, CardPatch, CardPriority, UserAppearance } from '@/types/board'
import { useLogger } from '@/composables/useLogger'
import { socketId } from '@/bootstrap'

const log = useLogger('boardApi')

export interface ApiCard {
  id: number
  columnId: number
  title: string
  description: string | null
  assignee: CardAssignee
  priority: CardPriority
  dueAt: string | null
  dueEndAt: string | null
  dueTime: string | null
  done: boolean
  position: number
}

/** camelCase-патч → snake_case payload; включаем только реально переданные ключи. */
function toSnakePatch(patch: CardPatch): Record<string, unknown> {
  const body: Record<string, unknown> = {}
  if ('title' in patch) body.title = patch.title
  if ('description' in patch) body.description = patch.description
  if ('assignee' in patch) body.assignee = patch.assignee
  if ('priority' in patch) body.priority = patch.priority
  if ('dueAt' in patch) body.due_at = patch.dueAt
  if ('dueEndAt' in patch) body.due_end_at = patch.dueEndAt
  if ('dueTime' in patch) body.due_time = patch.dueTime
  return body
}

export interface ApiColumn {
  id: number
  title: string
  position: number
  isTerminal: boolean
  cards: ApiCard[]
}

export interface ApiBoardMember {
  slot: AssigneeSlot
  id: number
  name: string | null
  appearance: UserAppearance | null
}

export interface ApiBoard {
  id: number
  coupleId: number // id пары → presence-канал couple.{id}
  name: string
  columns: ApiColumn[]
  members: ApiBoardMember[]
}

const BASE = '/api/couple'

function headers(): HeadersInit {
  const token = localStorage.getItem('cuploid.token')
  const sid = socketId() // X-Socket-ID → мутация не вернётся инициатору через broadcast()->toOthers()
  return {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    ...(token ? { Authorization: `Bearer ${token}` } : {}),
    ...(sid ? { 'X-Socket-Id': sid } : {}),
  }
}

async function call(method: string, path: string, body?: unknown): Promise<Response> {
  return fetch(`${BASE}${path}`, {
    method,
    headers: headers(),
    credentials: 'same-origin',
    body: body !== undefined ? JSON.stringify(body) : undefined,
  })
}

async function jsonOrNull<T>(res: Response): Promise<T | null> {
  return res.ok ? (res.json() as Promise<T>) : null
}

export const boardApi = {
  async fetchBoard(): Promise<ApiBoard | null> {
    return jsonOrNull<ApiBoard>(await call('GET', '/board'))
  },
  /** Карточки бэклога (убранные с доски, on_board=false) текущей пары; свежие — сверху. */
  async fetchBacklog(): Promise<ApiCard[] | null> {
    return jsonOrNull<ApiCard[]>(await call('GET', '/backlog'))
  },
  /** Создать карточку сразу в бэклоге (on_board=false) — не появляется на доске. */
  async createBacklogCard(title: string, assignee: CardAssignee): Promise<ApiCard | null> {
    return jsonOrNull<ApiCard>(await call('POST', '/backlog', { title, assignee }))
  },
  /** Вернуть карточку из бэклога на доску в конец колонки columnId (on_board=false → true). */
  async returnToBoard(cardId: number, columnId: number): Promise<ApiCard | null> {
    const res = await call('PATCH', `/cards/${cardId}/return`, { column_id: columnId })
    if (!res.ok) log.warn('[boardApi.returnToBoard] failed', { cardId, status: res.status })
    return jsonOrNull<ApiCard>(res)
  },
  async createCard(columnId: number, title: string, assignee: CardAssignee): Promise<ApiCard | null> {
    return jsonOrNull<ApiCard>(await call('POST', '/cards', { column_id: columnId, title, assignee }))
  },
  async updateCard(cardId: number, patch: CardPatch): Promise<ApiCard | null> {
    const res = await call('PATCH', `/cards/${cardId}`, toSnakePatch(patch))
    if (!res.ok) log.warn('[boardApi.updateCard] failed', { cardId, status: res.status })
    return jsonOrNull<ApiCard>(res)
  },
  async moveCard(cardId: number, columnId: number, position: number): Promise<ApiCard | null> {
    return jsonOrNull<ApiCard>(await call('PATCH', `/cards/${cardId}/move`, { column_id: columnId, position }))
  },
  async removeCard(cardId: number): Promise<boolean> {
    return (await call('DELETE', `/cards/${cardId}`)).ok
  },
  /** Убрать карточку с доски (в бэклог) — НЕ удаление: on_board=false, карточка остаётся в БД. */
  async removeFromBoard(cardId: number): Promise<boolean> {
    return (await call('PATCH', `/cards/${cardId}`, { on_board: false })).ok
  },
  async addColumn(boardId: number, title: string): Promise<ApiColumn | null> {
    return jsonOrNull<ApiColumn>(await call('POST', '/columns', { board_id: boardId, title }))
  },
  async renameColumn(columnId: number, title: string): Promise<ApiColumn | null> {
    return jsonOrNull<ApiColumn>(await call('PATCH', `/columns/${columnId}`, { title }))
  },
  async removeColumn(columnId: number): Promise<boolean> {
    return (await call('DELETE', `/columns/${columnId}`)).ok
  },
  async reorderColumns(ids: number[]): Promise<boolean> {
    return (await call('PATCH', '/columns/reorder', { ids })).ok
  },
}
