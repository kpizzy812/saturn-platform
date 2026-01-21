# WebSocket Hooks Integration Guide

Step-by-step guide to integrate real-time WebSocket hooks into existing pages.

## Quick Start: 3 Steps

### Step 1: Import the Hook

```tsx
import { useRealtimeStatus, useLogStream } from '@/hooks';
```

### Step 2: Use in Component

```tsx
const { isConnected } = useRealtimeStatus({
    onApplicationStatusChange: (data) => {
        // Update your state here
    }
});
```

### Step 3: Display Status

```tsx
<div>Connection: {isConnected ? '🟢' : '🔴'}</div>
```

That's it! The hook handles everything else automatically.

---

## Example Integrations

### 1. Dashboard - Real-time Project Status

**File:** `/home/user/saturn-Saturn/resources/js/pages/Dashboard.tsx`

**Before:**
```tsx
function Dashboard({ projects }: Props) {
    return (
        <div>
            {projects.map(project => (
                <ProjectCard key={project.id} project={project} />
            ))}
        </div>
    );
}
```

**After:**
```tsx
import { useRealtimeStatus } from '@/hooks';
import { useState } from 'react';

function Dashboard({ projects: initialProjects }: Props) {
    const [projects, setProjects] = useState(initialProjects);

    // Subscribe to real-time updates
    const { isConnected } = useRealtimeStatus({
        onApplicationStatusChange: (data) => {
            setProjects(prev => prev.map(project => ({
                ...project,
                environments: project.environments.map(env => ({
                    ...env,
                    applications: env.applications.map(app =>
                        app.id === data.applicationId
                            ? { ...app, status: data.status }
                            : app
                    )
                }))
            })));
        },

        onDeploymentCreated: (data) => {
            // Show toast notification
            toast.info('New deployment started');
        },
    });

    return (
        <div>
            <div className="connection-status">
                {isConnected ? '🟢 Live' : '🔴 Offline'}
            </div>
            {projects.map(project => (
                <ProjectCard key={project.id} project={project} />
            ))}
        </div>
    );
}
```

---

### 2. Deployment Logs - Live Log Streaming

**File:** `/home/user/saturn-Saturn/resources/js/pages/Deployments/BuildLogs.tsx`

**Before (with mock data):**
```tsx
function BuildLogs({ deploymentId }: Props) {
    const [logs, setLogs] = useState<string[]>([]);

    useEffect(() => {
        // Mock log generation
        const interval = setInterval(() => {
            setLogs(prev => [...prev, `Log entry ${Date.now()}`]);
        }, 1000);

        return () => clearInterval(interval);
    }, []);

    return (
        <div>
            {logs.map((log, i) => <div key={i}>{log}</div>)}
        </div>
    );
}
```

**After (with real streaming):**
```tsx
import { useLogStream } from '@/hooks';

function BuildLogs({ deploymentId }: Props) {
    const {
        logs,
        isStreaming,
        clearLogs,
        downloadLogs,
    } = useLogStream({
        resourceType: 'deployment',
        resourceId: deploymentId,
        autoScroll: true,
        maxLogEntries: 1000,
    });

    return (
        <div>
            <div className="log-controls">
                <span>{isStreaming ? '🟢 Streaming' : '⏸️ Paused'}</span>
                <button onClick={clearLogs}>Clear</button>
                <button onClick={downloadLogs}>Download</button>
            </div>

            <div data-log-container className="log-viewer">
                {logs.map((log) => (
                    <div key={log.id} className={`log-${log.level}`}>
                        <span>[{new Date(log.timestamp).toLocaleTimeString()}]</span>
                        <span>{log.message}</span>
                    </div>
                ))}
            </div>
        </div>
    );
}
```

---

### 3. Service Status - Real-time Updates

**File:** `/home/user/saturn-Saturn/resources/js/pages/Services/Show.tsx`

**Add to existing component:**
```tsx
import { useRealtimeStatus } from '@/hooks';

function ServiceShow({ service: initialService }: Props) {
    const [service, setService] = useState(initialService);

    useRealtimeStatus({
        // Update service status in real-time
        onServiceStatusChange: (data) => {
            if (data.serviceId === service.id) {
                setService(prev => ({ ...prev, status: data.status }));
            }
        },

        // Handle deployment events for this service
        onDeploymentFinished: (data) => {
            if (data.applicationId === service.id) {
                // Refresh service data or show notification
                router.reload({ only: ['service'] });
            }
        },
    });

    return (
        <div>
            <ServiceCard service={service} />
        </div>
    );
}
```

