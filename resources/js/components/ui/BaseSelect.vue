<script setup lang="ts">
/**
 * Кастомный селект дизайн-системы Kobra (замена нативного <select>, который нельзя
 * темизировать). Триггер — обычная кнопка (.btn .btn-secondary .btn-sm), поэтому в один
 * уровень с другими контролами во всех 3 темах; выпадающий список — .ctl-select__menu
 * (классы из themes.css, темизируется). A11y: combobox-паттерн (focus на триггере,
 * aria-activedescendant), клавиатура (стрелки/Enter/Esc), закрытие по клику вне/Esc.
 */
import { ref, computed } from 'vue'
import { onClickOutside } from '@vueuse/core'

interface Option { value: string; label: string }

const props = defineProps<{ modelValue: string; options: Option[]; ariaLabel?: string }>()
const emit = defineEmits<{ 'update:modelValue': [string] }>()

let seq = 0
const uid = `sel-${(seq += 1)}-${Math.round(performance.now())}`

const root = ref<HTMLElement | null>(null)
const open = ref(false)
const activeIndex = ref(-1)

const selectedLabel = computed(() => props.options.find((o) => o.value === props.modelValue)?.label ?? '')
const activeId = computed(() => (open.value && activeIndex.value >= 0 ? `${uid}-opt-${activeIndex.value}` : undefined))

function openMenu(): void {
  open.value = true
  const i = props.options.findIndex((o) => o.value === props.modelValue)
  activeIndex.value = i >= 0 ? i : 0
}
function closeMenu(): void {
  open.value = false
}
function toggle(): void {
  open.value ? closeMenu() : openMenu()
}
function select(value: string): void {
  emit('update:modelValue', value)
  closeMenu()
}
function onKeydown(e: KeyboardEvent): void {
  if (!open.value) {
    if (e.key === 'ArrowDown' || e.key === 'Enter' || e.key === ' ') {
      e.preventDefault()
      openMenu()
    }
    return
  }
  const n = props.options.length
  if (e.key === 'Escape') {
    e.preventDefault()
    closeMenu()
  } else if (e.key === 'ArrowDown') {
    e.preventDefault()
    activeIndex.value = (activeIndex.value + 1) % n
  } else if (e.key === 'ArrowUp') {
    e.preventDefault()
    activeIndex.value = (activeIndex.value - 1 + n) % n
  } else if (e.key === 'Home') {
    e.preventDefault()
    activeIndex.value = 0
  } else if (e.key === 'End') {
    e.preventDefault()
    activeIndex.value = n - 1
  } else if (e.key === 'Enter' || e.key === ' ') {
    e.preventDefault()
    const o = props.options[activeIndex.value]
    if (o) select(o.value)
  } else if (e.key === 'Tab') {
    closeMenu()
  }
}

onClickOutside(root, closeMenu)
</script>

<template>
  <div ref="root" class="ctl-select" :class="{ 'ctl-select--open': open }">
    <button
      type="button"
      class="btn btn-secondary btn-sm ctl-select__trigger"
      role="combobox"
      aria-haspopup="listbox"
      :aria-expanded="open"
      :aria-label="ariaLabel"
      :aria-activedescendant="activeId"
      @click="toggle"
      @keydown="onKeydown"
    >
      <span class="ctl-select__label">{{ selectedLabel }}</span>
      <svg class="ctl-select__chev" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M6 9l6 6 6-6" />
      </svg>
    </button>

    <ul v-if="open" class="ctl-select__menu" role="listbox" :aria-label="ariaLabel">
      <li
        v-for="(o, i) in options"
        :id="`${uid}-opt-${i}`"
        :key="o.value"
        role="option"
        :aria-selected="o.value === modelValue"
        class="ctl-select__option"
        :class="{ 'is-active': i === activeIndex, 'is-selected': o.value === modelValue }"
        @click="select(o.value)"
        @mouseenter="activeIndex = i"
      >
        {{ o.label }}
      </li>
    </ul>
  </div>
</template>
