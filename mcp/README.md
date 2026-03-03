# Saturn MCP Server

Allows AI agents (Claude Code, Cursor, Claude Desktop) to deploy apps, check logs and manage infrastructure — directly from chat.

## Quick Start

### 1. Create an API token

Go to **Saturn UI → Settings → MCP / AI Tools** — create a token and copy the ready-made install command for your tool.

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

## Tools (96)

| Domain | Tools |
|--------|-------|
| Applications | list, get, logs, create, update, delete, envs (list/set/delete/bulk), start, stop, restart, rollback, rollback-events |
| Deployments | deploy, list, get, logs, list-by-app, cancel, approve, reject, pending-approvals, analyze, get-analysis, code-review, get-code-review |
| Servers | list, get, validate, metrics, create, update, delete, reboot, domains, resources, hetzner-locations, hetzner-types, hetzner-create |
| Projects | overview, list, get, environment, create, update, delete, list-envs, create-env, delete-env |
| Databases | list, get, logs, create (8 types), update, delete, start, stop, restart, backups CRUD, restore, backup executions |
| Services | list, get, logs, create, update, delete, envs (list/set/delete/bulk), start, stop, restart, healthcheck |
| Teams | list-teams, members, activities, SSH keys CRUD, GitHub Apps, repositories, branches, webhooks CRUD, alerts CRUD |

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
