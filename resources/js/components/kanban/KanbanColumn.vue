<script setup lang="ts">
import { ref } from 'vue'
import draggable from 'vuedraggable'
import type { Card, Column } from '@/types/board'
import KanbanCard from './KanbanCard.vue'
import { useBoardStore } from '@/stores/board'
import { useLogger } from '@/composables/useLogger'
import { prefersReducedMotion } from '@/composables/useTheme'

const props = defineProps<{ column: Column }>()
const store = useBoardStore()
const log = useLogger('KanbanColumn')
const reduced = prefersReducedMotion()

const newTitle = ref('')
const editing = ref(false)
const editTitle = ref(props.column.title)

function addCard() {
  if (newTitle.value.trim() === '') return
  store.addCard(props.column.id, newTitle.value)
  newTitle.value = ''
  log.debug('add-card', { columnId: props.column.id })
}

function startRename() {
  editTitle.value = props.column.title
  editing.value = true
}

function saveRename() {
  store.renameColumn(props.column.id, editTitle.value)
  editing.value = false
  log.debug('rename', { columnId: props.column.id })
}

function remove() {
  if (window.confirm(`Удалить колонку «${props.column.title}» со всеми карточками?`)) {
    store.removeColumn(props.column.id)
    log.debug('remove', { columnId: props.column.id })
  }
}

function onCardChange(e: { added?: { element: Card; newIndex: number }; moved?: { element: Card; newIndex: number } }) {
  const change = e.added ?? e.moved
  if (change) store.persistCardMove(String(change.element.id), props.column.id, change.newIndex)
  log.debug('card-drop', { columnId: props.column.id })
}
</script>

<template>
  <div class="kanban-col">
    <div class="kanban-col__head">
      <template v-if="!editing">
        <h3 class="sub-h kanban-col__title" style="margin: 0" @dblclick="startRename">{{ column.title }}</h3>
        <span class="badge">{{ column.cards.length }}</span>
      </template>
      <input
        v-else
        v-model="editTitle"
        class="input"
        @keydown.enter="saveRename"
        @keydown.esc="editing = false"
        @blur="saveRename"
      />
      <button class="btn btn-ghost btn-sm" aria-label="Удалить колонку" @click="remove">&times;</button>
    </div>

    <draggable
      :list="column.cards"
      group="cards"
      item-key="id"
      class="kanban-col__cards"
      ghost-class="kanban-ghost"
      :animation="reduced ? 0 : 160"
      @change="onCardChange"
    >
      <template #item="{ element }">
        <KanbanCard
          :card="element"
          @toggle-done="store.toggleDone(element.id)"
          @remove="store.removeCard(element.id)"
        />
      </template>
    </draggable>

    <form class="kanban-col__add" @submit.prevent="addCard">
      <input v-model="newTitle" class="input" placeholder="+ карточка" />
    </form>
  </div>
</template>

<style scoped>
.kanban-col {
  width: 286px;
  flex: none;
  display: flex;
  flex-direction: column;
  gap: 12px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--r-lg);
  padding: 14px;
  max-height: 100%;
}
.kanban-col__head { display: flex; align-items: center; gap: 8px; }
.kanban-col__title { flex: 1; cursor: grab; }
.kanban-col__cards { display: flex; flex-direction: column; gap: 10px; overflow-y: auto; min-height: 24px; }
.kanban-col__add .input { font-size: 14px; }
</style>

<style>
/* ghost (перетаскиваемая копия) — не scoped, чтобы класс применился к клону */
.kanban-ghost { opacity: 0.4; }
</style>
