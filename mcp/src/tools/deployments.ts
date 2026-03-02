import { z } from 'zod';
import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import type { SaturnClient } from '../client.js';

export function registerDeploymentTools(server: McpServer, client: SaturnClient): void {
    // ── Trigger ───────────────────────────────────────────────────────────

    server.registerTool(
        'saturn_deploy',
        {
            title: 'Deploy Application',
            description:
                'Trigger a deployment for a Saturn application. ' +
                'Returns deployment UUID(s) — use saturn_get_deployment_logs to track progress.',
            inputSchema: z.object({
                uuid: z.string().describe('Application UUID to deploy'),
                force: z.boolean().optional().describe('Force rebuild without Docker cache (default: false)'),
            }),
        },
        async ({ uuid, force }) => {
            const params = new URLSearchParams({ uuid });
            if (force) params.set('force', 'true');
            const data = await client.get(`/deploy?${params.toString()}`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    // ── List & Get ────────────────────────────────────────────────────────

    server.registerTool(
        'saturn_list_deployments',
        {
            title: 'List Active Deployments',
            description: 'List all currently running or queued deployments across all applications.',
            inputSchema: z.object({}),
        },
        async () => {
            const data = await client.get('/deployments');
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_get_deployment',
        {
            title: 'Get Deployment',
            description: 'Get details of a specific deployment by UUID.',
            inputSchema: z.object({
                deployment_uuid: z.string().describe('Deployment UUID'),
            }),
        },
        async ({ deployment_uuid }) => {
            const data = await client.get(`/deployments/${deployment_uuid}`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_get_deployment_logs',
        {
            title: 'Get Deployment Logs',
            description:
                'Fetch status and build logs for a specific deployment. ' +
                'Poll until status is "finished" or "failed". Use saturn_get_application_logs for runtime logs.',
            inputSchema: z.object({
                deployment_uuid: z.string().describe('Deployment UUID returned by saturn_deploy'),
            }),
        },
        async ({ deployment_uuid }) => {
            const data = await client.get(`/deployments/${deployment_uuid}/logs`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_list_application_deployments',
        {
            title: 'List Application Deployments',
            description: 'Get deployment history for a specific application.',
            inputSchema: z.object({
                uuid: z.string().describe('Application UUID'),
            }),
        },
        async ({ uuid }) => {
            const data = await client.get(`/deployments/applications/${uuid}`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    // ── Actions ───────────────────────────────────────────────────────────

    server.registerTool(
        'saturn_cancel_deployment',
        {
            title: 'Cancel Deployment',
            description: 'Cancel a running or queued deployment.',
            inputSchema: z.object({
                deployment_uuid: z.string().describe('Deployment UUID to cancel'),
            }),
        },
        async ({ deployment_uuid }) => {
            const data = await client.post(`/deployments/${deployment_uuid}/cancel`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_approve_deployment',
        {
            title: 'Approve Deployment',
            description: 'Approve a deployment that is waiting for manual approval (production gate).',
            inputSchema: z.object({
                deployment_uuid: z.string().describe('Deployment UUID to approve'),
            }),
        },
        async ({ deployment_uuid }) => {
            const data = await client.post(`/deployments/${deployment_uuid}/approve`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    // ── AI Analysis ───────────────────────────────────────────────────────

    server.registerTool(
        'saturn_analyze_deployment',
        {
            title: 'Analyze Failed Deployment',
            description:
                'Trigger AI analysis of a failed deployment to get root cause and fix suggestions. ' +
                'Use saturn_get_deployment_analysis to read results after triggering.',
            inputSchema: z.object({
                deployment_uuid: z.string().describe('Deployment UUID (should be in failed status)'),
            }),
        },
        async ({ deployment_uuid }) => {
            const data = await client.post(`/deployments/${deployment_uuid}/analyze`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_get_deployment_analysis',
        {
            title: 'Get Deployment AI Analysis',
            description: 'Get the AI analysis results for a failed deployment.',
            inputSchema: z.object({
                deployment_uuid: z.string().describe('Deployment UUID'),
            }),
        },
        async ({ deployment_uuid }) => {
            const data = await client.get(`/deployments/${deployment_uuid}/analysis`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );
}
