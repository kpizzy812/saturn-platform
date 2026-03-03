import { z } from 'zod';
import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import type { SaturnClient } from '../client.js';
import { summarizeServers } from '../helpers.js';

export function registerServerTools(server: McpServer, client: SaturnClient): void {
    server.registerTool(
        'saturn_list_servers',
        {
            title: 'List Servers',
            description:
                'List all servers registered in Saturn Platform (compact summary). ' +
                'Use saturn_get_server for full details of a specific server.',
            inputSchema: z.object({}),
        },
        async () => {
            const data = await client.get<any[]>('/servers');
            return { content: [{ type: 'text', text: JSON.stringify(summarizeServers(data), null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_get_server',
        {
            title: 'Get Server',
            description: 'Get full details of a server by UUID.',
            inputSchema: z.object({
                uuid: z.string().describe('Server UUID'),
            }),
        },
        async ({ uuid }) => {
            const data = await client.get(`/servers/${uuid}`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_validate_server',
        {
            title: 'Validate Server',
            description: 'Check SSH connectivity and Docker availability on a server.',
            inputSchema: z.object({
                uuid: z.string().describe('Server UUID'),
            }),
        },
        async ({ uuid }) => {
            const data = await client.get(`/servers/${uuid}/validate`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_get_server_metrics',
        {
            title: 'Get Server Metrics',
            description: 'Get real-time CPU, memory and disk metrics from Sentinel agent on a server.',
            inputSchema: z.object({
                uuid: z.string().describe('Server UUID'),
            }),
        },
        async ({ uuid }) => {
            const data = await client.get(`/servers/${uuid}/sentinel/metrics`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_get_server_domains',
        {
            title: 'Get Server Domains',
            description: 'List all domains (FQDNs) used by applications running on a server.',
            inputSchema: z.object({
                uuid: z.string().describe('Server UUID'),
            }),
        },
        async ({ uuid }) => {
            const data = await client.get(`/servers/${uuid}/domains`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_get_server_resources',
        {
            title: 'Get Server Resources',
            description: 'List all applications, databases and services running on a server.',
            inputSchema: z.object({
                uuid: z.string().describe('Server UUID'),
            }),
        },
        async ({ uuid }) => {
            const data = await client.get(`/servers/${uuid}/resources`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    // ── CRUD ─────────────────────────────────────────────────────────────

    server.registerTool(
        'saturn_create_server',
        {
            title: 'Create Server',
            description: 'Register a new server in Saturn by providing SSH connection details.',
            inputSchema: z.object({
                name: z.string().optional().describe('Server name (auto-generated if omitted)'),
                description: z.string().optional().describe('Server description'),
                ip: z.string().describe('Server IP address or hostname'),
                port: z.number().optional().describe('SSH port (default: 22)'),
                user: z.string().optional().describe('SSH user (default: root)'),
                private_key_uuid: z.string().describe('UUID of the SSH private key to use'),
                proxy_type: z.enum(['traefik', 'caddy', 'nginx', 'none']).optional().describe('Proxy type to use'),
                instant_validate: z.boolean().optional().describe('Validate SSH connection immediately after creation (default: false)'),
            }),
        },
        async (body) => {
            const data = await client.post('/servers', body);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_update_server',
        {
            title: 'Update Server',
            description: 'Update server settings such as name, description, or proxy type.',
            inputSchema: z.object({
                uuid: z.string().describe('Server UUID'),
                name: z.string().optional().describe('New server name'),
                description: z.string().optional().describe('New description'),
                ip: z.string().optional().describe('New IP address'),
                port: z.number().optional().describe('New SSH port'),
                user: z.string().optional().describe('New SSH user'),
                private_key_uuid: z.string().optional().describe('New SSH private key UUID'),
                proxy_type: z.enum(['traefik', 'caddy', 'nginx', 'none']).optional().describe('New proxy type'),
            }),
        },
        async ({ uuid, ...body }) => {
            const data = await client.patch(`/servers/${uuid}`, body);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_delete_server',
        {
            title: 'Delete Server',
            description: 'Remove a server from Saturn. Cannot delete a server that has running resources.',
            inputSchema: z.object({
                uuid: z.string().describe('Server UUID'),
            }),
        },
        async ({ uuid }) => {
            const data = await client.delete(`/servers/${uuid}`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_reboot_server',
        {
            title: 'Reboot Server',
            description: 'Send a reboot command to the server via SSH.',
            inputSchema: z.object({
                uuid: z.string().describe('Server UUID'),
            }),
        },
        async ({ uuid }) => {
            const data = await client.post(`/servers/${uuid}/reboot`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    // ── Hetzner Cloud ─────────────────────────────────────────────────────

    server.registerTool(
        'saturn_hetzner_locations',
        {
            title: 'List Hetzner Locations',
            description: 'List available Hetzner Cloud data center locations.',
            inputSchema: z.object({}),
        },
        async () => {
            const data = await client.get('/hetzner/locations');
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_hetzner_server_types',
        {
            title: 'List Hetzner Server Types',
            description: 'List available Hetzner Cloud server types (CPU, RAM, price).',
            inputSchema: z.object({}),
        },
        async () => {
            const data = await client.get('/hetzner/server-types');
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_hetzner_create_server',
        {
            title: 'Create Hetzner Server',
            description:
                'Provision a new server on Hetzner Cloud and register it in Saturn. ' +
                'Requires a Hetzner Cloud token configured in Saturn. ' +
                'Use saturn_hetzner_locations and saturn_hetzner_server_types to get valid values.',
            inputSchema: z.object({
                cloud_token_uuid: z.string().describe('UUID of the Hetzner cloud provider token'),
                name: z.string().optional().describe('Server name'),
                server_type: z.string().describe('Hetzner server type, e.g. "cx22"'),
                location: z.string().describe('Hetzner location, e.g. "nbg1"'),
                image: z.string().optional().describe('OS image, e.g. "ubuntu-24.04" (default: ubuntu-24.04)'),
                private_key_uuid: z.string().optional().describe('SSH key UUID to install on the server'),
            }),
        },
        async (body) => {
            const data = await client.post('/servers/hetzner', body);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );
}
