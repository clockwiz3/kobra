<script setup lang="ts">
/** Переключатель тем (.theme-switch). Управляет useTheme (dark/light/pixel). */
import { useTheme, type Theme } from '../../composables/useTheme'

const { theme, themes, setTheme } = useTheme()
const labels: Record<Theme, string> = { dark: 'Dark', light: 'Light', pixel: 'Pixel' }
</script>

<template>
  <div class="theme-switch" role="group" aria-label="Тема">
    <button
      v-for="t in themes"
      :key="t"
      type="button"
      :aria-pressed="theme === t"
      @click="setTheme(t)"
    >
      {{ labels[t] }}
    </button>
  </div>
</template>
