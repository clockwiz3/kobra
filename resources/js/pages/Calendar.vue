<script setup lang="ts">
// Заглушка раздела «Календарь». Наполнение — позже (общий календарь пары).
// Рендерится внутри слота AppShell.
import { onMounted } from 'vue'
import { BaseCard } from '@/components/ui'
import { useLogger } from '@/composables/useLogger'

const log = useLogger('Calendar')
onMounted(() => log.debug('mounted'))
</script>

<template>
  <section class="section-stub">
    <h1 class="sec-title">Календарь</h1>
    <BaseCard>
      <p class="stub-text">Скоро здесь будет общий календарь пары — дедлайны, даты и тёплые годовщины. 📅</p>
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
