# WebSocket Hooks Documentation

This directory contains custom React hooks for real-time updates via WebSocket (Laravel Echo) with automatic polling fallback.

## Available Hooks

### `useRealtimeStatus`

Hook for subscribing to real-time status updates for applications, deployments, databases, services, and servers.

#### Features

- ✅ WebSocket connection via Laravel Echo
- ✅ Automatic polling fallback when WebSocket unavailable
- ✅ Team and user channel subscriptions
- ✅ Auto-reconnection with exponential backoff
- ✅ Connection status monitoring
- ✅ TypeScript support with full type safety

#### Events Supported

| Event | Channel | Description |
|-------|---------|-------------|
| `ApplicationStatusChanged` | `team.{teamId}` | Application started/stopped/restarted |
| `DatabaseStatusChanged` | `user.{userId}` | Database status changes |
| `ServiceStatusChanged` | `team.{teamId}` | Service status changes |
| `ServerReachabilityChanged` | `team.{teamId}` | Server connectivity changes |
| `DeploymentCreated` | `team.{teamId}` | New deployment started |
| `DeploymentFinished` | `team.{teamId}` | Deployment completed/failed |

#### Usage Example

```tsx
import { useRealtimeStatus } from '@/hooks';
import { useState } from 'react';

function ApplicationDashboard() {
    const [appStatus, setAppStatus] = useState('stopped');

    const { isConnected, isPolling, error } = useRealtimeStatus({
        // Enable WebSocket (default: true)
        enableWebSocket: true,

        // Polling interval when WebSocket unavailable (default: 5000ms)
        pollingInterval: 5000,

        // Handle application status changes
        onApplicationStatusChange: (data) => {
            console.log('App status changed:', data);
            setAppStatus(data.status);
        },

        // Handle deployment events
        onDeploymentCreated: (data) => {
            console.log('Deployment started:', data.deploymentId);
            // Show toast notification
        },

        onDeploymentFinished: (data) => {
            console.log('Deployment finished:', data.status);
            // Update UI
        },

        // Handle database status
        onDatabaseStatusChange: (data) => {
            console.log('Database status:', data);
        },

        // Handle service status
        onServiceStatusChange: (data) => {
            console.log('Service status:', data);
        },

        // Connection status callback
        onConnectionChange: (connected) => {
            if (connected) {
                console.log('WebSocket connected');
            } else {
                console.log('WebSocket disconnected');
            }
        },
    });

    return (
        <div>
            <div>Status: {appStatus}</div>
            <div>
                Connection: {isConnected ? '🟢 Connected' : '🔴 Disconnected'}
                {isPolling && ' (polling)'}
            </div>
            {error && <div>Error: {error.message}</div>}
        </div>
    );
}
```

#### API Reference

**Options:**

```typescript
interface UseRealtimeStatusOptions {
    enableWebSocket?: boolean;           // Enable WebSocket (default: true)
    pollingInterval?: number;            // Polling interval in ms (default: 5000)
    onConnectionChange?: (connected: boolean) => void;
    onApplicationStatusChange?: (data: ApplicationStatusEvent) => void;
    onDatabaseStatusChange?: (data: DatabaseStatusEvent) => void;
    onServiceStatusChange?: (data: ServiceStatusEvent) => void;
    onServerStatusChange?: (data: ServerStatusEvent) => void;
    onDeploymentCreated?: (data: DeploymentEvent) => void;
    onDeploymentFinished?: (data: DeploymentEvent) => void;
}
```

**Return Value:**

```typescript
interface UseRealtimeStatusReturn {
    isConnected: boolean;  // WebSocket connection status
    error: Error | null;   // Connection error if any
    reconnect: () => void; // Manual reconnection
    isPolling: boolean;    // Whether using polling fallback
}
```

---

### `useLogStream`

Hook for streaming logs in real-time from applications, deployments, databases, or services.

#### Features

- ✅ Real-time log streaming via WebSocket
- ✅ Automatic polling fallback
- ✅ Log filtering by level (info, error, warning, debug)
- ✅ Auto-scroll to bottom
- ✅ Memory-efficient (configurable max entries)
- ✅ Download logs as file
- ✅ Pause/resume streaming

#### Usage Example

