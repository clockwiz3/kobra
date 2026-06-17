/**
 * useConfirm — императивная замена window.confirm через Promise.
 *
 * Singleton-состояние на módule scope + один смонтированный <ConfirmRoot> (в App.vue).
 * Из любого компонента:
 *   const { confirm } = useConfirm()
 *   const { confirmed, choice } = await confirm({ title, choices })
 *
 * Параллельные вызовы выстраиваются в очередь и показываются последовательно.
 */
import { ref, shallowRef } from 'vue'
import type { ConfirmChoice } from '@/components/ui/ConfirmDialog.vue'
import { useLogger } from '@/composables/useLogger'

const log = useLogger('useConfirm')

export interface ConfirmOptions {
  title: string
  message?: string
  confirmText?: string
  cancelText?: string
  tone?: 'default' | 'danger'
  choices?: ConfirmChoice[]
  defaultChoice?: string
}

export interface ConfirmResult {
  confirmed: boolean
  choice?: string
}

interface ActiveRequest {
  options: ConfirmOptions
  resolve: (result: ConfirmResult) => void
}

// — singleton-состояние (одно на приложение) —
const open = ref(false)
const current = shallowRef<ActiveRequest | null>(null)
const queue: ActiveRequest[] = []

// мс ожидания leave-анимации модалки перед показом следующего запроса в очереди
const LEAVE_MS = 260

function pump(): void {
  if (current.value || queue.length === 0) return
  current.value = queue.shift() ?? null
  open.value = true
}

/** Поставить запрос подтверждения. Резолвится после выбора пользователя. */
export function confirm(options: ConfirmOptions): Promise<ConfirmResult> {
  log.debug('request', { title: options.title })
  return new Promise<ConfirmResult>((resolve) => {
    queue.push({ options, resolve })
    pump()
  })
}

/** Завершить текущий запрос результатом и (отложенно) показать следующий. */
function settle(result: ConfirmResult): void {
  const req = current.value
  if (!req) return
  open.value = false // запускаем leave-анимацию
  log.debug('resolve', result)
  req.resolve(result)
  // держим current до конца анимации (пропсы диалога должны оставаться валидными)
  setTimeout(() => {
    current.value = null
    pump()
  }, LEAVE_MS)
}

/** Внутренний хук для <ConfirmRoot>: реактивное состояние + обработчики. */
export function useConfirmHost() {
  return {
    open,
    current,
    onConfirm: (payload: { choice?: string }) => settle({ confirmed: true, choice: payload.choice }),
    onCancel: () => settle({ confirmed: false }),
  }
}

/** Публичный API для компонентов. */
export function useConfirm() {
  return { confirm }
}
