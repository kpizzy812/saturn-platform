# CLI for [Saturn](https://saturn.ac) API

## Installation

### Install script (recommended)

#### Linux/macOS

```bash
curl -fsSL https://raw.githubusercontent.com/saturn-platform/saturn-cli/main/scripts/install.sh | bash
```

It will install the CLI in `/usr/local/bin/saturn` and the configuration file in `~/.config/saturn/config.json`

#### Windows (PowerShell)

```powershell
irm https://raw.githubusercontent.com/saturn-platform/saturn-cli/main/scripts/install.ps1 | iex
```

It will install the CLI in `%ProgramFiles%\Saturn\saturn.exe` and the configuration file in `%USERPROFILE%\.config\saturn\config.json`

For user installation (no admin rights required):
```powershell
$env:SATURN_USER_INSTALL=1; irm https://raw.githubusercontent.com/saturn-platform/saturn-cli/main/scripts/install.ps1 | iex
```

For a specific version:
```powershell
$env:SATURN_VERSION='v1.0.0'; irm https://raw.githubusercontent.com/saturn-platform/saturn-cli/main/scripts/install.ps1 | iex
```

### Using `go install`

```bash
go install github.com/saturn-platform/saturn-cli/saturn@latest
```

This will install the `saturn` binary in your `$GOPATH/bin` directory (usually `~/go/bin`). Make sure this directory is in your `$PATH`.

### Using the install script


## Getting Started
1. Get a `<token>` from your Saturn dashboard (Cloud or self-hosted) at `/security/api-tokens`

### Cloud

2. Add the token with `saturn context set-token cloud <token>`

### Self-hosted

2. Add the token with `saturn context add -d <context_name> <url> <token>`

> Replace `<context_name>` with the name you want to give to the context.
>
> Replace `<url>` with the fully qualified domain name of your Saturn instance.

Now you can use the CLI with the token you just added.

## Change default context
You can change the default context with `saturn context use <context_name>` or `saturn context set-default <context_name>`
## Currently Supported Commands

### Update
- `saturn update` - Update the CLI to the latest version

### Configuration
- `saturn config` - Show configuration file location

### Shell Completion
- `saturn completion <shell>` - Generate shell completion script
  - Supported shells: `bash`, `zsh`, `fish`, `powershell`

### Context Management
- `saturn context list` - List all configured contexts
- `saturn context add <context_name> <url> <token>` - Add a new context
  - `-d, --default` - Set as default context
  - `-f, --force` - Force overwrite if context already exists
- `saturn context delete <context_name>` - Delete a context
- `saturn context get <context_name>` - Get details of a specific context
- `saturn context set-token <context_name> <token>` - Update the API token for a context
- `saturn context set-default <context_name>` - Set a context as the default
- `saturn context update <context_name>` - Update a context's properties
  - `--name <new_name>` - Change the context name
  - `--url <new_url>` - Change the context URL
  - `--token <new_token>` - Change the context token
- `saturn context use <context_name>` - Switch to a different context (set as default)
- `saturn context verify` - Verify current context connection and authentication
- `saturn context version` - Get the Saturn API version of the current context

### Servers

Commands can use `server` or `servers` interchangeably.

- `saturn server list` - List all servers
- `saturn server get <uuid>` - Get a server by UUID
  - `--resources` - Get the resources and their status of a server
- `saturn server add <name> <ip> <private_key_uuid>` - Add a new server
  - `-p, --port <port>` - SSH port (default: 22)
  - `-u, --user <user>` - SSH user (default: root)
  - `--validate` - Validate server immediately after adding
- `saturn server remove <uuid>` - Remove a server
- `saturn server validate <uuid>` - Validate a server connection
- `saturn server domains <uuid>` - Get server domains by UUID

### Projects
- `saturn projects list` - List all projects
- `saturn projects get <uuid>` - Get project environments

### Resources
- `saturn resources list` - List all resources

### Applications
- `saturn app list` - List all applications
- `saturn app get <uuid>` - Get application details
- `saturn app update <uuid>` - Update application configuration
  - `--name <name>` - Application name
  - `--description <description>` - Application description
  - `--git-branch <branch>` - Git branch
  - `--git-repository <url>` - Git repository URL
  - `--domains <domains>` - Domains (comma-separated)
  - `--build-command <cmd>` - Build command
  - `--start-command <cmd>` - Start command
  - `--install-command <cmd>` - Install command
  - `--base-directory <path>` - Base directory
  - `--publish-directory <path>` - Publish directory
  - `--dockerfile <content>` - Dockerfile content
  - `--docker-image <image>` - Docker image name
  - `--docker-tag <tag>` - Docker image tag
  - `--ports-exposes <ports>` - Exposed ports
  - `--ports-mappings <mappings>` - Port mappings
  - `--health-check-enabled` - Enable health check
  - `--health-check-path <path>` - Health check path
