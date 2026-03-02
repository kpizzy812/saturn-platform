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
            return {
                content: [{ type: 'text', text: JSON.stringify(data, null, 2) }],
            };
        },
    );
}
