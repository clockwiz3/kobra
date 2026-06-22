import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

// Фейковый presence-канал: записывает колбэки, чтобы дёргать их вручную.
function makeChannel() {
  const c: Record<string, unknown> & { listeners: Record<string, (p: unknown) => void> } = { listeners: {} } as never
  c.here = (cb: unknown) => ((c.hereCb = cb), c)
  c.joining = (cb: unknown) => ((c.joiningCb = cb), c)
  c.leaving = (cb: unknown) => ((c.leavingCb = cb), c)
  c.listen = (ev: string, cb: (p: unknown) => void) => ((c.listeners[ev] = cb), c)
  c.error = () => c
  return c
}

let channel: ReturnType<typeof makeChannel>
const echo = { join: vi.fn(() => channel), leave: vi.fn() }

vi.mock('@/bootstrap', () => ({ getEcho: () => echo, socketId: () => undefined }))
// Стор может уйти в refetch при «десинхроне» (колонки нет) — глушим реальный fetch.
vi.mock('@/api/board', () => ({
  boardApi: { fetchBoard: vi.fn().mockResolvedValue(null), fetchBacklog: vi.fn().mockResolvedValue(null) },
}))

import { useCoupleChannel } from '@/composables/useCoupleChannel'
import { useBoardStore } from '@/stores/board'
import { useAuthStore } from '@/stores/auth'

function setMe(id: number) {
  const auth = useAuthStore()
  auth.user = { id, name: 'me', email: null, avatar: null, appearance: null }
}

describe('useCoupleChannel', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    channel = makeChannel()
    echo.join.mockClear()
    echo.leave.mockClear()
  })
  afterEach(() => useCoupleChannel().unsubscribe())

  it('subscribe → join presence-канала couple.{id}', () => {
    useCoupleChannel().subscribe(7)
    expect(echo.join).toHaveBeenCalledWith('couple.7')
  })

  it('повторный subscribe с тем же id — идемпотентен', () => {
    const cc = useCoupleChannel()
    cc.subscribe(7)
    cc.subscribe(7)
    expect(echo.join).toHaveBeenCalledTimes(1)
  })

  it('CardBroadcast → board.applyRemoteCard(action, card)', () => {
    const board = useBoardStore()
    const spy = vi.spyOn(board, 'applyRemoteCard')
    useCoupleChannel().subscribe(7)
    channel.listeners['.CardBroadcast']({ action: 'moved', card: { id: 5, columnId: 1, onBoard: true, position: 0, title: 'X' } })
    expect(spy).toHaveBeenCalledWith('moved', expect.objectContaining({ id: 5 }))
  })

  it('ColumnsReordered → board.applyRemoteColumnsReordered', () => {
    const board = useBoardStore()
    const spy = vi.spyOn(board, 'applyRemoteColumnsReordered')
    useCoupleChannel().subscribe(7)
    channel.listeners['.ColumnsReordered']({ orderedIds: [3, 1, 2] })
    expect(spy).toHaveBeenCalledWith([3, 1, 2])
  })

  it('MemberAppearanceUpdated → board.setMemberAppearance', () => {
    const board = useBoardStore()
    const spy = vi.spyOn(board, 'setMemberAppearance')
    useCoupleChannel().subscribe(7)
    channel.listeners['.MemberAppearanceUpdated']({ slot: 'him', appearance: null })
    expect(spy).toHaveBeenCalledWith('him', null)
  })

  it('presence here → partnerOnline по другому участнику', () => {
    setMe(1)
    const cc = useCoupleChannel()
    cc.subscribe(7)
    ;(channel.hereCb as (m: unknown[]) => void)([{ id: 1, slot: 'her', name: 'me' }, { id: 2, slot: 'him', name: 'p' }])
    expect(cc.partnerOnline.value).toBe(true)
  })

  it('joining/leaving партнёра переключает online', () => {
    setMe(1)
    const cc = useCoupleChannel()
    cc.subscribe(7)
    ;(channel.joiningCb as (m: unknown) => void)({ id: 2, slot: 'him', name: 'p' })
    expect(cc.partnerOnline.value).toBe(true)
    ;(channel.leavingCb as (m: unknown) => void)({ id: 2, slot: 'him', name: 'p' })
    expect(cc.partnerOnline.value).toBe(false)
  })

  it('события наполняют ленту recentEvents', () => {
    const cc = useCoupleChannel()
    cc.subscribe(7)
    channel.listeners['.CardBroadcast']({ action: 'created', card: { id: 1, columnId: 1, onBoard: true, position: 0, title: 'Алоэ' } })
    expect(cc.recentEvents.value.length).toBe(1)
    expect(cc.recentEvents.value[0].text).toContain('Алоэ')
  })

  it('unsubscribe → leave и сброс состояния', () => {
    const cc = useCoupleChannel()
    cc.subscribe(7)
    channel.listeners['.CardRemoved']({ cardId: 1, columnId: 1 })
    cc.unsubscribe()
    expect(echo.leave).toHaveBeenCalledWith('couple.7')
    expect(cc.partnerOnline.value).toBe(false)
    expect(cc.recentEvents.value.length).toBe(0)
  })
})