- `saturn app delete <uuid>` - Delete an application
  - `-f, --force` - Skip confirmation prompt
- `saturn app start <uuid>` - Start an application
- `saturn app stop <uuid>` - Stop an application
- `saturn app restart <uuid>` - Restart an application
- `saturn app logs <uuid>` - Get application logs

#### Application Environment Variables
- `saturn app env list <app_uuid>` - List all environment variables
- `saturn app env get <app_uuid> <env_uuid_or_key>` - Get a specific environment variable
- `saturn app env create <app_uuid>` - Create a new environment variable
  - `--key <key>` - Variable key (required)
  - `--value <value>` - Variable value (required)
  - `--preview` - Available in preview deployments
  - `--build-time` - Available at build time
  - `--is-literal` - Treat value as literal (don't interpolate variables)
  - `--is-multiline` - Value is multiline
- `saturn app env update <app_uuid> <env_uuid>` - Update an environment variable
- `saturn app env delete <app_uuid> <env_uuid>` - Delete an environment variable
- `saturn app env sync <app_uuid>` - Sync environment variables from a .env file
  - `--file <path>` - Path to .env file (required)
  - `--build-time` - Make all variables available at build time
  - `--preview` - Make all variables available in preview deployments
  - `--is-literal` - Treat all values as literal (don't interpolate variables)
  - **Behavior**: Updates existing variables, creates missing ones. Does NOT delete variables not in the file.

#### Application Deployments
- `saturn app deployments list <app-uuid>` - List all deployments for an application
- `saturn app deployments logs <app-uuid> [deployment-uuid]` - Get deployment logs (formatted as human-readable text)
  - If only `app-uuid` is provided: retrieves logs from the **latest/most recent deployment only**
  - If `deployment-uuid` is also provided: retrieves logs for that **specific deployment**
  - `-n, --lines <n>` - Number of log lines to display (default: 0 = all lines)
  - `-f, --follow` - Follow log output in real-time (like tail -f)
  - `--debuglogs` - Show debug logs (includes hidden commands and internal operations)

### Databases
- `saturn database list` - List all databases
- `saturn database get <uuid>` - Get database details
- `saturn database create <type>` - Create a new database
  - Supported types: `postgresql`, `mysql`, `mariadb`, `mongodb`, `redis`, `keydb`, `clickhouse`, `dragonfly`
  - `--server-uuid <uuid>` - Server UUID (required)
  - `--project-uuid <uuid>` - Project UUID (required)
  - `--environment-name <name>` - Environment name (required unless using --environment-uuid)
  - `--environment-uuid <uuid>` - Environment UUID (required unless using --environment-name)
  - `--destination-uuid <uuid>` - Destination UUID if server has multiple destinations
  - `--name <name>` - Database name
  - `--description <description>` - Database description
  - `--image <image>` - Docker image
  - `--instant-deploy` - Deploy immediately after creation
  - `--is-public` - Make database publicly accessible
  - `--public-port <port>` - Public port number
  - `--limits-memory <size>` - Memory limit (e.g., '512m', '2g')
  - `--limits-cpus <cpus>` - CPU limit (e.g., '0.5', '2')
  - Database-specific flags (postgres-user, mysql-root-password, etc.)
- `saturn database update <uuid>` - Update database configuration
- `saturn database delete <uuid>` - Delete a database
  - `--delete-configurations` - Delete configurations (default: true)
  - `--delete-volumes` - Delete volumes (default: true)
  - `--docker-cleanup` - Run docker cleanup (default: true)
  - `--delete-connected-networks` - Delete connected networks (default: true)
- `saturn database start <uuid>` - Start a database
- `saturn database stop <uuid>` - Stop a database
- `saturn database restart <uuid>` - Restart a database

#### Database Backups
- `saturn database backup list <database_uuid>` - List all backup configurations
- `saturn database backup create <database_uuid>` - Create a new backup configuration
  - `--frequency <cron>` - Backup frequency (cron expression)
  - `--enabled` - Enable backup schedule
  - `--save-s3` - Save backups to S3
  - `--s3-storage-uuid <uuid>` - S3 storage UUID
  - `--databases-to-backup <list>` - Comma-separated list of databases to backup
  - `--dump-all` - Dump all databases
  - `--retention-amount-local <n>` - Number of backups to retain locally
  - `--retention-days-local <n>` - Days to retain backups locally
  - `--retention-storage-local <size>` - Max storage for local backups (e.g., '1GB', '500MB')
  - `--retention-amount-s3 <n>` - Number of backups to retain in S3
  - `--retention-days-s3 <n>` - Days to retain backups in S3
  - `--retention-storage-s3 <size>` - Max storage for S3 backups (e.g., '1GB', '500MB')
  - `--timeout <seconds>` - Backup timeout in seconds
  - `--disable-local` - Disable local backup storage
- `saturn database backup update <database_uuid> <backup_uuid>` - Update a backup configuration
- `saturn database backup delete <database_uuid> <backup_uuid>` - Delete a backup configuration
- `saturn database backup trigger <database_uuid> <backup_uuid>` - Trigger an immediate backup
- `saturn database backup executions <database_uuid> <backup_uuid>` - List backup executions
- `saturn database backup delete-execution <database_uuid> <backup_uuid> <execution_uuid>` - Delete a backup execution

### Services
- `saturn service list` - List all services
- `saturn service get <uuid>` - Get service details
- `saturn service start <uuid>` - Start a service
- `saturn service stop <uuid>` - Stop a service
- `saturn service restart <uuid>` - Restart a service
- `saturn service delete <uuid>` - Delete a service

#### Service Environment Variables
- `saturn service env list <service_uuid>` - List all environment variables
- `saturn service env get <service_uuid> <env_uuid_or_key>` - Get a specific environment variable
- `saturn service env create <service_uuid>` - Create a new environment variable
  - Same flags as application environment variables
- `saturn service env update <service_uuid> <env_uuid>` - Update an environment variable
- `saturn service env delete <service_uuid> <env_uuid>` - Delete an environment variable
- `saturn service env sync <service_uuid>` - Sync environment variables from a .env file
  - `--file <path>` - Path to .env file (required)
  - `--build-time` - Make all variables available at build time
  - `--preview` - Make all variables available in preview deployments
  - `--is-literal` - Treat all values as literal (don't interpolate variables)
  - **Behavior**: Updates existing variables, creates missing ones. Does NOT delete variables not in the file.

### Deployments
- `saturn deploy uuid <uuid>` - Deploy a resource by UUID
  - `-f, --force` - Force deployment
- `saturn deploy name <name>` - Deploy a resource by name
  - `-f, --force` - Force deployment
- `saturn deploy batch <name1,name2,...>` - Deploy multiple resources at once
  - `-f, --force` - Force all deployments
- `saturn deploy list` - List all deployments
- `saturn deploy get <uuid>` - Get deployment details
- `saturn deploy cancel <uuid>` - Cancel a deployment
  - `-f, --force` - Skip confirmation prompt

### GitHub Apps
- `saturn github list` - List all GitHub App integrations
- `saturn github get <app_uuid>` - Get GitHub App details
- `saturn github create` - Create a new GitHub App integration
  - `--name <name>` - GitHub App name (required)
  - `--api-url <url>` - GitHub API URL (required, e.g., https://api.github.com)
  - `--html-url <url>` - GitHub HTML URL (required, e.g., https://github.com)
  - `--app-id <id>` - GitHub App ID (required)
  - `--installation-id <id>` - GitHub Installation ID (required)
  - `--client-id <id>` - GitHub OAuth Client ID (required)
  - `--client-secret <secret>` - GitHub OAuth Client Secret (required)
  - `--private-key-uuid <uuid>` - UUID of existing private key (required)
  - `--organization <org>` - GitHub organization
  - `--custom-user <user>` - Custom user for SSH (default: git)
  - `--custom-port <port>` - Custom port for SSH (default: 22)
  - `--webhook-secret <secret>` - GitHub Webhook Secret
  - `--system-wide` - Is this app system-wide (cloud only)
- `saturn github update <app_uuid>` - Update a GitHub App
- `saturn github delete <app_uuid>` - Delete a GitHub App
  - `-f, --force` - Skip confirmation prompt
- `saturn github repos <app_uuid>` - List repositories accessible by a GitHub App
- `saturn github branches <app_uuid> <owner/repo>` - List branches for a repository

### Teams
- `saturn team list` - List all teams
- `saturn team get <team_id>` - Get team details
- `saturn team current` - Get current team
- `saturn team members list [team_id]` - List team members

### Private Keys

Commands can use `private-key`, `private-keys`, `key`, or `keys` interchangeably.

- `saturn private-key list` - List all private keys
- `saturn private-key add <key_name> <private-key>` - Add a new private key
  - Use `@filename` to read from file: `saturn private-key add mykey @~/.ssh/id_rsa`
- `saturn private-key remove <uuid>` - Remove a private key

## Global Flags

All commands support these global flags:

- `--context <name>` - Use a specific context instead of default
- `--host <fqdn>` - Override the Saturn instance hostname
- `--token <token>` - Override the authentication token
- `--format <format>` - Output format: `table` (default), `json`, or `pretty`
- `-s, --show-sensitive` - Show sensitive information (tokens, IPs, etc.)
- `-f, --force` - Force operation (skip confirmations)
- `--debug` - Enable debug mode

## Examples

### Multi-Environment Workflows

```bash
# Add multiple contexts
saturn context add prod https://saturn.ac <prod-token>
saturn context add staging https://uat.saturn.ac <staging-token>
saturn context add dev https://dev.saturn.ac <dev-token>

# Set default
saturn context use prod

# Use different contexts
saturn --context=staging servers list
saturn --context=prod deploy name api
saturn --context=dev resources list

# Default context (prod in this case)
saturn servers list
```

### Application Management

```bash
# List all applications
saturn app list

# Get application details
saturn app get <uuid>

# Manage application lifecycle
saturn app start <uuid>
saturn app stop <uuid>
saturn app restart <uuid>

# View application logs
saturn app logs <uuid>

# Environment variables
saturn app env list <uuid>
saturn app env create <uuid> --key API_KEY --value secret123

# Sync from .env file (updates existing, creates new, keeps others unchanged)
saturn app env sync <uuid> --file .env
saturn app env sync <uuid> --file .env.production --build-time --preview
```

### Database Management

```bash
# List databases
saturn database list

# Create a PostgreSQL database
saturn database create postgresql \
  --server-uuid <server-uuid> \
  --project-uuid <project-uuid> \
  --name mydb \
  --instant-deploy

# Manage database lifecycle
saturn database start <uuid>
saturn database stop <uuid>
saturn database restart <uuid>

# Backup management
saturn database backup list <database-uuid>
saturn database backup create <database-uuid> \
  --frequency "0 2 * * *" \
  --enabled \
  --save-s3 \
  --retention-days-locally 7
saturn database backup trigger <database-uuid> <backup-uuid>
```

### Service Management

```bash
# List services
saturn service list

# Get service details
saturn service get <uuid>

# Manage services
saturn service start <uuid>
saturn service restart <uuid>

# Environment variables (same as applications)
saturn service env sync <uuid> --file .env
```

### Deploy Workflows

```bash
# Deploy single app by name (easier than UUID)
saturn deploy name my-application

# Deploy multiple apps at once
saturn deploy batch api,worker,frontend

# Force deploy with specific context
saturn --context=prod deploy batch api,worker --force

# Traditional UUID deployment still works
saturn deploy uuid abc123-def456-...

# Monitor deployments
saturn deploy list
saturn deploy get <deployment-uuid>

# Cancel a deployment
saturn deploy cancel <deployment-uuid>
```

### GitHub Apps Integration

```bash
# List GitHub Apps
saturn github list

# Create a GitHub App integration
saturn github create \
  --name "My GitHub App" \
  --api-url "https://api.github.com" \
  --html-url "https://github.com" \
  --app-id 123456 \
  --installation-id 789012 \
  --client-id "Iv1.abc123" \
  --client-secret "secret" \
  --private-key-uuid <key-uuid>

# List repositories accessible by the app
saturn github repos <app-uuid>

# List branches for a repository
saturn github branches <app-uuid> owner/repo

# Delete a GitHub App
saturn github delete <app-uuid>
```

### Team Management

```bash
# List teams
saturn team list

# Get current team
saturn team current

# List team members
saturn team members list
```

### Server Management

```bash
# List servers in production
saturn --context=prod server list

# Add a server with validation
saturn server add myserver 192.168.1.100 <key-uuid> --validate

# Get server details with resources
saturn server get <uuid> --resources
```

## Output Formats

The CLI supports three output formats:

```bash
# Table format (default, human-readable)
saturn server list

# JSON format (for scripts)
saturn server list --format=json

# Pretty JSON (for debugging)
saturn server list --format=pretty
```

## Architecture

This CLI follows a clean architecture with:
- **Service Layer**: Business logic and API interactions
- **Output Layer**: Consistent formatting across all commands
- **Config Layer**: Multi-context configuration management
- **Models Layer**: Type-safe data structures

## Development

```bash
# Build
go build -o saturn ./saturn

# Run tests
go test ./...

# Run with coverage
go test -cover ./...

# Install locally
go install ./saturn
```

## Contributing

Contributions are welcome!

## License

MIT
