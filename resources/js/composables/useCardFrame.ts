// Рендер «рамки» карточки (appearance) по исполнителю. Превращает CardFrame в инлайн-стиль
// (+ класс пресета) и резолвит рамку участника пары через board.members. Единая точка правды,
// общая для KanbanCard, Backlog и живого превью в BaseColorPicker / Profile.
//
// Привязка — ПО ИСПОЛНИТЕЛЮ: карточка на him/her берёт рамку соответствующего аккаунта;
// together — смешанная рамка (градиент из цветов обоих, иначе акцент пары).
//
// Ограничение: градиентная рамка через background border-box не умеет dashed/dotted —
// для пунктира с градиентом откатываемся на сплошной border-color = первый цвет (paint.from).

import { computed } from 'vue'
import { useBoardStore } from '@/stores/board'
import type { CardAssignee, CardFrame } from '@/types/board'

export interface FrameRender {
  style: Record<string, string>
  class: string | null
}

const EMPTY: FrameRender = { style: {}, class: null }

/** Представительный цвет рамки (для смешивания together): solid → color, gradient → from. */
function paintColor(card: CardFrame | null | undefined): string | null {
  if (!card) return null
  return card.paint.kind === 'solid' ? card.paint.color : card.paint.from
}

/**
 * Градиентная рамка через двойной background (padding-box заливка + border-box градиент).
 * ВСЁ инлайном — иначе scoped-стили элемента (`.cpick__preview`, `.bl-card`…) с большей
 * специфичностью перебивают глобальные правила и рамка «пропадает».
 */
function gradientBorder(grad: string, width: number, surface: string): Record<string, string> {
  return {
    borderWidth: `${width}px`,
    borderStyle: 'solid',
    borderColor: 'transparent',
    backgroundImage: `linear-gradient(${surface}, ${surface}), ${grad}`,
    backgroundOrigin: 'border-box',
    backgroundClip: 'padding-box, border-box',
    WebkitBackgroundClip: 'padding-box, border-box', // Safari/старые WebKit
  }
}

/**
 * Заливка рамки → CSS-градиент. Для «мерцания» (animated) добавляем светлые/тёмные стопы и
 * широкий шаблон, который потом ездит по позиции (класс .frame-shimmer-anim) — блик бежит по рамке.
 * Сплошной цвет тоже получает блик (highlight/shadow того же тона).
 */
function paintGradient(frame: CardFrame): string {
  const sheenAngle = 110
  if (frame.paint.kind === 'solid') {
    const c = frame.paint.color
    const hl = `color-mix(in srgb, ${c} 45%, #ffffff)`
    const sh = `color-mix(in srgb, ${c} 72%, #000000)`
    return frame.animated
      ? `linear-gradient(${sheenAngle}deg, ${c}, ${hl}, ${c}, ${sh}, ${c})`
      : `linear-gradient(${sheenAngle}deg, ${c}, ${c})`
  }
  const { from, to, angle } = frame.paint
  if (!frame.animated) return `linear-gradient(${angle}deg, ${from}, ${to})`
  const fl = `color-mix(in srgb, ${from} 55%, #ffffff)`
  const tl = `color-mix(in srgb, ${to} 55%, #ffffff)`
  return `linear-gradient(${sheenAngle}deg, ${from}, ${fl}, ${to}, ${tl}, ${from})`
}

/**
 * CardFrame → инлайн-стиль рамки (+ класс .frame-shimmer-anim только при animated, для CSS-анимации).
 * `surface` — цвет заливки внутри рамки (padding-box); совпадает с фоном элемента
 * (.task → --surface-2, бэклог → --surface).
 */
export function frameStyle(frame: CardFrame | null, surface = 'var(--surface-2)'): FrameRender {
  if (!frame) return EMPTY
  const width = `${frame.width}px`

  // Простая сплошная рамка (без мерцания) — самый дешёвый путь, поддерживает dashed/dotted.
  if (frame.paint.kind === 'solid' && !frame.animated) {
    return { style: { borderWidth: width, borderStyle: frame.style, borderColor: frame.paint.color }, class: null }
  }
  // Градиент без мерцания + пунктир/точки: border-box-градиент не пунктирится → фолбэк на сплошной цвет.
  if (frame.paint.kind === 'gradient' && !frame.animated && frame.style !== 'solid') {
    return { style: { borderWidth: width, borderStyle: frame.style, borderColor: frame.paint.from }, class: null }
  }

  // Градиентная рамка (в т.ч. мерцающий сплошной цвет). При animated — ездящий блик.
  const style = gradientBorder(paintGradient(frame), frame.width, surface)
  if (frame.animated) {
    style.backgroundSize = '100% 100%, 300% 100%' // вторая (border-box) шире → анимируется по X
    return { style, class: 'frame-shimmer-anim' }
  }
  return { style, class: null }
}

export function useCardFrame() {
  const store = useBoardStore()

  const cardBySlot = computed<Record<'him' | 'her', CardFrame | null>>(() => {
    const her = store.members.find((m) => m.slot === 'her')?.appearance?.card ?? null
    const him = store.members.find((m) => m.slot === 'him')?.appearance?.card ?? null
    return { him, her }
  })

  /** Рамка для исполнителя. together → смешанный градиент; him/her → рамка участника (или null). */
  function frameForAssignee(assignee: CardAssignee): CardFrame | null {
    if (assignee === 'together') {
      const from = paintColor(cardBySlot.value.her) ?? 'var(--accent)'
      const to = paintColor(cardBySlot.value.him) ?? 'var(--accent-2)'
      return { paint: { kind: 'gradient', from, to, angle: 135 }, style: 'solid', width: 2, animated: false }
    }
    return cardBySlot.value[assignee]
  }

  /** Готовый рендер (стиль + класс) рамки карточки по исполнителю. */
  function renderForAssignee(assignee: CardAssignee, surface = 'var(--surface-2)'): FrameRender {
    return frameStyle(frameForAssignee(assignee), surface)
  }

  return { frameStyle, frameForAssignee, renderForAssignee }
}
