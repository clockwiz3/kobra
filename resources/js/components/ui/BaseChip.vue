<script setup lang="ts">
/** Чип/тег дизайн-системы (.chip / .chip.solid). solid = brand-gradient заливка. */
withDefaults(
  defineProps<{ solid?: boolean; removable?: boolean }>(),
  { solid: false, removable: false },
)
defineEmits<{ remove: [] }>()
</script>

<template>
  <span class="chip" :class="{ solid }">
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
