import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import BaseCheckbox from '@/components/ui/BaseCheckbox.vue'

describe('BaseCheckbox', () => {
  it('двусторонний v-model: клик эмитит update:modelValue', async () => {
    const w = mount(BaseCheckbox, { props: { modelValue: false, label: 'Готово' } })
    await w.find('input[type="checkbox"]').setValue(true)
    expect(w.emitted('update:modelValue')?.[0]).toEqual([true])
  })

  it('отражает входящее значение', () => {
    const w = mount(BaseCheckbox, { props: { modelValue: true } })
    expect((w.find('input').element as HTMLInputElement).checked).toBe(true)
  })

  it('disabled блокирует input', () => {
    const w = mount(BaseCheckbox, { props: { modelValue: false, disabled: true } })
    expect(w.find('input').attributes('disabled')).toBeDefined()
    expect(w.classes()).toContain('is-disabled')
  })

  it('indeterminate выставляется на нативном input', () => {
    const w = mount(BaseCheckbox, { props: { modelValue: false, indeterminate: true } })
    expect((w.find('input').element as HTMLInputElement).indeterminate).toBe(true)
  })

  it('рендерит слот лейбла', () => {
    const w = mount(BaseCheckbox, { slots: { default: 'Через слот' } })
    expect(w.text()).toContain('Через слот')
  })
})
