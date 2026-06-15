// Typed-клиент couple-API канбана. Bearer-токен из localStorage (до появления полноценного auth).
// При 401/403 функции возвращают null/false — store остаётся в мок-режиме.

import type { CardAssignee } from '@/types/board'

export interface ApiCard {
  id: number
  columnId: number
  title: string
  description: string | null
  assignee: CardAssignee
  dueAt: string | null
  done: boolean
  position: number
}

export interface ApiColumn {
  id: number
  title: string
  position: number
  cards: ApiCard[]
}

export interface ApiBoard {
  id: number
  name: string
  columns: ApiColumn[]
}

const BASE = '/api/couple'

function headers(): HeadersInit {
  const token = localStorage.getItem('cuploid.token')
  return {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    ...(token ? { Authorization: `Bearer ${token}` } : {}),
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
  async createCard(columnId: number, title: string, assignee: CardAssignee): Promise<ApiCard | null> {
    return jsonOrNull<ApiCard>(await call('POST', '/cards', { column_id: columnId, title, assignee }))
  },
  async updateCard(cardId: number, attrs: Partial<{ title: string; description: string | null; assignee: CardAssignee }>): Promise<ApiCard | null> {
    return jsonOrNull<ApiCard>(await call('PATCH', `/cards/${cardId}`, attrs))
  },
  async toggleDone(cardId: number): Promise<ApiCard | null> {
    return jsonOrNull<ApiCard>(await call('PATCH', `/cards/${cardId}/toggle-done`))
  },
  async moveCard(cardId: number, columnId: number, position: number): Promise<ApiCard | null> {
    return jsonOrNull<ApiCard>(await call('PATCH', `/cards/${cardId}/move`, { column_id: columnId, position }))
  },
  async removeCard(cardId: number): Promise<boolean> {
    return (await call('DELETE', `/cards/${cardId}`)).ok
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
