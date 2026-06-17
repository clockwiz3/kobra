<script setup lang="ts">
/**
 * Радио-кнопка дизайн-системы. Нативный <input type="radio"> (.sr-only) с общим
 * name из группы → нативная семантика и навигация стрелками; визуал .radio__dot
 * из themes.css (темо-зависим: dark/light/pixel). Используется внутри BaseRadioGroup.
 *
 * Презентационный leaf — рантайм-логов нет (осознанно).
 */
import { computed, inject } from 'vue'
import { radioGroupKey, type RadioValue } from './BaseRadioGroup.vue'

const props = withDefaults(
  defineProps<{
    value: RadioValue
    label?: string
    disabled?: boolean
  }>(),
  { disabled: false },
)

const group = inject(radioGroupKey, null)

const checked = computed(() => (group ? group.selected.value === props.value : false))
const isDisabled = computed(() => props.disabled || (group?.disabled.value ?? false))
const name = computed(() => group?.name)

function onChange(): void {
  group?.select(props.value)
}
</script>

<template>
  <label class="radio" :class="{ 'is-disabled': isDisabled }">
    <input
      type="radio"
      class="sr-only"
      :name="name"
      :value="String(value)"
      :checked="checked"
      :disabled="isDisabled"
      @change="onChange"
    />
    <span class="radio__dot" aria-hidden="true" />
    <span v-if="label || $slots.default" class="radio__label"><slot>{{ label }}</slot></span>
  </label>
</template>
