<script lang="ts">
/**
 * Контекст радио-группы (provide/inject). Вынесен в обычный <script>-блок,
 * чтобы InjectionKey можно было импортировать из BaseRadio.vue.
 */
import type { InjectionKey, Ref } from 'vue'

export type RadioValue = string | number

export interface RadioGroupContext {
  name: string
  selected: Ref<RadioValue | undefined>
  disabled: Ref<boolean>
  select: (value: RadioValue) => void
}

export const radioGroupKey: InjectionKey<RadioGroupContext> = Symbol('radioGroup')

// Монотонный счётчик имён групп (уникальный name → нативная навигация стрелками).
let groupSeq = 0
</script>

<script setup lang="ts">
/**
 * Радио-группа дизайн-системы. Владеет выбранным значением (v-model),
 * раздаёт детям контекст через provide. role="radiogroup".
 * Презентационный контейнер — рантайм-логов нет (как и BaseRadio/BaseCheckbox).
 */
import { provide, toRef } from 'vue'
import BaseRadio from './BaseRadio.vue'

const model = defineModel<RadioValue>()

const props = withDefaults(
  defineProps<{
    name?: string
    disabled?: boolean
    /** декларативный рендер без ручных <BaseRadio> в слоте */
    options?: Array<{ value: RadioValue; label?: string; hint?: string }>
  }>(),
  { disabled: false },
)

const name = props.name ?? `radiogroup-${(groupSeq += 1)}`

provide(radioGroupKey, {
  name,
  selected: model,
  disabled: toRef(props, 'disabled'),
  select: (value: RadioValue) => { model.value = value },
})
</script>

<template>
  <div class="radio-group" role="radiogroup">
    <slot>
      <BaseRadio v-for="o in options" :key="String(o.value)" :value="o.value" :label="o.label" />
    </slot>
  </div>
</template>

<style scoped>
.radio-group { display: flex; flex-direction: column; gap: 12px; }
</style>
