# Saturn MCP Server

Model Context Protocol (MCP) server for Saturn Platform. Lets AI agents (Claude, Cursor, etc.) trigger deploys, check logs, and inspect apps/servers without leaving the chat.

## Tools

| Tool | Description |
|------|-------------|
| `saturn_list_applications` | List all applications |
| `saturn_get_application` | Get application details by UUID |
| `saturn_deploy` | Trigger a deployment |
| `saturn_get_deployment_logs` | Fetch logs for a deployment |
| `saturn_list_servers` | List all servers |

## Setup

### 1. Install dependencies

```bash
cd mcp
npm install
npm run build
```

### 2. Get a Saturn API token

In Saturn UI → Settings → API Tokens → Create token with abilities: **read** + **deploy**.

### 3. Configure Claude Desktop

Add to `~/.claude/claude_desktop_config.json`:

```json
{
  "mcpServers": {
    "saturn": {
      "command": "node",
      "args": ["/path/to/coolify-Saturn/mcp/dist/index.js"],
      "env": {
        "SATURN_API_URL": "https://dev.saturn.ac",
        "SATURN_API_TOKEN": "your-token-here"
      }
    }
  }
}
```

Or for local dev (no build needed):

```json
{
  "mcpServers": {
    "saturn": {
      "command": "npx",
      "args": ["tsx", "/path/to/coolify-Saturn/mcp/src/index.ts"],
      "env": {
        "SATURN_API_URL": "https://dev.saturn.ac",
        "SATURN_API_TOKEN": "your-token-here"
      }
    }
  }
}
```

### 4. Configure Claude Code (CLI)

Add to `.claude/settings.json` in your project or globally:

```json
{
  "mcpServers": {
    "saturn": {
      "command": "node",
      "args": ["mcp/dist/index.js"],
      "env": {
        "SATURN_API_URL": "https://dev.saturn.ac",
        "SATURN_API_TOKEN": "your-token-here"
      }
    }
  }
}
```

## Example agent workflow

```
User: Deploy the frontend app to dev

Agent:
1. saturn_list_applications  → finds uuid=abc-123 (app: frontend)
2. saturn_deploy(uuid=abc-123) → deployment_uuid=dep-456
3. saturn_get_deployment_logs(deployment_uuid=dep-456) → status=in_progress
4. saturn_get_deployment_logs(deployment_uuid=dep-456) → status=finished ✓
```

## Environment variables

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `SATURN_API_TOKEN` | ✅ | — | API token from Saturn UI |
| `SATURN_API_URL` | ❌ | `https://dev.saturn.ac` | Saturn base URL |

## Development

```bash
cd mcp
npm install
npm run dev          # run with tsx (no build)
npm run typecheck    # type check only
npm run build        # build to dist/
```