```tsx
import { useLogStream } from '@/hooks';

function DeploymentLogs({ deploymentId }: { deploymentId: string }) {
    const {
        logs,
        isStreaming,
        isConnected,
        isPolling,
        loading,
        error,
        clearLogs,
        toggleStreaming,
        refresh,
        downloadLogs,
    } = useLogStream({
        // Resource type
        resourceType: 'deployment',

        // Resource ID
        resourceId: deploymentId,

        // Enable WebSocket (default: true)
        enableWebSocket: true,

        // Polling interval when WebSocket unavailable (default: 2000ms)
        pollingInterval: 2000,

        // Maximum log entries in memory (default: 1000)
        maxLogEntries: 1000,

        // Auto-scroll to bottom (default: true)
        autoScroll: true,

        // Filter by level (default: 'all')
        filterLevel: 'all', // 'info' | 'error' | 'warning' | 'debug' | 'all'

        // Callback on new log entry
        onLogEntry: (entry) => {
            console.log('New log:', entry.message);
        },

        // Callback when streaming starts
        onStreamStart: () => {
            console.log('Log streaming started');
        },

        // Callback when streaming stops
        onStreamStop: () => {
            console.log('Log streaming stopped');
        },
    });

    return (
        <div className="log-viewer">
            <div className="log-controls">
                <button onClick={toggleStreaming}>
                    {isStreaming ? 'Pause' : 'Resume'}
                </button>
                <button onClick={clearLogs}>Clear</button>
                <button onClick={refresh}>Refresh</button>
                <button onClick={downloadLogs}>Download</button>

                <div className="log-status">
                    {isConnected && '🟢 Live'}
                    {isPolling && '🟡 Polling'}
                    {loading && '⏳ Loading'}
                </div>
            </div>

            <div
                className="log-container"
                data-log-container
                style={{ height: '500px', overflow: 'auto' }}
            >
                {logs.map((log) => (
                    <div key={log.id} className={`log-entry log-${log.level}`}>
                        <span className="log-timestamp">{log.timestamp}</span>
                        <span className="log-level">{log.level?.toUpperCase()}</span>
                        <span className="log-message">{log.message}</span>
                    </div>
                ))}

                {logs.length === 0 && !loading && (
                    <div className="no-logs">No logs yet</div>
                )}
            </div>

            {error && (
                <div className="log-error">Error: {error.message}</div>
            )}
        </div>
    );
}
```

#### Multiple Resource Types

```tsx
// Application logs
const appLogs = useLogStream({
    resourceType: 'application',
    resourceId: applicationUuid,
});

// Deployment logs
const deploymentLogs = useLogStream({
    resourceType: 'deployment',
    resourceId: deploymentUuid,
});

// Database logs
const dbLogs = useLogStream({
    resourceType: 'database',
    resourceId: databaseUuid,
});

// Service logs
const serviceLogs = useLogStream({
    resourceType: 'service',
    resourceId: serviceUuid,
});
```

#### API Reference

**Options:**

```typescript
interface UseLogStreamOptions {
    resourceType: 'application' | 'deployment' | 'database' | 'service';
    resourceId: string | number;
    enableWebSocket?: boolean;      // Enable WebSocket (default: true)
    pollingInterval?: number;       // Polling interval in ms (default: 2000)
    maxLogEntries?: number;         // Max entries to keep (default: 1000)
    autoScroll?: boolean;           // Auto-scroll to bottom (default: true)
    filterLevel?: 'info' | 'error' | 'warning' | 'debug' | 'all';
    onLogEntry?: (entry: LogEntry) => void;
    onStreamStart?: () => void;
    onStreamStop?: () => void;
}
```

**Return Value:**

```typescript
interface UseLogStreamReturn {
    logs: LogEntry[];              // Array of log entries
    isStreaming: boolean;          // Whether streaming is active
    isConnected: boolean;          // WebSocket connection status
    isPolling: boolean;            // Whether using polling fallback
    loading: boolean;              // Initial load state
    error: Error | null;           // Error state
    clearLogs: () => void;         // Clear all logs
    toggleStreaming: () => void;   // Pause/resume streaming
    refresh: () => Promise<void>;  // Manually fetch latest logs
    downloadLogs: () => void;      // Download logs as file
}
```

**Log Entry Type:**

```typescript
interface LogEntry {
    id: string;                    // Unique log entry ID
    timestamp: string;             // ISO timestamp
    message: string;               // Log message
    level?: 'info' | 'error' | 'warning' | 'debug';
    source?: string;               // Log source (resource type)
}
```

---

## Environment Configuration

Add these variables to your `.env` file for WebSocket configuration:

```bash
# Broadcasting Driver (Pusher-compatible)
BROADCAST_DRIVER=pusher

# Pusher/Soketi Configuration
PUSHER_APP_ID=saturn
PUSHER_APP_KEY=saturn
PUSHER_APP_SECRET=saturn
PUSHER_BACKEND_HOST=saturn-realtime
PUSHER_BACKEND_PORT=6001
PUSHER_SCHEME=http

# Frontend Environment Variables (Vite)
VITE_PUSHER_HOST="${PUSHER_BACKEND_HOST}"
VITE_PUSHER_PORT="${PUSHER_BACKEND_PORT}"
VITE_PUSHER_SCHEME="${PUSHER_SCHEME}"
VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
```

For production with SSL:

```bash
PUSHER_SCHEME=https
VITE_PUSHER_SCHEME=https
```

---

## Architecture

### WebSocket Flow

```
┌─────────────┐
│   React     │
│  Component  │
└──────┬──────┘
       │
       │ useRealtimeStatus() / useLogStream()
       │
       v
┌──────────────────────────────────┐
│   Custom Hook                    │
│   - Subscribe to Echo channels   │
│   - Handle events                │
│   - Fallback to polling          │
└──────┬───────────────────────────┘
       │
       │ Laravel Echo
       │
       v
┌──────────────────────────────────┐
│   Pusher/Soketi                  │
│   WebSocket Server               │
└──────┬───────────────────────────┘
       │
       │ Private Channel
       │
       v
┌──────────────────────────────────┐
│   Laravel Backend                │
│   - Broadcast Events             │
│   - Channel Authorization        │
└──────────────────────────────────┘
```

