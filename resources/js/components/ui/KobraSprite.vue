<script setup lang="ts">
// Пиксельная кобра (талисман). SVG из прямоугольников, crispEdges — пиксели не мылятся
// при любом размере. Анимация по SEQUENCE из lib/kobraSprite; при reduced-motion стоит.
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { HEIGHT, IDLE, PALETTE, SEQUENCE, WIDTH, type Frame } from '@/lib/kobraSprite'
import { prefersReducedMotion } from '@/composables/useTheme'

const props = withDefaults(defineProps<{ size?: number; animated?: boolean; label?: string }>(), {
  size: 64,
  animated: true,
  label: 'Кобра',
})

const frame = ref<Frame>(IDLE)
let step = 0
let timer: ReturnType<typeof setTimeout> | null = null

function tick() {
  const s = SEQUENCE[step]
  frame.value = s.frame
  step = (step + 1) % SEQUENCE.length
  timer = setTimeout(tick, s.ms)
}

onMounted(() => {
  if (props.animated && !prefersReducedMotion()) tick()
})
onBeforeUnmount(() => {
  if (timer) clearTimeout(timer)
})

// Склеиваем подряд идущие пиксели одного цвета в строке — меньше rect-ов.
const rects = computed(() => {
  const out: { x: number; y: number; w: number; fill: string }[] = []
  frame.value.forEach((row, y) => {
    let x = 0
    while (x < row.length) {
      const c = row[x]
      let w = 1
      while (x + w < row.length && row[x + w] === c) w++
      if (PALETTE[c]) out.push({ x, y, w, fill: PALETTE[c] })
      x += w
    }
  })
  return out
})
</script>

<template>
  <svg
    class="kobra-sprite"
    :width="size"
    :height="Math.round((size * HEIGHT) / WIDTH)"
    :viewBox="`0 0 ${WIDTH} ${HEIGHT}`"
    shape-rendering="crispEdges"
    role="img"
    :aria-label="label"
  >
    <rect v-for="(r, i) in rects" :key="i" :x="r.x" :y="r.y" :width="r.w" height="1" :fill="r.fill" />
  </svg>
</template>

<style scoped>
.kobra-sprite { display: block; flex: none; image-rendering: pixelated; }
</style>
