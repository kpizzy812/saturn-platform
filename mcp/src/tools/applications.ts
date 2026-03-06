import { z } from 'zod';
import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import type { SaturnClient } from '../client.js';
import { summarizeApps } from '../helpers.js';

export function registerApplicationTools(server: McpServer, client: SaturnClient): void {
    // ── Read ──────────────────────────────────────────────────────────────

    server.registerTool(
        'saturn_list_applications',
        {
            title: 'List Applications',
            description:
                'List all Saturn applications (compact summary). ' +
                'Use saturn_get_application for full details of a specific app.',
            inputSchema: z.object({}),
        },
        async () => {
            const data = await client.get<any[]>('/applications');
            return { content: [{ type: 'text', text: JSON.stringify(summarizeApps(data), null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_get_application',
        {
            title: 'Get Application',
            description: 'Get full details of a Saturn application by its UUID.',
            inputSchema: z.object({
                uuid: z.string().describe('Application UUID'),
            }),
        },
        async ({ uuid }) => {
            const data = await client.get(`/applications/${uuid}`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_get_application_logs',
        {
            title: 'Get Application Logs',
            description: 'Get runtime container logs for a running application (not deployment logs).',
            inputSchema: z.object({
                uuid: z.string().describe('Application UUID'),
                lines: z.number().optional().describe('Number of log lines to return (default 100, max 10000)'),
            }),
        },
        async ({ uuid, lines }) => {
            const params = lines ? `?lines=${lines}` : '';
            const data = await client.get(`/applications/${uuid}/logs${params}`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    // ── Environment Variables ─────────────────────────────────────────────

    server.registerTool(
        'saturn_list_application_envs',
        {
            title: 'List Application Env Vars',
            description: 'List all environment variables for an application.',
            inputSchema: z.object({
                uuid: z.string().describe('Application UUID'),
            }),
        },
        async ({ uuid }) => {
            const data = await client.get(`/applications/${uuid}/envs`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_set_application_env',
        {
            title: 'Set Application Env Var',
            description: 'Create or update a single environment variable for an application.',
            inputSchema: z.object({
                uuid: z.string().describe('Application UUID'),
                key: z.string().describe('Variable name, e.g. DATABASE_URL'),
                value: z.string().describe('Variable value'),
                is_preview: z.boolean().optional().describe('Apply to PR preview deployments (default false)'),
            }),
        },
        async ({ uuid, key, value, is_preview }) => {
            const data = await client.post(`/applications/${uuid}/envs`, { key, value, is_preview });
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    // ── CRUD ─────────────────────────────────────────────────────────────

    server.registerTool(
        'saturn_create_application',
        {
            title: 'Create Application',
            description:
                'Create a new application in Saturn. ' +
                'Use type to specify the source: "public" (public git), "dockerfile" (custom Dockerfile), ' +
                '"dockerimage" (Docker image), "dockercompose" (Docker Compose), ' +
                '"private-github-app" (private via GitHub App), "private-deploy-key" (private via deploy key).',
            inputSchema: z.object({
                type: z
                    .enum(['public', 'dockerfile', 'dockerimage', 'dockercompose', 'private-github-app', 'private-deploy-key'])
                    .describe('Application source type'),
                project_uuid: z.string().describe('Project UUID'),
                environment_name: z.string().describe('Environment name, e.g. "production"'),
                server_uuid: z.string().describe('Server UUID to deploy on'),
                name: z.string().optional().describe('Application name (auto-generated if omitted)'),
                description: z.string().optional().describe('Application description'),
                git_repository: z.string().optional().describe('Git repository URL (for git-based types)'),
                git_branch: z.string().optional().describe('Git branch (default: main)'),
                build_pack: z.string().optional().describe('Build pack: railpack (default, auto-detect), nixpacks (legacy), dockerfile, static, dockercompose'),
                ports_exposes: z.string().optional().describe('Exposed ports, e.g. "3000" or "3000,8080"'),
                docker_image: z.string().optional().describe('Docker image (for dockerimage type), e.g. "nginx:latest"'),
                docker_compose_raw: z.string().optional().describe('Base64-encoded docker-compose.yml content (for dockercompose type)'),
                dockerfile: z.string().optional().describe('Base64-encoded Dockerfile content (for dockerfile type)'),
                instant_deploy: z.boolean().optional().describe('Start deployment immediately after creation (default: false)'),
            }),
        },
        async ({ type, ...body }) => {
            const data = await client.post(`/applications/${type}`, body);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_update_application',
        {
            title: 'Update Application',
            description: 'Update settings of an existing application (name, git branch, build pack, env variables, etc.).',
            inputSchema: z.object({
                uuid: z.string().describe('Application UUID'),
                name: z.string().optional().describe('New application name'),
                description: z.string().optional().describe('New description'),
                git_repository: z.string().optional().describe('Git repository URL'),
                git_branch: z.string().optional().describe('Git branch'),
                build_pack: z.string().optional().describe('Build pack: railpack (default), nixpacks (legacy), dockerfile, static, dockercompose'),
                ports_exposes: z.string().optional().describe('Exposed ports'),
                fqdn: z.string().optional().describe('Custom domain(s), comma-separated'),
                install_command: z.string().optional().describe('Install command'),
                build_command: z.string().optional().describe('Build command'),
                start_command: z.string().optional().describe('Start command'),
                health_check_enabled: z.boolean().optional().describe('Enable health check'),
                health_check_path: z.string().optional().describe('Health check path, e.g. "/health"'),
                limits_memory: z.string().optional().describe('Memory limit, e.g. "512m"'),
                limits_cpus: z.string().optional().describe('CPU limit, e.g. "0.5"'),
            }),
        },
        async ({ uuid, ...body }) => {
            const data = await client.patch(`/applications/${uuid}`, body);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_delete_application',
        {
            title: 'Delete Application',
            description: 'Delete an application and optionally remove its Docker containers and volumes.',
            inputSchema: z.object({
                uuid: z.string().describe('Application UUID'),
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
            const data = await client.delete(`/applications/${uuid}${qs}`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    // ── Environment Variables (extended) ─────────────────────────────────

    server.registerTool(
        'saturn_delete_application_env',
        {
            title: 'Delete Application Env Var',
            description: 'Delete a specific environment variable from an application by its UUID.',
            inputSchema: z.object({
                uuid: z.string().describe('Application UUID'),
                env_uuid: z.string().describe('Environment variable UUID'),
            }),
        },
        async ({ uuid, env_uuid }) => {
            const data = await client.delete(`/applications/${uuid}/envs/${env_uuid}`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_bulk_update_application_envs',
        {
            title: 'Bulk Update Application Env Vars',
            description: 'Create or update multiple environment variables for an application at once.',
            inputSchema: z.object({
                uuid: z.string().describe('Application UUID'),
                envs: z
                    .array(
                        z.object({
                            key: z.string().describe('Variable name'),
                            value: z.string().describe('Variable value'),
                            is_preview: z.boolean().optional().describe('Apply to preview deployments'),
                        }),
                    )
                    .describe('Array of environment variables to set'),
            }),
        },
        async ({ uuid, envs }) => {
            const data = await client.patch(`/applications/${uuid}/envs/bulk`, { data: envs });
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    // ── Rollback ──────────────────────────────────────────────────────────

    server.registerTool(
        'saturn_get_rollback_events',
        {
            title: 'Get Rollback Events',
            description: 'List available rollback points for an application (previous successful deployments).',
            inputSchema: z.object({
                uuid: z.string().describe('Application UUID'),
            }),
        },
        async ({ uuid }) => {
            const data = await client.get(`/applications/${uuid}/rollback-events`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_rollback',
        {
            title: 'Rollback Application',
            description:
                'Roll back an application to a specific previous deployment. ' +
                'Use saturn_get_rollback_events to list available rollback points and get deploymentUuid.',
            inputSchema: z.object({
                uuid: z.string().describe('Application UUID'),
                deployment_uuid: z.string().describe('Target deployment UUID to roll back to'),
            }),
        },
        async ({ uuid, deployment_uuid }) => {
            const data = await client.post(`/applications/${uuid}/rollback/${deployment_uuid}`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    // ── Actions ───────────────────────────────────────────────────────────

    server.registerTool(
        'saturn_start_application',
        {
            title: 'Start Application',
            description: 'Start a stopped Saturn application (triggers a deployment).',
            inputSchema: z.object({
                uuid: z.string().describe('Application UUID'),
            }),
        },
        async ({ uuid }) => {
            const data = await client.post(`/applications/${uuid}/start`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_stop_application',
        {
            title: 'Stop Application',
            description: 'Stop a running Saturn application.',
            inputSchema: z.object({
                uuid: z.string().describe('Application UUID'),
            }),
        },
        async ({ uuid }) => {
            const data = await client.post(`/applications/${uuid}/stop`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_restart_application',
        {
            title: 'Restart Application',
            description: 'Restart a Saturn application.',
            inputSchema: z.object({
                uuid: z.string().describe('Application UUID'),
            }),
        },
        async ({ uuid }) => {
            const data = await client.post(`/applications/${uuid}/restart`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    // ── saturn.yaml Generation ────────────────────────────────────────────

    server.registerTool(
        'saturn_generate_yaml',
        {
            title: 'Generate saturn.yaml',
            description:
                'Analyze a git repository and generate a ready-to-use saturn.yaml file. ' +
                'Saturn clones the repo, detects the tech stack, databases, ports, healthchecks and env vars, ' +
                'then produces a saturn.yaml you can commit to the repository root. ' +
                'The file will be auto-synced on every subsequent deploy. ' +
                'Use this before creating an application to get the correct configuration.',
            inputSchema: z.object({
                git_repository: z.string().describe('Git repository URL, e.g. https://github.com/owner/repo'),
                git_branch: z.string().optional().describe('Branch to analyze (default: main)'),
                github_app_id: z.number().optional().describe('GitHub App ID for private repositories'),
                private_key_id: z.number().optional().describe('Deploy key ID for private repositories'),
            }),
        },
        async ({ git_repository, git_branch, github_app_id, private_key_id }) => {
            const body: Record<string, unknown> = { git_repository };
            if (git_branch) body.git_branch = git_branch;
            if (github_app_id) body.github_app_id = github_app_id;
            if (private_key_id) body.private_key_id = private_key_id;

            const data = await client.post<{ success: boolean; yaml: string; analysis: unknown }>('/git/generate-yaml', body);

            if (!data.success) {
                return { content: [{ type: 'text', text: `Failed to generate saturn.yaml: ${(data as any).error}` }] };
            }

            const analysisInfo = JSON.stringify(data.analysis, null, 2);
            const output = [
                '## saturn.yaml generated successfully',
                '',
                'Commit this file to the root of your repository.',
                'Saturn will auto-sync on every deploy.',
                '',
                '### Detected resources',
                '```json',
                analysisInfo,
                '```',
                '',
                '### saturn.yaml',
                '```yaml',
                data.yaml,
                '```',
            ].join('\n');

            return { content: [{ type: 'text', text: output }] };
        },
    );
}
