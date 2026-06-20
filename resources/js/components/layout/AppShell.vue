<script setup lang="ts">
/**
 * Общий каркас авторизованной зоны Kobra. Топбар во всю ширину экрана:
 * слева — бургер (открывает меню) + лого, справа — иконки уведомлений и выхода.
 * Переключатель темы живёт в подвале меню (NavMenu), а не в топбаре.
 * Каркас держит ровно высоту вьюпорта; вертикальный скролл — внутри страницы-ребёнка.
 */
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { Menu2, Bell, Logout } from '@vicons/tabler'
import BrandMark from '@/components/ui/BrandMark.vue'
import NavMenu from '@/components/layout/NavMenu.vue'
import { useAuthStore } from '@/stores/auth'
import { useLogger } from '@/composables/useLogger'

const auth = useAuthStore()
const router = useRouter()
const log = useLogger('AppShell')

const navOpen = ref(false)

function toggleNav(): void {
  navOpen.value = !navOpen.value
  log.debug('toggle-nav', { open: navOpen.value })
}

function onNotifications(): void {
  // Заглушка: система уведомлений появится позже. Пока — только лог.
  log.debug('notifications-click')
}

async function logout(): Promise<void> {
  await auth.logout()
  log.debug('logout')
  router.replace({ name: 'login' })
}
</script>

<template>
  <div class="app-shell">
    <header class="topbar">
      <div class="topbar__bar">
        <div class="topbar__left">
          <button
            type="button"
            class="icon-btn burger"
            aria-label="Меню"
            :aria-expanded="navOpen"
            aria-controls="app-nav"
            @click="toggleNav"
          >
            <Menu2 />
          </button>
          <BrandMark />
        </div>

        <div class="topbar__right">
          <button type="button" class="icon-btn" aria-label="Уведомления" @click="onNotifications">
            <Bell />
          </button>
          <button type="button" class="icon-btn" aria-label="Выйти" @click="logout">
            <Logout />
          </button>
        </div>
      </div>
    </header>

    <!-- Выезжающее слева меню-навигация (телепортится в .ds-root) -->
    <NavMenu v-model:open="navOpen" @logout="logout" />

    <main class="app-main">
      <router-view />
    </main>
  </div>
</template>

<style scoped>
/* Каркас = ровно высота вьюпорта, без вертикального скролла страницы.
   100vh — синхрон с .ds-root{min-height:100vh}. Скролл держит страница-ребёнок. */
.app-shell {
  height: 100vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

/* Топбар во всю ширину экрана (без .wrap-центрирования): группы прижаты к краям.
   Боковые отступы совпадают с доской (16px моб / 32px десктоп) — лого в одну линию с контентом. */
.topbar__bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  min-height: 64px;
  width: 100%;
  padding: 10px 16px;
}
@media (min-width: 861px) {
  .topbar__bar { padding: 10px 32px; }
}

.topbar__left { display: flex; align-items: center; gap: 12px; min-width: 0; }
.topbar__right { display: flex; align-items: center; gap: 8px; flex: none; }

/* Квадратные icon-кнопки (бургер слева; уведомления + выход справа) — тач-таргет 40px,
   на дизайн-токенах (3 темы подхватываются автоматически). */
.icon-btn {
  display: inline-grid;
  place-items: center;
  width: 40px;
  height: 40px;
  flex: none;
  border: 1px solid var(--border);
  border-radius: var(--r-md);
  background: var(--surface);
  color: var(--text);
  cursor: pointer;
  transition: background 0.15s var(--ease, ease), border-color 0.15s var(--ease, ease), color 0.15s var(--ease, ease);
}
.icon-btn:hover { background: var(--surface-2); border-color: var(--border-strong); color: var(--accent); }
.icon-btn:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
.icon-btn :deep(svg) { width: 22px; height: 22px; }

/* Основная область: занимает остаток высоты и клипает; скролл — у страницы внутри. */
.app-main {
  flex: 1;
  min-height: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
</style>
