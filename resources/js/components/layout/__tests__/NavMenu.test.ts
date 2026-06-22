import { describe, it, expect } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory, type Router } from 'vue-router'
import NavMenu from '@/components/layout/NavMenu.vue'
import { navItems } from '@/lib/nav'

// Пустышка под все роуты-разделы — NavMenu интересует только навигация, не контент.
const Blank = { template: '<div />' }

function makeRouter(): Router {
  return createRouter({
    history: createMemoryHistory(),
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
}

async function mountMenu(props: Record<string, unknown> = {}, startPath = '/') {
  const router = makeRouter()
  router.push(startPath)
  await router.isReady()
  const w = mount(NavMenu, {
    props: { open: true, ...props },
    // teleport: true — BaseDrawer телепортит в .ds-root; стаб рендерит контент инлайн (как в BaseDrawer.test)
    global: { plugins: [router], stubs: { teleport: true } },
    attachTo: document.body,
  })
  return { w, router }
}

describe('NavMenu', () => {
  it('рендерит все пункты навигации когда open', async () => {
    const { w } = await mountMenu()
    expect(w.findAll('.nav-item')).toHaveLength(navItems.length)
    const text = w.text()
    for (const item of navItems) expect(text).toContain(item.label)
  })

  it('закрыт (open=false) → drawer и пункты не в DOM', async () => {
    const { w } = await mountMenu({ open: false })
    expect(w.find('.drawer-overlay').exists()).toBe(false)
    expect(w.find('.nav-item').exists()).toBe(false)
  })

  it('смена роута закрывает меню (update:open=false)', async () => {
    const { w, router } = await mountMenu()
    router.push('/wishlists')
    await flushPromises()
    expect(w.emitted('update:open')?.at(-1)).toEqual([false])
  })

  it('клик по пункту ведёт на его роут', async () => {
    const { w, router } = await mountMenu()
    const i = navItems.findIndex((x) => x.routeName === 'wishlists') // индекс не хардкодим
    await w.findAll('.nav-item')[i].trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.name).toBe('wishlists')
  })

  it('активный пункт подсвечен (is-active + aria-current) по текущему роуту', async () => {
    const { w } = await mountMenu({}, '/gantt')
    const items = w.findAll('.nav-item')
    const gantt = items[navItems.findIndex((x) => x.routeName === 'gantt')]
    expect(gantt.classes()).toContain('is-active')
    expect(gantt.attributes('aria-current')).toBe('page')
    // другой пункт (Доска) не активен
    expect(items[0].classes()).not.toContain('is-active')
    expect(items[0].attributes('aria-current')).toBeUndefined()
  })

  it('клик «Выйти» в подвале эмитит logout', async () => {
    const { w } = await mountMenu()
    const logoutBtn = w.findAll('button').find((b) => b.text().includes('Выйти'))
    expect(logoutBtn).toBeTruthy()
    await logoutBtn!.trigger('click')
    expect(w.emitted('logout')).toBeTruthy()
  })
})
