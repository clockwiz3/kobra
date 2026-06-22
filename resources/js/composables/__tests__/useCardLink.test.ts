import { describe, it, expect, vi, beforeEach } from 'vitest'
import { useCardLink } from '@/composables/useCardLink'

describe('useCardLink', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
  })

  it('linkFor строит абсолютную ссылку на страницу задачи', () => {
    const { linkFor } = useCardLink()
    // jsdom origin по умолчанию http://localhost:3000
    expect(linkFor('54')).toBe(`${window.location.origin}/task/54`)
  })

  it('copy пишет ссылку в буфер и выставляет copied', async () => {
    const writeText = vi.fn().mockResolvedValue(undefined)
    vi.stubGlobal('navigator', { clipboard: { writeText } })

    const { copy, copied } = useCardLink()
    expect(copied.value).toBe(false)
    await copy('54')

    expect(writeText).toHaveBeenCalledWith(`${window.location.origin}/task/54`)
    expect(copied.value).toBe(true)
  })

  it('copy не падает, если clipboard недоступен', async () => {
    vi.stubGlobal('navigator', {})
    const { copy, copied } = useCardLink()

    await expect(copy('54')).resolves.toBeUndefined()
    expect(copied.value).toBe(false)
  })
})
