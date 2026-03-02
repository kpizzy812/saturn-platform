# Saturn MCP Server

Allows AI agents (Claude Code, Cursor, Claude Desktop) to deploy apps, check logs and status — directly from chat.

## Tools

| Tool | Description |
|------|-------------|
| `saturn_list_applications` | List all applications |
| `saturn_get_application` | Get application details by UUID |
| `saturn_deploy` | Trigger a deployment |
| `saturn_get_deployment_logs` | Fetch deployment logs (poll until finished/failed) |
| `saturn_list_servers` | List all servers |

---

## Step 1 — Get your API token

Go to **Saturn UI → Settings → Tokens → Create token**

- Dev: `https://dev.saturn.ac/settings/tokens`
- UAT: `https://uat.saturn.ac/settings/tokens`
- Prod: `https://saturn.ac/settings/tokens`

Select abilities: **read** + **deploy**. Copy the token — it's shown only once.

---

## Step 2 — Install in your AI tool

### Claude Code (CLI)

Run once per environment from the project root:

```bash
# Dev
claude mcp add saturn-dev \
  -e SATURN_API_URL=https://dev.saturn.ac \
  -e SATURN_API_TOKEN=<your-dev-token> \
  -- npx tsx mcp/src/index.ts

# UAT
claude mcp add saturn-uat \
  -e SATURN_API_URL=https://uat.saturn.ac \
  -e SATURN_API_TOKEN=<your-uat-token> \
  -- npx tsx mcp/src/index.ts

# Production
claude mcp add saturn-prod \
  -e SATURN_API_URL=https://saturn.ac \
  -e SATURN_API_TOKEN=<your-prod-token> \
  -- npx tsx mcp/src/index.ts
```

Verify: `claude mcp list`

---

### Cursor

Open **Settings → MCP** and add:

```json
{
  "saturn-dev": {
    "command": "npx",
    "args": ["tsx", "/absolute/path/to/mcp/src/index.ts"],
    "env": {
      "SATURN_API_URL": "https://dev.saturn.ac",
      "SATURN_API_TOKEN": "<your-dev-token>"
    }
  }
}
```

---

### Claude Desktop

Edit `~/.claude/claude_desktop_config.json`:

```json
{
  "mcpServers": {
    "saturn-dev": {
      "command": "npx",
      "args": ["tsx", "/absolute/path/to/mcp/src/index.ts"],
      "env": {
        "SATURN_API_URL": "https://dev.saturn.ac",
        "SATURN_API_TOKEN": "<your-dev-token>"
      }
    }
  }
}
```

Then restart Claude Desktop.

---

## How agents use it

```
User: Deploy frontend to dev

Agent:
1. saturn_list_applications → finds uuid for "frontend"
2. saturn_deploy(uuid) → returns deployment_uuid
3. saturn_get_deployment_logs(deployment_uuid) → polls until status=finished
```

---

## Authorization

- Token belongs to **you** — agents see only your team's resources
- All actions are logged under your user in Saturn
- `read` = view apps/servers/logs, `deploy` = trigger deployments
- Production token: consider creating read-only (`read` only) for safety

---

## Environment variables

| Variable | Required | Default |
|----------|:--------:|---------|
| `SATURN_API_TOKEN` | ✅ | — |
| `SATURN_API_URL` | ❌ | `https://dev.saturn.ac` |

---

## Local development

```bash
make mcp-install   # npm install
make mcp-build     # build to dist/
make mcp-dev       # run with tsx (no build)
```
