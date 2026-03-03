import { z } from 'zod';
import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import type { SaturnClient } from '../client.js';
import { summarizeServices } from '../helpers.js';

export function registerServiceTools(server: McpServer, client: SaturnClient): void {
    // ── Read ──────────────────────────────────────────────────────────────

    server.registerTool(
        'saturn_list_services',
        {
            title: 'List Services',
            description:
                'List all Docker Compose services (compact summary). ' +
                'Use saturn_get_service for full details of a specific service.',
            inputSchema: z.object({}),
        },
        async () => {
            const data = await client.get<any[]>('/services');
            return { content: [{ type: 'text', text: JSON.stringify(summarizeServices(data), null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_get_service',
        {
            title: 'Get Service',
            description: 'Get full details of a Docker Compose service by its UUID.',
            inputSchema: z.object({
                uuid: z.string().describe('Service UUID'),
            }),
        },
        async ({ uuid }) => {
            const data = await client.get(`/services/${uuid}`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_get_service_logs',
        {
            title: 'Get Service Logs',
            description: 'Get runtime container logs for a running Docker Compose service.',
            inputSchema: z.object({
                uuid: z.string().describe('Service UUID'),
                lines: z.number().optional().describe('Number of log lines to return (default 100, max 10000)'),
            }),
        },
        async ({ uuid, lines }) => {
            const params = lines ? `?lines=${lines}` : '';
            const data = await client.get(`/services/${uuid}/logs${params}`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    // ── CRUD ─────────────────────────────────────────────────────────────

    server.registerTool(
        'saturn_create_service',
        {
            title: 'Create Service',
            description:
                'Create a new Docker Compose service stack. ' +
                'Provide docker_compose_raw as a base64-encoded docker-compose.yml content.',
            inputSchema: z.object({
                project_uuid: z.string().describe('Project UUID'),
                environment_name: z.string().describe('Environment name, e.g. "production"'),
                server_uuid: z.string().describe('Server UUID to deploy on'),
                name: z.string().optional().describe('Service name (auto-generated if omitted)'),
                description: z.string().optional().describe('Service description'),
                docker_compose_raw: z.string().optional().describe('Base64-encoded docker-compose.yml content'),
                instant_deploy: z.boolean().optional().describe('Start immediately after creation (default: false)'),
            }),
        },
        async (body) => {
            const data = await client.post('/services', body);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_update_service',
        {
            title: 'Update Service',
            description: 'Update a Docker Compose service settings.',
            inputSchema: z.object({
                uuid: z.string().describe('Service UUID'),
                name: z.string().optional().describe('New service name'),
                description: z.string().optional().describe('New description'),
                docker_compose_raw: z.string().optional().describe('New base64-encoded docker-compose.yml content'),
            }),
        },
        async ({ uuid, ...body }) => {
            const data = await client.patch(`/services/${uuid}`, body);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_delete_service',
        {
            title: 'Delete Service',
            description: 'Delete a Docker Compose service and optionally remove its volumes.',
            inputSchema: z.object({
                uuid: z.string().describe('Service UUID'),
                delete_configurations: z.boolean().optional().describe('Delete configuration files (default: true)'),
                delete_volumes: z.boolean().optional().describe('Delete Docker volumes (default: false)'),
                docker_cleanup: z.boolean().optional().describe('Run Docker cleanup after deletion (default: false)'),
            }),
        },
        async ({ uuid, ...body }) => {
            const params = new URLSearchParams();
            if (body.delete_configurations !== undefined) params.set('delete_configurations', String(body.delete_configurations));
            if (body.delete_volumes !== undefined) params.set('delete_volumes', String(body.delete_volumes));
            if (body.docker_cleanup !== undefined) params.set('docker_cleanup', String(body.docker_cleanup));
            const qs = params.toString() ? `?${params.toString()}` : '';
            const data = await client.delete(`/services/${uuid}${qs}`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    // ── Environment Variables ─────────────────────────────────────────────

    server.registerTool(
        'saturn_list_service_envs',
        {
            title: 'List Service Env Vars',
            description: 'List all environment variables for a Docker Compose service.',
            inputSchema: z.object({
                uuid: z.string().describe('Service UUID'),
            }),
        },
        async ({ uuid }) => {
            const data = await client.get(`/services/${uuid}/envs`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_set_service_env',
        {
            title: 'Set Service Env Var',
            description: 'Create or update a single environment variable for a Docker Compose service.',
            inputSchema: z.object({
                uuid: z.string().describe('Service UUID'),
                key: z.string().describe('Variable name, e.g. DATABASE_URL'),
                value: z.string().describe('Variable value'),
            }),
        },
        async ({ uuid, key, value }) => {
            const data = await client.post(`/services/${uuid}/envs`, { key, value });
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_delete_service_env',
        {
            title: 'Delete Service Env Var',
            description: 'Delete a specific environment variable from a Docker Compose service.',
            inputSchema: z.object({
                uuid: z.string().describe('Service UUID'),
                env_uuid: z.string().describe('Environment variable UUID'),
            }),
        },
        async ({ uuid, env_uuid }) => {
            const data = await client.delete(`/services/${uuid}/envs/${env_uuid}`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_bulk_update_service_envs',
        {
            title: 'Bulk Update Service Env Vars',
            description: 'Create or update multiple environment variables for a Docker Compose service at once.',
            inputSchema: z.object({
                uuid: z.string().describe('Service UUID'),
                envs: z
                    .array(
                        z.object({
                            key: z.string().describe('Variable name'),
                            value: z.string().describe('Variable value'),
                        }),
                    )
                    .describe('Array of environment variables to set'),
            }),
        },
        async ({ uuid, envs }) => {
            const data = await client.patch(`/services/${uuid}/envs/bulk`, { data: envs });
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    // ── Actions ───────────────────────────────────────────────────────────

    server.registerTool(
        'saturn_start_service',
        {
            title: 'Start Service',
            description: 'Start a stopped Docker Compose service.',
            inputSchema: z.object({
                uuid: z.string().describe('Service UUID'),
            }),
        },
        async ({ uuid }) => {
            const data = await client.post(`/services/${uuid}/start`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_stop_service',
        {
            title: 'Stop Service',
            description: 'Stop a running Docker Compose service.',
            inputSchema: z.object({
                uuid: z.string().describe('Service UUID'),
            }),
        },
        async ({ uuid }) => {
            const data = await client.post(`/services/${uuid}/stop`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_restart_service',
        {
            title: 'Restart Service',
            description: 'Restart a Docker Compose service.',
            inputSchema: z.object({
                uuid: z.string().describe('Service UUID'),
            }),
        },
        async ({ uuid }) => {
            const data = await client.post(`/services/${uuid}/restart`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_get_service_healthcheck',
        {
            title: 'Get Service Healthcheck',
            description: 'Get the health check configuration and current status for a Docker Compose service.',
            inputSchema: z.object({
                uuid: z.string().describe('Service UUID'),
            }),
        },
        async ({ uuid }) => {
            const data = await client.get(`/services/${uuid}/healthcheck`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );
}
