// Core models matching Laravel backend
export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export interface Team {
    id: number;
    name: string;
    personal_team: boolean;
    created_at: string;
    updated_at: string;
}

export interface Server {
    id: number;
    uuid: string;
    name: string;
    description: string | null;
    ip: string;
    port: number;
    user: string;
    is_reachable: boolean;
    is_usable: boolean;
    is_localhost: boolean;
    settings: ServerSettings | null;
    created_at: string;
    updated_at: string;
}

export interface ServerSettings {
    id: number;
    server_id: number;
    is_build_server: boolean;
    concurrent_builds: number;
}

export interface Project {
    id: number;
    uuid: string;
    name: string;
    description: string | null;
    team_id: number;
    environments: Environment[];
    created_at: string;
    updated_at: string;
}

export interface Environment {
    id: number;
    uuid: string;
    name: string;
    project_id: number;
    applications: Application[];
    databases: StandaloneDatabase[];
    services: Service[];
    created_at: string;
    updated_at: string;
}

export interface Destination {
    id: number;
    uuid: string;
    name: string;
    server_id: number;
    server?: Server;
}

export interface Application {
    id: number;
    uuid: string;
    name: string;
    description: string | null;
    fqdn: string | null;
    repository_project_id: number | null;
    git_repository: string | null;
    git_branch: string;
    build_pack: 'nixpacks' | 'dockerfile' | 'dockercompose' | 'dockerimage';
    status: ApplicationStatus;
    environment_id: number;
    destination_id: number;
    destination?: Destination;
    created_at: string;
    updated_at: string;
}

export type ApplicationStatus =
    | 'running'
    | 'stopped'
    | 'building'
    | 'deploying'
    | 'failed'
    | 'exited';

export interface DatabaseConnectionDetails {
    internal_host: string;
    external_host: string;
    port: string;
    public_port: number | null;
    username: string | null;
    password: string | null;
    database: string | null;
}

export interface StandaloneDatabase {
    id: number;
    uuid: string;
    name: string;
    description: string | null;
    database_type: DatabaseType;
    status: string;
    environment_id: number;
    destination_id?: number;
    destination?: Destination;
    // Connection URLs (appended by backend)
    internal_db_url?: string;
    external_db_url?: string;
    // Connection details (from controller)
    connection?: DatabaseConnectionDetails;
    // PostgreSQL specific
    postgres_user?: string;
    postgres_password?: string;
    postgres_db?: string;
    // MySQL/MariaDB specific
    mysql_user?: string;
    mysql_password?: string;
    mysql_database?: string;
    mysql_root_password?: string;
    // MongoDB specific
    mongo_initdb_root_username?: string;
    mongo_initdb_root_password?: string;
    mongo_initdb_database?: string;
    // Redis/KeyDB/Dragonfly specific
    redis_password?: string;
    keydb_password?: string;
    dragonfly_password?: string;
    // ClickHouse specific
    clickhouse_admin_user?: string;
    clickhouse_admin_password?: string;
    // Public port for external access
    public_port?: number;
    created_at: string;
    updated_at: string;
}

export type DatabaseType =
    | 'postgresql'
    | 'mysql'
    | 'mariadb'
    | 'mongodb'
    | 'redis'
    | 'keydb'
    | 'dragonfly'
    | 'clickhouse';

export interface Service {
    id: number;
    uuid: string;
    name: string;
    description: string | null;
    docker_compose_raw: string;
    environment_id: number;
    destination_id: number;
    created_at: string;
    updated_at: string;
}

export interface Deployment {
    id: number;
    uuid: string;
    deployment_uuid?: string;
    application_id: number;
    status: DeploymentStatus;
    commit: string | null;
    commit_message: string | null;
    created_at: string;
    updated_at: string;
    started_at?: string;
    finished_at?: string;
}

export type DeploymentStatus =
    | 'queued'
    | 'in_progress'
    | 'finished'
    | 'failed'
    | 'cancelled';

