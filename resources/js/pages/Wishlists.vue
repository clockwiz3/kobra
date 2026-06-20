<script setup lang="ts">
// Заглушка раздела «Вишлисты». Наполнение — позже (milestone Wishlists).
// Рендерится внутри слота AppShell: топбар/меню приходят из лейаута, страница даёт только тело.
import { onMounted } from 'vue'
import { BaseCard } from '@/components/ui'
import { useLogger } from '@/composables/useLogger'

const log = useLogger('Wishlists')
onMounted(() => log.debug('mounted'))
</script>

<template>
  <section class="section-stub">
    <h1 class="sec-title">Вишлисты</h1>
    <BaseCard>
      <p class="stub-text">Скоро здесь появятся ваши списки желаний — со «скрытой бронью» подарков партнёром. 💝</p>
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
