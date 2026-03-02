import { z } from 'zod';
import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import type { SaturnClient } from '../client.js';

export function registerServerTools(server: McpServer, client: SaturnClient): void {
    server.registerTool(
        'saturn_list_servers',
        {
            title: 'List Servers',
            description: 'List all servers registered in Saturn Platform.',
            inputSchema: z.object({}),
        },
        async () => {
            const data = await client.get('/servers');
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
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
}
