import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import BaseDatePicker from '@/components/ui/BaseDatePicker.vue'
import type { DateRange } from '@/lib/date'

beforeEach(() => {
  vi.useFakeTimers({ toFake: ['Date'] })
  vi.setSystemTime(new Date('2026-06-17T12:00:00'))
})
afterEach(() => vi.useRealTimers())

function lastEmit(w: ReturnType<typeof mount>) {
  return w.emitted('update:modelValue')?.at(-1)?.[0]
}

describe('BaseDatePicker single', () => {
  it('открывает поповер и показывает ru-заголовок месяца', async () => {
    const w = mount(BaseDatePicker, { props: { mode: 'single', modelValue: null }, attachTo: document.body })
    await w.find('.datepicker__trigger').trigger('click')
    expect(w.find('.datepicker__pop').exists()).toBe(true)
    expect(w.find('.datepicker__month').text()).toBe('Июнь 2026')
    w.unmount()
  })

  it('выбор дня эмитит ISO и закрывает поповер', async () => {
    const w = mount(BaseDatePicker, { props: { mode: 'single', modelValue: null }, attachTo: document.body })
    await w.find('.datepicker__trigger').trigger('click')
    await w.find('[data-iso="2026-06-20"]').trigger('click')

    expect(lastEmit(w)).toBe('2026-06-20')
    expect(w.find('.datepicker__pop').exists()).toBe(false)
    w.unmount()
  })
})

describe('BaseDatePicker range', () => {
  it('два клика дают {start,end}', async () => {
    const w = mount(BaseDatePicker, { props: { mode: 'range', modelValue: null }, attachTo: document.body })
    await w.find('.datepicker__trigger').trigger('click')

    await w.find('[data-iso="2026-06-17"]').trigger('click')
    expect(lastEmit(w)).toEqual({ start: '2026-06-17', end: null })
    await w.setProps({ modelValue: lastEmit(w) as DateRange })

    await w.find('[data-iso="2026-06-24"]').trigger('click')
    expect(lastEmit(w)).toEqual({ start: '2026-06-17', end: '2026-06-24' })
    expect(w.find('.datepicker__pop').exists()).toBe(false)
    w.unmount()
  })

  it('обратный порядок переставляет границы', async () => {
    const w = mount(BaseDatePicker, { props: { mode: 'range', modelValue: null }, attachTo: document.body })
    await w.find('.datepicker__trigger').trigger('click')

    await w.find('[data-iso="2026-06-24"]').trigger('click')
    await w.setProps({ modelValue: lastEmit(w) as DateRange })
    await w.find('[data-iso="2026-06-17"]').trigger('click')

    expect(lastEmit(w)).toEqual({ start: '2026-06-17', end: '2026-06-24' })
    w.unmount()
  })
})

describe('BaseDatePicker clear / min-max / навигация', () => {
  it('clear обнуляет значение', async () => {
    const w = mount(BaseDatePicker, { props: { mode: 'single', modelValue: '2026-06-20' }, attachTo: document.body })
    await w.find('.datepicker__clear').trigger('click')
    expect(lastEmit(w)).toBeNull()
    w.unmount()
  })

  it('min/max дизейблят дни вне диапазона', async () => {
    const w = mount(BaseDatePicker, {
      props: { mode: 'single', modelValue: null, min: '2026-06-10', max: '2026-06-20' },
      attachTo: document.body,
    })
    await w.find('.datepicker__trigger').trigger('click')

    const outside = w.find('[data-iso="2026-06-05"]')
    expect(outside.attributes('disabled')).toBeDefined()
    await outside.trigger('click')
    expect(w.emitted('update:modelValue')).toBeUndefined() // клик по disabled не выбирает

    expect(w.find('[data-iso="2026-06-15"]').attributes('disabled')).toBeUndefined()
    w.unmount()
  })

  it('навигация по месяцам', async () => {
    const w = mount(BaseDatePicker, { props: { mode: 'single', modelValue: null }, attachTo: document.body })
    await w.find('.datepicker__trigger').trigger('click')
    expect(w.find('.datepicker__month').text()).toBe('Июнь 2026')

    await w.find('[aria-label="Следующий месяц"]').trigger('click')
    expect(w.find('.datepicker__month').text()).toBe('Июль 2026')

    await w.find('[aria-label="Предыдущий месяц"]').trigger('click')
    await w.find('[aria-label="Предыдущий месяц"]').trigger('click')
    expect(w.find('.datepicker__month').text()).toBe('Май 2026')
    w.unmount()
  })
})
