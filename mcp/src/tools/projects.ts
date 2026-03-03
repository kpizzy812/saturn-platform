import { z } from 'zod';
import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import type { SaturnClient } from '../client.js';
import { summarizeApps, summarizeServers, summarizeDatabases } from '../helpers.js';

export function registerProjectTools(server: McpServer, client: SaturnClient): void {
    server.registerTool(
        'saturn_overview',
        {
            title: 'Saturn Overview',
            description:
                'START HERE. Returns a compact snapshot of the Saturn platform: ' +
                'all applications (uuid, name, status, fqdn), ' +
                'all servers (uuid, name, ip, status), ' +
                'and all databases (uuid, name, type, status). ' +
                'Use this first to find UUIDs before calling any other tool. ' +
                'For full details on any resource, use the corresponding get_* tool. ' +
                'Hierarchy: Project → Environment → Application/Database/Service.',
            inputSchema: z.object({}),
        },
        async () => {
            const [applications, servers, databases] = await Promise.all([
                client.get<any[]>('/applications'),
                client.get<any[]>('/servers'),
                client.get<any[]>('/databases'),
            ]);
            const overview = {
                applications: summarizeApps(applications),
                servers: summarizeServers(servers),
                databases: summarizeDatabases(databases),
            };
            return { content: [{ type: 'text', text: JSON.stringify(overview, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_list_projects',
        {
            title: 'List Projects',
            description: 'List all projects in Saturn. Projects contain environments which contain applications/databases.',
            inputSchema: z.object({}),
        },
        async () => {
            const data = await client.get('/projects');
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_get_project',
        {
            title: 'Get Project',
            description: 'Get details of a project including its environments.',
            inputSchema: z.object({
                uuid: z.string().describe('Project UUID'),
            }),
        },
        async ({ uuid }) => {
            const data = await client.get(`/projects/${uuid}`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_get_project_environment',
        {
            title: 'Get Project Environment',
            description: 'Get all resources (apps, databases, services) inside a specific environment.',
            inputSchema: z.object({
                project_uuid: z.string().describe('Project UUID'),
                environment_name: z.string().describe('Environment name, e.g. "production" or "staging"'),
            }),
        },
        async ({ project_uuid, environment_name }) => {
            const data = await client.get(`/projects/${project_uuid}/${environment_name}`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );
}
