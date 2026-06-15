import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { createRouter, createWebHistory } from 'vue-router'
import '../css/app.css'
import { useTheme } from './composables/useTheme'
import App from './App.vue'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', name: 'home', component: () => import('./pages/Waitlist.vue') },
    { path: '/confirm/:token', name: 'waitlist.confirm', component: () => import('./pages/WaitlistConfirm.vue') },
  ],
})

const app = createApp(App)
app.use(createPinia())
app.use(router)

// Навесить data-theme / data-accent на .ds-root из сохранённого выбора
useTheme()

app.mount('#app')
