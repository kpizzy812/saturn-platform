import { z } from 'zod';
import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import type { SaturnClient } from '../client.js';
import { summarizeDeployments } from '../helpers.js';

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
                'Poll until status is "finished" or "failed". Use saturn_get_application_logs for runtime logs.\n\n' +
                'PAGINATION: Logs can be large. Use these params to avoid token overflow:\n' +
                '- tail=50 — get the last 50 lines (best for diagnosing failures)\n' +
                '- errors_only=true — return only stderr/error lines\n' +
                '- offset + limit — page through all logs (default limit=200, max=500)\n' +
                '- Check "has_more" in response to know if more pages exist\n\n' +
                'RECOMMENDED WORKFLOW for failed deployments:\n' +
                '1. Call with tail=100 to see the end of the log\n' +
                '2. If not enough, call with errors_only=true to see all error lines\n' +
                '3. Use offset/limit to read earlier sections if needed',
            inputSchema: z.object({
                deployment_uuid: z.string().describe('Deployment UUID returned by saturn_deploy'),
                offset: z.number().int().min(0).optional().describe('Skip first N log entries (default: 0)'),
                limit: z.number().int().min(1).max(500).optional().describe('Max entries to return (default: 200, max: 500)'),
                tail: z.number().int().min(1).optional().describe('Return last N log entries — best for finding errors at the end of a failed build'),
                errors_only: z.boolean().optional().describe('Return only stderr/error lines to quickly find the root cause'),
            }),
        },
        async ({ deployment_uuid, offset, limit, tail, errors_only }) => {
            const params = new URLSearchParams();
            if (offset !== undefined) params.set('offset', String(offset));
            if (limit !== undefined) params.set('limit', String(limit));
            if (tail !== undefined) params.set('tail', String(tail));
            if (errors_only !== undefined) params.set('errors_only', String(errors_only));
            const query = params.toString() ? `?${params.toString()}` : '';
            const data = await client.get(`/deployments/${deployment_uuid}/logs${query}`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_list_application_deployments',
        {
            title: 'List Application Deployments',
            description:
                'Get deployment history for a specific application (compact summary, no logs). ' +
                'Use saturn_get_deployment_logs to fetch logs for a specific deployment UUID.',
            inputSchema: z.object({
                uuid: z.string().describe('Application UUID'),
                skip: z.number().int().min(0).optional().describe('Skip first N deployments (default: 0)'),
                take: z.number().int().min(1).max(50).optional().describe('Max deployments to return (default: 10)'),
            }),
        },
        async ({ uuid, skip, take }) => {
            const params = new URLSearchParams();
            if (skip !== undefined) params.set('skip', String(skip));
            if (take !== undefined) params.set('take', String(take));
            const query = params.toString() ? `?${params.toString()}` : '';
            const data = await client.get<any>(`/deployments/applications/${uuid}${query}`);
            return { content: [{ type: 'text', text: JSON.stringify(summarizeDeployments(data), null, 2) }] };
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

    server.registerTool(
        'saturn_reject_deployment',
        {
            title: 'Reject Deployment',
            description: 'Reject a deployment that is waiting for manual approval.',
            inputSchema: z.object({
                deployment_uuid: z.string().describe('Deployment UUID to reject'),
            }),
        },
        async ({ deployment_uuid }) => {
            const data = await client.post(`/deployments/${deployment_uuid}/reject`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_get_pending_approvals',
        {
            title: 'Get Pending Approvals',
            description: 'List all deployments waiting for your manual approval.',
            inputSchema: z.object({}),
        },
        async () => {
            const data = await client.get('/approvals/pending');
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

    // ── Code Review ───────────────────────────────────────────────────────

    server.registerTool(
        'saturn_trigger_code_review',
        {
            title: 'Trigger AI Code Review',
            description:
                'Trigger an AI code review for the changes in a deployment. ' +
                'Use saturn_get_code_review to fetch results after triggering.',
            inputSchema: z.object({
                deployment_uuid: z.string().describe('Deployment UUID'),
            }),
        },
        async ({ deployment_uuid }) => {
            const data = await client.post(`/deployments/${deployment_uuid}/code-review`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_get_code_review',
        {
            title: 'Get AI Code Review',
            description: 'Get the AI code review results (violations, suggestions) for a deployment.',
            inputSchema: z.object({
                deployment_uuid: z.string().describe('Deployment UUID'),
            }),
        },
        async ({ deployment_uuid }) => {
            const data = await client.get(`/deployments/${deployment_uuid}/code-review`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );
}
