<script setup lang="ts">
import { ref } from 'vue'
import BaseButton from './ui/BaseButton.vue'
import BaseField from './ui/BaseField.vue'
import { joinWaitlist } from '@/api/waitlist'
import { useLogger } from '@/composables/useLogger'

const log = useLogger('WaitlistForm')

const email = ref('')
const website = ref('') // honeypot
type State = 'idle' | 'loading' | 'success' | 'error'
const state = ref<State>('idle')
const error = ref('')

async function submit() {
  if (state.value === 'loading') return
  state.value = 'loading'
  error.value = ''
  log.debug('submit')
  try {
    const res = await joinWaitlist({
      email: email.value,
      website: website.value || undefined,
      locale: 'ru',
    })
    if (res.ok) {
      state.value = 'success'
      log.info('joined')
    } else {
      state.value = 'error'
      error.value = res.errors?.email?.[0] ?? res.message ?? 'Что-то пошло не так.'
      log.warn('rejected', res.errors)
    }
  } catch (e) {
    state.value = 'error'
    error.value = 'Сеть недоступна. Попробуйте ещё раз.'
    log.error('network', e)
  }
}
</script>

<template>
  <form v-if="state !== 'success'" class="grid" style="gap: 12px; max-width: 440px" @submit.prevent="submit">
    <BaseField
      v-model="email"
      type="email"
      placeholder="ваш@email"
      autocomplete="email"
      inputmode="email"
    />
    <!-- honeypot: скрыт от людей, ловит ботов -->
    <input
      v-model="website"
      name="website"
      tabindex="-1"
      autocomplete="off"
      aria-hidden="true"
      style="position: absolute; left: -9999px"
    />
    <div class="row-wrap">
      <BaseButton type="submit" variant="primary">
        {{ state === 'loading' ? 'Отправляем…' : 'Занять место ♥' }}
      </BaseButton>
    </div>
    <p v-if="state === 'error'" role="alert" style="color: var(--error); font-size: 14px; margin: 0">
      {{ error }}
    </p>
  </form>

  <div v-else class="card" style="max-width: 440px" role="status">
    <h3 class="sub-h" style="margin: 0 0 8px">Проверьте почту ✉</h3>
    <p style="color: var(--text-muted); margin: 0">
      Мы отправили письмо для подтверждения. Перейдите по ссылке — и вы в списке.
    </p>
  </div>
</template>
