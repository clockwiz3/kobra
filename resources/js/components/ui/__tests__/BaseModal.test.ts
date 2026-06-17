import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import BaseModal from '@/components/ui/BaseModal.vue'

const stubs = { teleport: true }

function mountOpen(props = {}, slots = {}) {
  return mount(BaseModal, {
    props: { modelValue: true, title: 'Заголовок', ...props },
    slots,
    global: { stubs },
    attachTo: document.body,
  })
}

describe('BaseModal', () => {
  it('рендерит dialog с заголовком когда open', () => {
    const w = mountOpen()
    expect(w.find('[role="dialog"]').exists()).toBe(true)
    expect(w.find('.modal__title').text()).toBe('Заголовок')
  })

  it('не рендерит overlay когда закрыт', () => {
    const w = mount(BaseModal, { props: { modelValue: false }, global: { stubs } })
    expect(w.find('.overlay').exists()).toBe(false)
  })

  it('клик по overlay закрывает с reason=overlay', async () => {
    const w = mountOpen()
    await w.find('.overlay').trigger('mousedown')
    expect(w.emitted('close')?.[0]).toEqual(['overlay'])
    expect(w.emitted('update:modelValue')?.at(-1)).toEqual([false])
  })

  it('клик по самой панели НЕ закрывает', async () => {
    const w = mountOpen()
    await w.find('.modal').trigger('mousedown')
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
    expect(w.find('.modal__title').text()).toBe('Слот-тайтл')
    expect(w.find('.ftr').exists()).toBe(true)
  })

  it('телепортит в .ds-root (не в body), чтобы остаться в тематическом поддереве', () => {
    // Регрессия: телепорт в <body> выносил модалку из [data-theme]-поддерева →
    // CSS-переменные (--surface/--bg/--grad…) undefined → диалог без фона/цветов.
    const root = document.createElement('div')
    root.className = 'ds-root'
    document.body.appendChild(root)

    // НЕ стабим teleport — проверяем реальную цель (default to = '.ds-root')
    const w = mount(BaseModal, { props: { modelValue: true, title: 'T' }, attachTo: document.body })

    expect(root.querySelector('.overlay')).not.toBeNull() // оверлей внутри .ds-root
    expect(document.body.querySelector(':scope > .overlay')).toBeNull() // НЕ прямой ребёнок body

    w.unmount()
    root.remove()
  })
})
