<script setup lang="ts">
/**
 * Индикатор присутствия партнёра (онлайн-статус из presence-канала пары).
 * Зелёная точка = в сети, приглушённая = офлайн. Опциональная подпись.
 */
defineProps<{ online: boolean; label?: string }>()
</script>

<template>
  <span
    class="presence"
    :class="{ 'is-online': online }"
    :title="label ?? (online ? 'Партнёр в сети' : 'Партнёр не в сети')"
  >
    <span class="presence__dot" aria-hidden="true" />
    <span v-if="label" class="presence__label">{{ label }}</span>
  </span>
</template>

<style scoped>
.presence {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.presence__dot {
  width: 9px;
  height: 9px;
  border-radius: 50%;
  flex: none;
  background: var(--border-strong);
}
.presence.is-online .presence__dot {
  background: var(--success);
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--success) 22%, transparent);
}
.presence__label {
  font-size: 13px;
  color: var(--text-muted, inherit);
}
</style>
