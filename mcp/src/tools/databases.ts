import { z } from 'zod';
import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import type { SaturnClient } from '../client.js';
import { summarizeDatabases } from '../helpers.js';

export function registerDatabaseTools(server: McpServer, client: SaturnClient): void {
    // ── Read ──────────────────────────────────────────────────────────────

    server.registerTool(
        'saturn_list_databases',
        {
            title: 'List Databases',
            description:
                'List all databases — PostgreSQL, MySQL, MongoDB, Redis, etc. (compact summary). ' +
                'Use saturn_get_database for full details of a specific database.',
            inputSchema: z.object({}),
        },
        async () => {
            const data = await client.get<any[]>('/databases');
            return { content: [{ type: 'text', text: JSON.stringify(summarizeDatabases(data), null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_get_database',
        {
            title: 'Get Database',
            description: 'Get details of a specific database by UUID.',
            inputSchema: z.object({
                uuid: z.string().describe('Database UUID'),
            }),
        },
        async ({ uuid }) => {
            const data = await client.get(`/databases/${uuid}`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_get_database_logs',
        {
            title: 'Get Database Logs',
            description: 'Get container logs for a running database.',
            inputSchema: z.object({
                uuid: z.string().describe('Database UUID'),
                lines: z.number().optional().describe('Number of log lines (default 100, max 10000)'),
            }),
        },
        async ({ uuid, lines }) => {
            const params = lines ? `?lines=${lines}` : '';
            const data = await client.get(`/databases/${uuid}/logs${params}`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    // ── CRUD ─────────────────────────────────────────────────────────────

    server.registerTool(
        'saturn_create_database',
        {
            title: 'Create Database',
            description:
                'Create a new database container. Supported types: postgresql, mysql, mariadb, mongodb, redis, clickhouse, dragonfly, keydb.',
            inputSchema: z.object({
                type: z
                    .enum(['postgresql', 'mysql', 'mariadb', 'mongodb', 'redis', 'clickhouse', 'dragonfly', 'keydb'])
                    .describe('Database engine type'),
                project_uuid: z.string().describe('Project UUID'),
                environment_name: z.string().describe('Environment name, e.g. "production"'),
                server_uuid: z.string().describe('Server UUID to run the database on'),
                name: z.string().optional().describe('Database name (auto-generated if omitted)'),
                description: z.string().optional().describe('Description'),
                image: z.string().optional().describe('Docker image, e.g. "postgres:16-alpine"'),
                is_public: z.boolean().optional().describe('Expose database port publicly (default: false)'),
                public_port: z.number().optional().describe('Public port number (required when is_public: true)'),
                instant_deploy: z.boolean().optional().describe('Start immediately after creation (default: false)'),
            }),
        },
        async ({ type, ...body }) => {
            const data = await client.post(`/databases/${type}`, body);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_update_database',
        {
            title: 'Update Database',
            description: 'Update settings of an existing database (name, image, public port, etc.).',
            inputSchema: z.object({
                uuid: z.string().describe('Database UUID'),
                name: z.string().optional().describe('New database name'),
                description: z.string().optional().describe('New description'),
                image: z.string().optional().describe('New Docker image'),
                is_public: z.boolean().optional().describe('Expose database port publicly'),
                public_port: z.number().optional().describe('Public port number'),
                limits_memory: z.string().optional().describe('Memory limit, e.g. "512m"'),
                limits_cpus: z.string().optional().describe('CPU limit, e.g. "0.5"'),
            }),
        },
        async ({ uuid, ...body }) => {
            const data = await client.patch(`/databases/${uuid}`, body);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_delete_database',
        {
            title: 'Delete Database',
            description: 'Delete a database container and optionally remove its data volumes.',
            inputSchema: z.object({
                uuid: z.string().describe('Database UUID'),
                delete_configurations: z.boolean().optional().describe('Delete configuration files (default: true)'),
                delete_volumes: z.boolean().optional().describe('Delete Docker volumes with all data (default: false)'),
                docker_cleanup: z.boolean().optional().describe('Run Docker cleanup after deletion (default: false)'),
            }),
        },
        async ({ uuid, ...body }) => {
            const params = new URLSearchParams();
            if (body.delete_configurations !== undefined) params.set('delete_configurations', String(body.delete_configurations));
            if (body.delete_volumes !== undefined) params.set('delete_volumes', String(body.delete_volumes));
            if (body.docker_cleanup !== undefined) params.set('docker_cleanup', String(body.docker_cleanup));
            const qs = params.toString() ? `?${params.toString()}` : '';
            const data = await client.delete(`/databases/${uuid}${qs}`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    // ── Backups ───────────────────────────────────────────────────────────

    server.registerTool(
        'saturn_list_database_backups',
        {
            title: 'List Database Backup Schedules',
            description: 'List all scheduled backup configurations for a database.',
            inputSchema: z.object({
                uuid: z.string().describe('Database UUID'),
            }),
        },
        async ({ uuid }) => {
            const data = await client.get(`/databases/${uuid}/backups`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_create_database_backup',
        {
            title: 'Create Database Backup Schedule',
            description: 'Create a new scheduled backup for a database.',
            inputSchema: z.object({
                uuid: z.string().describe('Database UUID'),
                frequency: z.string().describe('Cron expression, e.g. "0 2 * * *" (daily at 2am)'),
                save_s3: z.boolean().optional().describe('Upload backup to S3 (default: false)'),
                s3_storage_uuid: z.string().optional().describe('S3 storage UUID (required when save_s3: true)'),
                keep_last: z.number().optional().describe('Number of recent backups to keep (default: 7)'),
            }),
        },
        async ({ uuid, ...body }) => {
            const data = await client.post(`/databases/${uuid}/backups`, body);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_update_database_backup',
        {
            title: 'Update Database Backup Schedule',
            description: 'Update an existing backup schedule for a database.',
            inputSchema: z.object({
                uuid: z.string().describe('Database UUID'),
                scheduled_backup_uuid: z.string().describe('Backup schedule UUID'),
                frequency: z.string().optional().describe('New cron expression'),
                save_s3: z.boolean().optional().describe('Upload to S3'),
                keep_last: z.number().optional().describe('Number of backups to keep'),
                enabled: z.boolean().optional().describe('Enable or disable this backup schedule'),
            }),
        },
        async ({ uuid, scheduled_backup_uuid, ...body }) => {
            const data = await client.patch(`/databases/${uuid}/backups/${scheduled_backup_uuid}`, body);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_delete_database_backup',
        {
            title: 'Delete Database Backup Schedule',
            description: 'Delete a backup schedule and optionally remove stored backup files.',
            inputSchema: z.object({
                uuid: z.string().describe('Database UUID'),
                scheduled_backup_uuid: z.string().describe('Backup schedule UUID'),
            }),
        },
        async ({ uuid, scheduled_backup_uuid }) => {
            const data = await client.delete(`/databases/${uuid}/backups/${scheduled_backup_uuid}`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_list_backup_executions',
        {
            title: 'List Backup Executions',
            description: 'List all past backup executions for a given backup schedule.',
            inputSchema: z.object({
                uuid: z.string().describe('Database UUID'),
                scheduled_backup_uuid: z.string().describe('Backup schedule UUID'),
            }),
        },
        async ({ uuid, scheduled_backup_uuid }) => {
            const data = await client.get(`/databases/${uuid}/backups/${scheduled_backup_uuid}/executions`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_restore_database_backup',
        {
            title: 'Restore Database Backup',
            description: 'Restore a database from a previously created backup execution.',
            inputSchema: z.object({
                uuid: z.string().describe('Database UUID'),
                backup_uuid: z.string().describe('Backup execution UUID to restore from'),
            }),
        },
        async ({ uuid, backup_uuid }) => {
            const data = await client.post(`/databases/${uuid}/backups/${backup_uuid}/restore`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    // ── Actions ───────────────────────────────────────────────────────────

    server.registerTool(
        'saturn_start_database',
        {
            title: 'Start Database',
            description: 'Start a stopped database container.',
            inputSchema: z.object({
                uuid: z.string().describe('Database UUID'),
            }),
        },
        async ({ uuid }) => {
            const data = await client.post(`/databases/${uuid}/start`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_stop_database',
        {
            title: 'Stop Database',
            description: 'Stop a running database container.',
            inputSchema: z.object({
                uuid: z.string().describe('Database UUID'),
            }),
        },
        async ({ uuid }) => {
            const data = await client.post(`/databases/${uuid}/stop`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );

    server.registerTool(
        'saturn_restart_database',
        {
            title: 'Restart Database',
            description: 'Restart a database container.',
            inputSchema: z.object({
                uuid: z.string().describe('Database UUID'),
            }),
        },
        async ({ uuid }) => {
            const data = await client.post(`/databases/${uuid}/restart`);
            return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
        },
    );
}
