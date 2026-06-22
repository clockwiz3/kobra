<script setup lang="ts">
// Раздел «Задачи» (бэклог): карточки, убранные с доски (on_board=false) — БЕЗ удаления.
// Действия: вернуть на доску (с выбором колонки), удалить навсегда, открыть параметры (drawer).
import { onMounted, reactive, computed, ref } from 'vue'
import { BaseCard, BaseButton, BaseSelect, BaseChip } from '@/components/ui'
import { CardDetailDrawer } from '@/components/composite'
import { useBoardStore } from '@/stores/board'
import { useConfirm } from '@/composables/useConfirm'
import { useAssignees } from '@/composables/useAssignees'
import { useCardFrame } from '@/composables/useCardFrame'
import { formatRange } from '@/lib/date'
import { CARD_TITLE_MAX, type Card } from '@/types/board'
import { useLogger } from '@/composables/useLogger'

const store = useBoardStore()
const { confirm } = useConfirm()
const log = useLogger('Backlog')
const { assigneeLabel } = useAssignees()
const { renderForAssignee } = useCardFrame()
// Рамка бэклог-карточки по исполнителю (фон .bl-card = --surface).
const cardFrame = (card: Card) => renderForAssignee(card.assignee, 'var(--surface)')

// Колонки нужны и для селекта возврата, и для подписи исходной колонки — поэтому грузим доску,
// затем бэклог (fetchBacklog работает только в backed-режиме, который выставляет fetchBoard).
onMounted(async () => {
  await store.fetchBoard()
  await store.fetchBacklog()
  log.debug('mounted', { count: store.backlogCards.length })
})

// Опции селекта: все колонки доски (терминальная помечается — возврат в неё закроет карточку).
const columnOptions = computed(() =>
  store.columns.map((c) => ({ value: c.id, label: c.isTerminal ? `${c.title} (Готово)` : c.title })),
)

/** Дедлайн-чип (как на карточке доски): дата и/или время в один чип. */
function deadlineText(card: Card): string | null {
  const date = formatRange(card.dueAt, card.dueEndAt)
  const time = card.dueTime
  if (date && time) return `${date} ${time}`
  return date || time || null
}

/** Дефолтная колонка возврата: исходная (если ещё существует), иначе первая не-терминальная, иначе первая. */
function defaultColumnId(card: Card): string {
  if (card.columnId && store.columns.some((c) => c.id === card.columnId)) return card.columnId
  return (store.columns.find((c) => !c.isTerminal) ?? store.columns[0])?.id ?? ''
}

// Выбранная колонка возврата на карточку (по умолчанию — defaultColumnId, лениво).
const picked = reactive<Record<string, string>>({})
const targetFor = (card: Card): string => picked[card.id] ?? defaultColumnId(card)
const setTarget = (card: Card, v: string): void => { picked[card.id] = v }

function onReturn(card: Card): void {
  const to = targetFor(card)
  if (!to) return
  delete picked[card.id]
  void store.returnToBoard(card.id, to)
  log.debug('return', { cardId: card.id, to })
}

// Добавление задачи прямо в бэклог (on_board=false) — на доску не попадает.
const newTitle = ref('')
function onAdd(): void {
  const title = newTitle.value.trim()
  if (title === '') return
  void store.addBacklogCard(title)
  newTitle.value = ''
  log.debug('add', { title })
}

async function onDelete(card: Card): Promise<void> {
  const res = await confirm({
    title: 'Удалить карточку навсегда?',
    message: `«${card.title}» будет удалена без возможности восстановления.`,
    confirmText: 'Удалить',
    cancelText: 'Отмена',
    tone: 'danger',
  })
  if (!res.confirmed) return
  void store.removeFromBacklog(card.id)
  log.debug('delete', { cardId: card.id })
}
</script>

<template>
  <section class="section-stub">
    <h1 class="sec-title">Задачи</h1>

    <!-- Добавить задачу прямо в бэклог (минуя доску). Видна всегда, в т.ч. при пустом списке. -->
    <form class="bl-add" @submit.prevent="onAdd">
      <input
        v-model="newTitle"
        class="input"
        :maxlength="CARD_TITLE_MAX"
        placeholder="+ задача в бэклог"
        aria-label="Новая задача в бэклоге"
      />
      <BaseButton variant="primary" size="sm" type="submit" :disabled="newTitle.trim() === ''">Добавить</BaseButton>
    </form>

    <!-- Пусто: тёплый намёк, что сюда попадают убранные с доски карточки. -->
    <BaseCard v-if="store.backlogCards.length === 0">
      <p class="stub-text">Пока пусто. Добавьте задачу выше или уберите карточку с доски — она появится здесь. 🗂️</p>
    </BaseCard>

    <ul v-else class="backlog">
      <li v-for="card in store.backlogCards" :key="card.id" class="bl-card" :class="[{ done: card.done }, cardFrame(card).class]" :style="cardFrame(card).style">
        <!-- Тело: клик открывает панель параметров (редактирование) -->
        <div
          class="bl-card__body"
          role="button"
          tabindex="0"
          :aria-label="`Открыть карточку: ${card.title}`"
          @click="store.openCard(card.id)"
          @keydown.enter.space.prevent="store.openCard(card.id)"
        >
          <div class="bl-card__title">{{ card.title }}</div>
          <div class="bl-card__chips">
            <BaseChip :tone="card.assignee === 'together' ? 'accent' : 'neutral'">{{ assigneeLabel(card.assignee) }}</BaseChip>
            <BaseChip v-if="deadlineText(card)" tone="warn">{{ deadlineText(card) }}</BaseChip>
          </div>
        </div>

        <!-- Действия: выбор колонки + вернуть + удалить -->
        <div class="bl-card__actions" @click.stop>
          <BaseSelect
            :model-value="targetFor(card)"
            :options="columnOptions"
            aria-label="Колонка для возврата"
            @update:model-value="(v) => setTarget(card, v)"
          />
          <BaseButton variant="primary" size="sm" @click="onReturn(card)">На доску</BaseButton>
          <BaseButton variant="ghost" size="sm" @click="onDelete(card)">Удалить</BaseButton>
        </div>
      </li>
    </ul>
  </section>

  <!-- Панель параметров: самодостаточна (читает store.selectedCard; findCard видит бэклог). -->
  <CardDetailDrawer />
</template>

<style scoped>
.section-stub { flex: 1; min-height: 0; overflow-y: auto; padding: 24px 32px; }
.section-stub .sec-title { font-size: 30px; margin: 0 0 18px; }
.stub-text { color: var(--text-muted); margin: 0; text-wrap: pretty; }

/* Форма добавления задачи в бэклог. */
.bl-add { display: flex; gap: 8px; align-items: center; max-width: 760px; margin-bottom: 16px; }
.bl-add .input { flex: 1; min-width: 0; }

.backlog { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 10px; max-width: 760px; }
.bl-card {
  display: flex; align-items: center; gap: 16px; flex-wrap: wrap;
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--r-lg); padding: 12px 14px;
}
[data-theme="pixel"] .bl-card { border-width: 2px; }
.bl-card.done .bl-card__title { text-decoration: line-through; color: var(--text-muted); }

.bl-card__body { flex: 1; min-width: 220px; cursor: pointer; }
.bl-card__title {
  font-weight: 600;
  display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 2; line-clamp: 2; overflow: hidden;
}
.bl-card__chips { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; margin-top: 6px; }

.bl-card__actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }

@media (max-width: 860px) {
  .section-stub { padding: 16px; }
  .bl-card__actions { width: 100%; }
}
</style>
