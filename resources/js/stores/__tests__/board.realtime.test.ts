import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useBoardStore } from '@/stores/board'

// Карточка из realtime-события: ApiCard + onBoard + position.
const rcard = (id: number, columnId: number, onBoard = true, extra: Record<string, unknown> = {}) => ({
  id,
  columnId,
  title: `c${id}`,
  description: null,
  assignee: 'together' as const,
  priority: 'normal' as const,
  dueAt: null,
  dueEndAt: null,
  dueTime: null,
  done: false,
  position: 0,
  onBoard,
  ...extra,
})

const col = (id: string, isTerminal = false, cards: unknown[] = []) => ({ id, title: id, isTerminal, cards })
const card = (id: string) => ({ id, title: id, assignee: 'together', labels: [], dueAt: null, done: false })

describe('board store — применение realtime-событий', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('applyRemoteCard created → вставляет в колонку', () => {
    const b = useBoardStore()
    b.columns = [col('1')] as never
    b.applyRemoteCard('created', rcard(5, 1))
    expect(b.columns[0].cards.map((c) => c.id)).toContain('5')
  })

  it('applyRemoteCard onBoard=false → в бэклог', () => {
    const b = useBoardStore()
    b.applyRemoteCard('backlogCreated', rcard(6, 1, false))
    expect(b.backlogCards.map((c) => c.id)).toContain('6')
  })

  it('applyRemoteCard moved → переносит и снимает из старой колонки', () => {
    const b = useBoardStore()
    b.columns = [col('1', false, [card('5')]), col('2', true)] as never
    b.applyRemoteCard('moved', rcard(5, 2, true, { done: true }))
    expect(b.columns[0].cards.length).toBe(0)
    expect(b.columns[1].cards.map((c) => c.id)).toContain('5')
    expect(b.findCard('5')?.card.done).toBe(true)
  })

  it('applyRemoteCard «убрать с доски» (updated, onBoard=false) → из колонки в бэклог', () => {
    const b = useBoardStore()
    b.columns = [col('1', false, [card('5')])] as never
    b.applyRemoteCard('updated', rcard(5, 1, false))
    expect(b.columns[0].cards.length).toBe(0)
    expect(b.backlogCards.map((c) => c.id)).toContain('5')
  })

  it('applyRemoteCardRemoved → снимает карточку отовсюду', () => {
    const b = useBoardStore()
    b.columns = [col('1', false, [card('5')])] as never
    b.applyRemoteCardRemoved(5)
    expect(b.findCard('5')).toBeNull()
  })

  it('applyRemoteColumn created → перед терминальной', () => {
    const b = useBoardStore()
    b.columns = [col('1'), col('9', true)] as never
    b.applyRemoteColumn('created', { id: 2, title: 'New', position: 1, isTerminal: false, cards: [] } as never)
    expect(b.columns.map((c) => c.id)).toEqual(['1', '2', '9'])
  })

  it('applyRemoteColumn updated → переименование на месте', () => {
    const b = useBoardStore()
    b.columns = [col('1')] as never
    b.applyRemoteColumn('updated', { id: 1, title: 'Renamed', position: 0, isTerminal: false, cards: [] } as never)
    expect(b.columns[0].title).toBe('Renamed')
  })

  it('applyRemoteColumnsReordered → новый порядок', () => {
    const b = useBoardStore()
    b.columns = [col('1'), col('2'), col('3')] as never
    b.applyRemoteColumnsReordered([3, 1, 2])
    expect(b.columns.map((c) => c.id)).toEqual(['3', '1', '2'])
  })

  it('applyRemoteColumnRemoved → убирает колонку', () => {
    const b = useBoardStore()
    b.columns = [col('1'), col('2')] as never
    b.applyRemoteColumnRemoved(2)
    expect(b.columns.map((c) => c.id)).toEqual(['1'])
  })
})
