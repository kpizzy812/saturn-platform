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

    // ── CRUD — Projects ───────────────────────────────────────────────────

    server.registerTool(
        'saturn_create_project',
        {
            title: 'Create Project',
            description: 'Create a new project in Saturn. Projects group environments and their resources.',
            inputSchema: z.object({
                name: z.string().describe('Project name'),
                description: z.string().optional().describe('Project description'),
            }),
        },
        async (body) => {
            const data = await client.post('/projects', body);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_update_project',
        {
            title: 'Update Project',
            description: 'Update a project name or description.',
            inputSchema: z.object({
                uuid: z.string().describe('Project UUID'),
                name: z.string().optional().describe('New project name'),
                description: z.string().optional().describe('New description'),
            }),
        },
        async ({ uuid, ...body }) => {
            const data = await client.patch(`/projects/${uuid}`, body);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_delete_project',
        {
            title: 'Delete Project',
            description: 'Delete a project. The project must have no resources before it can be deleted.',
            inputSchema: z.object({
                uuid: z.string().describe('Project UUID'),
            }),
        },
        async ({ uuid }) => {
            const data = await client.delete(`/projects/${uuid}`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    // ── CRUD — Environments ───────────────────────────────────────────────

    server.registerTool(
        'saturn_list_environments',
        {
            title: 'List Environments',
            description: 'List all environments within a project.',
            inputSchema: z.object({
                project_uuid: z.string().describe('Project UUID'),
            }),
        },
        async ({ project_uuid }) => {
            const data = await client.get(`/projects/${project_uuid}/environments`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_create_environment',
        {
            title: 'Create Environment',
            description: 'Create a new environment within a project (e.g. "staging", "qa").',
            inputSchema: z.object({
                project_uuid: z.string().describe('Project UUID'),
                name: z.string().describe('Environment name'),
                description: z.string().optional().describe('Environment description'),
            }),
        },
        async ({ project_uuid, ...body }) => {
            const data = await client.post(`/projects/${project_uuid}/environments`, body);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_delete_environment',
        {
            title: 'Delete Environment',
            description: 'Delete an environment from a project. The environment must have no resources.',
            inputSchema: z.object({
                project_uuid: z.string().describe('Project UUID'),
                environment_name: z.string().describe('Environment name to delete'),
            }),
        },
        async ({ project_uuid, environment_name }) => {
            const data = await client.delete(`/projects/${project_uuid}/environments/${environment_name}`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );
}
