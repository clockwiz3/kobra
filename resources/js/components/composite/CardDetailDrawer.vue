<script setup lang="ts">
/**
 * Панель параметров карточки (composite) — содержимое выезжающей панели поверх BaseDrawer.
 * Привязана к store.selectedCard; открытие/закрытие — через store.selectedCardId.
 * Автосохранение: изменение любого поля → debounced store.updateCard (оптимистично).
 *
 * Секции параметров идут сверху вниз (название/описание/исполнитель/дедлайн).
 * МАСШТАБИРОВАНИЕ: новый параметр карточки = новая секция .card-detail__section +
 * поле в form + ветка в buildPatch(). Контракт патча — CardPatch (@/types/board).
 */
import { reactive, computed, watch, nextTick, ref, onBeforeUnmount } from 'vue'
import { BaseDrawer, BaseField, BaseRadioGroup, BaseDatePicker, BaseTimePicker } from '@/components/ui'
import type { RadioValue } from '@/components/ui/BaseRadioGroup.vue'
import { useBoardStore } from '@/stores/board'
import { CARD_TITLE_MAX, type Card, type CardAssignee, type CardPatch, type CardPriority } from '@/types/board'
import { toISODate, type DateRange } from '@/lib/date'
import { useLogger } from '@/composables/useLogger'

const store = useBoardStore()
const log = useLogger('CardDetailDrawer')

const card = computed<Card | null>(() => store.selectedCard)

// открытие управляется стором: панель открыта, пока выбрана карточка
const open = computed<boolean>({
  get: () => store.selectedCardId !== null,
  set: (v) => { if (!v) store.closeCard() },
})

const assigneeOptions: Array<{ value: CardAssignee; label: string }> = [
  { value: 'him', label: 'Он' },
  { value: 'her', label: 'Она' },
  { value: 'together', label: '♥ Вместе' },
]
const priorityOptions: Array<{ value: CardPriority; label: string }> = [
  { value: 'low', label: 'Низкий' },
  { value: 'normal', label: 'Обычный' },
  { value: 'high', label: 'Высокий' },
  { value: 'critical', label: 'Критический' },
]
const deadlineModeOptions = [
  { value: 'single', label: 'Дата' },
  { value: 'range', label: 'Период' },
]

type DeadlineMode = 'single' | 'range'
const form = reactive({
  title: '',
  description: '',
  assignee: 'together' as CardAssignee,
  priority: 'normal' as CardPriority,
  deadlineMode: 'single' as DeadlineMode,
  dateSingle: null as string | null,
  dateRange: null as DateRange | null,
  time: null as string | null,
})

const savedState = ref<'idle' | 'saving' | 'saved'>('idle')
const dirty = ref(false) // есть несохранённые правки
let loadedId: string | null = null // карточка, под которую заполнена форма
let timer: ReturnType<typeof setTimeout> | null = null

/** Заполнить форму из карточки (без триггера автосейва — обработчики реагируют только на ввод). */
function populate(c: Card | null): void {
  if (!c) return
  form.title = c.title
  form.description = c.description ?? ''
  form.assignee = c.assignee
  form.priority = c.priority ?? 'normal'
  form.time = c.dueTime ?? null
  if (c.dueEndAt) {
    form.deadlineMode = 'range'
    form.dateRange = { start: toISODate(c.dueAt ?? null), end: toISODate(c.dueEndAt) }
    form.dateSingle = toISODate(c.dueAt ?? null)
  } else {
    form.deadlineMode = 'single'
    form.dateSingle = toISODate(c.dueAt ?? null)
    form.dateRange = form.dateSingle ? { start: form.dateSingle, end: null } : null
  }
  savedState.value = 'idle'
}

// при смене выбранной карточки (включая закрытие → null): сначала ДОСохраняем ожидающие
// правки предыдущей карточки, затем перезаполняем форму под новую.
watch(
  () => store.selectedCardId,
  (newId) => {
    if (newId !== loadedId) flushSave()
    populate(card.value)
    loadedId = newId
  },
  { immediate: true },
)

/** Собрать патч из текущей формы (полный набор редактируемых полей; PATCH идемпотентен). */
function buildPatch(): CardPatch {
  const patch: CardPatch = {
    description: form.description.trim() === '' ? null : form.description,
    assignee: form.assignee,
    priority: form.priority,
    dueTime: form.time,
  }
  if (form.title.trim() !== '') patch.title = form.title.trim()
  if (form.deadlineMode === 'single') {
    patch.dueAt = form.dateSingle
    patch.dueEndAt = null
  } else {
    patch.dueAt = form.dateRange?.start ?? null
    patch.dueEndAt = form.dateRange?.end ?? null
  }
  return patch
}

/** Отправить текущую форму на КОНКРЕТНУЮ карточку (ту, под которую форма заполнена). */
async function persist(cardId: string): Promise<void> {
  dirty.value = false
  const patch = buildPatch()
  savedState.value = 'saving'
  log.debug('autosave', { cardId, keys: Object.keys(patch) })
  await store.updateCard(cardId, patch)
  savedState.value = 'saved'
}

