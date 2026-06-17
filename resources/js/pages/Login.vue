<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import ThemeSwitch from '@/components/ui/ThemeSwitch.vue'
import BaseField from '@/components/ui/BaseField.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BrandMark from '@/components/ui/BrandMark.vue'
import { useAuthStore } from '@/stores/auth'
import { useLogger } from '@/composables/useLogger'

const log = useLogger('Login')
const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const email = ref('')
const password = ref('')
const loading = ref(false)
const error = ref('')

async function submit() {
  if (loading.value) return
  loading.value = true
  error.value = ''
  log.debug('submit')
  try {
    const ok = await auth.login(email.value.trim(), password.value)
    if (ok) {
      const to = (route.query.redirect as string) || '/'
      router.replace(to)
    } else {
      error.value = 'Неверный email или пароль.'
      log.warn('rejected')
    }
  } catch (e) {
    error.value = 'Сеть недоступна. Попробуйте ещё раз.'
    log.error('network', e)
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <header class="topbar">
    <div class="wrap row">
      <BrandMark />
      <ThemeSwitch />
    </div>
  </header>

  <main class="auth-main">
    <div class="wrap">
      <div class="card auth-card" role="form">
        <h1 class="sec-title" style="font-size: 26px; margin: 0 0 4px">С возвращением &hearts;</h1>
        <p style="color: var(--text-muted); margin: 0 0 20px">Войдите в ваше общее пространство.</p>

        <form class="grid" style="gap: 14px" @submit.prevent="submit">
          <BaseField
            v-model="email"
            label="Email"
            type="email"
            placeholder="ваш@email"
            autocomplete="email"
            inputmode="email"
          />
          <BaseField
            v-model="password"
            label="Пароль"
            type="password"
            autocomplete="current-password"
          />
          <div class="row-wrap">
            <BaseButton type="submit" variant="primary" :disabled="loading">
              {{ loading ? 'Входим…' : 'Войти' }}
            </BaseButton>
          </div>
          <p v-if="error" role="alert" style="color: var(--error); font-size: 14px; margin: 0">{{ error }}</p>
        </form>
      </div>
    </div>
  </main>
</template>

<style scoped>
.auth-main {
  display: flex;
  align-items: center;
  min-height: calc(100vh - 66px);
}
.auth-card {
  width: 100%;
  max-width: 420px;
  margin: 0 auto;
}
</style>
