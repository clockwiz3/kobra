import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import CardDetailDrawer from '@/components/composite/CardDetailDrawer.vue'
import { useBoardStore } from '@/stores/board'
import type { Card } from '@/types/board'

const makeCard = (over: Partial<Card> = {}): Card => ({
  id: '5', title: 'Тест', description: 'desc', assignee: 'together',
  labels: [], dueAt: null, dueEndAt: null, done: false, ...over,
})

let pinia: ReturnType<typeof createPinia>
let store: ReturnType<typeof useBoardStore>

function mountDrawer() {
  return mount(CardDetailDrawer, {
    global: { plugins: [pinia], stubs: { teleport: true } },
    attachTo: document.body,
  })
}

function selectCard(card: Card) {
  store.columns = [{ id: 'c', title: 'C', isTerminal: false, cards: [card] }]
  store.openCard(card.id)
}

beforeEach(() => {
  vi.useFakeTimers({ toFake: ['Date', 'setTimeout', 'clearTimeout'] })
  vi.setSystemTime(new Date('2026-06-17T12:00:00'))
  pinia = createPinia()
  setActivePinia(pinia)
  store = useBoardStore()
})
afterEach(() => vi.useRealTimers())

describe('CardDetailDrawer', () => {
  it('пустой selectedCard → панель закрыта', () => {
    const w = mountDrawer()
    expect(w.find('[role="dialog"]').exists()).toBe(false)
  })

  it('рендерит секции для выбранной карточки', () => {
    selectCard(makeCard())
    const w = mountDrawer()
    expect(w.find('[role="dialog"]').exists()).toBe(true)
    expect((w.find('input.input').element as HTMLInputElement).value).toBe('Тест')
    expect((w.find('textarea.textarea').element as HTMLTextAreaElement).value).toBe('desc')
    // 3 исполнителя + 2 режима дедлайна = 5 радио
    expect(w.findAll('input[type="radio"]')).toHaveLength(5)
  })

  it('изменение названия триггерит debounced updateCard', async () => {
    selectCard(makeCard())
    const w = mountDrawer()
    const spy = vi.spyOn(store, 'updateCard').mockResolvedValue()

    await w.find('input.input').setValue('Новое название')
    expect(spy).not.toHaveBeenCalled() // дебаунс ещё не сработал

    await vi.advanceTimersByTimeAsync(500)
    expect(spy).toHaveBeenCalledWith('5', expect.objectContaining({ title: 'Новое название' }))
  })

  it('смена исполнителя триггерит updateCard', async () => {
    selectCard(makeCard())
    const w = mountDrawer()
    const spy = vi.spyOn(store, 'updateCard').mockResolvedValue()

    await w.findAll('input[type="radio"]')[0].setValue() // him
    await vi.advanceTimersByTimeAsync(500)
    expect(spy).toHaveBeenCalledWith('5', expect.objectContaining({ assignee: 'him' }))
  })

  it('переключение в режим «Период» и выбор дат шлёт dueAt+dueEndAt', async () => {
    selectCard(makeCard())
    const w = mountDrawer()
    const spy = vi.spyOn(store, 'updateCard').mockResolvedValue()

    await w.findAll('input[type="radio"]')[4].setValue() // режим range
    await w.find('.datepicker__trigger').trigger('click')
    await w.find('[data-iso="2026-06-17"]').trigger('click')
    await w.find('[data-iso="2026-06-24"]').trigger('click')

    await vi.advanceTimersByTimeAsync(500)
    expect(spy).toHaveBeenLastCalledWith('5', expect.objectContaining({
      dueAt: '2026-06-17', dueEndAt: '2026-06-24',
    }))
  })

  it('закрытие панели до окончания дебаунса всё равно отправляет правки (баг-фикс)', async () => {
    selectCard(makeCard())
    const w = mountDrawer()
    const spy = vi.spyOn(store, 'updateCard').mockResolvedValue()

    await w.find('input.input').setValue('Быстро')
    expect(spy).not.toHaveBeenCalled() // дебаунс ещё не сработал

    store.closeCard() // закрываем драйвер, не дождавшись сохранения
    await flushPromises()

    expect(spy).toHaveBeenCalledWith('5', expect.objectContaining({ title: 'Быстро' }))
  })

  it('режим «Дата»: одиночный выбор шлёт dueAt и dueEndAt=null', async () => {
    selectCard(makeCard())
    const w = mountDrawer()
    const spy = vi.spyOn(store, 'updateCard').mockResolvedValue()

    await w.find('.datepicker__trigger').trigger('click') // single по умолчанию
    await w.find('[data-iso="2026-06-20"]').trigger('click')

    await vi.advanceTimersByTimeAsync(500)
    expect(spy).toHaveBeenLastCalledWith('5', expect.objectContaining({
      dueAt: '2026-06-20', dueEndAt: null,
    }))
  })
})