export type DeploymentTrigger = 'push' | 'manual' | 'rollback' | 'scheduled';

// Build step for deployment logs
export interface BuildStep {
    id: number;
    name: string;
    status: 'pending' | 'running' | 'success' | 'failed';
    duration: string;
    logs: string[];
    startTime?: string;
    endTime?: string;
}

// Notification types
export type NotificationType =
    | 'deployment_success'
    | 'deployment_failure'
    | 'team_invite'
    | 'billing_alert'
    | 'security_alert'
    | 'info';

export interface Notification {
    id: string;
    type: NotificationType;
    title: string;
    description: string;
    timestamp: string;
    isRead: boolean;
    actionUrl?: string;
}

// Activity Log types
export type ActivityAction =
    | 'deployment_started'
    | 'deployment_completed'
    | 'deployment_failed'
    | 'settings_updated'
    | 'team_member_added'
    | 'team_member_removed'
    | 'database_created'
    | 'database_deleted'
    | 'server_connected'
    | 'server_disconnected'
    | 'application_started'
    | 'application_stopped'
    | 'application_restarted'
    | 'environment_variable_updated';

export interface ActivityLog {
    id: string;
    action: ActivityAction;
    description: string;
    user: {
        name: string;
        email: string;
        avatar?: string;
    };
    resource?: {
        type: 'project' | 'application' | 'database' | 'server' | 'team';
        name: string;
        id: string;
    };
    timestamp: string;
}

// Domain and SSL types
export type DomainStatus = 'active' | 'pending' | 'failed' | 'verifying';
export type SSLStatus = 'active' | 'pending' | 'expired' | 'expiring_soon' | 'failed';
export type VerificationMethod = 'dns' | 'http';
export type RedirectType = 301 | 302 | 307 | 308;

export interface Domain {
    id: string;
    domain: string;
    status: DomainStatus;
    ssl_status: SSLStatus;
    service_id: string;
    service_name: string;
    service_type: 'application' | 'database' | 'service';
    verification_method: VerificationMethod;
    verified_at: string | null;
    redirect_to_www: boolean;
    redirect_to_https: boolean;
    ssl_certificate_id: string | null;
    dns_records: DnsRecord[];
    created_at: string;
    updated_at: string;
}

export interface DnsRecord {
    type: 'A' | 'AAAA' | 'CNAME';
    name: string;
    value: string;
    ttl?: number;
}

export interface SSLCertificate {
    id: string;
    domain: string;
    domains: string[]; // All domains covered (including wildcards)
    issuer: string;
    issued_at: string;
    expires_at: string;
    auto_renew: boolean;
    type: 'letsencrypt' | 'custom';
    certificate_chain?: string;
    status: SSLStatus;
    days_until_expiry: number;
    created_at: string;
    updated_at: string;
}

export interface DomainRedirect {
    id: string;
    source_pattern: string;
    target_url: string;
    redirect_type: RedirectType;
    is_wildcard: boolean;
    preserve_path: boolean;
    preserve_query: boolean;
    domain_id: string;
    created_at: string;
    updated_at: string;
}

// Cron Job types
export type CronJobStatus = 'enabled' | 'disabled' | 'running' | 'failed';

export interface CronJob {
    id: number;
    uuid: string;
    name: string;
    description: string | null;
    command: string;
    schedule: string; // Cron expression
    timezone: string;
    timeout: number; // seconds
    retries: number;
    notify_on_failure: boolean;
    status: CronJobStatus;
    last_run: string | null;
    next_run: string | null;
    success_count: number;
    failure_count: number;
    average_duration: number; // seconds
    created_at: string;
    updated_at: string;
}

export interface CronJobExecution {
    id: number;
    cron_job_id: number;
    status: 'running' | 'success' | 'failed';
    started_at: string;
    finished_at: string | null;
    duration: number | null; // seconds
    exit_code: number | null;
    output: string | null;
    error: string | null;
}

