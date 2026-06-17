import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mount, flushPromises, type VueWrapper } from '@vue/test-utils'
import ConfirmRoot from '@/components/ui/ConfirmRoot.vue'
import { confirm } from '@/composables/useConfirm'

// useConfirm — singleton-сервис: один <ConfirmRoot>, состояние в module scope.
// Фейк-таймеры нужны, чтобы детерминированно прогонять leave-задержку (settle).
describe('useConfirm', () => {
  let host: VueWrapper

  beforeEach(() => {
    vi.useFakeTimers()
    host = mount(ConfirmRoot, { global: { stubs: { teleport: true } }, attachTo: document.body })
  })
  afterEach(() => {
    vi.runOnlyPendingTimers()
    vi.useRealTimers()
    host.unmount()
  })

  it('confirm() показывает диалог и резолвит confirmed:true + choice', async () => {
    const p = confirm({
      title: 'Удалить колонку?',
      choices: [
        { value: 'keep', label: 'Сохранить' },
        { value: 'delete', label: 'Удалить' },
      ],
    })
    await flushPromises()
    expect(host.find('[role="dialog"]').exists()).toBe(true)

    await host.findAll('input[type="radio"]')[1].trigger('change')
    await host.find('.btn-primary').trigger('click')

    await expect(p).resolves.toEqual({ confirmed: true, choice: 'delete' })
  })

  it('отмена резолвит confirmed:false', async () => {
    const p = confirm({ title: 'Точно?' })
    await flushPromises()
    await host.find('.btn-ghost').trigger('click')
    await expect(p).resolves.toEqual({ confirmed: false, choice: undefined })
  })

  it('Escape резолвит confirmed:false', async () => {
    const p = confirm({ title: 'Точно?' })
    await flushPromises()
    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
    await flushPromises()
    await expect(p).resolves.toEqual({ confirmed: false, choice: undefined })
  })
})
