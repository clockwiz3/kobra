# Kobra

Наш с Лесей трекер дел. Laravel + Vue, реалтайм через Reverb.

## Запуск

```bash
composer setup
```

```bash
php artisan db:seed --class=CoupleSeeder
```

```bash
npm run dev
```

```bash
php artisan reverb:start
```

Открывается на `http://cuploid.test/` (Herd). Пароль аккаунтов берётся из `COUPLE_SEED_PASSWORD`, сменить его можно командой `php artisan user:password <email>`.

## Доки

[База](docs/DATABASE.md) · [Реалтайм](docs/realtime.md) · [Дизайн](docs/DESIGN-SYSTEM.md) · [Компоненты](docs/ui-library.md)