### Polling Fallback

When WebSocket is unavailable:
1. Hook attempts to connect via Echo
2. Connection fails (network, config, or server issue)
3. Hook automatically falls back to polling
4. API endpoints are called at regular intervals
5. Hook retries WebSocket connection periodically

---

## Best Practices

### 1. Cleanup

Always ensure hooks are properly cleaned up when component unmounts. The hooks handle this automatically, but be aware:

```tsx
// ✅ Good - Hook auto-cleans on unmount
useEffect(() => {
    const { isConnected } = useRealtimeStatus({
        onApplicationStatusChange: handleStatusChange,
    });
}, []);

// ❌ Bad - Manual cleanup needed
const echo = getEcho();
echo.private('team.1').listen('SomeEvent', handler);
// Missing cleanup!
```

### 2. Conditional Subscriptions

Only subscribe to events you need:

```tsx
// ✅ Good - Only subscribe to needed events
useRealtimeStatus({
    onApplicationStatusChange: handleAppStatus,
    // Don't subscribe to database events if not needed
});

// ❌ Bad - Subscribing to everything
useRealtimeStatus({
    onApplicationStatusChange: handleAppStatus,
    onDatabaseStatusChange: handleDbStatus,
    onServiceStatusChange: handleServiceStatus,
    onServerStatusChange: handleServerStatus,
    // ... too many subscriptions
});
```

### 3. Error Handling

Always handle errors gracefully:

```tsx
const { error, isConnected } = useRealtimeStatus({
    onApplicationStatusChange: handleStatus,
});

if (error) {
    // Show user-friendly error message
    return <ErrorBanner message="Connection lost. Retrying..." />;
}

if (!isConnected) {
    // Show fallback UI
    return <PollingIndicator />;
}
```

### 4. Performance

Limit log entries and use filtering:

```tsx
const { logs } = useLogStream({
    resourceType: 'deployment',
    resourceId: deploymentId,
    maxLogEntries: 500,        // Limit memory usage
    filterLevel: 'error',      // Only show errors
    autoScroll: false,         // Disable if user scrolled up
});
```

---

## Troubleshooting

### WebSocket Not Connecting

1. **Check environment variables:**
   ```bash
   # .env
   BROADCAST_DRIVER=pusher
   VITE_PUSHER_HOST=your-soketi-host
   VITE_PUSHER_PORT=6001
   ```

2. **Check Soketi is running:**
   ```bash
   docker ps | grep soketi
   # or
   docker ps | grep saturn-realtime
   ```

3. **Check browser console:**
   - Look for WebSocket connection errors
   - Verify authentication errors
   - Check CORS issues

4. **Verify channel authorization:**
   - Ensure user is authenticated
   - Check `/broadcasting/auth` endpoint
   - Verify team/user IDs

### Polling Not Working

1. **Check API endpoints:**
   - Verify endpoints exist and return data
   - Check authentication tokens
   - Verify CORS headers

2. **Check polling interval:**
   ```tsx
   useLogStream({
       pollingInterval: 2000, // Not 0 or negative
   });
   ```

### High Memory Usage

1. **Reduce max log entries:**
   ```tsx
   useLogStream({
       maxLogEntries: 100, // Reduce from 1000
   });
   ```

2. **Clear logs periodically:**
   ```tsx
   useEffect(() => {
       const interval = setInterval(() => {
           clearLogs();
       }, 60000); // Clear every minute

       return () => clearInterval(interval);
   }, [clearLogs]);
   ```

---

## Testing

### Unit Tests

```tsx
import { renderHook, waitFor } from '@testing-library/react';
import { useRealtimeStatus } from '@/hooks';

test('connects to WebSocket', async () => {
    const { result } = renderHook(() => useRealtimeStatus());

    await waitFor(() => {
        expect(result.current.isConnected).toBe(true);
    });
});

test('handles status change events', async () => {
    const handleChange = jest.fn();

    renderHook(() => useRealtimeStatus({
        onApplicationStatusChange: handleChange,
    }));

    // Simulate event
    // ...

    expect(handleChange).toHaveBeenCalledWith({
        applicationId: 1,
        status: 'running',
    });
});
```

### Integration Tests

Test with actual WebSocket server or use mock Echo instance.

---

## Related Files

- `/home/user/saturn-Saturn/resources/js/lib/echo.ts` - Echo initialization
- `/home/user/saturn-Saturn/resources/js/hooks/useRealtimeStatus.ts` - Status hook
- `/home/user/saturn-Saturn/resources/js/hooks/useLogStream.ts` - Log streaming hook
- `/home/user/saturn-Saturn/config/broadcasting.php` - Laravel broadcast config
- `/home/user/saturn-Saturn/app/Events/` - Laravel broadcast events
