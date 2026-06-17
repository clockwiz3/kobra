/**
 * useTheme — runtime дизайн-системы Kobra.
 *
 * Управляет атрибутами [data-theme] и [data-accent] на корневом элементе .ds-root
 * (см. resources/css/themes.css). Заменяет inline-IIFE из прототипа Claude Design
 * на Vue-идиоматичный composable с персистом.
 *
 * Темы:   light (кремовая ретро, по умолчанию) · dark · pixel (8-бит)
 * Гаммы:  rose · sunset · honey · berry · lagoon (default — honey, по решению дизайна)
 *
 * NB (couple-as-shard): сейчас выбор персистится per-device (localStorage). Когда появится
 * активная пара — тема/гамма должны синхронизироваться в couples.settings (shard-scoped),
 * чтобы оба партнёра видели общий стиль пространства. См. milestone «Customization Engine».
 */
import { ref, watch } from 'vue'

export type Theme = 'dark' | 'light' | 'pixel'
export type Accent = 'rose' | 'sunset' | 'honey' | 'berry' | 'lagoon'

export const THEMES: Theme[] = ['dark', 'light', 'pixel']
export const ACCENTS: Accent[] = ['rose', 'sunset', 'honey', 'berry', 'lagoon']

const THEME_KEY = 'cuploid.theme'
const ACCENT_KEY = 'cuploid.accent'

function read<T extends string>(key: string, fallback: T, allowed: readonly T[]): T {
  if (typeof localStorage === 'undefined') return fallback
  const v = localStorage.getItem(key) as T | null
  return v && allowed.includes(v) ? v : fallback
}

function persist(key: string, value: string): void {
  if (typeof localStorage !== 'undefined') localStorage.setItem(key, value)
}

// Singleton-состояние: одно на приложение.
const theme = ref<Theme>(read(THEME_KEY, 'light', THEMES))
const accent = ref<Accent>(read(ACCENT_KEY, 'honey', ACCENTS))

function applyTo(el: HTMLElement | null): void {
  const target = el ?? (typeof document !== 'undefined' ? document.querySelector<HTMLElement>('.ds-root') : null)
  if (!target) return
  target.setAttribute('data-theme', theme.value)
  target.setAttribute('data-accent', accent.value)
}

let bound = false
function bindWatchers(): void {
  if (bound || typeof document === 'undefined') return
  bound = true
  watch(theme, (t) => { persist(THEME_KEY, t); applyTo(null) }, { immediate: true })
  watch(accent, (a) => { persist(ACCENT_KEY, a); applyTo(null) }, { immediate: true })
}

export function prefersReducedMotion(): boolean {
  return typeof window !== 'undefined'
    && window.matchMedia('(prefers-reduced-motion: reduce)').matches
}

export function useTheme() {
  bindWatchers()
  return {
    theme,
    accent,
    themes: THEMES,
    accents: ACCENTS,
    setTheme: (t: Theme) => { theme.value = t },
    setAccent: (a: Accent) => { accent.value = a },
    /** Применить текущие значения к конкретному корню (например, после маунта .ds-root). */
    apply: (el?: HTMLElement | null) => applyTo(el ?? null),
    prefersReducedMotion,
  }
}
