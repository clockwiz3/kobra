<script setup lang="ts">
import { computed } from 'vue'
import type { Card } from '@/types/board'
import { useLogger } from '@/composables/useLogger'

const props = defineProps<{ card: Card }>()
const emit = defineEmits<{ 'toggle-done': []; remove: [] }>()
const log = useLogger('KanbanCard')

const assigneeLabel = computed(() => {
  return { him: 'Он', her: 'Она', together: '♥ Вместе' }[props.card.assignee]
})

function toggle() {
  log.debug('toggle-done', { id: props.card.id })
  emit('toggle-done')
}
</script>

<template>
  <div class="task" :class="{ done: card.done }">
    <div
      class="check"
      role="checkbox"
      :aria-checked="card.done"
      tabindex="0"
      @click="toggle"
      @keydown.enter.space.prevent="toggle"
    >
      <svg v-if="card.done" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
        <path d="M20 6L9 17l-5-5" />
      </svg>
    </div>

    <div style="flex: 1; min-width: 0">
      <div class="tt">{{ card.title }}</div>
      <div class="tm">
        <span class="chip" :class="{ solid: card.assignee === 'together' }" style="padding: 3px 9px; font-size: 11px">
          {{ assigneeLabel }}
        </span>
        <span v-for="l in card.labels" :key="l" class="chip" style="padding: 2px 8px; font-size: 11px">{{ l }}</span>
        <span v-if="card.dueAt" class="badge warn" style="padding: 2px 7px">{{ card.dueAt }}</span>
      </div>
    </div>
  </div>
</template>
