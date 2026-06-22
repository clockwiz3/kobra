<script setup lang="ts">
/**
 * Навигационное меню Kobra: выезжающий слева drawer (поверх BaseDrawer) со списком
 * разделов из @/lib/nav. Активный пункт подсвечивается по имени текущего роута;
 * меню закрывается при смене раздела. В подвале — переключатель темы и выход.
 * Esc / overlay / focus-trap / блокировку скролла обеспечивает BaseDrawer.
 */
import { watch } from 'vue'
import { useRoute } from 'vue-router'
import { BaseDrawer, BrandMark, ThemeSwitch } from '@/components/ui'
import { navItems } from '@/lib/nav'
import { useLogger } from '@/composables/useLogger'

// именованная модель → v-model:open от AppShell
const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ logout: [] }>()

const route = useRoute()
const log = useLogger('NavMenu')

// Закрывать меню при смене раздела (клик по пункту → навигация → закрытие).
watch(
  () => route.fullPath,
  () => {
    if (open.value) open.value = false
  },
)

function onNavigate(routeName: string): void {
  log.debug('navigate', { to: routeName })
  // фактический переход выполняет RouterLink; закрытие — через watch(route)
}

function onLogout(): void {
  log.debug('logout')
  open.value = false
  emit('logout')
}
</script>

<template>
  <BaseDrawer v-model:open="open" side="left" width="sm" panel-id="app-nav">
    <template #title>
      <BrandMark />
    </template>

    <nav class="nav-list" aria-label="Разделы">
      <RouterLink
        v-for="item in navItems"
        :key="item.key"
        :to="{ name: item.routeName }"
        class="nav-item"
        :class="{ 'is-active': route.name === item.routeName }"
        :aria-current="route.name === item.routeName ? 'page' : undefined"
        @click="onNavigate(item.routeName)"
      >
        <span class="nav-ico"><component :is="item.icon" /></span>
        <span class="nav-label">{{ item.label }}</span>
      </RouterLink>
    </nav>

    <template #footer>
      <div class="nav-foot">
        <ThemeSwitch />
        <div class="nav-foot__row">
          <RouterLink
            :to="{ name: 'profile' }"
            class="nav-foot__link"
            :class="{ 'is-active': route.name === 'profile' }"
            :aria-current="route.name === 'profile' ? 'page' : undefined"
            @click="onNavigate('profile')"
          >Профиль</RouterLink>
          <button type="button" class="nav-foot__link" @click="onLogout">Выйти</button>
        </div>
      </div>
    </template>
  </BaseDrawer>
</template>

<style scoped>
/* Список разделов — на дизайн-токенах (3 темы подхватываются автоматически). */
.nav-list { display: flex; flex-direction: column; gap: 4px; }

.nav-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 11px 12px;
  border-radius: var(--r-md);
  color: var(--text);
  text-decoration: none;
  font-weight: 600;
  transition: background 0.15s var(--ease, ease), color 0.15s var(--ease, ease);
}
.nav-item:hover { background: var(--surface-2); }
.nav-item.is-active { background: var(--surface-2); color: var(--accent); }

/* Пиксель-тема: подписи разделов — пиксельным шрифтом (var(--font-pill) = Press Start 2P),
   как бренд «Kobra» и остальной pixel-хром (кнопки/чипы). Кириллицу шрифт покрывает.
   :global(...) нужен, т.к. [data-theme] висит на .ds-root — предке вне этого компонента. */
:global([data-theme="pixel"]) .nav-label {
  font-family: var(--font-pill);
  font-size: 13px;
  letter-spacing: 0.02em;
}

/* Иконки Tabler/Fluent — на currentColor; размер нормализуем (в xicons svg = 24px). */
.nav-ico { display: inline-grid; place-items: center; color: var(--text-muted); flex: none; }
.nav-ico :deep(svg) { width: 20px; height: 20px; }
.nav-item.is-active .nav-ico { color: var(--accent); }

/* Подвал меню — вертикально: переключатель темы во всю ширину (сегментами), «Выйти» отдельной
   строкой. Горизонтальный ряд не влезал в узкий drawer в pixel-теме (широкие кнопки/пиксель-шрифт). */
.nav-foot {
  display: flex;
  flex-direction: column;
  align-items: stretch;
  gap: 12px;
  width: 100%;
}
/* Сегментированный переключатель темы во всю ширину подвала — равные доли, не переполняется. */
.nav-foot :deep(.theme-switch) { width: 100%; }
.nav-foot :deep(.theme-switch button) { flex: 1 1 0; min-width: 0; padding-inline: 10px; }
/* Нижний ряд подвала: «Профиль» слева, «Выйти» справа — обе как ПЛОСКИЙ ТЕКСТ-ЛИНК:
   ни фона, ни рамки, ни плашки на hover/active — выделяется только цвет текста. */
.nav-foot__row { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.nav-foot__link {
  background: none;
  border: none;
  padding: 4px 2px;
  margin: 0;
  font: inherit;
  font-weight: 600;
  font-size: 13px; /* на 2px меньше базового текста подвала */
  color: var(--text-muted);
  text-decoration: none;
  cursor: pointer;
  transition: color 0.15s var(--ease, ease);
}
.nav-foot__link:hover { color: var(--accent); }
.nav-foot__link.is-active { color: var(--accent); }
.nav-foot__link:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; border-radius: 4px; }
/* Pixel-тема: тот же пиксельный шрифт, что у разделов/бренда. */
:global([data-theme="pixel"]) .nav-foot__link { font-family: var(--font-pill); font-size: 10px; }
</style>