---

### 4. Application Logs - Combined Example

**File:** `/home/user/saturn-Saturn/resources/js/pages/Services/Logs.tsx`

**Complete integration with both hooks:**
```tsx
import { useRealtimeStatus, useLogStream } from '@/hooks';
import { useState } from 'react';

interface LogsPageProps {
    application: Application;
}

function ApplicationLogs({ application }: LogsPageProps) {
    const [appStatus, setAppStatus] = useState(application.status);

    // Real-time status updates
    const { isConnected: statusConnected } = useRealtimeStatus({
        onApplicationStatusChange: (data) => {
            if (data.applicationId === application.id) {
                setAppStatus(data.status);
            }
        },
    });

    // Real-time log streaming
    const {
        logs,
        isStreaming,
        isConnected: logsConnected,
        clearLogs,
        toggleStreaming,
        downloadLogs,
    } = useLogStream({
        resourceType: 'application',
        resourceId: application.uuid,
        filterLevel: 'all',
        autoScroll: true,
    });

    return (
        <div className="logs-page">
            {/* Header */}
            <div className="header">
                <h1>{application.name}</h1>
                <div className="status-badges">
                    <span className={`badge badge-${appStatus}`}>
                        {appStatus}
                    </span>
                    <span className="badge">
                        {statusConnected && logsConnected ? '🟢 Live' : '🔴 Offline'}
                    </span>
                </div>
            </div>

            {/* Controls */}
            <div className="log-controls">
                <button onClick={toggleStreaming}>
                    {isStreaming ? 'Pause' : 'Resume'}
                </button>
                <button onClick={clearLogs}>Clear</button>
                <button onClick={downloadLogs}>Download</button>
            </div>

            {/* Log viewer */}
            <div className="log-container" data-log-container>
                {logs.length === 0 ? (
                    <div className="no-logs">No logs yet</div>
                ) : (
                    logs.map((log) => (
                        <div key={log.id} className={`log-entry log-${log.level}`}>
                            <span className="timestamp">
                                {new Date(log.timestamp).toLocaleTimeString()}
                            </span>
                            <span className="level">{log.level?.toUpperCase()}</span>
                            <span className="message">{log.message}</span>
                        </div>
                    ))
                )}
            </div>
        </div>
    );
}
```

---

## Common Patterns

### Pattern 1: Update State on Status Change

```tsx
const [resource, setResource] = useState(initialResource);

useRealtimeStatus({
    onApplicationStatusChange: (data) => {
        setResource(prev => ({ ...prev, status: data.status }));
    },
});
```

### Pattern 2: Show Toast Notifications

```tsx
import { toast } from 'sonner'; // or your toast library

useRealtimeStatus({
    onDeploymentFinished: (data) => {
        if (data.status === 'finished') {
            toast.success('Deployment completed!');
        } else if (data.status === 'failed') {
            toast.error('Deployment failed!');
        }
    },
});
```

### Pattern 3: Reload Page Data

```tsx
import { router } from '@inertiajs/react';

useRealtimeStatus({
    onApplicationStatusChange: () => {
        // Reload only specific props (Inertia partial reload)
        router.reload({ only: ['application', 'deployments'] });
    },
});
```

### Pattern 4: Conditional Subscriptions

```tsx
// Only subscribe when component is visible
const [isVisible, setIsVisible] = useState(true);

useRealtimeStatus({
    enableWebSocket: isVisible,
    onApplicationStatusChange: (data) => {
        // Only receives events when isVisible is true
    },
});
```

### Pattern 5: Multiple Resources

```tsx
// Monitor multiple applications
const [applications, setApplications] = useState<Application[]>([]);

useRealtimeStatus({
    onApplicationStatusChange: (data) => {
        setApplications(prev =>
            prev.map(app =>
                app.id === data.applicationId
                    ? { ...app, status: data.status }
                    : app
            )
        );
    },
});
```

---

## Hook Options Reference

### `useRealtimeStatus` Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `enableWebSocket` | boolean | `true` | Enable WebSocket connection |
| `pollingInterval` | number | `5000` | Polling interval in ms when WS unavailable |
| `onConnectionChange` | function | - | Called when connection status changes |
| `onApplicationStatusChange` | function | - | Application status update callback |
| `onDatabaseStatusChange` | function | - | Database status update callback |
| `onServiceStatusChange` | function | - | Service status update callback |
| `onServerStatusChange` | function | - | Server reachability callback |
| `onDeploymentCreated` | function | - | Deployment started callback |
| `onDeploymentFinished` | function | - | Deployment finished callback |

