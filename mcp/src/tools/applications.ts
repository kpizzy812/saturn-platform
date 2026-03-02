import { z } from 'zod';
import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import type { SaturnClient } from '../client.js';

export function registerApplicationTools(server: McpServer, client: SaturnClient): void {
    server.registerTool(
        'saturn_list_applications',
        {
            title: 'List Applications',
            description: 'List all Saturn applications accessible with the current API token.',
            inputSchema: z.object({}),
        },
        async () => {
            const data = await client.get('/applications');
            return {
                content: [{ type: 'text', text: JSON.stringify(data, null, 2) }],
            };
        },
    );

    server.registerTool(
        'saturn_get_application',
        {
            title: 'Get Application',
            description: 'Get full details of a Saturn application by its UUID.',
            inputSchema: z.object({
                uuid: z.string().describe('Application UUID (e.g. from saturn_list_applications)'),
            }),
        },
        async ({ uuid }) => {
            const data = await client.get(`/applications/${uuid}`);
            return {
                content: [{ type: 'text', text: JSON.stringify(data, null, 2) }],
            };
        },
    );
}
