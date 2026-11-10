// Анимированный фавикон: кадры кобры рисуются на canvas и подменяют href у <link rel="icon">.
// SVG-анимация во вкладке работает только в Firefox, поэтому так. В фоновой вкладке и при
// reduced-motion стоит статичный /favicon.svg.
import { SEQUENCE, WIDTH, HEIGHT, drawFrame } from '@/lib/kobraSprite'
import { prefersReducedMotion } from './useTheme'

const SIZE = 64
const SCALE = 3 // 20×21 → 60×63, по центру 64×64

let started = false

export function useAnimatedFavicon(): void {
  if (started || typeof document === 'undefined' || prefersReducedMotion()) return
  const link = document.querySelector<HTMLLinkElement>('link[rel="icon"]')
  const canvas = document.createElement('canvas')
  canvas.width = canvas.height = SIZE
  const ctx = canvas.getContext('2d')
  if (!link || !ctx) return
  started = true

  const staticHref = link.href
  const ox = Math.floor((SIZE - WIDTH * SCALE) / 2)
  const oy = Math.floor((SIZE - HEIGHT * SCALE) / 2)
  // Кадры рендерим один раз.
  const frames = SEQUENCE.map((s) => {
    drawFrame(ctx, s.frame, SCALE, ox, oy)
    return { href: canvas.toDataURL('image/png'), ms: s.ms }
  })

  let step = 0
  let timer: ReturnType<typeof setTimeout> | null = null

  const tick = () => {
    const f = frames[step]
    link.type = 'image/png'
    link.href = f.href
    step = (step + 1) % frames.length
    timer = setTimeout(tick, f.ms)
  }
  const stop = () => {
    if (timer) clearTimeout(timer)
    timer = null
    link.type = 'image/svg+xml'
    link.href = staticHref
  }

  document.addEventListener('visibilitychange', () => (document.hidden ? stop() : timer ?? tick()))
  if (!document.hidden) tick()
}
