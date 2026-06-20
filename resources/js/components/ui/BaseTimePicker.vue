<script setup lang="ts">
/**
 * Таймпикер дизайн-системы Kobra: выбор времени суток H:i (часы 00–23 + минуты с шагом).
 * Поле-триггер + поповер с двумя колонками (часы/минуты). Esc/клик-вне закрывают поповер;
 * Esc внутри поповера НЕ закрывает родительский drawer (stopPropagation).
 * Стили — классы .timepicker* из resources/css/themes.css (3 темы), в едином стиле с BaseDatePicker.
 *
 * v-model: string|null ('HH:MM').
 */
import { ref, computed } from 'vue'
import { onClickOutside } from '@vueuse/core'
import { useLogger } from '@/composables/useLogger'

const props = withDefaults(
  defineProps<{
    placeholder?: string
    clearable?: boolean
    disabled?: boolean
    /** Шаг минут в списке (по умолчанию 5 → 00,05,…,55). */
    minuteStep?: number
  }>(),
  { placeholder: 'Выберите время', clearable: true, disabled: false, minuteStep: 5 },
)

const model = defineModel<string | null>()
const log = useLogger('BaseTimePicker')

const rootEl = ref<HTMLElement | null>(null)
const triggerEl = ref<HTMLButtonElement | null>(null)
const open = ref(false)

const pad = (n: number): string => String(n).padStart(2, '0')

const hours = Array.from({ length: 24 }, (_, h) => pad(h))
const minutes = computed<string[]>(() => {
  const step = Math.min(Math.max(1, Math.floor(props.minuteStep)), 60)
  const out: string[] = []
  for (let m = 0; m < 60; m += step) out.push(pad(m))
  return out
})

// текущее значение, разобранное на HH/MM (null → пусто)
const parts = computed<{ h: string | null; m: string | null }>(() => {
  const v = model.value
  if (!v) return { h: null, m: null }
  const [h, m] = v.split(':')
  return { h: h ?? null, m: m ?? null }
})
const hasValue = computed(() => !!model.value)
const displayText = computed(() => model.value ?? '')

function setTime(h: string, m: string): void {
  const v = `${h}:${m}`
  model.value = v
  log.debug('select', { value: v })
}
// при выборе одной части вторая берётся из текущего значения (иначе 00)
function pickHour(h: string): void { setTime(h, parts.value.m ?? '00') }
function pickMinute(m: string): void { setTime(parts.value.h ?? '00', m) }

function clear(): void {
  model.value = null
  log.debug('clear')
}

function openPop(): void { if (!props.disabled) open.value = true }
function closePop(refocus = false): void { open.value = false; if (refocus) triggerEl.value?.focus() }
function toggle(): void { open.value ? closePop() : openPop() }

// Esc на корне: закрыть только поповер и НЕ дать событию закрыть родительский BaseDrawer.
function onKeydown(e: KeyboardEvent): void {
  if (e.key === 'Escape' && open.value) {
    e.preventDefault()
    e.stopPropagation()
    closePop(true)
  }
}

onClickOutside(rootEl, () => closePop())
</script>

<template>
  <div ref="rootEl" class="timepicker" @keydown="onKeydown">
    <div class="timepicker__field">
      <button
        ref="triggerEl"
        type="button"
        class="input timepicker__trigger"
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
        class="timepicker__clear"
        aria-label="Очистить время"
        @click="clear"
      >&times;</button>
    </div>

    <div v-if="open" class="timepicker__pop" role="dialog" aria-label="Выбор времени">
      <div class="timepicker__cols">
        <div class="timepicker__col">
          <div class="timepicker__collabel">Часы</div>
          <div class="timepicker__list ds-scroll">
            <button
              v-for="h in hours"
              :key="h"
              type="button"
              class="timepicker__cell"
              :class="{ 'is-selected': h === parts.h }"
              :aria-selected="h === parts.h"
              @click="pickHour(h)"
            >{{ h }}</button>
          </div>
        </div>
        <div class="timepicker__col">
          <div class="timepicker__collabel">Минуты</div>
          <div class="timepicker__list ds-scroll">
            <button
              v-for="m in minutes"
              :key="m"
              type="button"
              class="timepicker__cell"
              :class="{ 'is-selected': m === parts.m }"
              :aria-selected="m === parts.m"
              @click="pickMinute(m)"
            >{{ m }}</button>
          </div>
        </div>
      </div>

      <div v-if="clearable && hasValue" class="timepicker__actions">
        <button type="button" class="timepicker__clearbtn" @click="clear">Очистить</button>
      </div>
    </div>
  </div>
</template>
