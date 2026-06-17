<script lang="ts">
// Монотонный счётчик для уникальных id заголовка (связка aria-labelledby).
let modalSeq = 0
</script>

<script setup lang="ts">
/**
 * Базовое модальное окно дизайн-системы Kobra — замена браузерных диалогов.
 * Teleport в <body>, role="dialog"+aria-modal, слоты header/title/default/footer.
 * a11y/UX: Esc, клик по overlay, блокировка скролла body, минимальный focus-trap
 * с возвратом фокуса инициатору. Анимация уважает prefers-reduced-motion.
 * Стили — классы .overlay/.modal/... из resources/css/themes.css (3 темы).
 */
import { ref, watch, nextTick, computed, useSlots } from 'vue'
import { onKeyStroke, useScrollLock } from '@vueuse/core'
import { useLogger } from '@/composables/useLogger'
import { prefersReducedMotion } from '@/composables/useTheme'

type CloseReason = 'overlay' | 'esc' | 'button' | 'programmatic'

const open = defineModel<boolean>({ default: false })

const props = withDefaults(
  defineProps<{
    title?: string
    closable?: boolean
    closeOnOverlay?: boolean
    closeOnEsc?: boolean
    size?: 'sm' | 'md' | 'lg'
    /**
     * Цель Teleport. ВАЖНО: телепортим в `.ds-root` (корень с [data-theme]/[data-accent]),
     * НЕ в <body>. Иначе модалка окажется вне тематического поддерева и тематические
     * CSS-переменные (--surface/--bg/--grad/--text/--border…) будут undefined → диалог
     * без фона/цветов. `.ds-root` — прямой потомок body, поэтому overflow/stacking
     * вложенных контейнеров (канбан) всё равно не клипают fixed-оверлей.
     */
    to?: string
  }>(),
  { closable: true, closeOnOverlay: true, closeOnEsc: true, size: 'md', to: '.ds-root' },
)

const emit = defineEmits<{ close: [reason: CloseReason]; open: [] }>()

const log = useLogger('BaseModal')
const reduced = prefersReducedMotion()
const titleId = `modal-title-${(modalSeq += 1)}`

const slots = useSlots()
// заголовок есть, если задан проп title ИЛИ передан слот #title
const hasTitle = computed(() => !!props.title || !!slots.title)

const panel = ref<HTMLElement | null>(null)
let lastFocused: HTMLElement | null = null

// Блокировка прокрутки body на время открытия (vueuse).
const bodyLock = useScrollLock(typeof document !== 'undefined' ? document.body : null)

function close(reason: CloseReason): void {
  if (!open.value) return
  log.debug('close', { reason })
  open.value = false
  emit('close', reason)
}
// для программного закрытия снаружи (например из слота footer)
defineExpose({ close: () => close('programmatic') })

function onOverlayMousedown(e: MouseEvent): void {
  // закрываем только если жест начат на самом overlay, а не на панели
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
      log.debug('open', { title: props.title })
      emit('open')
      await nextTick()
      const items = focusables()
      ;(items[0] ?? panel.value)?.focus()
    } else if (was) {
      // вернуть фокус элементу-инициатору
      lastFocused?.focus?.()
      lastFocused = null
    }
  },
  { immediate: true },
)
</script>

<template>
  <Teleport :to="to">
    <Transition :name="reduced ? undefined : 'modal'">
      <div v-if="open" class="overlay" @mousedown="onOverlayMousedown">
        <div
          ref="panel"
          class="modal"
          :class="{ 'modal--sm': size === 'sm', 'modal--lg': size === 'lg' }"
          role="dialog"
          aria-modal="true"
          :aria-labelledby="hasTitle ? titleId : undefined"
          tabindex="-1"
        >
          <slot name="header">
            <div v-if="hasTitle || closable" class="modal__head">
              <h2 v-if="hasTitle" :id="titleId" class="modal__title"><slot name="title">{{ title }}</slot></h2>
              <button
                v-if="closable"
                type="button"
                class="modal__close"
                aria-label="Закрыть"
                @click="close('button')"
              >&times;</button>
            </div>
          </slot>

          <div class="modal__body">
            <slot />
          </div>

          <div v-if="$slots.footer" class="modal__foot">
            <slot name="footer" :close="() => close('button')" />
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
