// Пиксельная кобра — талисман Kobra. Один источник для фавикона и компонента KobraSprite.
// Спрайт 20×21: верх (голова + капюшон) покачивается, низ (кольца) стоит на месте.
// Буквы — индексы палитры, '.' — прозрачный пиксель.

export const PALETTE: Record<string, string> = {
  o: '#3A225C', // контур
  b: '#9B7CFC', // тело
  l: '#C4B2FF', // блик
  d: '#7054CE', // тень
  y: '#F4B53C', // брюшко
  h: '#FCD88C', // светлые чешуйки
  w: '#FFFFFF', // глаз
  k: '#221434', // зрачок
  t: '#D6246E', // язык
}

export const WIDTH = 20
export const HEIGHT = 21

const TOP = [
  '......oooooooo......',
  '.....obllllllbo.....',
  '....oblbbbbbbbdo....',
  '..oooowkbbbbkwoooo..',
  '.obbbobbbbbbbbobbbo.',
  'obllbdobbbbbbodbbbbo',
  'oblbbbdooyyoodbbbdbo',
  'obbbbdyhyhhyhydbbbdo',
  'obbbbdyyhyyhyydbbddo',
  'obbbdyhyyhhyyhydbddo',
  '.obbdyyhyyyyhyydbdo.',
  '.obbdyhyyhhyyhydbdo.',
  '..obdyyhyyyyhyydbo..',
  '...odyyyhhhhyyydo...',
  '....odyyyyyyyydo....',
]

const BOTTOM = [
  '......odyyyydo...oo.',
  '..ooooobyyyybooooblo',
  '.obllllbbbbbbbbbbbdo',
  'obbbbbbddddddbbbbdo.',
  'obddddoooooooodddo..',
  '.oooo.........ooo...',
]

export type Frame = string[]

interface Pose {
  dx?: -1 | 0 | 1 // покачивание верха
  tongue?: 0 | 1 | 2 // 0 — нет, 1 — высунут, 2 — раздвоенный
  blink?: boolean
}

export function buildFrame({ dx = 0, tongue = 0, blink = false }: Pose = {}): Frame {
  const rows = Array.from({ length: HEIGHT }, () => Array<string>(WIDTH).fill('.'))
  TOP.forEach((r, y) => {
    for (let x = 0; x < WIDTH; x++) {
      const nx = x + dx
      if (r[x] !== '.' && nx >= 0 && nx < WIDTH) rows[y][nx] = r[x]
    }
  })
  BOTTOM.forEach((r, y) => {
    for (let x = 0; x < WIDTH; x++) if (r[x] !== '.') rows[TOP.length + y][x] = r[x]
  })
  if (blink) rows[3] = rows[3].map((c) => (c === 'w' || c === 'k' ? 'o' : c))
  const cx = 9 + dx
  if (tongue >= 1) {
    rows[6][cx] = rows[6][cx + 1] = 't'
    rows[7][cx] = rows[7][cx + 1] = 't'
  }
  if (tongue === 2) {
    rows[8][cx] = rows[8][cx + 1] = 't'
    rows[9][cx - 1] = rows[9][cx + 2] = 't'
  }
  return rows.map((r) => r.join(''))
}

export const IDLE = buildFrame()

// Цикл анимации: покачивание, моргание, язык. ms — сколько держать кадр.
export const SEQUENCE: { frame: Frame; ms: number }[] = [
  { frame: IDLE, ms: 900 },
  { frame: buildFrame({ dx: -1 }), ms: 450 },
  { frame: IDLE, ms: 450 },
  { frame: buildFrame({ dx: 1 }), ms: 450 },
  { frame: IDLE, ms: 700 },
  { frame: buildFrame({ tongue: 1 }), ms: 110 },
  { frame: buildFrame({ tongue: 2 }), ms: 160 },
  { frame: buildFrame({ tongue: 1 }), ms: 110 },
  { frame: buildFrame({ tongue: 2 }), ms: 160 },
  { frame: IDLE, ms: 1100 },
  { frame: buildFrame({ blink: true }), ms: 140 },
]

/** Нарисовать кадр на canvas; scale — размер «пикселя» спрайта. */
export function drawFrame(ctx: CanvasRenderingContext2D, frame: Frame, scale: number, ox = 0, oy = 0): void {
  ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height)
  frame.forEach((row, y) => {
    for (let x = 0; x < row.length; x++) {
      const c = PALETTE[row[x]]
      if (!c) continue
      ctx.fillStyle = c
      ctx.fillRect(ox + x * scale, oy + y * scale, scale, scale)
    }
  })
}
