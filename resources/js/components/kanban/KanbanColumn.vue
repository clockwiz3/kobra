<script setup lang="ts">
import { ref, computed } from 'vue'
import draggable from 'vuedraggable'
import { CARD_TITLE_MAX, type Card, type Column } from '@/types/board'
import KanbanCard from './KanbanCard.vue'
import { useBoardStore } from '@/stores/board'
import { useLogger } from '@/composables/useLogger'
import { useConfirm } from '@/composables/useConfirm'
import { prefersReducedMotion } from '@/composables/useTheme'

const props = defineProps<{ column: Column }>()
const store = useBoardStore()
const log = useLogger('KanbanColumn')
const { confirm } = useConfirm()
const reduced = prefersReducedMotion()

// есть ли куда переносить карточки при удалении (нужна хотя бы одна соседняя колонка)
const hasSiblings = computed(() => store.columns.length > 1)

// число карточек, проходящих фильтр доски (бейдж показывает видимые, не все)
const visibleCount = computed(() => props.column.cards.filter((c) => store.cardMatchesFilter(c)).length)

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

async function remove() {
  const hasCards = props.column.cards.length > 0
  const offerChoice = hasSiblings.value && hasCards

  const res = await confirm(
    offerChoice
      ? {
          title: `Удалить колонку «${props.column.title}»?`,
          tone: 'danger',
          confirmText: 'Удалить колонку',
          defaultChoice: 'keep',
          choices: [
            { value: 'keep', label: 'Сохранить карточки', hint: 'перенести в соседнюю колонку' },
            { value: 'delete', label: 'Удалить карточки', hint: 'удалить вместе с колонкой' },
          ],
        }
      : {
          title: `Удалить колонку «${props.column.title}»?`,
          tone: 'danger',
          confirmText: 'Удалить колонку',
          message: hasCards ? 'Колонка и все её карточки будут удалены.' : 'Колонка будет удалена.',
        },
  )
  if (!res.confirmed) return

  await store.removeColumn(props.column.id, { keepCards: res.choice === 'keep' })
  log.debug('remove', { columnId: props.column.id, choice: res.choice })
}

function onCardChange(e: { added?: { element: Card; newIndex: number }; moved?: { element: Card; newIndex: number } }) {
  const change = e.added ?? e.moved
  if (change) store.persistCardMove(String(change.element.id), props.column.id, change.newIndex)
  log.debug('card-drop', { columnId: props.column.id })
}

// [FIX] флаг drag поднимается на источнике (start/end приходят в исходную колонку),
// списки карточек на это время клиппят overflow — иначе FLIP-трансформ карточки
// при переносе вылезает за content-box принимающей колонки и мигают скроллбары
function onDragStart() {
  store.dragging = true
  log.debug('[FIX] drag-start', { columnId: props.column.id })
}
function onDragEnd() {
  store.dragging = false
  log.debug('[FIX] drag-end', { columnId: props.column.id })
}
</script>

<template>
  <div class="kanban-col">
    <div class="kanban-col__head">
      <template v-if="!editing">
        <h3 class="sub-h kanban-col__title" style="margin: 0" @dblclick="startRename">{{ column.title }}</h3>
        <!-- В терминальной «Готово» крестика нет → счётчик уводим к правому краю (на место крестика). -->
        <span class="badge" :class="{ 'kanban-col__count--end': column.isTerminal }">{{ visibleCount }}</span>
      </template>
      <input
        v-else
        v-model="editTitle"
        class="input"
        @keydown.enter="saveRename"
        @keydown.esc="editing = false"
        @blur="saveRename"
      />
      <!-- Терминальную «Готово» удалять нельзя — кнопку прячем (visibility:hidden), но МЕСТО
           сохраняем: она самый высокий элемент шапки, поэтому держит её высоту вровень с
           остальными колонками (счётчик при этом уезжает правее неё, см. --count--end). -->
      <button
        class="btn btn-ghost btn-sm kanban-col__del"
        :class="{ 'is-invisible': column.isTerminal }"
        :aria-hidden="column.isTerminal || undefined"
        :tabindex="column.isTerminal ? -1 : undefined"
        aria-label="Удалить колонку"
        @click="!column.isTerminal && remove()"
      >&times;</button>
    </div>

    <!-- Скролл-область ПОД шапкой: только она прокручивается вертикально, поэтому скроллбар
         не заезжает на шапку. Шапка выше — статичная «крышка» (не sticky). -->
    <div class="kanban-col__scroll ds-scroll">
      <draggable
        :list="column.cards"
        group="cards"
        item-key="id"
        class="kanban-col__cards"
        ghost-class="kanban-ghost"
        :animation="reduced ? 0 : 160"
        @change="onCardChange"
        @start="onDragStart"
        @end="onDragEnd"
      >
        <template #item="{ element }">
          <KanbanCard
            :card="element"
            @open="store.openCard(element.id)"
            @remove="store.removeCard(element.id)"
          />
        </template>
      </draggable>

      <!-- В терминальной колонке карточки не создают напрямую — они попадают в неё переносом -->
      <form v-if="!column.isTerminal" class="kanban-col__add" @submit.prevent="addCard">
        <input v-model="newTitle" class="input" :maxlength="CARD_TITLE_MAX" placeholder="+ карточка" />
      </form>
    </div>
  </div>
