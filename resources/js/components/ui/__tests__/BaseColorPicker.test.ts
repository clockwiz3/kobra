import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import BaseColorPicker from '@/components/ui/BaseColorPicker.vue'
import type { CardFrame } from '@/types/board'

const solidFrame = (): CardFrame => ({ paint: { kind: 'solid', color: '#e84aa6' }, style: 'solid', width: 2, animated: false })

const mountPicker = (frame: CardFrame = solidFrame()) =>
  mount(BaseColorPicker, { props: { modelValue: frame } })

// Последнее эмитнутое значение модели.
function lastModel(w: ReturnType<typeof mountPicker>): CardFrame {
  const ev = w.emitted('update:modelValue')
  return ev!.at(-1)![0] as CardFrame
}

const byText = (w: ReturnType<typeof mountPicker>, text: string) =>
  w.findAll('button').find((b) => b.text() === text)!

describe('BaseColorPicker', () => {
  it('переключение заливки solid→gradient меняет paint.kind с дефолтным углом', async () => {
    const w = mountPicker()
    await byText(w, 'Градиент').trigger('click')
    const m = lastModel(w)
    expect(m.paint.kind).toBe('gradient')
    if (m.paint.kind === 'gradient') {
      expect(m.paint.from).toBe('#e84aa6') // основной цвет перенесён в from
      expect(m.paint.angle).toBe(135)
    }
  })

  it('клик по свотчу задаёт сплошной цвет', async () => {
    const w = mountPicker()
    await w.findAll('.cpick__swatch')[0].trigger('click') // первый свотч = #e84aa6
    const m = lastModel(w)
    expect(m.paint).toEqual({ kind: 'solid', color: '#e84aa6' })
  })

  it('выбор стиля рамки отражается в модели', async () => {
    const w = mountPicker()
    await byText(w, 'Пунктир').trigger('click')
    expect(lastModel(w).style).toBe('dashed')
  })

  it('выбор толщины отражается в модели', async () => {
    const w = mountPicker()
    await byText(w, '3px').trigger('click')
    expect(lastModel(w).width).toBe(3)
  })

  it('тоггл «Мерцание» включает и выключает animated', async () => {
    const w = mountPicker()
    await byText(w, '✨ Мерцание').trigger('click')
    expect(lastModel(w).animated).toBe(true)
    await byText(w, '✨ Мерцание').trigger('click')
    expect(lastModel(w).animated).toBe(false)
  })

  it('живое превью мерцающего кадра рендерит класс анимации + инлайн-градиент', async () => {
    const w = mountPicker({ ...solidFrame(), animated: true })
    const preview = w.find('.cpick__preview')
    expect(preview.classes()).toContain('frame-shimmer-anim')
    expect(preview.attributes('style')).toContain('linear-gradient')
  })
})
