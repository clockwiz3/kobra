<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { confirmWaitlist, getWaitlistStatus } from '@/api/waitlist'
import { useLogger } from '@/composables/useLogger'
import BrandMark from '@/components/ui/BrandMark.vue'

const log = useLogger('WaitlistConfirm')
const route = useRoute()

type State = 'loading' | 'done' | 'error'
const state = ref<State>('loading')
const message = ref('')
const position = ref<number | null>(null)

onMounted(async () => {
  const token = String(route.params.token ?? '')
  try {
    const res = await confirmWaitlist(token)
    if (res.ok) {
      state.value = 'done'
      message.value = res.message
      const status = await getWaitlistStatus(token)
      position.value = status?.static_position ?? null
      log.info('confirmed', { position: position.value })
    } else {
      state.value = 'error'
      message.value = res.message
      log.warn('confirm rejected')
    }
  } catch (e) {
    state.value = 'error'
    message.value = 'Сеть недоступна. Попробуйте позже.'
    log.error('network', e)
  }
})
</script>

<template>
  <header class="topbar">
    <div class="wrap row">
      <BrandMark />
    </div>
  </header>

  <main class="hero">
    <div class="wrap" style="position: relative; z-index: 1">
      <div class="card" style="max-width: 480px">
        <template v-if="state === 'loading'">
          <p style="margin: 0; color: var(--text-muted)">Подтверждаем…</p>
        </template>
        <template v-else-if="state === 'done'">
          <h2 class="sec-title" style="font-size: 26px; margin: 0 0 8px">Готово &hearts;</h2>
          <p style="margin: 0; color: var(--text-muted)">{{ message }}</p>
          <p v-if="position" style="margin: 12px 0 0">Вы <b>#{{ position }}</b> в списке.</p>
        </template>
        <template v-else>
          <h2 class="sec-title" style="font-size: 26px; margin: 0 0 8px">Хм…</h2>
          <p style="margin: 0; color: var(--text-muted)">{{ message }}</p>
        </template>
      </div>
    </div>
  </main>
</template>
