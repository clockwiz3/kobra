import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useBoardStore } from '@/stores/board'

// Экшены меню карточки в мок-режиме (backed=false → чистая локальная мутация).
describe('board store — меню карточки', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('moveCardToEdge переносит карточку в начало и конец колонки', async () => {
    const store = useBoardStore()
    const col = store.columns[1] // «На неделе» — 2 карточки
    const lastId = col.cards[col.cards.length - 1].id

    await store.moveCardToEdge(lastId, 'top')
    expect(col.cards[0].id).toBe(lastId)

    await store.moveCardToEdge(lastId, 'bottom')
    expect(col.cards[col.cards.length - 1].id).toBe(lastId)
  })

  it('removeFromBoard убирает карточку из колонки', async () => {
    const store = useBoardStore()
    const col = store.columns[0]
    const id = col.cards[0].id
    const before = col.cards.length

    await store.removeFromBoard(id)

    expect(col.cards.length).toBe(before - 1)
    expect(col.cards.find((c) => c.id === id)).toBeUndefined()
  })
})
