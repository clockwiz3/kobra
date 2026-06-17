<script setup lang="ts">
/**
 * Дейтпикер дизайн-системы Kobra: одиночная дата (mode='single') ИЛИ диапазон (mode='range').
 * Поле-триггер + поповер с month-grid (dayjs, ru, неделя с понедельника). Клавиатура:
 * стрелки двигают фокус по дням, Enter/Space выбирают, Esc закрывает. min/max блокируют дни.
 * Стили — классы .datepicker* из resources/css/themes.css (3 темы).
 *
 * v-model: single → string|null ('YYYY-MM-DD'); range → DateRange|null ({start,end}).
 *
 * NB (масштабирование): month-grid намеренно обособлен (nav + grid) — при появлении
 * календарного вида/Gantt его несложно вынести в отдельный composite/BaseCalendar.
 */
import { ref, computed, nextTick, watch } from 'vue'
import { onClickOutside } from '@vueuse/core'
import { useLogger } from '@/composables/useLogger'
import {
  dayjs, type DateRange,
  toISODate, parseISO, today,
  formatDate, formatRange, monthMatrix, monthTitle, weekdayLabels,
  isSameDate, isInRange,
} from '@/lib/date'

const props = withDefaults(
  defineProps<{
    mode: 'single' | 'range'
    min?: string | null
    max?: string | null
    placeholder?: string
    clearable?: boolean
    disabled?: boolean
  }>(),
  { min: null, max: null, placeholder: 'Выберите дату', clearable: true, disabled: false },
)

const model = defineModel<string | DateRange | null>()

const log = useLogger('BaseDatePicker')

const rootEl = ref<HTMLElement | null>(null)
const triggerEl = ref<HTMLButtonElement | null>(null)
const open = ref(false)
const viewDate = ref(today())
const focusedISO = ref<string>(toISODate(today()) as string)

const dow = weekdayLabels()
const weeks = computed(() => monthMatrix(viewDate.value))

// — текущее выбранное значение (нормализация по режиму) —
const selStart = computed<string | null>(() =>
  props.mode === 'single'
    ? ((model.value as string | null) ?? null)
    : ((model.value as DateRange | null)?.start ?? null),
)
const selEnd = computed<string | null>(() =>
  props.mode === 'single' ? null : ((model.value as DateRange | null)?.end ?? null),
)
const hasValue = computed(() => selStart.value !== null)

const displayText = computed(() =>
  props.mode === 'single' ? formatDate(selStart.value) : formatRange(selStart.value, selEnd.value),
)

function isDisabled(d: ReturnType<typeof dayjs>): boolean {
  const min = parseISO(props.min)
  const max = parseISO(props.max)
  if (min && d.isBefore(min, 'day')) return true
  if (max && d.isAfter(max, 'day')) return true
  return false
}

function dayClasses(d: ReturnType<typeof dayjs>): Record<string, boolean> {
  const iso = toISODate(d) as string
  const isEdge = isSameDate(d, selStart.value) || isSameDate(d, selEnd.value)
  return {
    'is-other': d.month() !== viewDate.value.month(),
    'is-today': isSameDate(d, today()),
    'is-selected': isEdge,
    'is-in-range': props.mode === 'range' && isInRange(iso, selStart.value, selEnd.value),
    'is-disabled': isDisabled(d),
  }
}

function isSelectedDay(d: ReturnType<typeof dayjs>): boolean {
  return isSameDate(d, selStart.value) || isSameDate(d, selEnd.value)
}

// — открытие/закрытие —
function openPopover(): void {
  if (props.disabled) return
  open.value = true
  const base = selStart.value ?? (toISODate(today()) as string)
  focusedISO.value = base
  viewDate.value = dayjs(base).startOf('month')
  void nextTick(focusDay)
}
function closePopover(refocus = false): void {
  open.value = false
  if (refocus) triggerEl.value?.focus()
}
function toggle(): void {
  open.value ? closePopover() : openPopover()
}

function focusDay(): void {
  rootEl.value?.querySelector<HTMLElement>(`[data-iso="${focusedISO.value}"]`)?.focus()
}

function prevMonth(): void {
  viewDate.value = viewDate.value.subtract(1, 'month')
}
function nextMonth(): void {
  viewDate.value = viewDate.value.add(1, 'month')
}

