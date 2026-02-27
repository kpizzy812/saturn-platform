# 🚀 Saturn Platform - Быстрый старт на VPS

## Первоначальная установка на сервер

### 1. Подключение к серверу

```bash
ssh root@YOUR_SERVER_IP
```

### 2. Клонирование репозитория

```bash
cd /root
git clone https://github.com/YOUR_ORG/coolify-Saturn.git
cd coolify-Saturn
git checkout dev  # или нужная ветка
```

### 3. Настройка окружения

```bash
# Создать директорию для данных (путь зависит от окружения: dev/staging/production)
mkdir -p /data/saturn/dev/source

# Скопировать example .env
cp deploy/environments/dev/.env.example /data/saturn/dev/source/.env

# Отредактировать .env
nano /data/saturn/dev/source/.env
```

**Обязательные настройки в .env:**

```env
APP_NAME="Saturn Platform"
APP_ENV=production
APP_URL=http://YOUR_SERVER_IP:8000

DB_DATABASE=saturn
DB_USERNAME=saturn
DB_PASSWORD=STRONG_PASSWORD_HERE

REDIS_PASSWORD=STRONG_PASSWORD_HERE

PUSHER_APP_ID=your-app-id
PUSHER_APP_KEY=your-app-key
PUSHER_APP_SECRET=your-app-secret
```

### 4. Первый деплой

```bash
cd /root/coolify-Saturn/deploy/scripts
chmod +x *.sh
# SATURN_ENV обязателен: dev | staging | production
SATURN_ENV=dev ./deploy.sh
```

Деплой займет 2-5 минут. Скрипт выполнит:
- Проверку Docker
- Pull образов
- Запуск всех сервисов
- Миграции БД
- Health check

### 5. Проверка работы

```bash
# Проверить статус контейнеров
docker ps

# Проверить здоровье (порт не экспозится на хост — только через docker exec)
docker exec saturn-dev curl -sf http://127.0.0.1:8080/api/health

# Открыть в браузере (через Traefik)
# https://dev.saturn.ac
```

---

## Ежедневное использование

### Запуск панели управления

```bash
cd /root/coolify-Saturn/deploy/scripts
./saturn-ctl.sh
```

**Или создайте алиас (рекомендуется):**

```bash
# Добавить в ~/.bashrc или ~/.zshrc
echo 'alias saturn="cd /root/coolify-Saturn/deploy/scripts && ./saturn-ctl.sh"' >> ~/.bashrc
source ~/.bashrc

# Теперь можно использовать просто:
saturn
```

### Частые задачи

#### Посмотреть логи

```bash
./saturn-ctl.sh logs
# Или через алиас:
saturn-logs  # (если настроили)
```

#### Деплой новой версии

```bash
cd /root/coolify-Saturn
git pull origin dev
cd deploy/scripts
SATURN_ENV=dev ./deploy.sh
```

#### Перезапуск приложения

```bash
./saturn-ctl.sh restart
# Или напрямую (имя контейнера включает окружение):
docker restart saturn-dev     # dev
docker restart saturn-staging # staging
docker restart saturn-production # production
```

#### Создать бэкап БД

```bash
./saturn-ctl.sh
# Выбрать: 5 (Database) -> 4 (Create Backup)
```

#### Очистить кэши

```bash
./saturn-ctl.sh
# Выбрать: 4 (Build & Cache) -> 6 (Clear + Rebuild All)
```

---

## Структура команд saturn-ctl.sh

### Главное меню

```
1) Deploy & Restart       - Деплой и управление
2) View Logs             - Просмотр логов
3) Service Control       - Управление сервисами
4) Build & Cache         - Сборка и кэши
5) Database Operations   - Работа с БД
6) System Information    - Системная информация
0) Exit                  - Выход
```

### Быстрые команды (без меню)

```bash
./saturn-ctl.sh logs      # Открыть меню логов
./saturn-ctl.sh deploy    # Запустить деплой
./saturn-ctl.sh status    # Показать статус
./saturn-ctl.sh restart   # Перезапустить все
```

---

## Полезные алиасы

Добавьте в `~/.bashrc` или `~/.zshrc`:

```bash
# Saturn Platform
alias saturn='cd /root/coolify-Saturn/deploy/scripts && ./saturn-ctl.sh'
alias saturn-logs='cd /root/coolify-Saturn/deploy/scripts && ./saturn-ctl.sh logs'
alias saturn-deploy='cd /root/coolify-Saturn/deploy/scripts && ./deploy.sh'
alias saturn-status='cd /root/coolify-Saturn/deploy/scripts && ./saturn-ctl.sh status'

# Прямой доступ к контейнерам (для dev-окружения)
alias saturn-shell='docker exec -it saturn-dev sh'
alias saturn-db='docker exec -it saturn-db-dev psql -U saturn -d saturn'
alias saturn-artisan='docker exec saturn-dev php artisan'

# Логи напрямую
alias saturn-app-logs='docker logs -f --tail=1000 saturn-dev'
alias saturn-db-logs='docker logs -f --tail=1000 saturn-db-dev'
```

