import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useBoardStore } from '@/stores/board'
import { CARD_TITLE_MAX } from '@/types/board'

// Лимит длины названия карточки: стор обрезает до CARD_TITLE_MAX (бэкстоп к maxlength инпута
// и бэкенд-валидации max:100). Мок-режим (backed=false) — чистая локальная мутация.
describe('board store — лимит названия карточки', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('addCard обрезает название до CARD_TITLE_MAX символов', async () => {
    const store = useBoardStore()
    const col = store.columns[0]
    const before = col.cards.length
    const long = 'я'.repeat(CARD_TITLE_MAX + 50)

    await store.addCard(col.id, long)

    const added = col.cards[col.cards.length - 1]
    expect(col.cards.length).toBe(before + 1)
    expect(added.title).toHaveLength(CARD_TITLE_MAX)
    expect(added.title).toBe(long.slice(0, CARD_TITLE_MAX))
  })

  it('updateCard обрезает title до CARD_TITLE_MAX символов', async () => {
    const store = useBoardStore()
    const card = store.columns[0].cards[0]
    const long = 'a'.repeat(CARD_TITLE_MAX + 30)

    await store.updateCard(card.id, { title: long })

    expect(card.title).toHaveLength(CARD_TITLE_MAX)
    expect(card.title).toBe(long.slice(0, CARD_TITLE_MAX))
  })

  it('название ровно в CARD_TITLE_MAX символов проходит без обрезки', async () => {
    const store = useBoardStore()
    const col = store.columns[0]
    const exact = 'x'.repeat(CARD_TITLE_MAX)

    await store.addCard(col.id, exact)

    expect(col.cards[col.cards.length - 1].title).toBe(exact)
  })
})