// Scheduled Task types
export type ScheduledTaskStatus = 'pending' | 'running' | 'completed' | 'failed' | 'cancelled';

export interface ScheduledTask {
    id: number;
    uuid: string;
    name: string;
    description: string | null;
    command: string;
    scheduled_for: string;
    timezone: string;
    timeout: number; // seconds
    retries: number;
    status: ScheduledTaskStatus;
    executed_at: string | null;
    finished_at: string | null;
    duration: number | null; // seconds
    exit_code: number | null;
    output: string | null;
    error: string | null;
    created_at: string;
    updated_at: string;
}

// Volume and Storage types
export type VolumeStatus = 'active' | 'creating' | 'deleting' | 'error';
export type StorageClass = 'standard' | 'fast' | 'archive';

export interface Volume {
    id: number;
    uuid: string;
    name: string;
    description: string | null;
    size: number; // in GB
    used: number; // in GB
    status: VolumeStatus;
    storage_class: StorageClass;
    mount_path: string;
    attached_services: AttachedService[];
    created_at: string;
    updated_at: string;
}

export interface AttachedService {
    id: number;
    uuid: string;
    name: string;
    type: 'application' | 'database' | 'service';
}

export interface VolumeSnapshot {
    id: number;
    uuid: string;
    volume_id: number;
    name: string;
    size: string;
    status: 'completed' | 'creating' | 'failed';
    created_at: string;
}

export interface StorageBackup {
    id: number;
    uuid: string;
    resource_type: 'volume' | 'database';
    resource_id: number;
    filename: string;
    size: string;
    status: 'completed' | 'in_progress' | 'failed';
    created_at: string;
}

export interface BackupSchedule {
    enabled: boolean;
    frequency: 'hourly' | 'daily' | 'weekly' | 'monthly';
    retention_days: number;
    next_run: string | null;
}

// Preview Deployment types
export type PreviewDeploymentStatus =
    | 'deploying'
    | 'running'
    | 'stopped'
    | 'failed'
    | 'deleting';

export interface PreviewDeployment {
    id: number;
    uuid: string;
    application_id: number;
    pull_request_id: number;
    pull_request_number: number;
    pull_request_title: string;
    branch: string;
    commit: string;
    commit_message: string | null;
    preview_url: string;
    status: PreviewDeploymentStatus;
    auto_delete_at: string | null;
    created_at: string;
    updated_at: string;
}

export interface PreviewDeploymentSettings {
    enabled: boolean;
    auto_deploy_on_pr: boolean;
    url_template: string;
    auto_delete_days: number;
    resource_limits: {
        cpu: string;
        memory: string;
    };
}

// Tag types
export interface Tag {
    id: number;
    name: string;
    team_id: number;
    color?: string;
    created_at: string;
    updated_at: string;
}

export interface TagWithResources extends Tag {
    applications_count: number;
    services_count: number;
    databases_count?: number;
}

// Git Source types
export type GitSourceType = 'github' | 'gitlab' | 'bitbucket';

export interface GitSource {
    id: number;
    uuid: string;
    name: string;
    type: GitSourceType;
    html_url?: string;
    api_url?: string;
    is_public: boolean;
    team_id: number;
    created_at: string;
    updated_at: string;
}

// Private Key types
export interface PrivateKey {
    id: number;
    uuid: string;
    name: string;
    description: string | null;
    is_git_related: boolean;
    team_id: number;
    created_at: string;
    updated_at: string;
}

// S3 Storage types
export type S3Provider = 'aws' | 'wasabi' | 'backblaze' | 'minio' | 'custom';

export interface S3Storage {
    id: number;
    uuid: string;
    name: string;
    description: string | null;
    region: string;
    key: string;
    secret: string;
    bucket: string;
    endpoint: string | null;
    path?: string | null;
    is_usable: boolean;
    unusable_email_sent: boolean;
    team_id: number;
    created_at: string;
    updated_at: string;
}
