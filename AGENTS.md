# AGENTS.md

PHP 8.3 без фреймворка (роутинг — league/route, валидация — league/openapi-psr7-validator) + HTML/CSS/JS без сборки.
Контракт API — TypeSpec (`tsp/`), источник истины; артефакты даёт одна команда `npm run generate`.

## Структура
- public/ — веб-корень (index.html, api.php, assets/)
- src/ — классы бэкенда, namespace App (обработчики — src/Handler/)
- tsp/ — TypeSpec-контракт, источник истины; openapi.json — его порождение
- generated/ — только генерация (DTO, нормализаторы, таблица маршрутов, SDK); руками не править
- scripts/ — скрипты цепочки генерации
- tests/ — PHPUnit

## Команды
- Запуск: `php -S localhost:8000 -t public public/router.php`
- Генерация артефактов: `npm run generate`
- Тесты: `composer test` (PHP), `npm run test:sdk` (смоук бандла SDK, `node --test`)
- Линт: `composer lint && npm run lint`

## Конфигурация
- Переменные окружения: `CALENDAR_DB_PATH` (файл SQLite, по умолчанию `var/calendar.sqlite`), `CALENDAR_TIMEZONE` (пояс приложения, по умолчанию `Europe/Moscow`).
- `App\Kernel` собирает приложение на старте: схема SQLite, роутинг из generated/routes.php, валидация запросов и ответов против openapi.json, ошибки — problem+json.
- Источник «сейчас» — `App\Clock`: в проде `App\SystemClock` (подмена невозможна), в тестах — фиксированные часы.

## Правила
- Перед завершением задачи прогнать тесты и линтеры.
- SQL только через PDO prepared statements.
- В JS пользовательский текст выводить через textContent, не innerHTML.
- Не править вручную CHANGELOG.md и .release-please-manifest.json.
- ОС разработки: Windows/PowerShell, команды без `&&` и `mkdir -p`

## Генерация
- Цепочка: `tsp compile` → `jane-openapi generate` (DTO + нормализаторы) → `scripts/generate-routes.mjs` (таблица маршрутов из openapi.json) → `@hey-api/openapi-ts -f` (SDK) → esbuild (ESM-бандл SDK для фронта).
- `generated/` и `openapi.json` коммитятся; `generated/` исключён из phpstan / php-cs-fixer / eslint и подключён в автозагрузку composer PSR-4; правки руками запрещены.
- CI: установка Node и PHP до шага генерации, затем `npm run generate` и проверка `git diff --exit-code`.
- Версии генераторов зафиксированы точными (без `^`) в composer.json и package.json.
- operationId в контракте — camelCase (глагол + сущность); обработчик — `App\Handler\<PascalCase(operationId)>Handler` с методом `__invoke`; связь — по operationId в сгенерированной таблице маршрутов.
- В openapi.json обязателен блок `servers` (иначе SDK получает мусорный baseUrl).

## Коммиты
Conventional Commits: `type(scope): описание`.
Типы: feat, fix, docs, refactor, test, chore, ci. Breaking change: `!` после типа
или `BREAKING CHANGE:` в теле. Релизы строит release-please по этим коммитам.

## Agent skills

### Issue tracker

Issues live in this repo's GitHub Issues (via the `gh` CLI). See `docs/agents/issue-tracker.md`.

### Triage labels

Default vocabulary: `needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, `wontfix`. See `docs/agents/triage-labels.md`.

### Domain docs

Single-context: root `GLOSSARY.md` + `docs/adr/`. See `docs/agents/domain.md`.