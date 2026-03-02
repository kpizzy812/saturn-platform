import { z } from 'zod';
import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import type { SaturnClient } from '../client.js';

export function registerDatabaseTools(server: McpServer, client: SaturnClient): void {
    // ── Read ──────────────────────────────────────────────────────────────

    server.registerTool(
        'saturn_list_databases',
        {
            title: 'List Databases',
            description: 'List all databases (PostgreSQL, MySQL, MongoDB, Redis, etc.) in Saturn.',
            inputSchema: z.object({}),
        },
        async () => {
            const data = await client.get('/databases');
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_get_database',
        {
            title: 'Get Database',
            description: 'Get details of a specific database by UUID.',
            inputSchema: z.object({
                uuid: z.string().describe('Database UUID'),
            }),
        },
        async ({ uuid }) => {
            const data = await client.get(`/databases/${uuid}`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_get_database_logs',
        {
            title: 'Get Database Logs',
            description: 'Get container logs for a running database.',
            inputSchema: z.object({
                uuid: z.string().describe('Database UUID'),
                lines: z.number().optional().describe('Number of log lines (default 100, max 10000)'),
            }),
        },
        async ({ uuid, lines }) => {
            const params = lines ? `?lines=${lines}` : '';
            const data = await client.get(`/databases/${uuid}/logs${params}`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    // ── Actions ───────────────────────────────────────────────────────────

    server.registerTool(
        'saturn_start_database',
        {
            title: 'Start Database',
            description: 'Start a stopped database container.',
            inputSchema: z.object({
                uuid: z.string().describe('Database UUID'),
            }),
        },
        async ({ uuid }) => {
            const data = await client.post(`/databases/${uuid}/start`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_stop_database',
        {
            title: 'Stop Database',
            description: 'Stop a running database container.',
            inputSchema: z.object({
                uuid: z.string().describe('Database UUID'),
            }),
        },
        async ({ uuid }) => {
            const data = await client.post(`/databases/${uuid}/stop`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_restart_database',
        {
            title: 'Restart Database',
            description: 'Restart a database container.',
            inputSchema: z.object({
                uuid: z.string().describe('Database UUID'),
            }),
        },
        async ({ uuid }) => {
            const data = await client.post(`/databases/${uuid}/restart`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );
}
