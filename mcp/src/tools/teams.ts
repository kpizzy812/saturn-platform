import { z } from 'zod';
import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import type { SaturnClient } from '../client.js';

export function registerTeamTools(server: McpServer, client: SaturnClient): void {
    // ── Teams ─────────────────────────────────────────────────────────────

    server.registerTool(
        'saturn_list_teams',
        {
            title: 'List Teams',
            description: 'List all teams the current API token has access to.',
            inputSchema: z.object({}),
        },
        async () => {
            const data = await client.get('/teams');
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_get_team_members',
        {
            title: 'Get Team Members',
            description: 'List all members of a team by team ID.',
            inputSchema: z.object({
                team_id: z.number().describe('Team ID'),
            }),
        },
        async ({ team_id }) => {
            const data = await client.get(`/teams/${team_id}/members`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_get_team_activities',
        {
            title: 'Get Team Activities',
            description: 'Get recent activity log for the current team (deploys, changes, user actions).',
            inputSchema: z.object({
                page: z.number().optional().describe('Page number (default: 1)'),
            }),
        },
        async ({ page }) => {
            const params = page ? `?page=${page}` : '';
            const data = await client.get(`/teams/current/activities${params}`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    // ── SSH Keys ─────────────────────────────────────────────────────────

    server.registerTool(
        'saturn_list_ssh_keys',
        {
            title: 'List SSH Keys',
            description: 'List all SSH private keys registered in Saturn.',
            inputSchema: z.object({}),
        },
        async () => {
            const data = await client.get('/security/keys');
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_get_ssh_key',
        {
            title: 'Get SSH Key',
            description: 'Get details of a specific SSH private key by UUID.',
            inputSchema: z.object({
                uuid: z.string().describe('SSH key UUID'),
            }),
        },
        async ({ uuid }) => {
            const data = await client.get(`/security/keys/${uuid}`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_create_ssh_key',
        {
            title: 'Create SSH Key',
            description:
                'Register a new SSH private key in Saturn. ' +
                'The private_key can be provided as PEM text (-----BEGIN...) or base64-encoded.',
            inputSchema: z.object({
                name: z.string().describe('Key name'),
                description: z.string().optional().describe('Key description'),
                private_key: z.string().describe('SSH private key content (PEM format or base64-encoded)'),
            }),
        },
        async (body) => {
            const data = await client.post('/security/keys', body);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_update_ssh_key',
        {
            title: 'Update SSH Key',
            description: 'Update the name or description of an SSH private key.',
            inputSchema: z.object({
                uuid: z.string().describe('SSH key UUID'),
                name: z.string().optional().describe('New key name'),
                description: z.string().optional().describe('New description'),
            }),
        },
        async ({ uuid, ...body }) => {
            const data = await client.patch(`/security/keys/${uuid}`, body);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_delete_ssh_key',
        {
            title: 'Delete SSH Key',
            description: 'Delete an SSH private key. Will fail if the key is currently in use by a server.',
            inputSchema: z.object({
                uuid: z.string().describe('SSH key UUID'),
            }),
        },
        async ({ uuid }) => {
            const data = await client.delete(`/security/keys/${uuid}`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    // ── GitHub Apps ───────────────────────────────────────────────────────

    server.registerTool(
        'saturn_list_github_apps',
        {
            title: 'List GitHub Apps',
            description: 'List all GitHub App integrations configured in Saturn.',
            inputSchema: z.object({}),
        },
        async () => {
            const data = await client.get('/github-apps');
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_list_repositories',
        {
            title: 'List GitHub Repositories',
            description: 'List repositories accessible through a GitHub App integration.',
            inputSchema: z.object({
                github_app_id: z.number().describe('GitHub App ID'),
            }),
        },
        async ({ github_app_id }) => {
            const data = await client.get(`/github-apps/${github_app_id}/repositories`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_list_branches',
        {
            title: 'List Repository Branches',
            description: 'List branches of a specific repository accessible through a GitHub App.',
            inputSchema: z.object({
                github_app_id: z.number().describe('GitHub App ID'),
                owner: z.string().describe('Repository owner (username or organization)'),
                repo: z.string().describe('Repository name'),
            }),
        },
        async ({ github_app_id, owner, repo }) => {
            const data = await client.get(`/github-apps/${github_app_id}/repositories/${owner}/${repo}/branches`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    // ── Webhooks ──────────────────────────────────────────────────────────

    server.registerTool(
        'saturn_list_webhooks',
        {
            title: 'List Webhooks',
            description: 'List all outgoing webhooks configured for the current team.',
            inputSchema: z.object({}),
        },
        async () => {
            const data = await client.get('/webhooks');
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_create_webhook',
        {
            title: 'Create Webhook',
            description: 'Create a new outgoing webhook that notifies an external URL on Saturn events.',
            inputSchema: z.object({
                name: z.string().describe('Webhook name'),
                url: z.string().describe('Destination URL to send events to'),
                secret: z.string().optional().describe('Secret for HMAC signature verification'),
                events: z
                    .array(z.string())
                    .optional()
                    .describe('Events to subscribe to, e.g. ["deployment.started", "deployment.finished"]'),
                enabled: z.boolean().optional().describe('Enable the webhook immediately (default: true)'),
            }),
        },
        async (body) => {
            const data = await client.post('/webhooks', body);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_delete_webhook',
        {
            title: 'Delete Webhook',
            description: 'Delete an outgoing webhook.',
            inputSchema: z.object({
                uuid: z.string().describe('Webhook UUID'),
            }),
        },
        async ({ uuid }) => {
            const data = await client.delete(`/webhooks/${uuid}`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    // ── Alerts ────────────────────────────────────────────────────────────

    server.registerTool(
        'saturn_list_alerts',
        {
            title: 'List Alerts',
            description: 'List all monitoring alerts configured for the current team.',
            inputSchema: z.object({}),
        },
        async () => {
            const data = await client.get('/alerts');
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_create_alert',
        {
            title: 'Create Alert',
            description: 'Create a new monitoring alert (e.g. notify when a container goes down).',
            inputSchema: z.object({
                name: z.string().describe('Alert name'),
                type: z.string().describe('Alert type, e.g. "container_status"'),
                resource_uuid: z.string().optional().describe('UUID of the resource to monitor (application, database, etc.)'),
                threshold: z.number().optional().describe('Numeric threshold value (depends on alert type)'),
                enabled: z.boolean().optional().describe('Enable the alert immediately (default: true)'),
            }),
        },
        async (body) => {
            const data = await client.post('/alerts', body);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );
}
