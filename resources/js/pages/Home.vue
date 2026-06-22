<script setup lang="ts">
// Главная — дашборд-сводка пары: счётчики доски/бэклога, онлайн-статус партнёра,
// live-лента активности (realtime), быстрые ссылки и плейсхолдеры геймификации.
import { onMounted, computed } from 'vue'
import { RouterLink } from 'vue-router'
import { BaseCard, BaseBadge, PresenceDot } from '@/components/ui'
import { useBoardStore } from '@/stores/board'
import { useAuthStore } from '@/stores/auth'
import { useCoupleChannel } from '@/composables/useCoupleChannel'
import { useLogger } from '@/composables/useLogger'

const store = useBoardStore()
const auth = useAuthStore()
const { partnerOnline, recentEvents } = useCoupleChannel()
const log = useLogger('Home')

onMounted(async () => {
  await store.fetchBoard()
  await store.fetchBacklog()
  log.debug('mounted', { cards: store.totalCards })
})

const greetingName = computed(() => auth.user?.name?.trim() || 'дорогой человек')
const pairNames = computed(() => store.members.map((m) => m.name).filter(Boolean).join(' + '))
const hasPartner = computed(() => store.members.length > 1)

const stats = computed(() => [
  { key: 'cards', label: 'Задач на доске', value: store.totalCards },
  { key: 'columns', label: 'Колонок', value: store.columns.length },
  { key: 'backlog', label: 'В бэклоге', value: store.backlogCards.length },
])

const quickLinks = [
  { key: 'board', label: 'Доска', to: 'board' },
  { key: 'backlog', label: 'Задачи', to: 'backlog' },
  { key: 'profile', label: 'Профиль', to: 'profile' },
]
</script>

<template>
  <section class="home">
    <header class="home__head">
      <h1 class="sec-title">Привет, {{ greetingName }}!</h1>
      <p v-if="pairNames" class="home__pair">Пара: {{ pairNames }}</p>
      <PresenceDot
        v-if="hasPartner"
        class="home__presence"
        :online="partnerOnline"
        :label="partnerOnline ? 'Партнёр в сети' : 'Партнёр не в сети'"
      />
    </header>

    <!-- Скелетон первичной загрузки -->
    <div v-if="store.loading" class="home__grid">
      <div v-for="n in 3" :key="n" class="home__tile home__tile--skel" aria-hidden="true" />
    </div>

    <template v-else>
      <!-- Плитки-счётчики -->
      <div class="home__grid">
        <BaseCard v-for="s in stats" :key="s.key" class="home__tile">
          <div class="home__tile-value">{{ s.value }}</div>
          <div class="home__tile-label">{{ s.label }}</div>
        </BaseCard>
      </div>

      <!-- Быстрые ссылки -->
      <nav class="home__links" aria-label="Быстрые переходы">
        <RouterLink v-for="l in quickLinks" :key="l.key" :to="{ name: l.to }" class="home__link">
          {{ l.label }}
        </RouterLink>
      </nav>

      <div class="home__cols">
        <!-- Лента активности (realtime) -->
        <BaseCard class="home__feed">
          <div class="home__feed-head">
            <h2 class="home__h2">Активность пары</h2>
            <BaseBadge>live</BaseBadge>
          </div>
          <ul v-if="recentEvents.length" class="home__feed-list">
            <li v-for="e in recentEvents" :key="e.id" class="home__feed-item">{{ e.text }}</li>
          </ul>
          <p v-else class="stub-text">Пока тихо — изменения партнёра появятся здесь сразу.</p>
        </BaseCard>

        <!-- Геймификация (плейсхолдеры до Gamification Core) -->
        <BaseCard class="home__game">
          <h2 class="home__h2">Геймификация</h2>
          <div class="home__game-row">
            <span class="home__game-key">Стрик пары</span>
            <span class="stub-text">скоро</span>
          </div>
          <div class="home__game-row">
            <span class="home__game-key">Уровень / XP</span>
            <span class="stub-text">скоро</span>
          </div>
          <div class="home__game-row">
            <span class="home__game-key">Достижения</span>
            <span class="stub-text">скоро</span>
          </div>
        </BaseCard>
      </div>
    </template>
  </section>
</template>

<style scoped>
.home {
  padding: 16px;
  overflow-y: auto;
  height: 100%;
}
@media (min-width: 861px) {
  .home { padding: 24px 32px; }
}

.home__head { margin-bottom: 18px; }
.home__pair { margin: 4px 0 0; color: var(--text-muted, inherit); }
.home__presence { margin-top: 8px; }

.home__grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: 12px;
}
.home__tile {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 16px;
}
.home__tile--skel {
  min-height: 84px;
  border-radius: var(--r-md);
  background: var(--surface-2);
  animation: home-pulse 1.4s ease-in-out infinite;
}
@media (prefers-reduced-motion: reduce) {
  .home__tile--skel { animation: none; }
}
@keyframes home-pulse {
  0%, 100% { opacity: 0.6; }
  50% { opacity: 1; }
}
.home__tile-value { font-size: 28px; font-weight: 700; line-height: 1; color: var(--accent); }
.home__tile-label { font-size: 13px; color: var(--text-muted, inherit); }

.home__links {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin: 16px 0;
}
.home__link {
  padding: 8px 14px;
  border: 1px solid var(--border);
  border-radius: var(--r-md);
  background: var(--surface);
  color: var(--text);
  text-decoration: none;
  transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease;
}
.home__link:hover { background: var(--surface-2); border-color: var(--border-strong); color: var(--accent); }

.home__cols {
  display: grid;
  grid-template-columns: 1fr;
  gap: 12px;
}
@media (min-width: 861px) {
  .home__cols { grid-template-columns: 2fr 1fr; }
}
.home__feed, .home__game { padding: 16px; }
.home__feed-head { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
.home__h2 { font-size: 16px; margin: 0; }
.home__feed-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px; }
.home__feed-item {
  padding: 8px 10px;
  border-radius: var(--r-sm, 8px);
  background: var(--surface-2);
  font-size: 14px;
}
.home__game-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-top: 1px solid var(--border); }
.home__game-row:first-of-type { border-top: none; }
.home__game-key { font-size: 14px; }
</style>
