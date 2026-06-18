import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import {
  parseISO,
  toISODate,
  formatDate,
  formatRange,
  monthMatrix,
  monthTitle,
  weekdayLabels,
  isRangeValid,
  isInRange,
  isSameDate,
  isOverdue,
  dayjs,
} from '@/lib/date'

// Фиксируем «сегодня» = 17 июня 2026, чтобы year-логика была детерминированной.
beforeEach(() => {
  vi.useFakeTimers({ toFake: ['Date'] })
  vi.setSystemTime(new Date('2026-06-17T12:00:00'))
})
afterEach(() => {
  vi.useRealTimers()
})

describe('parseISO / toISODate', () => {
  it('парсит валидную дату и форматирует обратно в YYYY-MM-DD', () => {
    expect(toISODate(parseISO('2026-06-17'))).toBe('2026-06-17')
  })
  it('null/невалид → null', () => {
    expect(parseISO(null)).toBeNull()
    expect(parseISO('не-дата')).toBeNull()
    expect(toISODate(null)).toBeNull()
    expect(toISODate('')).toBeNull()
  })
  it('toISODate принимает Date', () => {
    expect(toISODate(new Date('2026-12-31T10:00:00'))).toBe('2026-12-31')
  })
})

describe('formatDate', () => {
  it('текущий год — без года', () => {
    expect(formatDate('2026-06-17')).toBe('17 июня')
  })
  it('другой год — с годом', () => {
    expect(formatDate('2027-01-03')).toBe('3 января 2027')
  })
  it('явный opts.year переопределяет', () => {
    expect(formatDate('2026-06-17', { year: true })).toBe('17 июня 2026')
  })
  it('пусто → пустая строка', () => {
    expect(formatDate(null)).toBe('')
  })
})

describe('formatRange', () => {
  it('без end → одиночная дата', () => {
    expect(formatRange('2026-06-17', null)).toBe('17 июня')
  })
  it('один и тот же день → одиночная дата', () => {
    expect(formatRange('2026-06-17', '2026-06-17')).toBe('17 июня')
  })
  it('один месяц → схлопывает месяц', () => {
    expect(formatRange('2026-06-17', '2026-06-24')).toBe('17–24 июня')
  })
  it('один год, разные месяцы', () => {
    expect(formatRange('2026-06-17', '2026-07-03')).toBe('17 июня – 3 июля')
  })
  it('разные годы → с годами', () => {
    expect(formatRange('2026-06-17', '2027-01-03')).toBe('17 июня 2026 – 3 января 2027')
  })
  it('пустой start → пусто', () => {
    expect(formatRange(null, '2026-06-24')).toBe('')
  })
})

describe('monthMatrix', () => {
  const grid = () => monthMatrix(dayjs('2026-06-15'))

  it('сетка 6×7', () => {
    const m = grid()
    expect(m).toHaveLength(6)
    m.forEach((row) => expect(row).toHaveLength(7))
  })
  it('первая ячейка — понедельник', () => {
    const first = grid()[0][0]
    // day(): 0=Вс..6=Сб; (day+6)%7 === 0 ⇔ понедельник
    expect((first.day() + 6) % 7).toBe(0)
  })
  it('содержит первый и последний день месяца', () => {
    const flat = grid().flat().map((d) => d.format('YYYY-MM-DD'))
    expect(flat).toContain('2026-06-01')
    expect(flat).toContain('2026-06-30')
  })
})

describe('monthTitle / weekdayLabels', () => {
  it('заголовок месяца с заглавной, ru', () => {
    expect(monthTitle(dayjs('2026-06-01'))).toBe('Июнь 2026')
  })
  it('подписи дней с понедельника', () => {
    expect(weekdayLabels()).toEqual(['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'])
  })
})

describe('isRangeValid', () => {
  it('нет start → false', () => {
    expect(isRangeValid(null, null)).toBe(false)
    expect(isRangeValid(null, '2026-06-24')).toBe(false)
  })
  it('start без end → true (одиночная)', () => {
    expect(isRangeValid('2026-06-17', null)).toBe(true)
  })
  it('end >= start → true', () => {
    expect(isRangeValid('2026-06-17', '2026-06-24')).toBe(true)
    expect(isRangeValid('2026-06-17', '2026-06-17')).toBe(true)
  })
  it('end < start → false', () => {
    expect(isRangeValid('2026-06-24', '2026-06-17')).toBe(false)
  })
})

describe('isInRange / isSameDate', () => {
  it('isInRange включительно', () => {
    expect(isInRange('2026-06-20', '2026-06-17', '2026-06-24')).toBe(true)
    expect(isInRange('2026-06-17', '2026-06-17', '2026-06-24')).toBe(true)
    expect(isInRange('2026-06-25', '2026-06-17', '2026-06-24')).toBe(false)
    expect(isInRange('2026-06-20', null, '2026-06-24')).toBe(false)
  })
  it('isSameDate', () => {
    expect(isSameDate('2026-06-17', '2026-06-17')).toBe(true)
    expect(isSameDate('2026-06-17', '2026-06-18')).toBe(false)
    expect(isSameDate(null, '2026-06-17')).toBe(false)
  })
})

describe('isOverdue (сегодня = 2026-06-17)', () => {
  it('дата в прошлом → true, сегодня/будущее → false', () => {
    expect(isOverdue('2026-06-16')).toBe(true)
    expect(isOverdue('2026-06-17')).toBe(false) // строго раньше сегодня
    expect(isOverdue('2026-06-18')).toBe(false)
  })
  it('эффективная дата — это end (конец диапазона), а не start', () => {
    expect(isOverdue('2026-06-10', '2026-06-20')).toBe(false) // диапазон ещё не закончился
    expect(isOverdue('2026-06-01', '2026-06-16')).toBe(true) // закончился вчера
  })
  it('пусто → false', () => {
    expect(isOverdue(null)).toBe(false)
    expect(isOverdue(undefined, undefined)).toBe(false)
  })
})
