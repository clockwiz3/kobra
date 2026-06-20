import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import BaseTimePicker from '@/components/ui/BaseTimePicker.vue'

function mountTP(props: Record<string, unknown> = {}) {
  return mount(BaseTimePicker, { props, attachTo: document.body })
}

describe('BaseTimePicker', () => {
  it('пустой → триггер показывает placeholder, поповер закрыт', () => {
    const w = mountTP({ modelValue: null, placeholder: 'Выберите время' })
    expect(w.find('.timepicker__trigger').text()).toBe('Выберите время')
    expect(w.find('.timepicker__pop').exists()).toBe(false)
  })

  it('клик по триггеру открывает поповер с двумя колонками (часы/минуты)', async () => {
    const w = mountTP({ modelValue: null })
    await w.find('.timepicker__trigger').trigger('click')
    expect(w.find('.timepicker__pop').exists()).toBe(true)
    expect(w.findAll('.timepicker__col')).toHaveLength(2)
    // 24 часа в первой колонке
    expect(w.findAll('.timepicker__col')[0].findAll('.timepicker__cell')).toHaveLength(24)
  })

  it('из пустого: выбор часа даёт HH:00', async () => {
    const w = mountTP({ modelValue: null })
    await w.find('.timepicker__trigger').trigger('click')
    await w.findAll('.timepicker__col')[0].findAll('.timepicker__cell')[9].trigger('click') // '09'
    expect(w.emitted('update:modelValue')?.at(-1)).toEqual(['09:00'])
  })

  it('из пустого: выбор минуты даёт 00:MM (шаг 5)', async () => {
    const w = mountTP({ modelValue: null, minuteStep: 5 })
    await w.find('.timepicker__trigger').trigger('click')
    await w.findAll('.timepicker__col')[1].findAll('.timepicker__cell')[6].trigger('click') // '30'
    expect(w.emitted('update:modelValue')?.at(-1)).toEqual(['00:30'])
  })

  it('выбор часа сохраняет уже выбранную минуту', async () => {
    const w = mountTP({ modelValue: '14:30' })
    await w.find('.timepicker__trigger').trigger('click')
    await w.findAll('.timepicker__col')[0].findAll('.timepicker__cell')[9].trigger('click') // '09'
    expect(w.emitted('update:modelValue')?.at(-1)).toEqual(['09:30'])
  })

  it('со значением: триггер показывает HH:MM и подсвечивает выбранные час/минуту', async () => {
    const w = mountTP({ modelValue: '14:30', minuteStep: 5 })
    expect(w.find('.timepicker__trigger').text()).toBe('14:30')
    await w.find('.timepicker__trigger').trigger('click')
    const selected = w.findAll('.timepicker__cell.is-selected').map((c) => c.text())
    expect(selected).toContain('14')
    expect(selected).toContain('30')
  })

  it('очистка эмитит null', async () => {
    const w = mountTP({ modelValue: '14:30' })
    await w.find('.timepicker__clear').trigger('click')
    expect(w.emitted('update:modelValue')?.at(-1)).toEqual([null])
  })
})
