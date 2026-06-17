<script setup lang="ts">
/**
 * Хост императивного сервиса подтверждений. Монтируется ОДИН раз (в App.vue).
 * Берёт текущий запрос из useConfirmHost() и рендерит <ConfirmDialog>,
 * резолвя связанный Promise по confirm/cancel.
 */
import ConfirmDialog from './ConfirmDialog.vue'
import { useConfirmHost } from '@/composables/useConfirm'

const { open, current, onConfirm, onCancel } = useConfirmHost()
</script>

<template>
  <ConfirmDialog
    v-if="current"
    v-model="open"
    :title="current.options.title"
    :message="current.options.message"
    :confirm-text="current.options.confirmText"
    :cancel-text="current.options.cancelText"
    :tone="current.options.tone"
    :choices="current.options.choices"
    :default-choice="current.options.defaultChoice"
    @confirm="onConfirm"
    @cancel="onCancel"
  />
</template>
