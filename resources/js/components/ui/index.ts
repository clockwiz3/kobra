/**
 * Библиотека UI-компонентов Kobra (дизайн-система) — barrel-экспорт.
 *
 * Импорт из одной точки:
 *   import { BaseModal, BaseCheckbox, useConfirmHelpers } from '@/components/ui'
 *
 * ──────────────────────────────────────────────────────────────────────────
 * КОНВЕНЦИИ БИБЛИОТЕКИ (расширяется со временем)
 * ──────────────────────────────────────────────────────────────────────────
 * Два яруса:
 *   1. ui/        — примитивы и общие контролы дизайн-системы (этот каталог).
 *                   Префикс `Base*`. Стили — классами из resources/css/themes.css
 *                   (НЕ scoped: темизация под dark/light/pixel идёт централизованно
 *                   через CSS-переменные). scoped-стиль — только под строго локальную
 *                   геометрию. Обязательная a11y (role/aria/tabindex/клавиатура).
 *                   API на typed `defineProps` / `defineModel` / `defineEmits`.
 *   2. composite/ — сложносоставные доменно-агностичные виджеты (Gantt, Calendar,
 *                   таймлайн…). Могут собираться из примитивов ui/. См. composite/README.md.
 *
 * Композиты, живущие здесь как «диалоговый слой» (Modal/ConfirmDialog/ConfirmRoot),
 * остаются в ui/, т.к. это базовый overlay-слой ДС, а не доменные виджеты.
 */

// — Примитивы / общие контролы —
export { default as BaseButton } from './BaseButton.vue'
export { default as BaseField } from './BaseField.vue'
export { default as BaseCard } from './BaseCard.vue'
export { default as BaseChip } from './BaseChip.vue'
export { default as BaseBadge } from './BaseBadge.vue'
export { default as BaseCheckbox } from './BaseCheckbox.vue'
export { default as BaseRadio } from './BaseRadio.vue'
export { default as BaseRadioGroup } from './BaseRadioGroup.vue'
export { default as BaseDatePicker } from './BaseDatePicker.vue'

// — Брендинг / темизация —
export { default as BrandMark } from './BrandMark.vue'
export { default as ThemeSwitch } from './ThemeSwitch.vue'
export { default as AccentPicker } from './AccentPicker.vue'

// — Overlay / диалоговый слой —
export { default as BaseModal } from './BaseModal.vue'
export { default as BaseDrawer } from './BaseDrawer.vue'
export { default as ConfirmDialog } from './ConfirmDialog.vue'
export { default as ConfirmRoot } from './ConfirmRoot.vue'
