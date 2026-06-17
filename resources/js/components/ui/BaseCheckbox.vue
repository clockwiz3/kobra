<script setup lang="ts">
/**
 * Чекбокс дизайн-системы Kobra. Нативный <input type="checkbox"> (.sr-only) для
 * доступности/формы/клавиатуры + визуальный .checkbox__box из themes.css.
 * Темо-зависим автоматически (dark/light/pixel) через CSS-переменные.
 *
 * v-model — boolean. Презентационный leaf: рантайм-логов нет (это осознанно —
 * лог тоггла лежит на ответственности потребителя).
 */
import { ref, watch, onMounted } from 'vue'

const model = defineModel<boolean>({ default: false })

const props = withDefaults(
  defineProps<{
    label?: string
    disabled?: boolean
    /** «частично выбрано» — визуально-промежуточное состояние (напр. parent-чекбокс) */
    indeterminate?: boolean
    name?: string
  }>(),
  { disabled: false, indeterminate: false },
)

// indeterminate у <input> выставляется ТОЛЬКО через JS-свойство, не атрибутом.
// onMounted гарантирует доступность ref сразу после маунта; watch — на изменения.
const input = ref<HTMLInputElement | null>(null)
const syncIndeterminate = (): void => {
  if (input.value) input.value.indeterminate = props.indeterminate
}
onMounted(syncIndeterminate)
watch(() => props.indeterminate, syncIndeterminate)
</script>

<template>
  <label class="checkbox" :class="{ 'is-disabled': disabled }">
    <input
      ref="input"
      v-model="model"
      type="checkbox"
      class="sr-only"
      :name="name"
      :disabled="disabled"
    />
    <span class="checkbox__box" aria-hidden="true">
      <!-- dash при indeterminate, иначе галочка; видимость даёт CSS-заливка бокса -->
      <svg v-if="indeterminate" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round">
        <path d="M5 12h14" />
      </svg>
      <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
        <path d="M20 6L9 17l-5-5" />
      </svg>
    </span>
    <span v-if="label || $slots.default" class="checkbox__label"><slot>{{ label }}</slot></span>
  </label>
</template>
