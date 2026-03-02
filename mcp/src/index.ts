#!/usr/bin/env node
/**
 * Saturn Platform MCP Server
 *
 * Exposes Saturn deploy/status/logs operations as MCP tools
 * for AI agents (Claude, Cursor, etc.).
 *
 * Configuration via environment variables:
 *   SATURN_API_URL   — e.g. https://dev.saturn.ac
 *   SATURN_API_TOKEN — API token with `deploy` + `read` abilities
 */
import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';

import { SaturnClient } from './client.js';
import { registerApplicationTools } from './tools/applications.js';
import { registerDeploymentTools } from './tools/deployments.js';
import { registerServerTools } from './tools/servers.js';

const server = new McpServer({
    name: 'saturn',
    version: '0.1.0',
});

let client: SaturnClient;
try {
    client = new SaturnClient();
} catch (err) {
    console.error('[saturn-mcp] Failed to initialize client:', err);
    process.exit(1);
}

registerApplicationTools(server, client);
registerDeploymentTools(server, client);
registerServerTools(server, client);

async function main() {
    const transport = new StdioServerTransport();
    await server.connect(transport);
    console.error('[saturn-mcp] MCP server running on stdio');
}

main().catch((err) => {
    console.error('[saturn-mcp] Fatal error:', err);
    process.exit(1);
});
