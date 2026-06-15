<script setup lang="ts">
/**
 * Выбор акцентной гаммы (.palette-grid из themes.css). 5 гамм через [data-accent].
 * Это UI-зерно будущего Customization Engine (выбор пары → couples.settings).
 */
import { useTheme, type Accent } from '../../composables/useTheme'

const { accent, accents, setAccent } = useTheme()

// Превью-рампы (для свотча). Точные значения — в themes.css [data-accent].
const ramps: Record<Accent, [string, string]> = {
  rose: ['#FF6B9D', '#7C5CFC'],
  sunset: ['#FF8E5E', '#FF5E9C'],
  honey: ['#F4B53C', '#9B7CFC'],
  berry: ['#F43F8E', '#6D5CFC'],
  lagoon: ['#37D9BC', '#6C8CFC'],
}
const names: Record<Accent, string> = {
  rose: 'Rose × Violet',
  sunset: 'Sunset',
  honey: 'Honey Lavender',
  berry: 'Berry',
  lagoon: 'Lagoon',
}
</script>

<template>
  <div class="palette-grid">
    <button
      v-for="a in accents"
      :key="a"
      type="button"
      class="palette"
      :aria-pressed="accent === a"
      @click="setAccent(a)"
    >
      <div class="ramp" :style="{ background: `linear-gradient(135deg, ${ramps[a][0]}, ${ramps[a][1]})` }" />
      <div class="pname">{{ names[a] }}</div>
      <div class="dots">
        <i :style="{ background: ramps[a][0] }" />
        <i :style="{ background: ramps[a][1] }" />
      </div>
    </button>
  </div>
</template>
