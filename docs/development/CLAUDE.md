# saturn-Saturn - PaaS Platform (Saturn Platform Fork)

## Быстрые команды
```bash
# Development
composer install
npm install
php artisan serve                    # Local dev
php artisan migrate                  # Миграции
php artisan queue:work               # Queue worker

# Docker
docker-compose up -d

# Tests
php artisan test
```

## Архитектура
```
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   └── Livewire/        # Livewire компоненты
│   ├── Models/              # Eloquent models
│   ├── Services/            # Бизнес-логика
│   └── Jobs/                # Queue jobs
├── resources/
│   ├── views/               # Blade templates
│   └── js/                  # Frontend assets
├── database/
│   └── migrations/
├── routes/
└── config/
```

## Стек
- Backend: Laravel 10+, PHP 8.2+
- Frontend: Livewire, Alpine.js, Tailwind
- DB: PostgreSQL/MySQL
- Queue: Redis
- Deploy: Self-hosted Docker

## Git Workflow — AUTO PR
```bash
# 1. Новая ветка
git checkout -b feature/[название]

# 2. Изменения
git add .
git commit -m "feat: [описание]"

# 3. Push + PR
git push -u origin feature/[название]
gh pr create --title "feat: [описание]" --body "## Что сделано\n- ..."

# 4. Self-hosted — деплой вручную или через CI
```

## Self-hosted деплой
В отличие от других проектов, Saturn деплоится на свои сервера:
1. SSH на сервер
2. `git pull`
3. `composer install --no-dev`
4. `php artisan migrate`
5. `php artisan queue:restart`

## Что это делает
Self-hosted PaaS альтернатива Heroku/Vercel:
- Деплой приложений через SSH
- Управление Docker контейнерами
- Базы данных (PostgreSQL, MySQL, Redis, MongoDB)
- SSL сертификаты (Let's Encrypt)
- Git-based deployments

## Ключевые сервисы
- **Server Management** — подключение серверов по SSH
- **Application Deployment** — деплой из Git
- **Database Management** — создание/бэкап БД
- **SSL/TLS** — автоматические сертификаты
- **Monitoring** — health checks, logs

## Паттерны кода
- Laravel conventions
- Livewire для интерактивного UI
- Jobs для async операций
- Services для бизнес-логики

## Отличия от оригинального Saturn Platform
- Кастомизации под твои нужды
- Интеграция с твоей инфраструктурой
- Master-server архитектура

## PHP Standards
```bash
# Форматирование
./vendor/bin/pint

# Тесты
php artisan test

# Static analysis
./vendor/bin/phpstan analyse
```
