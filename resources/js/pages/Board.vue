<script setup lang="ts">
import { onMounted, ref } from 'vue'
import draggable from 'vuedraggable'
import ThemeSwitch from '@/components/ui/ThemeSwitch.vue'
import KanbanColumn from '@/components/kanban/KanbanColumn.vue'
import { useBoardStore } from '@/stores/board'
import { useLogger } from '@/composables/useLogger'
import { prefersReducedMotion } from '@/composables/useTheme'

const store = useBoardStore()
const log = useLogger('Board')
const reduced = prefersReducedMotion()
const newColumn = ref('')

function addColumn() {
  if (newColumn.value.trim() === '') return
  store.addColumn(newColumn.value)
  newColumn.value = ''
  log.debug('add-column')
}

onMounted(() => store.fetchBoard())

function onColumnReorder() {
  store.persistColumnReorder()
  log.debug('column-reorder')
}
</script>

<template>
  <header class="topbar">
    <div class="wrap row">
      <div class="brandmark"><span class="dot">&hearts;</span> Cuploid</div>
      <div class="controls">
        <div class="couple">
          <span class="ring"><span class="av a">М</span></span>
          <span class="av b" style="margin-left: -12px">А</span>
        </div>
        <span class="badge">&hearts; стрик 24</span>
        <ThemeSwitch />
      </div>
    </div>
  </header>

  <main class="board-main">
    <div class="board-head wrap">
      <h1 class="sec-title" style="font-size: 30px">Наши дела</h1>
      <span class="label-strip" style="margin: 0">{{ store.totalCards }} карточек · только вы вдвоём</span>
    </div>

    <draggable
      :list="store.columns"
      group="columns"
      item-key="id"
      handle=".kanban-col__title"
      class="board-scroll"
      ghost-class="kanban-ghost"
      :animation="reduced ? 0 : 160"
      @change="onColumnReorder"
    >
      <template #item="{ element }">
        <KanbanColumn :column="element" />
      </template>
      <template #footer>
        <form class="board-addcol" @submit.prevent="addColumn">
          <input v-model="newColumn" class="input" placeholder="+ колонка" />
        </form>
      </template>
    </draggable>
  </main>
</template>

<style scoped>
.board-main {
  padding: 24px 0 0;
  height: calc(100vh - 66px);
  display: flex;
  flex-direction: column;
}
.board-head { display: flex; align-items: baseline; gap: 14px; margin-bottom: 18px; }
.board-scroll {
  flex: 1;
  display: flex;
  gap: 16px;
  align-items: flex-start;
  overflow-x: auto;
  padding: 0 32px 16px;
  min-height: 0;
}
.board-scroll > * { max-height: 100%; }
.board-addcol { flex: none; width: 240px; }
.board-addcol .input { background: var(--surface-2); }
@media (max-width: 860px) {
  .board-scroll { padding: 0 16px 16px; scroll-snap-type: x mandatory; }
}
</style>
