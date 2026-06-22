import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { mount, flushPromises } from '@vue/test-utils'
import Home from '@/pages/Home.vue'

// Мок API: fetchBoard → null → стор остаётся в мок-режиме (4 колонки, 5 карточек, 0 бэклог).
vi.mock('@/api/board', () => ({
  boardApi: {
    fetchBoard: vi.fn().mockResolvedValue(null),
    fetchBacklog: vi.fn().mockResolvedValue(null),
  },
}))

async function mountHome() {
  const w = mount(Home, {
    global: { stubs: { RouterLink: { template: '<a><slot/></a>' } } },
  })
  await flushPromises()
  return w
}

describe('Home.vue (дашборд-сводка)', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('показывает плитки-счётчики из стора', async () => {
    const w = await mountHome()
    const values = w.findAll('.home__tile-value').map((n) => n.text())
    expect(values).toEqual(['5', '4', '0']) // карточек / колонок / бэклог (мок-доска)
  })

  it('рендерит быстрые ссылки (доска/задачи/профиль)', async () => {
    const w = await mountHome()
    expect(w.findAll('.home__link').length).toBe(3)
  })

  it('пустая лента активности показывает заглушку', async () => {
    const w = await mountHome()
    expect(w.find('.home__feed .stub-text').exists()).toBe(true)
  })

  it('показывает индикатор присутствия партнёра (в паре 2 участника)', async () => {
    const w = await mountHome()
    expect(w.findComponent({ name: 'PresenceDot' }).exists()).toBe(true)
  })

  it('геймификация — три плейсхолдера «скоро»', async () => {
    const w = await mountHome()
    const rows = w.findAll('.home__game-row')
    expect(rows.length).toBe(3)
    expect(rows.every((r) => r.text().includes('скоро'))).toBe(true)
  })
})
