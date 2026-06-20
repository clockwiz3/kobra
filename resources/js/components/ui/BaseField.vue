<script setup lang="ts">
/**
 * Поле ввода дизайн-системы (.field + .input/.textarea из themes.css).
 * Текст-лейбл — настоящий <label> внутри .field (его стилизует `.field label`)
 * и связан с инпутом через for/id (доступность). v-model совместимо.
 */
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    label?: string
    modelValue?: string
    placeholder?: string
    type?: string
    multiline?: boolean
    maxlength?: number
    /** Показывать счётчик символов (count/maxlength). Требует заданного maxlength. */
    counter?: boolean
    autocomplete?: string
    inputmode?: 'search' | 'text' | 'email' | 'tel' | 'url' | 'none' | 'numeric' | 'decimal'
  }>(),
  { type: 'text', multiline: false },
)
defineEmits<{ 'update:modelValue': [string] }>()

// Счётчик символов. .length = UTF-16 code units — совпадает с поведением maxlength
// инпута и бэкенд-валидацией max:N для обычного текста.
const count = computed(() => (props.modelValue ?? '').length)

// Стабильный уникальный id (порядок маунта) для связки label↔control.
let _seq = 0
const id = `field-${(_seq += 1, _seq)}-${Math.round(performance.now())}`
</script>

<template>
  <div class="field">
    <div v-if="counter && maxlength" class="field__head">
      <label v-if="label" :for="id">{{ label }}</label>
      <span class="field__counter" :class="{ 'is-max': count >= maxlength }">{{ count }}/{{ maxlength }}</span>
    </div>
    <label v-else-if="label" :for="id">{{ label }}</label>
    <textarea
      v-if="multiline"
      :id="id"
      class="textarea"
      :value="modelValue"
      :placeholder="placeholder"
      :maxlength="maxlength"
      @input="$emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
    />
    <input
      v-else
      :id="id"
      class="input"
      :type="type"
      :value="modelValue"
      :placeholder="placeholder"
      :maxlength="maxlength"
      :autocomplete="autocomplete"
      :inputmode="inputmode"
      @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
    />
  </div>
</template>
