import { vi } from 'vitest'

/**
 * Глобальный setup тестов. jsdom не реализует matchMedia — он нужен
 * prefersReducedMotion() (useTheme), которым пользуются BaseModal/KanbanColumn.
 */
if (!window.matchMedia) {
  window.matchMedia = vi.fn().mockImplementation((query: string) => ({
    matches: false,
    media: query,
    onchange: null,
    addEventListener: vi.fn(),
    removeEventListener: vi.fn(),
    addListener: vi.fn(),
    removeListener: vi.fn(),
    dispatchEvent: vi.fn(),
  })) as unknown as typeof window.matchMedia
}
