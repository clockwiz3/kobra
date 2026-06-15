<script setup lang="ts">
/** Бейдж дизайн-системы (.badge / .badge.ok|warn|err из themes.css). */
withDefaults(
  defineProps<{ tone?: 'default' | 'ok' | 'warn' | 'err' }>(),
  { tone: 'default' },
)
</script>

<template>
  <span class="badge" :class="{ ok: tone === 'ok', warn: tone === 'warn', err: tone === 'err' }">
    <slot />
  </span>
</template>
