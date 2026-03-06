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

export interface DeploymentSummary {
    id: number;
    deployment_uuid: string;
    status: string;
    commit: string | null;
    commit_message: string | null;
    git_type: string | null;
    force_rebuild: boolean;
    rollback: boolean;
    is_api: boolean;
    is_webhook: boolean;
    requires_approval: boolean | null;
    approval_status: string | null;
    started_at: string | null;
    created_at: string | null;
    updated_at: string | null;
}

export function summarizeDeployment(d: any): DeploymentSummary {
    return {
        id: d.id ?? 0,
        deployment_uuid: d.deployment_uuid ?? '',
        status: d.status ?? 'unknown',
        commit: d.commit ?? null,
        commit_message: d.commit_message ?? null,
        git_type: d.git_type ?? null,
        force_rebuild: d.force_rebuild ?? false,
        rollback: d.rollback ?? false,
        is_api: d.is_api ?? false,
        is_webhook: d.is_webhook ?? false,
        requires_approval: d.requires_approval ?? null,
        approval_status: d.approval_status ?? null,
        started_at: d.started_at ?? null,
        created_at: d.created_at ?? null,
        updated_at: d.updated_at ?? null,
    };
}

export function summarizeDeployments(data: any): { count: number; deployments: DeploymentSummary[] } {
    const deployments = Array.isArray(data?.deployments) ? data.deployments.map(summarizeDeployment) : [];
    return { count: data?.count ?? deployments.length, deployments };
}

export interface ServiceSummary {
    uuid: string;
    name: string;
    description: string | null;
    status: string | null;
    environment_id: number | null;
    server: string | null;
    created_at: string | null;
    updated_at: string | null;
}

export function summarizeService(svc: any): ServiceSummary {
    return {
        uuid: svc.uuid ?? '',
        name: svc.name ?? '',
        description: svc.description ?? null,
        status: svc.status ?? null,
        environment_id: svc.environment_id ?? null,
        server: svc.destination?.server?.name ?? null,
        created_at: svc.created_at ?? null,
        updated_at: svc.updated_at ?? null,
    };
}

export function summarizeServices(services: any[]): ServiceSummary[] {
    return Array.isArray(services) ? services.map(summarizeService) : [];
}
