import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { createPinia } from 'pinia'
import AppShell from '@/components/layout/AppShell.vue'

// Контент роутов нам не важен — проверяем только хром (бургер ↔ меню).
const Blank = { template: '<div class="page" />' }

async function mountShell() {
  const router = createRouter({
    history: createMemoryHistory(),
    // Все роуты-разделы нужны, чтобы RouterLink внутри NavMenu разрешался.
    routes: [
      { path: '/', name: 'dashboard', component: Blank },
      { path: '/board', name: 'board', component: Blank },
      { path: '/backlog', name: 'backlog', component: Blank },
      { path: '/wishlists', name: 'wishlists', component: Blank },
      { path: '/gantt', name: 'gantt', component: Blank },
      { path: '/calendar', name: 'calendar', component: Blank },
      { path: '/profile', name: 'profile', component: Blank },
      { path: '/login', name: 'login', component: Blank },
    ],
  })
  router.push('/')
  await router.isReady()
  const w = mount(AppShell, {
    global: { plugins: [router, createPinia()], stubs: { teleport: true } },
    attachTo: document.body,
  })
  return { w }
}

describe('AppShell', () => {
  it('бургер тогглит меню навигации', async () => {
    const { w } = await mountShell()
    const burger = w.find('.burger')

    // изначально закрыто
    expect(burger.attributes('aria-expanded')).toBe('false')
    expect(w.find('.drawer-overlay').exists()).toBe(false)

    // открыть
    await burger.trigger('click')
    expect(burger.attributes('aria-expanded')).toBe('true')
    expect(w.find('.drawer-overlay').exists()).toBe(true)
    // бургер связан с панелью меню через aria-controls
    expect(burger.attributes('aria-controls')).toBe('app-nav')
    expect(w.find('#app-nav').exists()).toBe(true)

    // закрыть повторным кликом
    await burger.trigger('click')
    expect(burger.attributes('aria-expanded')).toBe('false')
    expect(w.find('.drawer-overlay').exists()).toBe(false)
  })

  it('в правой части топбара — иконки уведомлений и выхода (без текстовых кнопок)', async () => {
    const { w } = await mountShell()
    expect(w.find('.topbar__right [aria-label="Уведомления"]').exists()).toBe(true)
    expect(w.find('.topbar__right [aria-label="Выйти"]').exists()).toBe(true)
    // переключатель темы из топбара убран (он в подвале меню)
    expect(w.find('.topbar .theme-switch').exists()).toBe(false)
  })
})
