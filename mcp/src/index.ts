#!/usr/bin/env node
/**
 * Saturn Platform MCP Server
 *
 * Exposes Saturn deploy/status/logs/env operations as MCP tools
 * for AI agents (Claude, Cursor, etc.).
 *
 * Usage:
 *   npx tsx mcp/src/index.ts --url https://saturn.ac --token <your-token>
 */
import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';

import { SaturnClient, parseCliArgs } from './client.js';
import { registerApplicationTools } from './tools/applications.js';
import { registerDatabaseTools } from './tools/databases.js';
import { registerDeploymentTools } from './tools/deployments.js';
import { registerProjectTools } from './tools/projects.js';
import { registerServerTools } from './tools/servers.js';
import { registerServiceTools } from './tools/services.js';
import { registerTeamTools } from './tools/teams.js';
import { registerResourceLinkTools } from './tools/resource-links.js';

const server = new McpServer({
    name: 'saturn',
    version: '0.4.0',
});

const cliArgs = parseCliArgs();

let client: SaturnClient;
try {
    client = new SaturnClient(cliArgs);
} catch (err) {
    console.error('[saturn-mcp]', (err as Error).message);
    process.exit(1);
}

registerApplicationTools(server, client);
registerDeploymentTools(server, client);
registerServerTools(server, client);
registerProjectTools(server, client);
registerDatabaseTools(server, client);
registerServiceTools(server, client);
registerTeamTools(server, client);
registerResourceLinkTools(server, client);

async function main() {
    const transport = new StdioServerTransport();
    await server.connect(transport);
    console.error(`[saturn-mcp] v0.4.0 connected to ${cliArgs.url ?? process.env.SATURN_API_URL ?? 'saturn.ac'}`);
}

main().catch((err) => {
    console.error('[saturn-mcp] Fatal error:', err);
    process.exit(1);
});
