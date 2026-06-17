<script setup lang="ts">
import { computed } from 'vue'

/**
 * Чип/тег дизайн-системы (.chip). Геометрия (высота, контур, радиус, шрифт, паддинг) едина
 * во всех темах — различаются ТОЛЬКО содержимое и цвет/фон через tone:
 *   neutral — нейтральный (по умолчанию), accent — brand-gradient заливка, warn — акцент (дедлайн).
 * `solid` — обратносовместимый алиас tone="accent".
 */
const props = withDefaults(
  defineProps<{ tone?: 'neutral' | 'accent' | 'warn'; solid?: boolean; removable?: boolean }>(),
  { tone: 'neutral', solid: false, removable: false },
)
defineEmits<{ remove: [] }>()

const toneClass = computed(() => ({
  solid: props.tone === 'accent' || props.solid,
  warn: props.tone === 'warn',
}))
</script>

<template>
  <span class="chip" :class="toneClass">
    <slot />
    <span
      v-if="removable"
      class="x"
      role="button"
      tabindex="0"
      aria-label="Удалить"
      @click="$emit('remove')"
      @keydown.enter.space.prevent="$emit('remove')"
    >&times;</span>
  </span>
</template>
