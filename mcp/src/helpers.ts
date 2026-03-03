/**
 * Response summarizers for Saturn MCP tools.
 *
 * Full API responses can be 100K+ chars (nested server settings, backup configs, etc.).
 * List/overview tools return compact summaries; detail tools (get_*) keep full data.
 */

/* eslint-disable @typescript-eslint/no-explicit-any */

export interface AppSummary {
    uuid: string;
    name: string;
    description: string | null;
    status: string;
    fqdn: string | null;
    git_repository: string | null;
    git_branch: string | null;
    docker_image: string | null;
    build_pack: string | null;
    environment_id: number | null;
    server: string | null;
    created_at: string | null;
    updated_at: string | null;
}

export interface ServerSummary {
    uuid: string;
    name: string;
    description: string | null;
    ip: string;
    port: number;
    user: string;
    is_reachable: boolean;
    is_usable: boolean;
    is_localhost: boolean;
    proxy_type: string | null;
    proxy_status: string | null;
    sentinel_enabled: boolean;
    wildcard_domain: string | null;
}

export interface DatabaseSummary {
    uuid: string;
    name: string;
    description: string | null;
    status: string;
    database_type: string;
    image: string;
    is_public: boolean;
    environment_id: number | null;
    server: string | null;
    backups_count: number;
    last_backup_status: string | null;
    created_at: string | null;
    updated_at: string | null;
}

export function summarizeApp(app: any): AppSummary {
    return {
        uuid: app.uuid ?? '',
        name: app.name ?? app.description ?? '',
        description: app.description ?? null,
        status: app.status ?? 'unknown',
        fqdn: app.fqdn ?? null,
        git_repository: app.git_repository ?? null,
        git_branch: app.git_branch ?? null,
        docker_image: app.docker_image ?? app.image ?? null,
        build_pack: app.build_pack ?? null,
        environment_id: app.environment_id ?? null,
        server: app.destination?.server?.name ?? null,
        created_at: app.created_at ?? null,
        updated_at: app.updated_at ?? null,
    };
}

export function summarizeServer(srv: any): ServerSummary {
    return {
        uuid: srv.uuid ?? '',
        name: srv.name ?? '',
        description: srv.description ?? null,
        ip: srv.ip ?? '',
        port: srv.port ?? 22,
        user: srv.user ?? 'root',
        is_reachable: srv.is_reachable ?? srv.settings?.is_reachable ?? false,
        is_usable: srv.is_usable ?? srv.settings?.is_usable ?? false,
        is_localhost: srv.is_localhost ?? false,
        proxy_type: srv.proxy?.type ?? null,
        proxy_status: srv.proxy?.status ?? null,
        sentinel_enabled: srv.settings?.is_sentinel_enabled ?? false,
        wildcard_domain: srv.settings?.wildcard_domain ?? null,
    };
}

export function summarizeDatabase(db: any): DatabaseSummary {
    const backups = Array.isArray(db.backup_configs) ? db.backup_configs : [];
    const latestLog = backups[0]?.latest_log ?? null;

    return {
        uuid: db.uuid ?? '',
        name: db.name ?? '',
        description: db.description ?? null,
        status: db.status ?? 'unknown',
        database_type: db.database_type ?? '',
        image: db.image ?? '',
        is_public: db.is_public ?? false,
        environment_id: db.environment_id ?? null,
        server: db.destination?.server?.name ?? null,
        backups_count: backups.length,
        last_backup_status: latestLog?.status ?? null,
        created_at: db.created_at ?? null,
        updated_at: db.updated_at ?? null,
    };
}

export function summarizeApps(apps: any[]): AppSummary[] {
    return Array.isArray(apps) ? apps.map(summarizeApp) : [];
}

export function summarizeServers(servers: any[]): ServerSummary[] {
    return Array.isArray(servers) ? servers.map(summarizeServer) : [];
}

export function summarizeDatabases(databases: any[]): DatabaseSummary[] {
    return Array.isArray(databases) ? databases.map(summarizeDatabase) : [];
}
