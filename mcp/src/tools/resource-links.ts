import { z } from 'zod';
import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import type { SaturnClient } from '../client.js';

export function registerResourceLinkTools(server: McpServer, client: SaturnClient): void {
    server.registerTool(
        'saturn_list_resource_links',
        {
            title: 'List Resource Links',
            description:
                'List all resource links (connections) in an environment. ' +
                'Shows how applications are linked to databases and other applications, ' +
                'including auto-injected env variables.',
            inputSchema: z.object({
                environment_uuid: z.string().describe('Environment UUID'),
            }),
        },
        async ({ environment_uuid }) => {
            const data = await client.get(`/environments/${environment_uuid}/links`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_create_resource_link',
        {
            title: 'Create Resource Link',
            description:
                'Create a connection between an application and a database or another application. ' +
                'Automatically injects the connection URL as an env variable (e.g. DATABASE_URL, REDIS_URL, APP_URL). ' +
                'For app-to-app links, a bidirectional link is created automatically.',
            inputSchema: z.object({
                environment_uuid: z.string().describe('Environment UUID'),
                source_id: z.number().describe('Source Application ID (numeric, not UUID)'),
                target_type: z.enum([
                    'postgresql', 'mysql', 'mariadb', 'redis', 'keydb',
                    'dragonfly', 'mongodb', 'clickhouse', 'application',
                ]).describe('Target resource type'),
                target_id: z.number().describe('Target resource ID (numeric, not UUID)'),
                inject_as: z.string().optional().describe('Custom env variable name (e.g. "MY_DB_URL"). Auto-detected if omitted.'),
                auto_inject: z.boolean().optional().describe('Auto-inject connection URL on deploy (default: true)'),
                use_external_url: z.boolean().optional().describe('Use external FQDN instead of internal Docker URL (default: true for app-to-app)'),
            }),
        },
        async ({ environment_uuid, ...body }) => {
            const data = await client.post(`/environments/${environment_uuid}/links`, body);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_update_resource_link',
        {
            title: 'Update Resource Link',
            description: 'Update settings of an existing resource link (inject_as, auto_inject, use_external_url).',
            inputSchema: z.object({
                environment_uuid: z.string().describe('Environment UUID'),
                link_id: z.number().describe('Link ID'),
                inject_as: z.string().optional().describe('Custom env variable name'),
                auto_inject: z.boolean().optional().describe('Auto-inject connection URL on deploy'),
                use_external_url: z.boolean().optional().describe('Use external FQDN instead of internal Docker URL'),
            }),
        },
        async ({ environment_uuid, link_id, ...body }) => {
            const data = await client.patch(`/environments/${environment_uuid}/links/${link_id}`, body);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_delete_resource_link',
        {
            title: 'Delete Resource Link',
            description:
                'Delete a resource link. By default also removes the injected env variable. ' +
                'For app-to-app links, the reverse link is also deleted.',
            inputSchema: z.object({
                environment_uuid: z.string().describe('Environment UUID'),
                link_id: z.number().describe('Link ID'),
                remove_env_var: z.boolean().optional().describe('Remove the injected env variable (default: true)'),
            }),
        },
        async ({ environment_uuid, link_id, remove_env_var }) => {
            const params = new URLSearchParams();
            if (remove_env_var !== undefined) params.set('remove_env_var', String(remove_env_var));
            const qs = params.toString() ? `?${params.toString()}` : '';
            const data = await client.delete(`/environments/${environment_uuid}/links/${link_id}${qs}`);
            return { content: [{ type: 'text', text: JSON.stringify(data ?? { message: 'Link deleted.' }, null, 2) }] };
        },
    );
}
