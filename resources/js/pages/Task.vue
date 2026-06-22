<script setup lang="ts">
// Отдельная страница задачи (/task/:id) — шарится прямой ссылкой. Дублирует панель параметров
// (CardDetailForm) и дополнительно показывает текущий статус задачи (колонка / бэклог / выполнено).
import { computed, onMounted, ref } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { BaseCard, BaseChip, BaseButton } from '@/components/ui'
import { CardDetailForm } from '@/components/composite'
import { useBoardStore } from '@/stores/board'
import { useCardLink } from '@/composables/useCardLink'
import { useLogger } from '@/composables/useLogger'

const route = useRoute()
const store = useBoardStore()
const { copied, copy } = useCardLink()
const log = useLogger('Task')

const id = computed(() => String(route.params.id))
const loading = ref(true)

// Колонки и бэклог нужны, чтобы резолвить карточку и её статус. Грузим оба источника.
onMounted(async () => {
  await store.fetchBoard()
  await store.fetchBacklog()
  loading.value = false
  log.debug('mounted', { id: id.value, found: store.findCard(id.value) !== null })
})

const location = computed(() => store.findCard(id.value))
const card = computed(() => location.value?.card ?? null)

// Статус: на доске — название колонки (+ «Выполнено», если done); вне доски — «В бэклоге».
const status = computed<{ label: string; tone: 'accent' | 'neutral' } | null>(() => {
  const loc = location.value
  if (!loc) return null
  if (loc.column === null) return { label: 'В бэклоге', tone: 'neutral' }
  return { label: loc.column.title, tone: 'accent' }
})
</script>

<template>
  <section class="task-page">
    <RouterLink :to="{ name: 'dashboard' }" class="task-page__back">← к доске</RouterLink>

    <!-- Загрузка -->
    <BaseCard v-if="loading"><p class="task-page__muted">Загрузка задачи…</p></BaseCard>

    <!-- Не найдена (нет такой карточки в текущей паре) -->
    <BaseCard v-else-if="!card">
      <p class="task-page__muted">Задача <b>#{{ id }}</b> не найдена. Возможно, она удалена.</p>
    </BaseCard>

    <template v-else>
      <header class="task-page__head">
        <div class="task-page__title-row">
          <h1 class="sec-title task-page__title">{{ card.title }}</h1>
          <button
            type="button"
            class="task-page__id"
            :aria-label="copied ? 'Ссылка скопирована' : 'Скопировать ссылку на задачу'"
            :title="copied ? 'Ссылка скопирована' : 'Скопировать ссылку на задачу'"
            @click="copy(id)"
          >{{ copied ? '✓ скопировано' : `#${id}` }}</button>
        </div>

        <div class="task-page__status">
          <span class="task-page__status-label">Статус:</span>
          <BaseChip v-if="status" :tone="status.tone">{{ status.label }}</BaseChip>
          <BaseChip v-if="card.done" tone="accent">Выполнено</BaseChip>
        </div>
      </header>

      <BaseCard>
        <CardDetailForm :card-id="id" />
      </BaseCard>

      <div class="task-page__share">
        <BaseButton variant="secondary" size="sm" @click="copy(id)">
          {{ copied ? '✓ Ссылка скопирована' : 'Скопировать ссылку' }}
        </BaseButton>
      </div>
    </template>
  </section>
</template>

<style scoped>
.task-page { flex: 1; min-height: 0; overflow-y: auto; padding: 24px 32px; max-width: 760px; }
.task-page__back { display: inline-block; margin-bottom: 14px; color: var(--text-muted); text-decoration: none; font-size: 14px; }
.task-page__back:hover { color: var(--accent); }
.task-page__muted { color: var(--text-muted); margin: 0; }

.task-page__head { margin-bottom: 16px; }
.task-page__title-row { display: flex; align-items: baseline; gap: 10px; flex-wrap: wrap; }
.task-page__title { font-size: 28px; margin: 0; min-width: 0; }
.task-page__id {
  flex: none; border: 0; background: transparent; cursor: pointer;
  font-family: var(--font-mono, var(--font-body)); font-size: 14px; font-weight: 500;
  color: var(--text-muted); padding: 2px 6px; border-radius: var(--r-sm);
  transition: color .14s var(--ease, ease), background .14s var(--ease, ease);
}
.task-page__id:hover { color: var(--accent); background: color-mix(in srgb, var(--accent) 12%, transparent); }

.task-page__status { display: flex; align-items: center; gap: 8px; margin-top: 12px; }
.task-page__status-label { font-size: 13px; color: var(--text-muted); }

.task-page__share { margin-top: 16px; }

@media (max-width: 860px) {
  .task-page { padding: 16px; }
}
</style>