</template>

<style scoped>
.kanban-col {
  width: 286px;
  flex: none;
  /* высота = вьюпорт (align-items:stretch + height:100% у .board-row) */
  height: 100%;
  display: flex;
  flex-direction: column;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--r-lg);
  /* колонка сама НЕ скроллится — скроллит .kanban-col__scroll под шапкой. overflow:hidden
     клипает контент (статичную шапку сверху и список снизу) ровно по скруглённым углам. */
  overflow: hidden;
}
/* Статичная «крышка» колонки (НЕ sticky): всегда сверху, скроллбар на неё не заезжает, т.к.
   скроллится только область ниже. position:relative + z-index — чтобы её нижняя тень рисовалась
   ПОВЕРХ скролл-области (карточки уходят под тень). Углы скругляет overflow:hidden у колонки. */
.kanban-col__head {
  position: relative; z-index: 1;
  display: flex; align-items: center; gap: 8px;
  background: var(--surface);
  padding: 14px 14px 12px;
  /* тонкая нижняя тень — отделяет шапку от уезжающих под неё карточек (во всех темах) */
  box-shadow: 0 8px 10px -10px rgba(0, 0, 0, 0.45);
}
/* в светлой теме чёрная тень жёсткая → делаем мягче и теплее (под палитру темы).
   data-theme висит на .ds-root (предок), поэтому селектор с ним — рабочий и в scoped. */
[data-theme="light"] .kanban-col__head { box-shadow: 0 7px 14px -10px rgba(110, 72, 52, 0.22); }
/* Скролл-область под шапкой — единственный вертикальный скроллер колонки. padding-top = отступ
   первой карточки от шапки/тени (чтобы не липла вплотную). */
.kanban-col__scroll {
  flex: 1; min-height: 0;
  overflow-y: auto; overflow-x: hidden;
  padding: 10px 14px 14px;
  display: flex; flex-direction: column;
}
/* кнопка удаления в «Готово»: скрыта, но занимает место (держит высоту шапки) */
.kanban-col__del.is-invisible { visibility: hidden; pointer-events: none; }
/* в «Готово» счётчик становится последним flex-элементом (после скрытого крестика) → прижат
   к правому краю, ровно туда, где у остальных колонок стоит крестик */
.kanban-col__count--end { order: 1; }
.kanban-col__title { flex: 1; cursor: grab; }
/* flex: 1 0 auto — список заполняет свободную высоту (вся пустая область остаётся зоной дропа
   для DnD) и прижимает форму вниз, НО не сжимается ниже своего контента (иначе форма
   «вклинивалась» бы в середину). При многих карточках список = высоте контента и скроллится. */
.kanban-col__cards { flex: 1 0 auto; display: flex; flex-direction: column; gap: 10px; min-height: 24px; }
.kanban-col__add { margin-top: 12px; } /* отступ от карточек до формы */
.kanban-col__add .input { font-size: 14px; }
</style>

<style>
/* ghost (перетаскиваемая копия) — не scoped, чтобы класс применился к клону */
.kanban-ghost { opacity: 0.4; }

/* [FIX] во время DnD клиппим список карточек: FLIP-перестановка иначе вылезает за скролл-область
   и та моргает скроллбаром при вставке. Клиппим именно список, а не .kanban-col__scroll — её
   авто-скролл при drag остаётся рабочим. .is-dragging висит на .board-main (см. Board.vue),
   поэтому правило non-scoped. */
.is-dragging .kanban-col__cards { overflow: hidden; }

/* Фильтры доски — чистый CSS: контекст на .board-main ([data-filter-*]), карточки несут свои
   data-атрибуты (KanbanCard). Скрываем несоответствующие; они остаются в DOM и :list драггабла,
   поэтому индексы DnD не плывут, а vnode айтема vuedraggable не трогаем (иначе ломается его рендер). */
[data-filter-assignee="him"] .task:not([data-assignee="him"]),
[data-filter-assignee="her"] .task:not([data-assignee="her"]),
[data-filter-assignee="together"] .task:not([data-assignee="together"]),
[data-filter-deadline="has"] .task:not([data-has-due]),
[data-filter-deadline="none"] .task[data-has-due],
[data-filter-deadline="overdue"] .task:not([data-overdue]) { display: none; }
</style>
