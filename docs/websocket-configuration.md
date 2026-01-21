# WebSocket Configuration for Saturn (React/Inertia)

This document describes the Laravel Echo and WebSocket configuration for real-time updates in the Saturn project.

## Overview

The Saturn project uses **Laravel Echo** with **Pusher protocol** (compatible with Soketi) to provide real-time updates for:
- Server status changes
- Application deployment progress
- Database status updates
- Service status changes
- Live application logs

## Architecture

```
┌─────────────────┐      ┌─────────────────┐      ┌─────────────────┐
│  React Frontend │◄────►│  Laravel Echo   │◄────►│  Soketi Server  │
│  (Inertia)      │      │  (pusher-js)    │      │  (WebSocket)    │
└─────────────────┘      └─────────────────┘      └─────────────────┘
                                                            ▲
                                                            │
                                                            ▼
                                                   ┌─────────────────┐
                                                   │  Laravel Queue  │
                                                   │  (Broadcasting) │
                                                   └─────────────────┘
```

## Environment Configuration

### Development (.env.development.example)

```bash
# Broadcasting / WebSocket Configuration (Soketi)
BROADCAST_DRIVER=pusher
PUSHER_APP_ID=saturn
PUSHER_APP_KEY=saturn
PUSHER_APP_SECRET=saturn
PUSHER_BACKEND_HOST=saturn-realtime
PUSHER_BACKEND_PORT=6001
PUSHER_SCHEME=http

# Frontend WebSocket Configuration (must match PUSHER_* above)
VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
VITE_PUSHER_HOST="${PUSHER_BACKEND_HOST}"
VITE_PUSHER_PORT="${PUSHER_BACKEND_PORT}"
VITE_PUSHER_SCHEME="${PUSHER_SCHEME}"
```

### Production (.env.production)

```bash
# Broadcasting / WebSocket Configuration (Soketi)
BROADCAST_DRIVER=pusher
PUSHER_APP_ID=your-app-id
PUSHER_APP_KEY=your-app-key
PUSHER_APP_SECRET=your-app-secret
# Backend host - internal Docker network (Laravel -> Soketi)
PUSHER_BACKEND_HOST=saturn-realtime
PUSHER_BACKEND_PORT=6001
PUSHER_SCHEME=https

# Frontend WebSocket Configuration
# IMPORTANT: Leave VITE_PUSHER_HOST empty for auto-detection (recommended)
# or set to your public domain. NEVER use saturn-realtime here!
VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
VITE_PUSHER_HOST=
VITE_PUSHER_PORT=443
VITE_PUSHER_SCHEME=wss
```

**Important:** `VITE_PUSHER_HOST` is for the browser (external). If left empty, it auto-detects from `window.location.hostname`. `PUSHER_BACKEND_HOST` is for Laravel backend (internal Docker network).

## Backend Configuration

### Broadcast Channels (routes/channels.php)

The following private channels are configured:

| Channel | Authorization | Purpose |
|---------|--------------|---------|
| `team.{teamId}` | User must be member of team | Team-wide events (applications, servers, deployments) |
| `user.{userId}` | User must own the userId | User-specific events |
| `application.{applicationId}.logs` | User's team must own application | Application log streaming |
| `deployment.{deploymentId}` | User's team must own deployment | Deployment progress updates |
| `deployment.{deploymentUuid}.logs` | User's team must own deployment | Deployment log streaming |
| `server.{serverId}` | User's team must own server | Server-specific events |
| `database.{databaseId}` | User's team must own database | Database-specific events |
| `database.{databaseId}.logs` | User's team must own database | Database log streaming |
| `service.{serviceId}.logs` | User's team must own service | Service log streaming |

### Broadcast Events (app/Events/)

#### ApplicationStatusChanged
Broadcasts when an application's status changes.

```php
event(new ApplicationStatusChanged(
    applicationId: $application->id,
    status: 'running',
    teamId: $application->team_id
));
```

**Channel:** `team.{teamId}`
**Event Data:**
```json
{
  "applicationId": 123,
  "status": "running"
}
```

#### DatabaseStatusChanged
Broadcasts when a database's status changes.

```php
event(new DatabaseStatusChanged(
    databaseId: $database->id,
    status: 'running',
    teamId: $database->team_id
));
```

**Channel:** `team.{teamId}`
**Event Data:**
```json
{
  "databaseId": 456,
  "status": "running"
}
```

#### ServiceStatusChanged
Broadcasts when a service's status changes.

```php
event(new ServiceStatusChanged(
    serviceId: $service->id,
    status: 'running',
    teamId: $service->team_id
));
```

