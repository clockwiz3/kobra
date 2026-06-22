<script setup lang="ts">
/**
 * Панель параметров карточки (composite) — выезжающая панель поверх BaseDrawer.
 * Привязана к store.selectedCard; открытие/закрытие — через store.selectedCardId.
 * Тело редактора (поля + автосейв) — переиспользуемый CardDetailForm.
 *
 * В шапке рядом с названием — «#<id>»: клик копирует прямую ссылку на задачу (страница /task/:id).
 */
import { computed } from 'vue'
import { BaseDrawer } from '@/components/ui'
import CardDetailForm from './CardDetailForm.vue'
import { useBoardStore } from '@/stores/board'
import { useCardLink } from '@/composables/useCardLink'
import type { Card } from '@/types/board'

const store = useBoardStore()
const { copied, copy } = useCardLink()

const card = computed<Card | null>(() => store.selectedCard)

// открытие управляется стором: панель открыта, пока выбрана карточка
const open = computed<boolean>({
  get: () => store.selectedCardId !== null,
  set: (v) => { if (!v) store.closeCard() },
})
</script>

<template>
  <BaseDrawer v-model:open="open" width="xl">
    <template #title>
      <span class="cd-head">
        <span class="cd-head__title">{{ card?.title || 'Карточка' }}</span>
        <button
          v-if="card"
          type="button"
          class="cd-head__id"
          :aria-label="copied ? 'Ссылка скопирована' : 'Скопировать ссылку на задачу'"
          :title="copied ? 'Ссылка скопирована' : 'Скопировать ссылку на задачу'"
          @click="copy(card.id)"
        >{{ copied ? '✓ скопировано' : `#${card.id}` }}</button>
      </span>
    </template>

    <CardDetailForm :card-id="store.selectedCardId" />
  </BaseDrawer>
</template>

<style scoped>
.cd-head { display: inline-flex; align-items: baseline; gap: 10px; flex-wrap: wrap; }
.cd-head__title { min-width: 0; }
/* «#id» — приглушённая кликабельная метка-ссылка; при наведении подсвечивается акцентом. */
.cd-head__id {
  flex: none; border: 0; background: transparent; cursor: pointer;
  font-family: var(--font-mono, var(--font-body)); font-size: 13px; font-weight: 500;
  color: var(--text-muted); padding: 2px 6px; border-radius: var(--r-sm);
  transition: color .14s var(--ease, ease), background .14s var(--ease, ease);
}
.cd-head__id:hover { color: var(--accent); background: color-mix(in srgb, var(--accent) 12%, transparent); }
</style>
