// Единый источник пунктов навигации (data-driven): добавить раздел = одна строка.
// Иконки — xicons-компоненты на currentColor: основной набор @vicons/tabler,
// плюс @vicons/fluent для Ганта (в Tabler нет литерального Gantt).
// Модуль НЕ reactive → markRaw для компонентов не нужен.
import type { Component } from 'vue'
import { Dashboard, LayoutKanban, Heart, Calendar, ListDetails } from '@vicons/tabler'
import { GanttChart24Regular } from '@vicons/fluent'

export interface NavItem {
  /** Стабильный ключ для v-for и тестов. */
  key: string
  /** Подпись пункта (артефакты на ru). */
  label: string
  /** Имя роута (vue-router) — переход через :to="{ name }". */
  routeName: string
  /** Иконка-компонент (рендерится через <component :is="item.icon" />). */
  icon: Component
}

export const navItems: NavItem[] = [
  { key: 'home', label: 'Главная', routeName: 'dashboard', icon: Dashboard },
  { key: 'board', label: 'Доска', routeName: 'board', icon: LayoutKanban },
  { key: 'backlog', label: 'Задачи', routeName: 'backlog', icon: ListDetails },
  { key: 'wishlists', label: 'Вишлисты', routeName: 'wishlists', icon: Heart },
  { key: 'gantt', label: 'Гант', routeName: 'gantt', icon: GanttChart24Regular },
  { key: 'calendar', label: 'Календарь', routeName: 'calendar', icon: Calendar },
]
