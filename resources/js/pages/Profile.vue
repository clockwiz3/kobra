<script setup lang="ts">
// Профиль текущего юзера: смена имени + персональный «внешний вид» в задачах (рамка карточки).
// Сохранение через PATCH /api/user; при успехе обновляем authStore.user и СВОЙ слот в board.members,
// чтобы доска перекрасилась сразу (без перезагрузки).
import { onMounted, reactive, ref, computed } from 'vue'
import { BaseField, BaseButton, BaseColorPicker } from '@/components/ui'
import { useAuthStore } from '@/stores/auth'
import { useBoardStore } from '@/stores/board'
import { updateProfile } from '@/api/profile'
import type { CardFrame } from '@/types/board'
import { useLogger } from '@/composables/useLogger'

const auth = useAuthStore()
const store = useBoardStore()
const log = useLogger('Profile')

const NAME_MAX = 50
// Дефолтная рамка, если у юзера ещё нет appearance.
const DEFAULT_FRAME: CardFrame = { paint: { kind: 'solid', color: '#e84aa6' }, style: 'solid', width: 2, animated: false }

// Глубокая копия plain-данных. НЕ structuredClone: appearance.card из стора — реактивный Proxy,
// который structuredClone клонировать не умеет (DataCloneError → краш setup → блэкскрин).
function cloneFrame(card: CardFrame | null | undefined): CardFrame {
  return JSON.parse(JSON.stringify(card ?? DEFAULT_FRAME)) as CardFrame
}

// Локальная форма (правки до сохранения).
const form = reactive<{ name: string; frame: CardFrame }>({
  name: auth.user?.name ?? '',
  frame: cloneFrame(auth.user?.appearance?.card),
})

const saved = ref<'idle' | 'saving' | 'saved' | 'error'>('idle')

// Грузим доску — чтобы board.members содержали реальные id/slot (для перекраски своего слота).
onMounted(() => { void store.fetchBoard() })

// Слот текущего юзера (him/her) — по совпадению id с участником пары.
const mySlot = computed(() => store.members.find((m) => m.id === String(auth.user?.id))?.slot ?? null)

async function onSave(): Promise<void> {
  const name = form.name.trim()
  saved.value = 'saving'
  const updated = await updateProfile({ name, appearance: { card: form.frame } })
  if (!updated) {
    saved.value = 'error'
    log.warn('save failed')
    return
  }
  auth.applyProfile(updated)
  if (mySlot.value) store.setMemberAppearance(mySlot.value, updated.appearance) // живая перекраска доски
  saved.value = 'saved'
  log.debug('saved', { slot: mySlot.value })
}
</script>

<template>
  <section class="section-stub">
    <h1 class="sec-title">Профиль</h1>

    <div v-if="auth.user" class="profile">
      <!-- Имя -->
      <div class="profile__section">
        <BaseField label="Имя" :model-value="form.name" :maxlength="NAME_MAX" counter placeholder="Как тебя показывать" @update:model-value="(v: string) => (form.name = v)" />
      </div>

      <!-- Внешний вид в задачах -->
      <div class="profile__section">
        <span class="profile__label">Внешний вид в задачах</span>
        <p class="profile__hint">Рамка твоих карточек на доске (по исполнителю).</p>
        <BaseColorPicker v-model="form.frame" />
      </div>

      <!-- Сохранение -->
      <div class="profile__actions">
        <BaseButton variant="primary" size="md" :disabled="saved === 'saving'" @click="onSave">Сохранить</BaseButton>
        <span class="profile__status" :data-state="saved">
          <span v-if="saved === 'saving'">Сохранение…</span>
          <span v-else-if="saved === 'saved'">Сохранено</span>
          <span v-else-if="saved === 'error'">Не удалось сохранить</span>
        </span>
      </div>
    </div>

    <p v-else class="stub-text">Нужно войти, чтобы редактировать профиль.</p>
  </section>
</template>

<style scoped>
.section-stub { flex: 1; min-height: 0; overflow-y: auto; padding: 24px 32px; }
.section-stub .sec-title { font-size: 30px; margin: 0 0 18px; }
.stub-text { color: var(--text-muted); margin: 0; }

.profile { display: flex; flex-direction: column; gap: 24px; max-width: 560px; }
.profile__section { display: flex; flex-direction: column; gap: 8px; }
.profile__label { font-size: 14px; color: var(--text); font-weight: 600; }
.profile__hint { font-size: 13px; color: var(--text-muted); margin: 0 0 4px; }

.profile__actions { display: flex; align-items: center; gap: 14px; }
.profile__status { font-size: 13px; color: var(--text-muted); }
.profile__status[data-state="error"] { color: var(--error); }
.profile__status[data-state="saved"] { color: var(--success); }

@media (max-width: 860px) {
  .section-stub { padding: 16px; }
}
</style>
