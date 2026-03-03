# Saturn MCP Server

Allows AI agents (Claude Code, Cursor, Claude Desktop) to deploy apps, check logs and manage infrastructure — directly from chat.

## Quick Start

### 1. Create an API token

Go to **Saturn UI → Settings → Tokens → Create token**, select abilities (**read** + **deploy**), copy the token.

### 2. Add to your AI tool

#### Claude Code

```bash
claude mcp add saturn -- npx --yes tsx mcp/src/index.ts --url https://saturn.ac --token YOUR_TOKEN
```

That's it. Verify with `claude mcp list`.

**Multiple environments:**

```bash
claude mcp add saturn-dev  -- npx --yes tsx mcp/src/index.ts --url https://dev.saturn.ac --token DEV_TOKEN
claude mcp add saturn-prod -- npx --yes tsx mcp/src/index.ts --url https://saturn.ac    --token PROD_TOKEN
```

#### Cursor

Open **Settings → MCP** and add:

```json
{
  "saturn": {
    "command": "npx",
    "args": ["--yes", "tsx", "mcp/src/index.ts", "--url", "https://saturn.ac", "--token", "YOUR_TOKEN"]
  }
}
```

#### Claude Desktop

Edit `~/.claude/claude_desktop_config.json`:

```json
{
  "mcpServers": {
    "saturn": {
      "command": "npx",
      "args": ["--yes", "tsx", "/path/to/mcp/src/index.ts", "--url", "https://saturn.ac", "--token", "YOUR_TOKEN"]
    }
  }
}
```

---

## Tools (30)

| Domain | Tools |
|--------|-------|
| Applications | `saturn_list_applications`, `saturn_get_application`, `saturn_get_application_logs`, `saturn_list_application_envs`, `saturn_set_application_env`, `saturn_start_application`, `saturn_stop_application`, `saturn_restart_application` |
| Deployments | `saturn_deploy`, `saturn_list_deployments`, `saturn_get_deployment`, `saturn_get_deployment_logs`, `saturn_list_application_deployments`, `saturn_cancel_deployment`, `saturn_approve_deployment`, `saturn_analyze_deployment`, `saturn_get_deployment_analysis` |
| Servers | `saturn_list_servers`, `saturn_get_server`, `saturn_validate_server`, `saturn_get_server_metrics` |
| Projects | `saturn_overview`, `saturn_list_projects`, `saturn_get_project`, `saturn_get_project_environment` |
| Databases | `saturn_list_databases`, `saturn_get_database`, `saturn_get_database_logs`, `saturn_start_database`, `saturn_stop_database`, `saturn_restart_database` |

**Start with `saturn_overview`** — it returns all apps, servers, and databases with their UUIDs.

---

## How agents use it

```
User: Deploy frontend to dev

Agent:
1. saturn_overview → finds all apps with UUIDs
2. saturn_deploy(uuid) → triggers deployment, gets deployment_uuid
3. saturn_get_deployment_logs(deployment_uuid) → polls until finished
```

---

## Authorization

- Token belongs to **you** — agents see only your team's resources
- All actions are logged under your user in Saturn
- `read` = view apps/servers/logs, `deploy` = trigger deployments
- For production: consider read-only token (`read` ability only)

---

## Configuration priority

Token and URL are resolved in order (first wins):

1. **CLI args**: `--token` and `--url`
2. **Environment variables**: `SATURN_API_TOKEN` and `SATURN_API_URL`

Default URL: `https://saturn.ac`

---

## Local development

```bash
make mcp-install   # npm install
make mcp-build     # build to dist/
make mcp-dev       # run with tsx (no build)
```
