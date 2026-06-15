# Project Roadmap

> Приватный романтический таск-трекер для пар; каждая пара — изолированный shard (рынок СНГ).

## Milestones

- [x] **Project Foundation** — scaffold Laravel 13 + Vue 3 + TS + PWA, тулинг, DB-фундамент и tenancy-слой (couple-as-shard). План: `plans/foundation-waitlist.md`, задачи 1–6
- [ ] **Waitlist Launch** — double-opt-in вайтлист + лендинг-PWA + Telegram-уведомления; сбор аудитории до запуска (задачи 7–13)
- [ ] **Couple Shard & Pairing** — регистрация, Sanctum-auth (passwordless/Telegram-ready), создание/активация пары, изоляция tenancy в бою (CoupleContext, Policy, leak-тесты)
- [ ] **Private Gamification Core** — один общий профиль пары, upside-only стрик, XP/уровни, приватные ачивки и пасхалки, check-in; атрибуция партнёров только комбинированная
- [ ] **Kanban (совместные задачи)** — boards/columns/cards/labels/comments, real-time через Reverb (приватный канал `couple.{id}`)
- [ ] **Wishlists** — списки желаний пары, «скрытая бронь» подарков партнёром
- [ ] **Customization Engine («цифровой ад»)** — движок цветовых тем, анимаций, частиц и пасхалок; hearts как валюта на кастомизацию
- [ ] **Telegram Bot — полный цикл** — warmth-триггеры (годовщины, «в этот день», вехи), команды, управление уведомлениями
- [ ] **Mobile (Capacitor)** — упаковка PWA в iOS/Android, нативные пуши (`@capacitor/push-notifications`)
- [ ] **Gamification v2 & Retention** — кооперативные quests (upside-only), seasonal/годовщины, hearts-магазин тем, daily check-in; гейт по D7/D30
- [ ] **Privacy & Lifecycle Hardening** — роспуск/архив/удаление шарда, grace-window + уведомление партнёра, TTL-purge архивов, reconcile-флоу
- [ ] **Production Readiness** — CI, Docker, observability/логи, бэкапы, нагрузочные и security-проверки

## Completed

| Milestone | Date |
|-----------|------|
| Project Foundation | 2026-06-15 |
