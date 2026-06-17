import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import BaseRadioGroup from '@/components/ui/BaseRadioGroup.vue'

const options = [
  { value: 'a', label: 'A' },
  { value: 'b', label: 'B' },
  { value: 'c', label: 'C' },
]

describe('BaseRadioGroup / BaseRadio', () => {
  it('отмечает выбранную опцию (взаимоисключающе)', () => {
    const w = mount(BaseRadioGroup, { props: { modelValue: 'b', options } })
    const inputs = w.findAll('input[type="radio"]')
    expect(inputs).toHaveLength(3)
    expect((inputs[0].element as HTMLInputElement).checked).toBe(false)
    expect((inputs[1].element as HTMLInputElement).checked).toBe(true)
    expect((inputs[2].element as HTMLInputElement).checked).toBe(false)
  })

  it('v-model отдаёт value выбранной опции', async () => {
    const w = mount(BaseRadioGroup, { props: { modelValue: 'a', options } })
    await w.findAll('input[type="radio"]')[2].trigger('change')
    expect(w.emitted('update:modelValue')?.at(-1)).toEqual(['c'])
  })

  it('disabled на группе блокирует все опции', () => {
    const w = mount(BaseRadioGroup, { props: { modelValue: 'a', options, disabled: true } })
    for (const i of w.findAll('input[type="radio"]')) {
      expect(i.attributes('disabled')).toBeDefined()
    }
  })

  it('общий name → нативная группировка', () => {
    const w = mount(BaseRadioGroup, { props: { modelValue: 'a', options } })
    const names = w.findAll('input[type="radio"]').map((i) => i.attributes('name'))
    expect(new Set(names).size).toBe(1)
    expect(names[0]).toBeTruthy()
  })
})