После добавления:

```bash
source ~/.bashrc  # или source ~/.zshrc
```

---

## Мониторинг

### Проверка здоровья

```bash
# Через docker exec (порт не экспозится на хост напрямую)
docker exec saturn-dev curl -sf http://127.0.0.1:8080/api/health

# Или через домен (если Traefik настроен)
curl https://dev.saturn.ac/api/health
```

Ответ должен быть: `{"status":"healthy"}`

### Статус всех контейнеров

```bash
docker ps --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"
```

### Ресурсы контейнеров

```bash
docker stats --no-stream
```

### Логи с фильтром ошибок

```bash
docker logs saturn-dev 2>&1 | grep -i error | tail -50
```

---

## Автоматизация

### Cron для автоматических бэкапов

```bash
# Открыть crontab
crontab -e

# Добавить (бэкап каждый день в 3:00)
0 3 * * * docker exec saturn-db pg_dump -U saturn -d saturn > /data/saturn/backups/auto_$(date +\%Y\%m\%d).sql

# Очистка старых бэкапов (оставить последние 30)
0 4 * * * cd /data/saturn/backups && ls -t auto_*.sql | tail -n +31 | xargs -r rm
```

### Systemd service для автозапуска

Создать `/etc/systemd/system/saturn-dev.service` (замените `dev` на нужное окружение):

```ini
[Unit]
Description=Saturn Platform (dev)
Requires=docker.service
After=docker.service

[Service]
Type=oneshot
RemainAfterExit=yes
WorkingDirectory=/root/saturn
Environment=SATURN_ENV=dev
ExecStart=/bin/bash deploy/scripts/deploy.sh
ExecStop=/usr/bin/docker compose -f docker-compose.env.yml \
  -p saturn-dev \
  --env-file /data/saturn/dev/source/.env \
  down

[Install]
WantedBy=multi-user.target
```

Активировать:

```bash
systemctl daemon-reload
systemctl enable saturn
systemctl start saturn
```

---

## Troubleshooting

### Проблема: Контейнер не запускается

```bash
# Проверить логи (замените dev на нужное окружение)
docker logs saturn-dev

# Проверить .env файл
cat /data/saturn/dev/source/.env

# Попробовать пересоздать контейнеры
cd ~/saturn
SATURN_ENV=dev docker compose -f docker-compose.env.yml \
  --env-file /data/saturn/dev/source/.env \
  -p saturn-dev down
SATURN_ENV=dev ./deploy/scripts/deploy.sh
```

### Проблема: Ошибка соединения с БД

```bash
# Проверить что контейнер БД работает
docker ps | grep saturn-db-dev

# Проверить логи БД
docker logs saturn-db-dev

# Проверить подключение через PgBouncer
docker exec saturn-pgbouncer-dev pg_isready -h localhost -p 5432
```

### Проблема: Не хватает места на диске

```bash
# Проверить использование
df -h

# Очистить Docker
docker system prune -a

# Удалить старые бэкапы
cd /data/saturn/backups
ls -t *.sql | tail -n +10 | xargs rm
```

### Проблема: Медленная работа

```bash
# Проверить ресурсы
docker stats

# Проверить логи на ошибки
docker logs saturn-dev 2>&1 | grep -i error

# Очистить кэши Laravel
docker exec saturn-dev php artisan cache:clear
docker exec saturn-dev php artisan config:clear
```

---

## Обновление до новой версии

```bash
# 1. Создать бэкап
cd /root/coolify-Saturn/deploy/scripts
./saturn-ctl.sh
# Выбрать: 5 -> 4

# 2. Pull новой версии
cd /root/coolify-Saturn
git pull origin dev

# 3. Деплой
cd deploy/scripts
./deploy.sh

# 4. Проверить
curl http://localhost:8000/api/health
```

---

## Откат к предыдущей версии

```bash
cd ~/saturn/deploy/scripts
# Восстанавливает БД из последнего pre-deploy бэкапа
# + перезапускает стек с предыдущим образом (сохранён в /data/saturn/{env}/backups/.previous_image)
SATURN_ENV=dev ./deploy.sh --rollback
```

Или через меню:

```bash
./saturn-ctl.sh
# Выбрать: 1 -> 4 (Rollback)
```

---

## Контакты и поддержка

- 📖 Полная документация: [README.md](scripts/README.md)
- 🏗️ Архитектура: [CLAUDE.md](../CLAUDE.md)
- 🐛 Issues: GitHub Issues

---

**Важно:** Регулярно создавайте бэкапы БД! Автоматические бэкапы создаются при каждом деплое в `/data/saturn/backups/`.
