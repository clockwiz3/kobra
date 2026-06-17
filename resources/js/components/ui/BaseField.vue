<script setup lang="ts">
/**
 * Поле ввода дизайн-системы (.field + .input/.textarea из themes.css).
 * Текст-лейбл — настоящий <label> внутри .field (его стилизует `.field label`)
 * и связан с инпутом через for/id (доступность). v-model совместимо.
 */
withDefaults(
  defineProps<{
    label?: string
    modelValue?: string
    placeholder?: string
    type?: string
    multiline?: boolean
    autocomplete?: string
    inputmode?: 'search' | 'text' | 'email' | 'tel' | 'url' | 'none' | 'numeric' | 'decimal'
  }>(),
  { type: 'text', multiline: false },
)
defineEmits<{ 'update:modelValue': [string] }>()

// Стабильный уникальный id (порядок маунта) для связки label↔control.
let _seq = 0
const id = `field-${(_seq += 1, _seq)}-${Math.round(performance.now())}`
</script>

<template>
  <div class="field">
    <label v-if="label" :for="id">{{ label }}</label>
    <textarea
      v-if="multiline"
      :id="id"
      class="textarea"
      :value="modelValue"
      :placeholder="placeholder"
      @input="$emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
    />
    <input
      v-else
      :id="id"
      class="input"
      :type="type"
      :value="modelValue"
      :placeholder="placeholder"
      :autocomplete="autocomplete"
      :inputmode="inputmode"
      @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
    />
  </div>
</template>
