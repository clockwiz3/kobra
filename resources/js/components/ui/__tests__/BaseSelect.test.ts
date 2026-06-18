import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import BaseSelect from '@/components/ui/BaseSelect.vue'

const options = [
  { value: 'all', label: 'Все' },
  { value: 'him', label: 'Он' },
  { value: 'her', label: 'Она' },
]

const mountSel = (modelValue = 'all') =>
  mount(BaseSelect, { props: { modelValue, options, ariaLabel: 'Фильтр' } })

describe('BaseSelect', () => {
  it('триггер показывает label выбранного значения', () => {
    expect(mountSel('him').find('.ctl-select__trigger').text()).toContain('Он')
  })

  it('меню закрыто по умолчанию и открывается по клику', async () => {
    const w = mountSel()
    expect(w.find('.ctl-select__menu').exists()).toBe(false)
    await w.find('.ctl-select__trigger').trigger('click')
    expect(w.find('.ctl-select__menu').exists()).toBe(true)
    expect(w.findAll('.ctl-select__option')).toHaveLength(3)
  })

  it('клик по опции эмитит update:modelValue и закрывает меню', async () => {
    const w = mountSel('all')
    await w.find('.ctl-select__trigger').trigger('click')
    await w.findAll('.ctl-select__option')[1].trigger('click') // 'him'
    expect(w.emitted('update:modelValue')?.[0]).toEqual(['him'])
    expect(w.find('.ctl-select__menu').exists()).toBe(false)
  })

  it('aria-expanded отражает открытость', async () => {
    const w = mountSel()
    const trigger = w.find('.ctl-select__trigger')
    expect(trigger.attributes('aria-expanded')).toBe('false')
    await trigger.trigger('click')
    expect(trigger.attributes('aria-expanded')).toBe('true')
  })

  it('Escape закрывает меню', async () => {
    const w = mountSel()
    const trigger = w.find('.ctl-select__trigger')
    await trigger.trigger('click')
    await trigger.trigger('keydown', { key: 'Escape' })
    expect(w.find('.ctl-select__menu').exists()).toBe(false)
  })

  it('выбранная опция помечена aria-selected', async () => {
    const w = mountSel('her')
    await w.find('.ctl-select__trigger').trigger('click')
    const selected = w.findAll('.ctl-select__option').find((o) => o.attributes('aria-selected') === 'true')
    expect(selected?.text()).toBe('Она')
  })
})
