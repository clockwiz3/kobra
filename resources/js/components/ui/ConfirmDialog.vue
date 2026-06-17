<script lang="ts">
/** Вариант выбора в диалоге подтверждения (для choices). */
export interface ConfirmChoice {
  value: string
  label: string
  hint?: string
}
</script>

<script setup lang="ts">
/**
 * Диалог подтверждения дизайн-системы — композит поверх BaseModal.
 * Первый «сложносоставной» компонент библиотеки: простое да/нет ИЛИ подтверждение
 * с выбором варианта (radio-карточки .choice). Возвращает выбор через событие.
 * Темизирован под все три темы (стили из themes.css).
 */
import { ref, computed, watch } from 'vue'
import BaseModal from './BaseModal.vue'
import BaseButton from './BaseButton.vue'
import BaseRadioGroup from './BaseRadioGroup.vue'
import BaseRadio from './BaseRadio.vue'
import { useLogger } from '@/composables/useLogger'

const open = defineModel<boolean>({ default: false })

const props = withDefaults(
  defineProps<{
    title: string
    message?: string
    confirmText?: string
    cancelText?: string
    tone?: 'default' | 'danger'
    choices?: ConfirmChoice[]
    defaultChoice?: string
  }>(),
  { confirmText: 'Подтвердить', cancelText: 'Отмена', tone: 'default' },
)

const emit = defineEmits<{ confirm: [payload: { choice?: string }]; cancel: [] }>()
const log = useLogger('ConfirmDialog')

const hasChoices = computed(() => !!props.choices?.length)
const selected = ref<string | undefined>(props.defaultChoice ?? props.choices?.[0]?.value)

// сбрасываем выбор к дефолту при каждом открытии
watch(open, (isOpen) => {
  if (isOpen) selected.value = props.defaultChoice ?? props.choices?.[0]?.value
})

function onConfirm(): void {
  const choice = hasChoices.value ? selected.value : undefined
  log.debug('confirm', { choice })
  emit('confirm', { choice })
  open.value = false // закрываем без эмита close → отмена не сработает
}

// Любое закрытие, кроме confirm (esc/overlay/X/кнопка отмены) = отмена.
function onCancel(): void {
  log.debug('cancel')
  emit('cancel')
  open.value = false
}
</script>

<template>
  <BaseModal
    v-model="open"
    :title="title"
    :size="hasChoices ? 'md' : 'sm'"
    @close="onCancel"
  >
    <p v-if="!$slots.default && message" class="modal__msg">{{ message }}</p>
    <slot />

    <BaseRadioGroup v-if="hasChoices" v-model="selected" class="choice-list">
      <div
        v-for="c in choices"
        :key="c.value"
        class="choice"
        :class="{ 'is-selected': selected === c.value }"
      >
        <BaseRadio :value="c.value">
          <span class="choice__text">
            <span class="choice__label">{{ c.label }}</span>
            <span v-if="c.hint" class="choice__hint">{{ c.hint }}</span>
          </span>
        </BaseRadio>
      </div>
    </BaseRadioGroup>

    <template #footer>
      <BaseButton variant="ghost" @click="onCancel">{{ cancelText }}</BaseButton>
      <BaseButton variant="primary" :class="{ 'is-danger': tone === 'danger' }" @click="onConfirm">
        {{ confirmText }}
      </BaseButton>
    </template>
  </BaseModal>
</template>

<style scoped>
/* tone=danger: подтверждающая кнопка в цвете ошибки (бьёт .btn-primary по специфичности) */
.is-danger { background: var(--error) !important; color: #fff; border-color: transparent; }
</style>
