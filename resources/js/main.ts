import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { createRouter, createWebHistory } from 'vue-router'
import '../css/app.css'
import { useTheme } from './composables/useTheme'
import { useAuthStore } from './stores/auth'
import App from './App.vue'
import AppShell from './components/layout/AppShell.vue'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    // Авторизованная зона: общий каркас AppShell (топбар + меню) + вложенные разделы.
    // requiresAuth на родителе наследуется детьми (to.meta мёржит все matched-записи).
    {
      path: '/',
      component: AppShell,
      meta: { requiresAuth: true },
      children: [
        // Главная = дашборд пары (канбан с карточками). /board — алиас.
        { path: '', name: 'dashboard', alias: '/board', component: () => import('./pages/Board.vue') },
        { path: 'backlog', name: 'backlog', component: () => import('./pages/Backlog.vue') },
        { path: 'wishlists', name: 'wishlists', component: () => import('./pages/Wishlists.vue') },
        { path: 'gantt', name: 'gantt', component: () => import('./pages/Gantt.vue') },
        { path: 'calendar', name: 'calendar', component: () => import('./pages/Calendar.vue') },
      ],
    },
    // Вне каркаса (без меню): вход и подтверждение вайтлиста.
    { path: '/login', name: 'login', component: () => import('./pages/Login.vue') },
    { path: '/confirm/:token', name: 'waitlist.confirm', component: () => import('./pages/WaitlistConfirm.vue') },
  ],
})

const app = createApp(App)
const pinia = createPinia()
app.use(pinia)
app.use(router)

// Навесить data-theme / data-accent на .ds-root из сохранённого выбора
useTheme()

const auth = useAuthStore(pinia)

// Гейт авторизации: восстановить сессию (идемпотентно) и развести гостя/юзера.
router.beforeEach(async (to) => {
  await auth.restore()
  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login', query: to.fullPath !== '/' ? { redirect: to.fullPath } : undefined }
  }
  if (to.name === 'login' && auth.isAuthenticated) {
    return { path: '/' }
  }
  return true
})

app.mount('#app')
