# Календарь звонков (продолжение)


[![hexlet-check](https://github.com/SergeyBesck/ai-for-developers-project-387/actions/workflows/hexlet-check.yml/badge.svg)](https://github.com/SergeyBesck/ai-for-developers-project-387/actions)

Интегрируйте работу агентов в GitHub проект

Учебный проект Хекслета: https://ru.hexlet.io/programs/ai-for-developers
Как это должно работать: https://files.hexlet.app/a/2ipc5m

## Демо

Опубликованное приложение: https://ai-for-developers-project-386-30f5.onrender.com/

На бесплатном тарифе Render сервис засыпает после простоя, первый запрос может занять около минуты, а данные (типы событий и записи) не сохраняются между перезапусками.

## Стек

- js, html, php, css

## Установка

```bash
git clone https://github.com/SergeyBesck/ai-for-developers-project-387.git
cd ai-for-developers-project-387
composer install
npm ci
```

## Использование

```bash
php -S localhost:8000 -t public public/router.php
composer test
composer lint
npm run lint
npm run test:sdk
```

Страницы: главная — `index.html`, запись — `booking.html`, админка Хоста — `admin.html` (ссылка «Админка» есть в шапке всех страниц).

## Конфигурация

Настройки задаются переменными окружения:

| Переменная | По умолчанию | Назначение |
| --- | --- | --- |
| `CALENDAR_DB_PATH` | `var/calendar.sqlite` | Путь к файлу SQLite, каталог создаётся на старте |
| `CALENDAR_TIMEZONE` | `Europe/Moscow` | Часовой пояс приложения (IANA-идентификатор) |

Схема SQLite создаётся на старте приложения (миграция идемпотентна). Источник «сейчас» — `App\Clock`: в проде собирается как `SystemClock` из конфигурации, в тестах подменяется фиксированными часами.

## Docker

Образ собирается без генерации: `generated/`, `openapi.json` и `public/assets/sdk.gen.js` уже в репозитории, Composer ставит только prod-зависимости. Контейнер работает от нерутового пользователя `www-data`, схема SQLite создаётся на старте.

```bash
docker build -t call-calendar .
docker run --rm -p 8000:8000 call-calendar
```

Переменные окружения контейнера:

| Переменная | По умолчанию | Назначение |
| --- | --- | --- |
| `PORT` | `8000` | Порт встроенного PHP-сервера внутри контейнера |
| `CALENDAR_DB_PATH` | `/app/var/calendar.sqlite` | Путь к файлу SQLite внутри контейнера |
| `CALENDAR_TIMEZONE` | `Europe/Moscow` | Часовой пояс приложения |

Пример с другим портом и постоянным хранилищем для базы:

```bash
docker run --rm -e PORT=9000 -p 9000:9000 \
  -v calendar-data:/app/var \
  call-calendar
```

Именованный том при первом монтировании наследует владельца каталога из образа (`www-data`, uid 33). При bind-монтировании своего каталога убедитесь, что он доступен на запись этому пользователю.

## API

Контракт — `openapi.json` (порождён из TypeSpec). Запросы и ответы валидируются против него (`league/openapi-psr7-validator`), ошибки отдаются в формате `application/problem+json`. Маршруты берутся из `generated/routes.php`, обработчики — `src/Handler/`.

Примеры:

```bash
curl -X POST http://localhost:8000/api/event-types \
  -H "Content-Type: application/json" \
  -d '{"id":"consult","name":"Консультация","durationMinutes":30}'

curl http://localhost:8000/api/event-types

# Расписание приёма записей: по умолчанию пн–пт, 10:00–18:00 в поясе приложения.
curl http://localhost:8000/api/schedule

curl -X PUT http://localhost:8000/api/schedule \
  -H "Content-Type: application/json" \
  -d '{"workingDays":["mon","tue","wed","thu","fri","sat"],"dayStart":"10:00","dayEnd":"14:00"}'
```

Расписание одно на весь календарь: рабочие дни недели плюс единые часы для всех рабочих дней. Слоты предлагаются и записи принимаются только в рабочие дни; окно записи — 14 календарных дней. Уже созданные записи при смене расписания не изменяются. Изменить расписание можно и в админке — вкладка «Расписание».

## Генерация кода из контракта

Источник истины API — TypeSpec-контракт в `tsp/`. Вся генерация запускается одной командой:

```bash
npm run generate
```

Шаги цепочки (по отдельности — `gen:openapi`, `gen:php`, `gen:routes`, `gen:sdk`):

1. `npm run gen:openapi` — `tsp compile tsp`: TypeSpec → `openapi.json`;
2. `npm run gen:php` — jane: PHP-типы и нормализаторы → `generated/Model`, `generated/Normalizer`;
3. `npm run gen:routes` — `scripts/generate-routes.mjs`: таблица маршрутов из `openapi.json` → `generated/routes.php`;
4. `npm run gen:sdk` — `@hey-api/openapi-ts` и esbuild: клиентский SDK → `generated/sdk/` и `public/assets/sdk.gen.js`.

Всё в `generated/` и `public/assets/sdk.gen.js` — сгенерированный код, руками не править. Изменения вносятся в `tsp/main.tsp` (или в скрипты цепочки), затем прогоняется `npm run generate`. CI проверяет, что после генерации `git diff` пуст.

---

<details>
<summary>Автоматические тесты Хекслета</summary>

Тесты запускаются на каждый коммит. За запуск отвечает файл `.github/workflows/hexlet-check.yml` — не удаляйте и не переименовывайте ни его, ни репозиторий.

</details>

## О Хекслете

[Хекслет](https://ru.hexlet.io/) — школа программирования: авторские программы обучения с практикой, поддержкой наставников и реальными проектами, которые остаются в резюме. Этот репозиторий — один из таких проектов.
