import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'

const stubs = { teleport: true }
const choices = [
  { value: 'keep', label: 'Сохранить' },
  { value: 'delete', label: 'Удалить' },
]

function mountDialog(props = {}) {
  return mount(ConfirmDialog, {
    props: { modelValue: true, title: 'Удалить?', ...props },
    global: { stubs },
    attachTo: document.body,
  })
}

describe('ConfirmDialog', () => {
  it('без choices показывает message и подтверждает без choice', async () => {
    const w = mountDialog({ message: 'Точно?' })
    expect(w.find('.modal__msg').text()).toBe('Точно?')
    expect(w.findAll('.choice')).toHaveLength(0)
    await w.find('.btn-primary').trigger('click')
    expect(w.emitted('confirm')?.[0]).toEqual([{ choice: undefined }])
  })

  it('рендерит choices и подтверждает дефолтным выбором', async () => {
    const w = mountDialog({ choices })
    expect(w.findAll('.choice')).toHaveLength(2)
    await w.find('.btn-primary').trigger('click')
    expect(w.emitted('confirm')?.[0]).toEqual([{ choice: 'keep' }])
  })

  it('учитывает defaultChoice', async () => {
    const w = mountDialog({ choices, defaultChoice: 'delete' })
    await w.find('.btn-primary').trigger('click')
    expect(w.emitted('confirm')?.[0]).toEqual([{ choice: 'delete' }])
  })

  it('возвращает выбранный пользователем choice', async () => {
    const w = mountDialog({ choices })
    await w.findAll('input[type="radio"]')[1].trigger('change')
    await w.find('.btn-primary').trigger('click')
    expect(w.emitted('confirm')?.[0]).toEqual([{ choice: 'delete' }])
  })

  it('кнопка отмены эмитит cancel', async () => {
    const w = mountDialog({ choices })
    await w.find('.btn-ghost').trigger('click')
    expect(w.emitted('cancel')).toBeTruthy()
    expect(w.emitted('confirm')).toBeUndefined()
  })

  it('tone=danger вешает класс на кнопку подтверждения', () => {
    const w = mountDialog({ tone: 'danger' })
    expect(w.find('.btn-primary').classes()).toContain('is-danger')
  })
})
