<script lang="ts">
// Монотонный счётчик для уникальных id заголовка (связка aria-labelledby).
let drawerSeq = 0
</script>

<script setup lang="ts">
/**
 * Базовая выезжающая панель (drawer) дизайн-системы Kobra — переиспользуемый примитив
 * для боковых панелей (детали карточки, фильтры, настройки). Построен по образцу BaseModal:
 * Teleport в `.ds-root`, role="dialog"+aria-modal, focus-trap, Esc, блокировка скролла body,
 * возврат фокуса инициатору. Слайд-ин с края (side), анимация уважает prefers-reduced-motion.
 * Стили — классы .drawer/.drawer-overlay/… из resources/css/themes.css (3 темы).
 */
import { ref, watch, nextTick, computed, useSlots } from 'vue'
import { onKeyStroke, useScrollLock } from '@vueuse/core'
import { useLogger } from '@/composables/useLogger'
import { prefersReducedMotion } from '@/composables/useTheme'

type CloseReason = 'overlay' | 'esc' | 'button' | 'programmatic'

// именованная модель → используется как v-model:open
const open = defineModel<boolean>('open', { default: false })

const props = withDefaults(
  defineProps<{
    title?: string
    /** Сторона выезда. По умолчанию справа (масштабирование: панели слева тоже поддержаны). */
    side?: 'right' | 'left'
    width?: 'sm' | 'md' | 'lg'
    closable?: boolean
    closeOnOverlay?: boolean
    closeOnEsc?: boolean
    /** Цель Teleport — `.ds-root` (тематическое поддерево), см. пояснение в BaseModal. */
    to?: string
  }>(),
  { side: 'right', width: 'md', closable: true, closeOnOverlay: true, closeOnEsc: true, to: '.ds-root' },
)

const emit = defineEmits<{ close: [reason: CloseReason]; open: [] }>()

const log = useLogger('BaseDrawer')
const reduced = prefersReducedMotion()
const titleId = `drawer-title-${(drawerSeq += 1)}`
// направление слайда задаётся именем перехода (drawer-right / drawer-left)
const transitionName = computed(() => (reduced ? undefined : `drawer-${props.side}`))

const slots = useSlots()
const hasTitle = computed(() => !!props.title || !!slots.title)

const panel = ref<HTMLElement | null>(null)
let lastFocused: HTMLElement | null = null

const bodyLock = useScrollLock(typeof document !== 'undefined' ? document.body : null)

function close(reason: CloseReason): void {
  if (!open.value) return
  log.debug('close', { reason })
  open.value = false
  emit('close', reason)
}
defineExpose({ close: () => close('programmatic') })

function onOverlayMousedown(e: MouseEvent): void {
  if (props.closeOnOverlay && e.target === e.currentTarget) close('overlay')
}

function focusables(): HTMLElement[] {
  if (!panel.value) return []
  const sel = 'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])'
  return Array.from(panel.value.querySelectorAll<HTMLElement>(sel)).filter((el) => el.offsetParent !== null)
}

onKeyStroke('Escape', (e) => {
  if (open.value && props.closeOnEsc) {
    e.preventDefault()
    close('esc')
  }
})

// Минимальный focus-trap: зацикливаем Tab/Shift+Tab внутри панели.
onKeyStroke('Tab', (e) => {
  if (!open.value || !panel.value) return
  const items = focusables()
  if (items.length === 0) {
    e.preventDefault()
    panel.value.focus()
    return
  }
  const first = items[0]
  const last = items[items.length - 1]
  const active = document.activeElement as HTMLElement | null
  const inside = active ? panel.value.contains(active) : false
  if (e.shiftKey && (active === first || !inside)) {
    e.preventDefault()
    last.focus()
  } else if (!e.shiftKey && (active === last || !inside)) {
    e.preventDefault()
    first.focus()
  }
})

watch(
  open,
  async (isOpen, was) => {
    bodyLock.value = isOpen
    if (isOpen) {
      lastFocused = (document.activeElement as HTMLElement) ?? null
      log.debug('open', { title: props.title, side: props.side })
      emit('open')
      await nextTick()
      const items = focusables()
      ;(items[0] ?? panel.value)?.focus()
    } else if (was) {
      lastFocused?.focus?.()
      lastFocused = null
    }
  },
  { immediate: true },
)
</script>

<template>
  <Teleport :to="to">
    <Transition :name="transitionName">
      <div
        v-if="open"
        class="drawer-overlay"
        :class="side === 'left' ? 'is-left' : 'is-right'"
        @mousedown="onOverlayMousedown"
      >
        <aside
          ref="panel"
          class="drawer"
          :class="[`drawer--${width}`, `drawer--${side}`]"
          role="dialog"
          aria-modal="true"
          :aria-labelledby="hasTitle ? titleId : undefined"
          tabindex="-1"
        >
          <slot name="header">
            <div v-if="hasTitle || closable" class="drawer__head">
              <h2 v-if="hasTitle" :id="titleId" class="drawer__title"><slot name="title">{{ title }}</slot></h2>
              <button
                v-if="closable"
                type="button"
                class="drawer__close"
                aria-label="Закрыть"
                @click="close('button')"
              >&times;</button>
            </div>
          </slot>

          <div class="drawer__body">
            <slot />
          </div>

          <div v-if="$slots.footer" class="drawer__foot">
            <slot name="footer" :close="() => close('button')" />
          </div>
        </aside>
      </div>
    </Transition>
  </Teleport>
</template>