/** Запланировать отложенное сохранение (debounce ~450мс). */
function scheduleSave(): void {
  dirty.value = true
  if (timer) clearTimeout(timer)
  timer = setTimeout(() => {
    timer = null
    if (loadedId) void persist(loadedId)
  }, 450)
}

/**
 * Немедленно отправить ожидающее сохранение — при закрытии панели, смене карточки или
 * размонтировании. БАГ-ФИКС: раньше дебаунс отбрасывался при закрытии и правки терялись.
 */
function flushSave(): void {
  if (timer) {
    clearTimeout(timer)
    timer = null
  }
  if (dirty.value && loadedId) void persist(loadedId)
}

// уход со страницы (размонтирование) тоже не должен терять несохранённые правки
onBeforeUnmount(flushSave)

// — обработчики ввода (только пользовательские изменения попадают в автосейв) —
function onTitle(v: string): void { form.title = v; scheduleSave() }
function onDescription(v: string): void { form.description = v; scheduleSave() }
// emit'ы BaseRadioGroup/BaseDatePicker (defineModel без required) могут отдать undefined —
// для радио это «нет выбора» (no-op), для дат трактуем как сброс (→ null).
function onAssignee(v: RadioValue | undefined): void { if (v === undefined) return; form.assignee = v as CardAssignee; scheduleSave() }
function onPriority(v: RadioValue | undefined): void { if (v === undefined) return; form.priority = v as CardPriority; scheduleSave() }
function onSingle(v: string | DateRange | null | undefined): void { form.dateSingle = (v ?? null) as string | null; scheduleSave() }
function onRange(v: string | DateRange | null | undefined): void { form.dateRange = (v ?? null) as DateRange | null; scheduleSave() }
function onTime(v: string | null | undefined): void { form.time = v ?? null; scheduleSave() }

function onDeadlineMode(v: RadioValue | undefined): void {
  if (v === undefined) return
  const m = v as DeadlineMode
  if (m === form.deadlineMode) return
  if (m === 'range') {
    const start = form.dateSingle ?? form.dateRange?.start ?? null
    form.dateRange = start ? { start, end: form.dateRange?.end ?? null } : null
  } else {
    form.dateSingle = form.dateRange?.start ?? form.dateSingle ?? null
  }
  form.deadlineMode = m
  void nextTick(scheduleSave)
}
</script>

<template>
  <BaseDrawer v-model:open="open" :title="card?.title || 'Карточка'" width="xl">
    <div v-if="card" class="card-detail">
      <!-- Название -->
      <div class="card-detail__section">
        <BaseField label="Название" :model-value="form.title" :maxlength="CARD_TITLE_MAX" counter placeholder="Что нужно сделать" @update:model-value="onTitle" />
      </div>

      <!-- Описание -->
      <div class="card-detail__section">
        <BaseField label="Описание" multiline :model-value="form.description" placeholder="Детали, ссылки, заметки" @update:model-value="onDescription" />
      </div>

      <!-- Исполнитель -->
      <div class="card-detail__section">
        <span class="card-detail__label">Исполнитель</span>
        <BaseRadioGroup :model-value="form.assignee" :options="assigneeOptions" @update:model-value="onAssignee" />
      </div>

      <!-- Приоритет -->
      <div class="card-detail__section">
        <span class="card-detail__label">Приоритет</span>
        <BaseRadioGroup :model-value="form.priority" :options="priorityOptions" @update:model-value="onPriority" />
      </div>

      <!-- Дедлайн -->
      <div class="card-detail__section">
        <span class="card-detail__label">Дедлайн</span>
        <BaseRadioGroup
          class="card-detail__modes"
          :model-value="form.deadlineMode"
          :options="deadlineModeOptions"
          @update:model-value="onDeadlineMode"
        />
        <BaseDatePicker
          v-if="form.deadlineMode === 'single'"
          mode="single"
          :model-value="form.dateSingle"
          placeholder="Выберите дату"
          @update:model-value="onSingle"
        />
        <BaseDatePicker
          v-else
          mode="range"
          :model-value="form.dateRange"
          placeholder="Выберите период"
          @update:model-value="onRange"
        />
      </div>

      <!-- Время (H:i, время суток дедлайна/события) -->
      <div class="card-detail__section">
        <span class="card-detail__label">Время</span>
        <BaseTimePicker :model-value="form.time" @update:model-value="onTime" />
      </div>

      <p class="card-detail__status" :data-state="savedState">
        <span v-if="savedState === 'saving'">Сохранение…</span>
        <span v-else-if="savedState === 'saved'">Сохранено</span>
      </p>
    </div>
  </BaseDrawer>
</template>

<style scoped>
.card-detail { display: flex; flex-direction: column; gap: 20px; }
.card-detail__section { display: flex; flex-direction: column; gap: 8px; }
.card-detail__label { font-size: 13px; color: var(--text-muted); font-weight: 500; }
.card-detail__modes { flex-direction: row !important; gap: 16px; }
.card-detail__status { min-height: 16px; font-size: 12px; color: var(--text-muted); margin: 0; }
</style>
