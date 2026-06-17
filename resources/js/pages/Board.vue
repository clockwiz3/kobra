<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import draggable from 'vuedraggable'
import ThemeSwitch from '@/components/ui/ThemeSwitch.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BrandMark from '@/components/ui/BrandMark.vue'
import KanbanColumn from '@/components/kanban/KanbanColumn.vue'
import { CardDetailDrawer } from '@/components/composite'
import { useBoardStore } from '@/stores/board'
import { useAuthStore } from '@/stores/auth'
import { useLogger } from '@/composables/useLogger'
import { prefersReducedMotion } from '@/composables/useTheme'

const store = useBoardStore()
const auth = useAuthStore()
const router = useRouter()
const log = useLogger('Board')
const reduced = prefersReducedMotion()
const newColumn = ref('')

// число карточек-плейсхолдеров на колонку скелетона (просто для естественного вида)
const skeletonColumns = [2, 3, 1, 2]

async function logout() {
  await auth.logout()
  log.debug('logout')
  router.replace({ name: 'login' })
}

function addColumn() {
  if (newColumn.value.trim() === '') return
  store.addColumn(newColumn.value)
  newColumn.value = ''
  log.debug('add-column')
}

onMounted(() => store.fetchBoard())

function onColumnReorder() {
  store.persistColumnReorder()
  log.debug('column-reorder')
}

// [FIX] drag колонок тоже клиппит overflow карточных списков на время жеста
function onColumnDragStart() {
  store.dragging = true
}
function onColumnDragEnd() {
  store.dragging = false
}
</script>

<template>
  <header class="topbar">
    <div class="wrap row">
      <BrandMark />
      <div class="controls">
        <ThemeSwitch />
        <BaseButton variant="ghost" size="sm" @click="logout">Выйти</BaseButton>
      </div>
    </div>
  </header>

  <main class="board-main" :class="{ 'is-dragging': store.dragging }" :aria-busy="store.loading">
    <div class="board-head wrap">
      <h1 class="sec-title" style="font-size: 30px">Делишки</h1>
    </div>

    <!-- [FIX] скелетон на первичную загрузку: иначе на долю секунды мигают мок-карточки,
         которыми инициализирован стор (graceful-фолбэк), до ответа fetchBoard -->
    <div v-if="store.loading" class="board-scroll board-skeleton" aria-hidden="true">
      <div v-for="(count, ci) in skeletonColumns" :key="ci" class="sk-col">
        <div class="sk-bar sk-head"></div>
        <div v-for="n in count" :key="n" class="sk-bar sk-card"></div>
      </div>
    </div>

    <draggable
      v-else
      :list="store.columns"
      group="columns"
      item-key="id"
      handle=".kanban-col__title"
      class="board-scroll ds-scroll"
      ghost-class="kanban-ghost"
      :animation="reduced ? 0 : 160"
      @change="onColumnReorder"
      @start="onColumnDragStart"
      @end="onColumnDragEnd"
    >
      <template #item="{ element }">
        <KanbanColumn :column="element" />
      </template>
      <template #footer>
        <form class="board-addcol" @submit.prevent="addColumn">
          <input v-model="newColumn" class="input" placeholder="+ колонка" />
        </form>
      </template>
    </draggable>
  </main>

  <!-- Выезжающая панель параметров карточки (самодостаточна: open берёт из стора) -->
  <CardDetailDrawer />
</template>

<style scoped>
.board-main {
  padding: 24px 0 0;
  height: calc(100vh - 66px);
  display: flex;
  flex-direction: column;
}
.board-head { display: flex; align-items: baseline; gap: 14px; margin-bottom: 18px; }
.board-scroll {
  flex: 1;
  display: flex;
  gap: 16px;
  align-items: flex-start;
  /* лента доски скроллится только по горизонтали; overflow-y явно hidden, иначе
     при overflow-x:auto спека форсит overflow-y → auto и в drag мигает верт. скролл.
     Колонки скроллятся внутри себя (.kanban-col__cards). */
  overflow-x: auto;
  overflow-y: hidden;
  padding: 0 32px 16px;
  min-height: 0;
}
.board-scroll > * { max-height: 100%; }
.board-addcol { flex: none; width: 240px; }
.board-addcol .input { background: var(--surface-2); }
@media (max-width: 860px) {
  .board-scroll { padding: 0 16px 16px; scroll-snap-type: x mandatory; }
}

/* ── Скелетон первичной загрузки доски ── */
.board-skeleton { pointer-events: none; }
.sk-col {
  width: 286px;
  flex: none;
  display: flex;
  flex-direction: column;
  gap: 12px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--r-lg);
  padding: 14px;
  max-height: 100%;
}
.sk-bar {
  position: relative;
  overflow: hidden;
  background: var(--surface-2);
}
.sk-head { height: 22px; width: 60%; border-radius: var(--r-pill); }
.sk-card { height: 66px; border-radius: var(--r-md); }
/* мягкий shimmer; токены тем (включая pixel с r=0) подхватываются автоматически */
.sk-bar::after {
  content: '';
  position: absolute;
  inset: 0;
  transform: translateX(-100%);
  background: linear-gradient(90deg, transparent, color-mix(in srgb, var(--text) 9%, transparent), transparent);
  animation: sk-shimmer 1.25s var(--ease, ease) infinite;
}
@keyframes sk-shimmer { to { transform: translateX(100%); } }
@media (prefers-reduced-motion: reduce) {
  .sk-bar::after { animation: none; }
}
</style>
