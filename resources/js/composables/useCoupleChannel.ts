// Подписка на presence-канал пары couple.{id}: единая точка real-time. Принимает доменные
// события партнёра и применяет их точечными мутациями board-стора (reuse, без API), а также
// ведёт онлайн-статус партнёра через presence (here/joining/leaving).
// Module-singleton (как useConfirm): один канал на приложение.

import { ref } from 'vue'
import { getEcho } from '@/bootstrap'
import { useBoardStore } from '@/stores/board'
import { useAuthStore } from '@/stores/auth'
import { useLogger } from '@/composables/useLogger'
import type { ApiCard, ApiColumn } from '@/api/board'
import type { AssigneeSlot, UserAppearance } from '@/types/board'

const log = useLogger('useCoupleChannel')

// ─── Типы payload'ов (broadcastWith на бэке) ───
interface RemoteCardEvent {
  action: string
  card: ApiCard & { onBoard: boolean; position: number }
}
interface CardRemovedEvent {
  cardId: number
  columnId: number
}
interface ColumnEvent {
  action: 'created' | 'updated'
  column: ApiColumn
}
interface ColumnsReorderedEvent {
  orderedIds: number[]
}
interface ColumnRemovedEvent {
  columnId: number
}
interface AppearanceEvent {
  slot: AssigneeSlot
  appearance: UserAppearance | null
}
interface PresenceMember {
  id: number
  slot: string
  name: string
}

// Минимальный контракт presence-канала Echo, который мы используем.
interface CoupleChannel {
  here(cb: (members: PresenceMember[]) => void): CoupleChannel
  joining(cb: (member: PresenceMember) => void): CoupleChannel
  leaving(cb: (member: PresenceMember) => void): CoupleChannel
  listen(event: string, cb: (payload: unknown) => void): CoupleChannel
  error(cb: (e: unknown) => void): CoupleChannel
}

/** Запись ленты активности пары (для дашборда). */
export interface ActivityEntry {
  id: number
  text: string
  at: number
}

// ─── Module-singleton состояние ───
const partnerOnline = ref(false)
const recentEvents = ref<ActivityEntry[]>([]) // лента активности (свежие — сверху)
let subscribedCoupleId: number | null = null
let activitySeq = 0
const ACTIVITY_CAP = 30

export interface UseCoupleChannel {
  partnerOnline: typeof partnerOnline
  recentEvents: typeof recentEvents
  subscribe: (coupleId: number) => void
  unsubscribe: () => void
}

export function useCoupleChannel(): UseCoupleChannel {
  const board = useBoardStore()
  const auth = useAuthStore()

  /** id текущего юзера — чтобы отличить партнёра в presence. */
  function myId(): number | null {
    return auth.user?.id ?? null
  }

  /** Имя партнёра (участник пары ≠ я) для ленты активности; фолбэк — «Партнёр». */
  function partnerName(): string {
    const me = myId()
    const partner = board.members.find((m) => Number(m.id) !== me)
    return partner?.name?.trim() || 'Партнёр'
  }

  /** Добавить запись в ленту активности (свежие сверху, кап ACTIVITY_CAP). */
  function pushActivity(text: string): void {
    recentEvents.value.unshift({ id: (activitySeq += 1), text, at: Date.now() })
    if (recentEvents.value.length > ACTIVITY_CAP) recentEvents.value.length = ACTIVITY_CAP
    log.debug('activity', { text })
  }

  /**
   * Подписаться на couple.{id}. Идемпотентно: повторный вызов с тем же id игнорируется,
   * со сменой id — переподписка. Требует инициализированный Echo (initEcho в main.ts).
   */
  function subscribe(coupleId: number): void {
    if (subscribedCoupleId === coupleId) return
    const echo = getEcho()
    if (!echo) {
      log.warn('subscribe: Echo не инициализирован')
      return
    }
    if (subscribedCoupleId !== null) unsubscribe()

    const channel = echo.join(`couple.${coupleId}`) as unknown as CoupleChannel
    subscribedCoupleId = coupleId

    channel
      .here((members) => {
        partnerOnline.value = members.some((m) => m.id !== myId())
        log.info('presence here', { online: partnerOnline.value })
      })
      .joining((member) => {
        if (member.id !== myId()) partnerOnline.value = true
        log.debug('presence joining', { slot: member.slot })
      })
      .leaving((member) => {
        if (member.id !== myId()) partnerOnline.value = false
        log.debug('presence leaving', { slot: member.slot })
      })
      .listen('.CardBroadcast', (p) => {
        const e = p as RemoteCardEvent
        board.applyRemoteCard(e.action, e.card)
        const verb =
          ({ created: 'добавил(а) задачу', backlogCreated: 'добавил(а) в бэклог', moved: 'переместил(а)', returned: 'вернул(а) на доску', updated: 'изменил(а)' } as Record<string, string>)[
            e.action
          ] ?? 'обновил(а)'
        pushActivity(`${partnerName()} ${verb} «${e.card.title}»`)
      })
      .listen('.CardRemoved', (p) => {
        const e = p as CardRemovedEvent
        board.applyRemoteCardRemoved(e.cardId)
        pushActivity(`${partnerName()} удалил(а) задачу`)
      })
      .listen('.ColumnBroadcast', (p) => {
        const e = p as ColumnEvent
        board.applyRemoteColumn(e.action, e.column)
        const verb = e.action === 'created' ? 'создал(а) колонку' : 'переименовал(а) колонку в'
        pushActivity(`${partnerName()} ${verb} «${e.column.title}»`)
      })
      .listen('.ColumnsReordered', (p) => {
        const e = p as ColumnsReorderedEvent
        board.applyRemoteColumnsReordered(e.orderedIds)
        pushActivity(`${partnerName()} изменил(а) порядок колонок`)
      })
      .listen('.ColumnRemoved', (p) => {
        const e = p as ColumnRemovedEvent
        board.applyRemoteColumnRemoved(e.columnId)
        pushActivity(`${partnerName()} удалил(а) колонку`)
      })
      .listen('.MemberAppearanceUpdated', (p) => {
        const e = p as AppearanceEvent
        board.setMemberAppearance(e.slot, e.appearance)
        pushActivity(`${partnerName()} обновил(а) оформление карточек`)
      })
      .error((e) => log.warn('channel error', e))

    log.debug('subscribed', { coupleId })
  }

  /** Отписаться от канала пары и сбросить онлайн-статус. */
  function unsubscribe(): void {
    if (subscribedCoupleId === null) return
    getEcho()?.leave(`couple.${subscribedCoupleId}`)
    log.debug('unsubscribed', { coupleId: subscribedCoupleId })
    subscribedCoupleId = null
    partnerOnline.value = false
    recentEvents.value = []
  }

  return { partnerOnline, recentEvents, subscribe, unsubscribe }
}
