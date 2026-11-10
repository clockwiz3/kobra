# Правила кода

## Имена

- **PHP:** PSR-12. Классы `PascalCase`, методы и переменные `camelCase`, константы `UPPER_SNAKE`.
- **База:** таблицы `snake_case` во множественном числе, внешние ключи `<entity>_id`.
- **Vue:** компоненты `PascalCase`, composables `useXxx`, сторы `useXxxStore`.
- **TS:** типы `PascalCase`, переменные и функции `camelCase`.

## Где что

- Бэк: `app/Models`, `app/Http/Controllers/Api`, `app/Http/Requests`, `app/Services`, `app/Events`, `app/Data`.
- Фронт: `resources/js/{pages,components,stores,composables,api,lib}`.
- Модели пары используют `CoupleScoped`. Список таких таблиц ведётся в `docs/DATABASE.md`.

## Ошибки

- Валидация через `FormRequest`, ответы в JSON.
- Операции, которые могут повториться (закрытие задачи, ачивки, уведомления), делаем идемпотентными.
- `DB::table()` по таблицам пары не используем: он обходит scope.

## Логи

- Формат `[Service.method] message {context}`, уровень берётся из `LOG_LEVEL`.
- `DEBUG` для потока выполнения, `INFO` для событий, `WARN` для странностей, `ERROR` для сбоев.
- Персональные данные в логи не пишем, `telegram_id` маскируем.

## Тесты

- Pest на бэке, Vitest на фронте.
- Тесты изоляции двух пар (в `tests/Feature/Couple` и `tests/Feature/Realtime`) должны быть зелёными всегда. Новая сущность пары получает такой тест.
- Проверяем побочные эффекты (события, записи в базе), а не только HTTP-код.

## Реалтайм

Всё, что меняет данные пары, должно сразу отражаться у второго. Без события фича не готова.

- После изменения сервис делает `broadcast(new XxxEvent(...))->toOthers()` на `couple.{id}`.
- В payload кладём только DTO (`broadcastWith()`). `couple_id` передаётся в конструктор события, в payload его нет.
- Фронт шлёт `X-Socket-Id`, иначе инициатор получит своё же событие поверх optimistic-update.
- Канал один на пару, отдельные каналы под сущности не заводим.
- Фронт применяет событие точечной мутацией стора. Если что-то не сходится, делает `refetch`.
- В тестах `BROADCAST_CONNECTION=null`, проверяем через `Event::fake`.

## Shell

Зависимые команды не склеиваем через `&&`. Сначала `git checkout main`, потом отдельно `git pull`.
