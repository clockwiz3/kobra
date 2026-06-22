import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { frameStyle, useCardFrame } from '@/composables/useCardFrame'
import { useBoardStore } from '@/stores/board'
import type { CardFrame } from '@/types/board'

beforeEach(() => setActivePinia(createPinia()))

const solid = (color = '#ff0000'): CardFrame => ({ paint: { kind: 'solid', color }, style: 'solid', width: 2, animated: false })

describe('frameStyle', () => {
  it('сплошной цвет → border со стилем/толщиной/цветом', () => {
    const r = frameStyle(solid('#abcdef'))
    expect(r.class).toBeNull()
    expect(r.style).toMatchObject({ borderWidth: '2px', borderStyle: 'solid', borderColor: '#abcdef' })
  })

  it('градиент + solid → background border-box трюк', () => {
    const r = frameStyle({ paint: { kind: 'gradient', from: '#111111', to: '#222222', angle: 90 }, style: 'solid', width: 3, animated: false })
    expect(r.class).toBeNull()
    expect(r.style.borderColor).toBe('transparent')
    expect(r.style.backgroundImage).toContain('linear-gradient(90deg, #111111, #222222)')
    expect(r.style.backgroundClip).toBe('padding-box, border-box')
  })

  it('градиент + пунктир (без мерцания) → фолбэк на сплошной border-color = from', () => {
    const r = frameStyle({ paint: { kind: 'gradient', from: '#abcabc', to: '#def0de', angle: 45 }, style: 'dashed', width: 1, animated: false })
    expect(r.style.borderStyle).toBe('dashed')
    expect(r.style.borderColor).toBe('#abcabc')
    expect(r.style.backgroundImage).toBeUndefined()
  })

  it('мерцание сплошного цвета → градиентная рамка + класс анимации', () => {
    const r = frameStyle({ ...solid('#ff0000'), animated: true })
    expect(r.class).toBe('frame-shimmer-anim')
    expect(r.style.backgroundImage).toContain('#ff0000')
    expect(r.style.backgroundSize).toBe('100% 100%, 300% 100%')
    expect(r.style.borderColor).toBe('transparent')
  })

  it('мерцание градиента → класс анимации + блик из обоих цветов', () => {
    const r = frameStyle({ paint: { kind: 'gradient', from: '#112233', to: '#445566', angle: 120 }, style: 'solid', width: 2, animated: true })
    expect(r.class).toBe('frame-shimmer-anim')
    expect(r.style.backgroundImage).toContain('#112233')
    expect(r.style.backgroundImage).toContain('#445566')
  })

  it('null → пустой рендер', () => {
    expect(frameStyle(null)).toEqual({ style: {}, class: null })
  })
})

describe('useCardFrame.frameForAssignee', () => {
  it('her → рамка участницы (мок: сплошной)', () => {
    const { frameForAssignee } = useCardFrame()
    const f = frameForAssignee('her')
    expect(f?.paint.kind).toBe('solid')
  })

  it('him → рамка участника (мок: градиент)', () => {
    const { frameForAssignee } = useCardFrame()
    expect(frameForAssignee('him')?.paint.kind).toBe('gradient')
  })

  it('together → смешанный градиент из цветов обоих', () => {
    const store = useBoardStore()
    store.setMemberAppearance('her', { card: solid('#aa0000') })
    store.setMemberAppearance('him', { card: solid('#0000bb') })
    const { frameForAssignee } = useCardFrame()
    const f = frameForAssignee('together')
    expect(f?.paint.kind).toBe('gradient')
    if (f?.paint.kind === 'gradient') {
      expect(f.paint.from).toBe('#aa0000') // her
      expect(f.paint.to).toBe('#0000bb') // him
    }
  })

  it('нет appearance → renderForAssignee пустой (дефолтная рамка .task)', () => {
    const store = useBoardStore()
    store.setMemberAppearance('her', null)
    const { renderForAssignee } = useCardFrame()
    expect(renderForAssignee('her')).toEqual({ style: {}, class: null })
  })

  it('together без кастома → акцентные токены пары', () => {
    const store = useBoardStore()
    store.setMemberAppearance('her', null)
    store.setMemberAppearance('him', null)
    const { frameForAssignee } = useCardFrame()
    const f = frameForAssignee('together')
    if (f?.paint.kind === 'gradient') {
      expect(f.paint.from).toBe('var(--accent)')
      expect(f.paint.to).toBe('var(--accent-2)')
    }
  })
})
