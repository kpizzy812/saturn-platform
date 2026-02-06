# Saturn Admin Settings - TODO

Задачи по расширению админ-панели настроек.

## Выполнено

- [x] **OAuth/SSO Settings** — Страница `/admin/settings/oauth` с 10 провайдерами (GitHub, GitLab, Google, Azure, Bitbucket, Discord, Authentik, Clerk, Infomaniak, Zitadel)
- [x] **AI Provider Configuration** — Секция в Settings: выбор провайдера (Claude/OpenAI/Ollama), API ключи, модели, кэширование
- [x] **Global S3 Storage** — Секция в Infrastructure: endpoint, bucket, region, credentials для платформенных бэкапов

## В работе

### HIGH Priority

- [ ] **Server Settings bulk management** — Централизованный overview всех серверов с их настройками (concurrent_builds, docker_cleanup, sentinel, log drain). Bulk edit для массового изменения.

- [x] **Application Global Defaults** — Глобальные дефолты для новых приложений: auto-deploy, force HTTPS, preview deployments, auto-rollback, build cache, git submodules/lfs, docker images retention.

- [ ] **SSH Key Management overview** — Админ-страница со списком всех SSH ключей по командам, где используется каждый ключ (servers, apps), fingerprints. Security audit.

### MEDIUM Priority

- [ ] **SSH/Docker/Proxy global config** — Настройки SSH (mux, timeouts, retries), Docker registry URL (private registry), default proxy type (Traefik/Caddy) для новых серверов.

- [ ] **Rate Limiting and Queue config** — API rate limit (сейчас hardcoded 200 req/min), Horizon worker configuration (processes, memory, retention), queue wait thresholds.

- [ ] **Notification Overview** — Админ-страница с обзором notification channels по всем командам: какие команды настроили Discord/Slack/Telegram/Email/Webhook.

### LOW Priority

- [ ] **Build Defaults and Deployment Policy** — Default buildpack (nixpacks/static/dockerfile), build timeout, deployment approval enforcement для production.

## Технические заметки

### Модели для изучения:
- `ServerSetting` — настройки per-server
- `ApplicationSetting` — настройки per-application
- `PrivateKey` — SSH ключи (team-scoped)
- Notification models: `EmailNotificationSettings`, `DiscordNotificationSettings`, etc.

### Конфиги (hardcoded в .env):
- `config/constants.php` — SSH tuning, Docker registry, terminal settings
- `config/horizon.php` — Queue worker settings
- `config/api.php` — Rate limiting

### Миграции созданы:
- `2026_02_05_120000_add_ai_provider_settings_to_instance_settings.php`
- `2026_02_05_130000_add_global_s3_settings_to_instance_settings.php`