**Channel:** `team.{teamId}`
**Event Data:**
```json
{
  "serviceId": 789,
  "status": "running"
}
```

#### ServerReachabilityChanged
Broadcasts when a server's reachability status changes.

```php
event(new ServerReachabilityChanged(
    server: $server
));
```

**Channel:** `team.{teamId}`
**Event Data:**
```json
{
  "serverId": 1,
  "isReachable": true,
  "isUsable": true
}
```

#### DeploymentCreated
Broadcasts when a new deployment is created.

```php
event(new DeploymentCreated(
    deploymentId: $deployment->id,
    applicationId: $application->id,
    deploymentUuid: $deployment->uuid,
    teamId: $application->team_id
));
```

**Channel:** `team.{teamId}`
**Event Data:**
```json
{
  "deploymentId": 123,
  "applicationId": 456,
  "deploymentUuid": "abc-123-def"
}
```

#### DeploymentFinished
Broadcasts when a deployment finishes.

```php
event(new DeploymentFinished(
    deploymentId: $deployment->id,
    applicationId: $application->id,
    status: 'finished',
    error: null,
    teamId: $application->team_id
));
```

**Channel:** `team.{teamId}`
**Event Data:**
```json
{
  "deploymentId": 123,
  "applicationId": 456,
  "status": "finished",
  "error": null
}
```

#### LogEntry
Broadcasts real-time log entries for applications.

```php
event(new LogEntry(
    applicationId: $application->id,
    message: 'Starting application...',
    timestamp: now()->toIso8601String(),
    level: 'info',
    teamId: $application->team_id
));
```

**Channel:** `application.{applicationId}.logs`
**Event Data:**
```json
{
  "message": "Starting application...",
  "timestamp": "2026-01-04T10:30:00Z",
  "level": "info"
}
```

## Frontend Usage

### React Hooks

#### useRealtimeStatus Hook

Subscribe to real-time status updates for servers, applications, databases, and deployments.

```typescript
import { useRealtimeStatus } from '@/hooks/useRealtimeStatus';

function MyComponent() {
  const { isConnected, error, reconnect } = useRealtimeStatus({
    // Enable/disable WebSocket (default: true)
    enableWebSocket: true,

    // Polling fallback interval in ms (default: 5000)
    pollingInterval: 5000,

    // Connection status callback
    onConnectionChange: (connected) => {
      console.log('WebSocket connected:', connected);
    },

    // Application status changes
    onApplicationStatusChange: (data) => {
      console.log('App status changed:', data);
      // Update your state: setApplications(...)
    },

    // Database status changes
    onDatabaseStatusChange: (data) => {
      console.log('Database status changed:', data);
      // Update your state: setDatabases(...)
    },

    // Service status changes
    onServiceStatusChange: (data) => {
      console.log('Service status changed:', data);
    },

    // Server status changes
    onServerStatusChange: (data) => {
      console.log('Server status changed:', data);
      // data: { serverId, isReachable, isUsable }
    },

    // Deployment created
    onDeploymentCreated: (data) => {
      console.log('New deployment:', data);
      // data: { deploymentId, applicationId, deploymentUuid }
    },

    // Deployment finished
    onDeploymentFinished: (data) => {
      console.log('Deployment finished:', data);
      // data: { deploymentId, applicationId, status, error }
    },
  });

  return (
    <div>
      {isConnected ? '🟢 Connected' : '🔴 Disconnected'}
      {error && <div>Error: {error.message}</div>}
      <button onClick={reconnect}>Reconnect</button>
    </div>
  );
}
```

#### useLogStream Hook

Stream real-time logs from applications, deployments, databases, or services.

```typescript
import { useLogStream } from '@/hooks/useLogStream';

function LogViewer({ deploymentUuid }: { deploymentUuid: string }) {
  const {
    logs,
    isStreaming,
    isConnected,
    clearLogs,
    downloadLogs,
    toggleStreaming,
  } = useLogStream({
    resourceType: 'deployment',
    resourceId: deploymentUuid,

    // Optional: filter by log level
    filterLevel: 'all', // 'info' | 'error' | 'warning' | 'debug' | 'all'

    // Optional: max logs to keep in memory
    maxLogEntries: 1000,

    // Optional: auto-scroll to bottom
    autoScroll: true,

    // Optional: callbacks
    onLogEntry: (entry) => {
      console.log('New log:', entry.message);
    },
    onStreamStart: () => console.log('Streaming started'),
    onStreamStop: () => console.log('Streaming stopped'),
  });

  return (
    <div>
      <div>
        Status: {isStreaming ? '▶️ Streaming' : '⏸️ Paused'}
        {isConnected && ' (WebSocket)'}
      </div>

      <div data-log-container className="h-96 overflow-y-auto">
        {logs.map((log) => (
          <div key={log.id}>
            [{log.timestamp}] {log.level}: {log.message}
          </div>
        ))}
      </div>

      <div className="flex gap-2">
        <button onClick={toggleStreaming}>
          {isStreaming ? 'Pause' : 'Resume'}
        </button>
        <button onClick={clearLogs}>Clear</button>
        <button onClick={downloadLogs}>Download</button>
      </div>
    </div>
  );
}
```

