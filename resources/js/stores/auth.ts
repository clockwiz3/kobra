import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { login as apiLogin, logout as apiLogout, me as apiMe, getToken, type AuthUser } from '@/api/auth'
import { useLogger } from '@/composables/useLogger'

const log = useLogger('authStore')

export const useAuthStore = defineStore('auth', () => {
  const user = ref<AuthUser | null>(null)
  const token = ref<string | null>(getToken())
  const ready = ref(false) // restore() уже отработал
  const isAuthenticated = computed(() => token.value !== null && user.value !== null)

  async function login(email: string, password: string): Promise<boolean> {
    const session = await apiLogin(email, password)
    if (!session) {
      log.warn('login failed')
      return false
    }
    user.value = session.user
    token.value = session.token
    log.info('login ok', { id: session.user.id })
    return true
  }

  async function logout(): Promise<void> {
    await apiLogout()
    user.value = null
    token.value = null
    log.info('logout')
  }

  /** Восстановить сессию из сохранённого токена (валидация через /api/user). Идемпотентно. */
  async function restore(): Promise<void> {
    if (ready.value) return
    const t = getToken()
    if (t) {
      const u = await apiMe()
      if (u) {
        user.value = u
        token.value = t
      } else {
        token.value = null // протух/отозван
        log.debug('restore: token invalid')
      }
    }
    ready.value = true
    log.debug('restore done', { authed: token.value !== null && user.value !== null })
  }

  return { user, token, ready, isAuthenticated, login, logout, restore }
})
