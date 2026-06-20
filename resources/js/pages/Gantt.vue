<script setup lang="ts">
// Заглушка раздела «Гант». Наполнение — позже (диаграмма Ганта по задачам пары).
// Рендерится внутри слота AppShell.
import { onMounted } from 'vue'
import { BaseCard } from '@/components/ui'
import { useLogger } from '@/composables/useLogger'

const log = useLogger('Gantt')
onMounted(() => log.debug('mounted'))
</script>

<template>
  <section class="section-stub">
    <h1 class="sec-title">Гант</h1>
    <BaseCard>
      <p class="stub-text">Скоро здесь будет диаграмма Ганта — задачи пары на временной шкале. 📊</p>
    </BaseCard>
  </section>
</template>

<style scoped>
/* Обычный раздел (не board-каркас): тело со своим вертикальным скроллом внутри .app-main. */
.section-stub { flex: 1; min-height: 0; overflow-y: auto; padding: 24px 32px; }
.section-stub .sec-title { font-size: 30px; margin: 0 0 18px; }
.stub-text { color: var(--text-muted); margin: 0; text-wrap: pretty; }
@media (max-width: 860px) {
  .section-stub { padding: 16px; }
}
</style>