### `useLogStream` Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `resourceType` | string | - | **Required**: 'application', 'deployment', 'database', 'service' |
| `resourceId` | string/number | - | **Required**: Resource UUID or ID |
| `enableWebSocket` | boolean | `true` | Enable WebSocket connection |
| `pollingInterval` | number | `2000` | Polling interval in ms when WS unavailable |
| `maxLogEntries` | number | `1000` | Maximum logs to keep in memory |
| `autoScroll` | boolean | `true` | Auto-scroll to bottom on new logs |
| `filterLevel` | string | `'all'` | Filter by level: 'info', 'error', 'warning', 'debug', 'all' |
| `onLogEntry` | function | - | Called on each new log entry |
| `onStreamStart` | function | - | Called when streaming starts |
| `onStreamStop` | function | - | Called when streaming stops |

---

## Performance Tips

### 1. Limit Event Handlers

Only subscribe to events you actually need:

```tsx
// ❌ Bad - subscribing to everything
useRealtimeStatus({
    onApplicationStatusChange: () => {},
    onDatabaseStatusChange: () => {},
    onServiceStatusChange: () => {},
    onServerStatusChange: () => {},
    onDeploymentCreated: () => {},
    onDeploymentFinished: () => {},
});

// ✅ Good - only what you need
useRealtimeStatus({
    onApplicationStatusChange: () => {},
    onDeploymentFinished: () => {},
});
```

### 2. Reduce Log Entries

For long-running processes, limit memory usage:

```tsx
useLogStream({
    resourceType: 'deployment',
    resourceId: deploymentId,
    maxLogEntries: 100, // Keep only last 100 logs
});
```

### 3. Disable When Not Visible

Stop streaming when component is not visible:

```tsx
const [isVisible, setIsVisible] = useState(true);

useEffect(() => {
    const handleVisibilityChange = () => {
        setIsVisible(!document.hidden);
    };
    document.addEventListener('visibilitychange', handleVisibilityChange);
    return () => document.removeEventListener('visibilitychange', handleVisibilityChange);
}, []);

useLogStream({
    resourceType: 'deployment',
    resourceId: deploymentId,
    enableWebSocket: isVisible, // Disable when tab is hidden
});
```

### 4. Debounce State Updates

For rapid updates, debounce state changes:

```tsx
import { debounce } from 'lodash';

const updateStatus = debounce((status) => {
    setAppStatus(status);
}, 500);

useRealtimeStatus({
    onApplicationStatusChange: (data) => {
        updateStatus(data.status);
    },
});
```

---

## Troubleshooting

### Logs not streaming?

1. Check `data-log-container` attribute exists for auto-scroll
2. Verify `resourceId` is correct (UUID, not numeric ID)
3. Check API endpoint returns data: `/api/v1/{resource}/{id}/logs`
4. Look for errors in browser console

### Status not updating?

1. Verify WebSocket connection: check `isConnected` value
2. Check backend is broadcasting events
3. Verify you're listening to correct events
4. Check team/user IDs match authenticated user

### High memory usage?

1. Reduce `maxLogEntries` in `useLogStream`
2. Clear logs periodically
3. Disable streaming when not visible
4. Use log filtering (`filterLevel`)

### Connection keeps dropping?

1. Check Soketi container is running
2. Verify network stability
3. Check browser console for WebSocket errors
4. Increase reconnection attempts if needed

---

## Next Steps

1. **Start with one page** - Pick Deployments/BuildLogs as first integration
2. **Test WebSocket connection** - Verify Soketi is running and accessible
3. **Check browser console** - Look for connection and event logs
4. **Add to more pages** - Dashboard, Services, Databases
5. **Implement API endpoints** - Backend log streaming endpoints
6. **Write tests** - Unit and integration tests for hooks

For complete examples, see:
- `/home/user/saturn-Saturn/resources/js/hooks/README.md`
- `/home/user/saturn-Saturn/resources/js/hooks/examples/DeploymentMonitor.example.tsx`
- `/home/user/saturn-Saturn/WEBSOCKET_IMPLEMENTATION.md`