### Echo Client API

For direct access to Laravel Echo:

```typescript
import { getEcho, isEchoConnected, disconnectEcho } from '@/lib/echo';

// Get Echo instance
const echo = getEcho();

// Check connection
if (isEchoConnected()) {
  // Subscribe to a private channel
  echo.private('team.123')
    .listen('ApplicationStatusChanged', (e) => {
      console.log('Status changed:', e);
    });
}

// Leave a channel
echo.leave('team.123');

// Disconnect (cleanup)
disconnectEcho();
```

## Testing Considerations

### Test Environment Handling

The Echo client automatically handles test environments where:
- WebSocket server may not be available
- CSRF tokens may not be present
- `window` object may not exist

**Graceful degradation:**
1. If Echo initialization fails, it returns `null` instead of throwing
2. Hooks check for `null` Echo and fall back to polling or silent failure
3. Tests won't crash due to missing WebSocket infrastructure

### Mocking in Tests

```typescript
// Mock Echo in your tests
jest.mock('@/lib/echo', () => ({
  getEcho: () => null, // Simulate unavailable WebSocket
  isEchoConnected: () => false,
  initializeEcho: () => null,
  disconnectEcho: () => {},
}));
```

## Troubleshooting

### Common Issues

#### 1. "Failed to initialize Laravel Echo" in tests
**Cause:** CSRF token not available in test environment
**Solution:** This is expected. The error is logged but doesn't break functionality.

#### 2. WebSocket connection refused
**Cause:** Soketi server not running
**Solution:** Ensure Soketi container is running: `docker ps | grep soketi`

#### 3. Authentication failures on private channels
**Cause:** Missing or invalid CSRF token
**Solution:** Ensure CSRF token meta tag exists in your Inertia layout (`resources/views/app.blade.php`):
```html
<meta name="csrf-token" content="{{ csrf_token() }}">
```
**Note:** This has been added to the default `resources/views/app.blade.php` layout for React/Inertia.

#### 4. Events not broadcasting
**Cause:** Queue worker not running or event doesn't implement `ShouldBroadcast`
**Solution:**
- Check queue worker: `php artisan queue:work`
- Verify event implements `ShouldBroadcast` interface
- Check broadcast driver in `.env`: `BROADCAST_DRIVER=pusher`

### Debug Logging

Enable debug logging in the browser console:

```javascript
// In browser console
localStorage.debug = 'pusher:*';
// Reload page to see Pusher debug logs
```

## Performance Considerations

1. **Channel Limits:** Each Echo instance maintains persistent WebSocket connections. Limit subscriptions to necessary channels.

2. **Event Payload Size:** Keep broadcast event data small. Large payloads slow down real-time updates.

3. **Polling Fallback:** Default polling interval is 5 seconds. Adjust based on your needs:
   ```typescript
   useRealtimeStatus({ pollingInterval: 10000 }) // 10 seconds
   ```

4. **Log Memory:** Default max log entries is 1000. For longer sessions, consider:
   ```typescript
   useLogStream({ maxLogEntries: 500 }) // Reduce memory usage
   ```

## Security

1. **Private Channels:** All channels are private and require authentication
2. **Authorization:** Channel authorization callbacks check team membership
3. **CSRF Protection:** All requests include CSRF token
4. **TLS:** Use `forceTLS: true` in production (set via `PUSHER_SCHEME=https`)

## Related Files

**Backend:**
- `/config/broadcasting.php` - Broadcasting configuration
- `/routes/channels.php` - Channel authorization
- `/app/Events/*` - Broadcast event classes

**Frontend:**
- `/resources/js/lib/echo.ts` - Echo initialization
- `/resources/js/hooks/useRealtimeStatus.ts` - Status updates hook
- `/resources/js/hooks/useLogStream.ts` - Log streaming hook
- `/resources/js/app.tsx` - Echo initialization on app boot

**Environment:**
- `/.env.development.example` - Development environment template
- `/.env.production` - Production environment template
