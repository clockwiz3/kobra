<script setup lang="ts">
import { onMounted, ref } from 'vue'
import draggable from 'vuedraggable'
import BaseButton from '@/components/ui/BaseButton.vue'
import { BaseModal, BaseField, BaseSelect } from '@/components/ui'
import KanbanColumn from '@/components/kanban/KanbanColumn.vue'
import { CardDetailDrawer } from '@/components/composite'
import type { Column } from '@/types/board'
import { useBoardStore } from '@/stores/board'
import { useAssignees } from '@/composables/useAssignees'
import { useLogger } from '@/composables/useLogger'
import { prefersReducedMotion } from '@/composables/useTheme'

const store = useBoardStore()
const log = useLogger('Board')
const reduced = prefersReducedMotion()
const { assigneeFilterOptions } = useAssignees()

const newColumn = ref('')
const showAddColumn = ref(false)

// Опции фильтров доски (значения совпадают со стором: filterAssignee / filterDeadline).
// По исполнителю — имена аккаунтов пары (см. useAssignees), + «Кто угодно».
const deadlineOptions = [
  { value: 'all', label: 'Дедлайн: любой' },
  { value: 'has', label: 'С дедлайном' },
  { value: 'none', label: 'Без дедлайна' },
  { value: 'overdue', label: 'Просроченные' },
]
function onAssigneeFilter(v: string) { store.filterAssignee = v as typeof store.filterAssignee }
function onDeadlineFilter(v: string) { store.filterDeadline = v as typeof store.filterDeadline }

// число карточек-плейсхолдеров на колонку скелетона (просто для естественного вида)
const skeletonColumns = [2, 3, 1, 2]

function openAddColumn() {
  newColumn.value = ''
  showAddColumn.value = true
}

function submitAddColumn() {
  const title = newColumn.value.trim()
  if (title === '') return
  store.addColumn(title) // вставится ПЕРЕД терминальной «Готово»
  newColumn.value = ''
  showAddColumn.value = false
  log.debug('add-column')
}

onMounted(() => store.fetchBoard())

// «Готово» всегда последняя: нельзя двигать саму терминальную колонку и нельзя
// вставать ПОСЛЕ неё (willInsertAfter). Возврат false отменяет drop в этих случаях.
function onColumnMove(evt: {
  draggedContext: { element: Column }
  relatedContext: { element: Column | null }
  willInsertAfter?: boolean
}): boolean {
  if (evt.draggedContext.element.isTerminal) return false
  if (evt.relatedContext.element?.isTerminal && evt.willInsertAfter) return false
  return true
}

function onColumnReorder() {
  // бэкстоп к :move и серверу — держим терминальную последней и локально
  const ti = store.columns.findIndex((c) => c.isTerminal)
  if (ti !== -1 && ti !== store.columns.length - 1) {
    const [term] = store.columns.splice(ti, 1)
    store.columns.push(term)
  }
  store.persistColumnReorder()
  log.debug('column-reorder')
}

// [FIX] drag колонок тоже клиппит overflow карточных списков на время жеста
function onColumnDragStart() {
  store.dragging = true
}
function onColumnDragEnd() {
  store.dragging = false
}
</script>

