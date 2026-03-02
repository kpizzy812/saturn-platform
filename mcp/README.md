# Saturn MCP Server

Model Context Protocol (MCP) server для Saturn Platform. Позволяет AI-агентам (Claude Code, Claude Desktop, Cursor и др.) деплоить приложения, смотреть логи и проверять статус — прямо из чата.

## Инструменты (Tools)

| Tool | Описание |
|------|----------|
| `saturn_list_applications` | Список всех приложений |
| `saturn_get_application` | Детали приложения по UUID |
| `saturn_deploy` | Запустить деплой |
| `saturn_get_deployment_logs` | Логи деплоя (poll до finished/failed) |
| `saturn_list_servers` | Список серверов |

## Авторизация

- Каждый участник команды создаёт **свой** токен: Saturn UI → Settings → API Tokens → Create
- Abilities: `read` + `deploy` (для деплоя), только `read` (для мониторинга)
- Токен привязан к **команде** — видит только ресурсы своей команды
- Все действия логируются в Saturn с именем токена

## Окружения

Один и тот же MCP-сервер может работать с любым окружением через `SATURN_API_URL`:

| Окружение | URL |
|-----------|-----|
| **dev** | `https://dev.saturn.ac` |
| **staging/UAT** | `https://uat.saturn.ac` |
| **production** | `https://saturn.ac` |

Рекомендуется зарегистрировать **три** отдельных MCP-сервера (saturn-dev, saturn-uat, saturn-prod), чтобы агент мог явно выбирать окружение.

## Установка для команды

### 1. Получить код и зависимости

```bash
git pull                  # mcp/ уже в репозитории
cd mcp && npm install
```

### 2. Создать API-токены

Для каждого окружения создай **отдельный токен** в Saturn UI:
- `dev.saturn.ac` → токен для dev
- `uat.saturn.ac` → токен для staging
- `saturn.ac` → токен для prod (можно без `deploy`, только `read`)

### 3. Добавить env-переменные в шелл

В `~/.zshrc` или `~/.bashrc`:

```bash
export SATURN_TOKEN_DEV="1|abcdef..."
export SATURN_TOKEN_UAT="2|ghijkl..."
export SATURN_TOKEN_PROD="3|mnopqr..."
```

Затем `source ~/.zshrc`.

### 4. Добавить серверы в `.mcp.json` проекта

Файл `.mcp.json` в корне проекта **не коммитится** (gitignored) — каждый заполняет свой.
Добавь saturn-серверы к уже существующим записям:

```json
{
  "mcpServers": {
    "saturn-dev": {
      "command": "npx",
      "args": ["--yes", "tsx", "mcp/src/index.ts"],
      "env": {
        "SATURN_API_URL": "https://dev.saturn.ac",
        "SATURN_API_TOKEN": "${SATURN_TOKEN_DEV}"
      }
    },
    "saturn-uat": {
      "command": "npx",
      "args": ["--yes", "tsx", "mcp/src/index.ts"],
      "env": {
        "SATURN_API_URL": "https://uat.saturn.ac",
        "SATURN_API_TOKEN": "${SATURN_TOKEN_UAT}"
      }
    },
    "saturn-prod": {
      "command": "npx",
      "args": ["--yes", "tsx", "mcp/src/index.ts"],
      "env": {
        "SATURN_API_URL": "https://saturn.ac",
        "SATURN_API_TOKEN": "${SATURN_TOKEN_PROD}"
      }
    }
  }
}
```

`${VAR}` автоматически берётся из твоего шелла — токен не хранится в файле.

### 5. Разрешить серверы в Claude Code

Claude Code спросит при первом запуске — нажать **Allow** для каждого сервера.
Или добавить в `.claude/settings.json`:
```json
{
  "enableAllProjectMcpServers": true
}
```

## Как агенты используют MCP

```
User: Задеплой frontend в dev

Agent (claude-dev):
1. saturn-dev: saturn_list_applications
   → [{ uuid: "abc-123", name: "frontend" }, ...]
2. saturn-dev: saturn_deploy(uuid="abc-123")
   → { deployment_uuid: "dep-456", status: "queued" }
3. saturn-dev: saturn_get_deployment_logs(deployment_uuid="dep-456")
   → { status: "in_progress", logs: [...] }
4. saturn-dev: saturn_get_deployment_logs(deployment_uuid="dep-456")
   → { status: "finished" } ✓
```

```
User: Проверь что на prod всё в порядке

Agent:
1. saturn-prod: saturn_list_servers     → серверы, статус
2. saturn-prod: saturn_list_applications → приложения, статус
```

## Переменные окружения

| Переменная | Обязательная | По умолчанию | Описание |
|------------|:---:|---|---|
| `SATURN_API_TOKEN` | ✅ | — | API токен из Saturn UI |
| `SATURN_API_URL` | ❌ | `https://dev.saturn.ac` | Base URL окружения |

## Разработка

```bash
cd mcp
npm install
npm run dev          # запуск через tsx (без сборки)
npm run typecheck    # проверка типов
npm run build        # сборка в dist/
```

Или через Makefile из корня проекта:
```bash
make mcp-install
make mcp-build
make mcp-dev
```
