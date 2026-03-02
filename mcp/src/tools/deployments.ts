import { z } from 'zod';
import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import type { SaturnClient } from '../client.js';

export function registerDeploymentTools(server: McpServer, client: SaturnClient): void {
    server.registerTool(
        'saturn_deploy',
        {
            title: 'Deploy Application',
            description:
                'Trigger a deployment for a Saturn application. ' +
                'Returns deployment UUID(s) that can be used with saturn_get_deployment_logs to track progress.',
            inputSchema: z.object({
                uuid: z.string().describe('Application UUID to deploy'),
                force: z
                    .boolean()
                    .optional()
                    .describe('Force rebuild without Docker cache (default: false)'),
            }),
        },
        async ({ uuid, force }) => {
            const params = new URLSearchParams({ uuid });
            if (force) params.set('force', 'true');
            const data = await client.get(`/deploy?${params.toString()}`);
            return {
                content: [{ type: 'text', text: JSON.stringify(data, null, 2) }],
            };
        },
    );

    server.registerTool(
        'saturn_get_deployment_logs',
        {
            title: 'Get Deployment Logs',
            description:
                'Fetch status and logs for a specific deployment. ' +
                'Poll this after saturn_deploy until status is "finished" or "failed".',
            inputSchema: z.object({
                deployment_uuid: z.string().describe('Deployment UUID returned by saturn_deploy'),
            }),
        },
        async ({ deployment_uuid }) => {
            const data = await client.get(`/deployments/${deployment_uuid}/logs`);
            return {
                content: [{ type: 'text', text: JSON.stringify(data, null, 2) }],
            };
        },
    );
}
