<script setup lang="ts">
// Заглушка раздела «Задачи» (бэклог): сюда попадают карточки, убранные с доски (on_board=false).
// Наполнение (список + возврат на доску) — позже. Рендерится внутри слота AppShell.
import { onMounted } from 'vue'
import { BaseCard } from '@/components/ui'
import { useLogger } from '@/composables/useLogger'

const log = useLogger('Backlog')
onMounted(() => log.debug('mounted'))
</script>

<template>
  <section class="section-stub">
    <h1 class="sec-title">Задачи</h1>
    <BaseCard>
      <p class="stub-text">Скоро здесь будет бэклог — карточки, убранные с доски (без удаления), и возврат их обратно. 🗂️</p>
    </BaseCard>
  </section>
</template>

<style scoped>
.section-stub { flex: 1; min-height: 0; overflow-y: auto; padding: 24px 32px; }
.section-stub .sec-title { font-size: 30px; margin: 0 0 18px; }
.stub-text { color: var(--text-muted); margin: 0; text-wrap: pretty; }
@media (max-width: 860px) {
  .section-stub { padding: 16px; }
}
</style>
