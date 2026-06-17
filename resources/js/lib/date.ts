// Единый модуль работы с датами (dayjs, ru-локаль, неделя с понедельника).
// Используется дейтпикером (BaseDatePicker) и отображением дедлайнов на карточках.
// Дедлайны — date-only (без времени): на проводе и в v-model — строки 'YYYY-MM-DD'.
// Бэкенд хранит due_at/due_end_at как timestamp и отдаёт ISO 8601 — parseISO это переваривает.

import dayjs, { type Dayjs } from 'dayjs'
import customParseFormat from 'dayjs/plugin/customParseFormat'
import isSameOrBefore from 'dayjs/plugin/isSameOrBefore'
import isBetween from 'dayjs/plugin/isBetween'
import localeData from 'dayjs/plugin/localeData'
import weekday from 'dayjs/plugin/weekday'
import 'dayjs/locale/ru'
import { useLogger } from '@/composables/useLogger'

dayjs.extend(customParseFormat)
dayjs.extend(isSameOrBefore)
dayjs.extend(isBetween)
dayjs.extend(localeData)
dayjs.extend(weekday)
dayjs.locale('ru')

const log = useLogger('date')

/** Диапазон дат (или одиночная дата при end=null). Значения — 'YYYY-MM-DD' либо null. */
export interface DateRange {
  start: string | null
  end: string | null
}

/** Формат хранения/передачи дедлайна (date-only). */
export const DATE_FMT = 'YYYY-MM-DD'

/** Парс ISO/строки в Dayjs; null при пустом/невалидном вводе (с WARN). */
export function parseISO(s: string | null | undefined): Dayjs | null {
  if (!s) return null
  const d = dayjs(s)
  if (!d.isValid()) {
    log.warn('[date.parseISO] invalid input', { value: s })
    return null
  }
  return d
}

/** Dayjs/Date/строка → 'YYYY-MM-DD' (date-only); null при пустом/невалидном. */
export function toISODate(value: Dayjs | Date | string | null | undefined): string | null {
  if (value === null || value === undefined || value === '') return null
  const d = dayjs(value)
  if (!d.isValid()) {
    log.warn('[date.toISODate] invalid input', { value })
    return null
  }
  return d.format(DATE_FMT)
}

/** Сегодня (начало дня). */
export function today(): Dayjs {
  return dayjs().startOf('day')
}

/** Человекочитаемая дата, ru. По умолчанию «17 июня», с годом — если год не текущий. */
export function formatDate(s: string | null | undefined, opts: { year?: boolean } = {}): string {
  const d = parseISO(s)
  if (!d) return ''
  const withYear = opts.year ?? d.year() !== dayjs().year()
  return d.format(withYear ? 'D MMMM YYYY' : 'D MMMM')
}

/**
 * Человекочитаемый диапазон, ru, со схлопыванием общего месяца/года:
 *  - один день / без end → «17 июня»
 *  - один месяц       → «17–24 июня»
 *  - один год         → «17 июня – 3 июля»
 *  - разные годы      → «17 июня 2026 – 3 января 2027»
 */
export function formatRange(start: string | null | undefined, end: string | null | undefined): string {
  const a = parseISO(start)
  const b = parseISO(end)
  if (!a) return ''
  if (!b || a.isSame(b, 'day')) return formatDate(start)

  const sameYear = a.year() === b.year()
  const sameMonth = sameYear && a.month() === b.month()
  const curYear = dayjs().year()
  const showYear = !sameYear || a.year() !== curYear

  if (sameMonth) {
    // «17–24 июня» (+ год, если не текущий)
    const tail = b.format(showYear ? 'D MMMM YYYY' : 'D MMMM')
    return `${a.format('D')}–${tail}`
  }
  const left = a.format(showYear ? 'D MMMM YYYY' : 'D MMMM')
  const right = b.format(showYear ? 'D MMMM YYYY' : 'D MMMM')
  return `${left} – ${right}`
}

/**
 * Сетка месяца 6×7 (начало недели — понедельник), включая хвосты соседних месяцев.
 * Геометрия считается явно (Monday-based), не завися от конфигурации локали.
 */
export function monthMatrix(viewDate: Dayjs): Dayjs[][] {
  const firstOfMonth = viewDate.startOf('month')
  // day(): 0=Вс..6=Сб → смещение до ближайшего понедельника слева
  const offset = (firstOfMonth.day() + 6) % 7
  const gridStart = firstOfMonth.subtract(offset, 'day').startOf('day')

  const weeks: Dayjs[][] = []
  for (let w = 0; w < 6; w += 1) {
    const row: Dayjs[] = []
    for (let d = 0; d < 7; d += 1) {
      row.push(gridStart.add(w * 7 + d, 'day'))
    }
    weeks.push(row)
  }
  return weeks
}

/** Заголовок месяца, ru, с заглавной: «Июнь 2026». */
export function monthTitle(viewDate: Dayjs): string {
  const t = viewDate.format('MMMM YYYY')
  return t.charAt(0).toUpperCase() + t.slice(1)
}

/** Короткие подписи дней недели (с понедельника), ru: ['Пн','Вт',…,'Вс']. */
export function weekdayLabels(): string[] {
  // Берём заведомо известный понедельник и идём 7 дней.
  const monday = dayjs('2024-01-01') // 1 января 2024 — понедельник
  return Array.from({ length: 7 }, (_, i) => {
    const label = monday.add(i, 'day').format('dd') // 'пн','вт',…
    return label.charAt(0).toUpperCase() + label.slice(1)
  })
}

/** Совпадение по дню (любые принимаемые dayjs значения). */
export function isSameDate(a: Dayjs | string | null, b: Dayjs | string | null): boolean {
  if (!a || !b) return false
  return dayjs(a).isSame(dayjs(b), 'day')
}

/** d в [start, end] включительно (по дню). */
export function isInRange(
  d: Dayjs | string,
  start: string | null,
  end: string | null,
): boolean {
  const s = parseISO(start)
  const e = parseISO(end)
  if (!s || !e) return false
  return dayjs(d).isBetween(s, e, 'day', '[]')
}

/**
 * Коректность дедлайна:
 *  - нет start → false (диапазон/дата без начала бессмысленны);
 *  - есть start, нет end → true (одиночная дата);
 *  - оба есть → end >= start.
 */
export function isRangeValid(start: string | null | undefined, end: string | null | undefined): boolean {
  const s = parseISO(start)
  if (!s) return false
  const e = parseISO(end)
  if (!e) return true
  return s.isSameOrBefore(e, 'day')
}

export { dayjs }
export type { Dayjs }