// — выбор дня —
function pick(d: ReturnType<typeof dayjs>): void {
  if (isDisabled(d)) return
  const iso = toISODate(d) as string

  if (props.mode === 'single') {
    model.value = iso
    log.debug('select', { mode: 'single', value: iso })
    closePopover(true)
    return
  }

  const start = selStart.value
  const end = selEnd.value
  if (!start || (start && end)) {
    // начинаем новый диапазон
    model.value = { start: iso, end: null }
    log.debug('select', { mode: 'range', start: iso })
  } else {
    // завершаем диапазон (переставляем, если выбран более ранний день)
    const range: DateRange = dayjs(iso).isBefore(dayjs(start))
      ? { start: iso, end: start }
      : { start, end: iso }
    model.value = range
    log.debug('select', { mode: 'range', ...range })
    closePopover(true)
  }
}

function clear(): void {
  model.value = null
  log.debug('clear', { mode: props.mode })
}

// — клавиатура в поповере —
function moveFocus(deltaDays: number): void {
  const nd = dayjs(focusedISO.value).add(deltaDays, 'day')
  focusedISO.value = toISODate(nd) as string
  if (nd.month() !== viewDate.value.month() || nd.year() !== viewDate.value.year()) {
    viewDate.value = nd.startOf('month')
  }
  void nextTick(focusDay)
}

function onKeydown(e: KeyboardEvent): void {
  switch (e.key) {
    case 'ArrowLeft': e.preventDefault(); moveFocus(-1); break
    case 'ArrowRight': e.preventDefault(); moveFocus(1); break
    case 'ArrowUp': e.preventDefault(); moveFocus(-7); break
    case 'ArrowDown': e.preventDefault(); moveFocus(7); break
    case 'Enter':
    case ' ': e.preventDefault(); pick(dayjs(focusedISO.value)); break
    case 'Escape': e.preventDefault(); closePopover(true); break
    default: break
  }
}

onClickOutside(rootEl, () => closePopover())

// если значение сбросили извне при открытом поповере — синхронизируем фокус-курсор
watch(selStart, (s) => {
  if (open.value && s) {
    focusedISO.value = s
    viewDate.value = dayjs(s).startOf('month')
  }
})
</script>

<template>
  <div ref="rootEl" class="datepicker">
    <div class="datepicker__field">
      <button
        ref="triggerEl"
        type="button"
        class="input datepicker__trigger"
        :class="{ 'is-empty': !hasValue }"
        :disabled="disabled"
        aria-haspopup="dialog"
        :aria-expanded="open"
        @click="toggle"
      >
        {{ displayText || placeholder }}
      </button>
      <button
        v-if="clearable && hasValue && !disabled"
        type="button"
        class="datepicker__clear"
        aria-label="Очистить дату"
        @click="clear"
      >&times;</button>
    </div>

    <div
      v-if="open"
      class="datepicker__pop"
      role="dialog"
      aria-label="Выбор даты"
      @keydown="onKeydown"
    >
      <div class="datepicker__nav">
        <button type="button" class="datepicker__navbtn" aria-label="Предыдущий месяц" @click="prevMonth">‹</button>
        <span class="datepicker__month" aria-live="polite">{{ monthTitle(viewDate) }}</span>
        <button type="button" class="datepicker__navbtn" aria-label="Следующий месяц" @click="nextMonth">›</button>
      </div>

      <div class="datepicker__grid" role="grid">
        <div class="datepicker__row datepicker__dow" role="row">
          <span v-for="w in dow" :key="w" class="datepicker__dowcell" role="columnheader">{{ w }}</span>
        </div>
        <div v-for="(week, wi) in weeks" :key="wi" class="datepicker__row" role="row">
          <button
            v-for="day in week"
            :key="day.format('YYYY-MM-DD')"
            type="button"
            role="gridcell"
            class="datepicker__day"
            :class="dayClasses(day)"
            :data-iso="day.format('YYYY-MM-DD')"
            :tabindex="day.format('YYYY-MM-DD') === focusedISO ? 0 : -1"
            :aria-label="day.format('D MMMM YYYY')"
            :aria-selected="isSelectedDay(day)"
            :disabled="isDisabled(day)"
            @click="pick(day)"
          >{{ day.date() }}</button>
        </div>
      </div>

      <div v-if="clearable && hasValue" class="datepicker__actions">
        <button type="button" class="datepicker__clearbtn" @click="clear">Очистить</button>
      </div>
    </div>
  </div>
</template>
