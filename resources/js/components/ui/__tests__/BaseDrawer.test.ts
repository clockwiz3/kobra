import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import BaseDrawer from '@/components/ui/BaseDrawer.vue'

const stubs = { teleport: true }

function mountOpen(props = {}, slots = {}) {
  return mount(BaseDrawer, {
    props: { open: true, title: 'Параметры', ...props },
    slots,
    global: { stubs },
    attachTo: document.body,
  })
}

describe('BaseDrawer', () => {
  it('рендерит dialog с заголовком когда open', () => {
    const w = mountOpen()
    expect(w.find('[role="dialog"]').exists()).toBe(true)
    expect(w.find('.drawer__title').text()).toBe('Параметры')
  })

  it('не рендерит overlay когда закрыт', () => {
    const w = mount(BaseDrawer, { props: { open: false }, global: { stubs } })
    expect(w.find('.drawer-overlay').exists()).toBe(false)
  })

  it('по умолчанию выезжает справа (is-right + drawer--right)', () => {
    const w = mountOpen()
    expect(w.find('.drawer-overlay').classes()).toContain('is-right')
    expect(w.find('.drawer').classes()).toContain('drawer--right')
  })

  it('side=left → is-left + drawer--left', () => {
    const w = mountOpen({ side: 'left' })
    expect(w.find('.drawer-overlay').classes()).toContain('is-left')
    expect(w.find('.drawer').classes()).toContain('drawer--left')
  })

  it('width прокидывается в класс панели', () => {
    const w = mountOpen({ width: 'lg' })
    expect(w.find('.drawer').classes()).toContain('drawer--lg')
  })

  it('width=xl → drawer--xl', () => {
    const w = mountOpen({ width: 'xl' })
    expect(w.find('.drawer').classes()).toContain('drawer--xl')
  })

  it('клик по overlay закрывает с reason=overlay', async () => {
    const w = mountOpen()
    await w.find('.drawer-overlay').trigger('mousedown')
    expect(w.emitted('close')?.[0]).toEqual(['overlay'])
    expect(w.emitted('update:open')?.at(-1)).toEqual([false])
  })

  it('клик по самой панели НЕ закрывает', async () => {
    const w = mountOpen()
    await w.find('.drawer').trigger('mousedown')
    expect(w.emitted('close')).toBeUndefined()
  })

  it('Escape закрывает с reason=esc', async () => {
    const w = mountOpen()
    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
    await nextTick()
    expect(w.emitted('close')?.some((e) => e[0] === 'esc')).toBe(true)
    w.unmount()
  })

  it('closeOnEsc=false игнорирует Escape', async () => {
    const w = mountOpen({ closeOnEsc: false })
    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
    await nextTick()
    expect(w.emitted('close')).toBeUndefined()
    w.unmount()
  })

  it('рендерит слоты title и footer', () => {
    const w = mountOpen({ title: undefined }, {
      title: 'Слот-тайтл',
      footer: '<button class="ftr">OK</button>',
    })
    expect(w.find('.drawer__title').text()).toBe('Слот-тайтл')
    expect(w.find('.ftr').exists()).toBe(true)
  })

  it('телепортит в .ds-root (не в body)', () => {
    const root = document.createElement('div')
    root.className = 'ds-root'
    document.body.appendChild(root)

    const w = mount(BaseDrawer, { props: { open: true, title: 'T' }, attachTo: document.body })

    expect(root.querySelector('.drawer-overlay')).not.toBeNull()
    expect(document.body.querySelector(':scope > .drawer-overlay')).toBeNull()

    w.unmount()
    root.remove()
  })
})