<template>
  <!-- Доска живёт в слоте AppShell (топбар/меню — в лейауте). Корень страницы — .board-main:
       занимает высоту слота (flex:1) и держит скролл внутри (горизонталь — лента колонок,
       вертикаль — внутри каждой колонки). Сама страница вертикально не скроллится. -->
  <div
    class="board-main"
    :class="{ 'is-dragging': store.dragging }"
    :aria-busy="store.loading"
    :data-filter-assignee="store.filterAssignee"
    :data-filter-deadline="store.filterDeadline"
  >
    <!-- «Делишки» слева, далее ряд контролов доски: добавить колонку + фильтры -->
    <div class="board-head">
      <h1 class="sec-title board-head__title">Делишки</h1>
      <div class="board-head__controls">
        <BaseButton variant="secondary" size="sm" @click="openAddColumn">+ колонка</BaseButton>
        <BaseSelect
          :model-value="store.filterAssignee"
          :options="assigneeFilterOptions"
          aria-label="Фильтр по исполнителю"
          @update:model-value="onAssigneeFilter"
        />
        <BaseSelect
          :model-value="store.filterDeadline"
          :options="deadlineOptions"
          aria-label="Фильтр по дедлайну"
          @update:model-value="onDeadlineFilter"
        />
      </div>
    </div>

    <!-- Единственный скролл-контейнер всего канбана: вертикаль (классический скролл справа) +
         горизонталь для колонок. Колонки внутри — по высоте контента, без внутренних скроллов,
         поэтому вся лента листается синхронно одним скроллом. -->
    <div class="board-viewport ds-scroll">
      <!-- [FIX] скелетон на первичную загрузку: иначе на долю секунды мигают мок-карточки,
           которыми инициализирован стор (graceful-фолбэк), до ответа fetchBoard -->
      <div v-if="store.loading" class="board-row board-skeleton" aria-hidden="true">
        <div v-for="(count, ci) in skeletonColumns" :key="ci" class="sk-col">
          <div class="sk-bar sk-head"></div>
          <div v-for="n in count" :key="n" class="sk-bar sk-card"></div>
        </div>
      </div>

      <draggable
        v-else
        :list="store.columns"
        group="columns"
        item-key="id"
        handle=".kanban-col__title"
        class="board-row"
        ghost-class="kanban-ghost"
        :animation="reduced ? 0 : 160"
        :move="onColumnMove"
        @change="onColumnReorder"
        @start="onColumnDragStart"
        @end="onColumnDragEnd"
      >
        <template #item="{ element }">
          <KanbanColumn :column="element" />
        </template>
      </draggable>
    </div>
  </div>

  <!-- Выезжающая панель параметров карточки (самодостаточна: open берёт из стора) -->
  <CardDetailDrawer />

  <!-- Добавление колонки через модалку. Колонка добавляется последней, но перед «Готово». -->
  <BaseModal v-model="showAddColumn" title="Новая колонка" size="sm">
    <form @submit.prevent="submitAddColumn">
      <BaseField v-model="newColumn" label="Название колонки" placeholder="Например, На неделе" />
    </form>
    <template #footer="{ close }">
      <BaseButton variant="ghost" size="sm" @click="close">Отмена</BaseButton>
      <BaseButton variant="primary" size="sm" :disabled="newColumn.trim() === ''" @click="submitAddColumn">Добавить</BaseButton>
    </template>
  </BaseModal>
</template>

<style scoped>
/* Корень страницы доски = flex-ребёнок .app-main (каркас и высоту даёт AppShell).
   Высота берётся из flex-слота, а не из magic-числа calc(100vh - 66px);
   страница вертикально не скроллится — вертикаль только внутри колонок. */
.board-main {
  padding: 24px 0 0;
  flex: 1;
  min-height: 0;
  display: flex;
  flex-direction: column;
}
/* «Делишки» слева (в одну линию с колонками — те же 32px паддинга, без .wrap-центрирования),
   контролы — сразу после неё */
.board-head { display: flex; align-items: center; gap: 16px; margin-bottom: 18px; padding: 0 32px; }
.board-head__title { font-size: 30px; }
.board-head__controls { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
/* Вьюпорт скроллит ТОЛЬКО по горизонтали (лента колонок). Вертикаль — внутри каждой колонки
   (overflow-y у .kanban-col), чтобы её скруглённые углы клипали прилипшую шапку (см. KanbanColumn). */
.board-viewport {
  flex: 1;
  min-height: 0;
  overflow-x: auto;
  overflow-y: hidden;
  padding: 0 32px 16px;
}
.board-row {
  display: flex;
  gap: 16px;
  /* колонки одной высоты: тянутся по кросс-оси на всю высоту ряда */
  align-items: stretch;
  /* height:100% — ряд ровно во вьюпорт; колонки = высоте вьюпорта и скроллят контент внутри себя */
  height: 100%;
  width: max-content;
  min-width: 100%;
}
@media (max-width: 860px) {
  .board-head { padding: 0 16px; }
  .board-viewport { padding: 0 16px 16px; }
}

/* ── Скелетон первичной загрузки доски ── */
.board-skeleton { pointer-events: none; }
.sk-col {
  width: 286px;
  flex: none;
  display: flex;
  flex-direction: column;
  gap: 12px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--r-lg);
  padding: 14px;
}
.sk-bar {
  position: relative;
  overflow: hidden;
  background: var(--surface-2);
}
.sk-head { height: 22px; width: 60%; border-radius: var(--r-pill); }
.sk-card { height: 66px; border-radius: var(--r-md); }
/* мягкий shimmer; токены тем (включая pixel с r=0) подхватываются автоматически */
.sk-bar::after {
  content: '';
  position: absolute;
  inset: 0;
  transform: translateX(-100%);
  background: linear-gradient(90deg, transparent, color-mix(in srgb, var(--text) 9%, transparent), transparent);
  animation: sk-shimmer 1.25s var(--ease, ease) infinite;
}
@keyframes sk-shimmer { to { transform: translateX(100%); } }
@media (prefers-reduced-motion: reduce) {
  .sk-bar::after { animation: none; }
}
</style>
