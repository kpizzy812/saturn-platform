import { z } from 'zod';
import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import type { SaturnClient } from '../client.js';

export function registerApplicationTools(server: McpServer, client: SaturnClient): void {
    // ── Read ──────────────────────────────────────────────────────────────

    server.registerTool(
        'saturn_list_applications',
        {
            title: 'List Applications',
            description: 'List all Saturn applications accessible with the current API token.',
            inputSchema: z.object({}),
        },
        async () => {
            const data = await client.get('/applications');
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_get_application',
        {
            title: 'Get Application',
            description: 'Get full details of a Saturn application by its UUID.',
            inputSchema: z.object({
                uuid: z.string().describe('Application UUID'),
            }),
        },
        async ({ uuid }) => {
            const data = await client.get(`/applications/${uuid}`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_get_application_logs',
        {
            title: 'Get Application Logs',
            description: 'Get runtime container logs for a running application (not deployment logs).',
            inputSchema: z.object({
                uuid: z.string().describe('Application UUID'),
                lines: z.number().optional().describe('Number of log lines to return (default 100, max 10000)'),
            }),
        },
        async ({ uuid, lines }) => {
            const params = lines ? `?lines=${lines}` : '';
            const data = await client.get(`/applications/${uuid}/logs${params}`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    // ── Environment Variables ─────────────────────────────────────────────

    server.registerTool(
        'saturn_list_application_envs',
        {
            title: 'List Application Env Vars',
            description: 'List all environment variables for an application.',
            inputSchema: z.object({
                uuid: z.string().describe('Application UUID'),
            }),
        },
        async ({ uuid }) => {
            const data = await client.get(`/applications/${uuid}/envs`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_set_application_env',
        {
            title: 'Set Application Env Var',
            description: 'Create or update a single environment variable for an application.',
            inputSchema: z.object({
                uuid: z.string().describe('Application UUID'),
                key: z.string().describe('Variable name, e.g. DATABASE_URL'),
                value: z.string().describe('Variable value'),
                is_preview: z.boolean().optional().describe('Apply to PR preview deployments (default false)'),
            }),
        },
        async ({ uuid, key, value, is_preview }) => {
            const data = await client.post(`/applications/${uuid}/envs`, { key, value, is_preview });
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    // ── Actions ───────────────────────────────────────────────────────────

    server.registerTool(
        'saturn_start_application',
        {
            title: 'Start Application',
            description: 'Start a stopped Saturn application (triggers a deployment).',
            inputSchema: z.object({
                uuid: z.string().describe('Application UUID'),
            }),
        },
        async ({ uuid }) => {
            const data = await client.post(`/applications/${uuid}/start`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_stop_application',
        {
            title: 'Stop Application',
            description: 'Stop a running Saturn application.',
            inputSchema: z.object({
                uuid: z.string().describe('Application UUID'),
            }),
        },
        async ({ uuid }) => {
            const data = await client.post(`/applications/${uuid}/stop`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_restart_application',
        {
            title: 'Restart Application',
            description: 'Restart a Saturn application.',
            inputSchema: z.object({
                uuid: z.string().describe('Application UUID'),
            }),
        },
        async ({ uuid }) => {
            const data = await client.post(`/applications/${uuid}/restart`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );
}
