<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { prefersReducedMotion } from '@/composables/useTheme'

// Лёгкие плавающие сердечки (.hearts-bg / @keyframes floatup из themes.css).
// Уважает prefers-reduced-motion: при reduce ничего не рисуем.
const root = ref<HTMLElement | null>(null)

const HEART =
  '<svg viewBox="0 0 24 24" fill="currentColor" width="100%" height="100%"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>'

onMounted(() => {
  if (prefersReducedMotion() || root.value === null) return
  for (let i = 0; i < 9; i++) {
    const node = document.createElement('div')
    const size = 14 + Math.random() * 34
    node.innerHTML = HEART
    node.style.cssText = [
      'position:absolute',
      'color:var(--accent)',
      'opacity:.12',
      `width:${size}px`,
      `height:${size}px`,
      `left:${Math.random() * 100}%`,
      'bottom:-40px',
      `animation:floatup ${11 + Math.random() * 8}s linear infinite`,
      `animation-delay:${-Math.random() * 14}s`,
    ].join(';')
    root.value.appendChild(node)
  }
})
</script>

<template>
  <div ref="root" class="hearts-bg" aria-hidden="true" />
</template>
