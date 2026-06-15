<script setup lang="ts">
/**
 * Кнопка дизайн-системы Cuploid. Классы из resources/css/themes.css (.btn / .btn-*).
 * Темо- и гаммо-зависима автоматически через CSS-переменные.
 */
withDefaults(
  defineProps<{
    variant?: 'primary' | 'secondary' | 'ghost'
    size?: 'md' | 'sm'
    type?: 'button' | 'submit' | 'reset'
  }>(),
  { variant: 'primary', size: 'md', type: 'button' },
)
</script>

<template>
  <button
    class="btn"
    :class="[`btn-${variant}`, { 'btn-sm': size === 'sm' }]"
    :type="type"
  >
    <slot />
  </button>
</template>
