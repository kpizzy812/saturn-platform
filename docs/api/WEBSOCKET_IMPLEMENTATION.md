# WebSocket Implementation Summary

This document summarizes the WebSocket real-time status updates and log streaming implementation for the Saturn React frontend.

## Files Created

### Core Files

1. **`/home/user/saturn-Saturn/resources/js/lib/echo.ts`**
   - Laravel Echo initialization and configuration
   - WebSocket connection management
   - Pusher/Soketi client setup
   - Connection helper functions

2. **`/home/user/saturn-Saturn/resources/js/hooks/useRealtimeStatus.ts`**
   - Custom hook for real-time status updates
   - Subscribes to team and user channels
   - Handles events: ApplicationStatusChanged, DatabaseStatusChanged, ServiceStatusChanged, ServerReachabilityChanged, DeploymentCreated, DeploymentFinished
   - Automatic WebSocket reconnection with exponential backoff
   - Polling fallback when WebSocket unavailable

3. **`/home/user/saturn-Saturn/resources/js/hooks/useLogStream.ts`**
   - Custom hook for streaming logs in real-time
   - Supports application, deployment, database, and service logs
   - WebSocket streaming with polling fallback
   - Features: auto-scroll, log filtering, download, pause/resume
   - Memory-efficient with configurable max entries

4. **`/home/user/saturn-Saturn/resources/js/hooks/index.ts`** (Updated)
   - Exports for new hooks
   - Centralized hook management

5. **`/home/user/saturn-Saturn/resources/js/app.tsx`** (Updated)
   - Initializes Laravel Echo on app startup
   - Sets up global WebSocket connection

### Documentation & Examples

6. **`/home/user/saturn-Saturn/resources/js/hooks/README.md`**
   - Comprehensive documentation for WebSocket hooks
   - Usage examples and API reference
   - Troubleshooting guide
   - Best practices

7. **`/home/user/saturn-Saturn/resources/js/hooks/examples/DeploymentMonitor.example.tsx`**
   - Complete working example
   - Shows how to use both hooks together
   - Deployment monitoring with live logs
   - Connection status indicators

## Features Implemented

### Real-time Status Updates (`useRealtimeStatus`)

✅ **WebSocket Events:**
- ApplicationStatusChanged (team channel)
- DatabaseStatusChanged (user channel)
- ServiceStatusChanged (team channel)
- ServerReachabilityChanged (team channel)
- DeploymentCreated (team channel)
- DeploymentFinished (team channel)

✅ **Features:**
- Automatic WebSocket connection
- Team-based and user-based channel subscriptions
- Auto-reconnection with exponential backoff (max 3 attempts)
- Polling fallback (5-second intervals by default)
- Connection status tracking
- Error handling

### Log Streaming (`useLogStream`)

✅ **Supported Resources:**
- Application logs
- Deployment logs
- Database logs
- Service logs

✅ **Features:**
- Real-time log streaming via WebSocket
- Polling fallback (2-second intervals by default)
- Auto-scroll to bottom
- Log filtering by level (info, error, warning, debug)
- Configurable max entries (default: 1000)
- Download logs as text file
- Pause/resume streaming
- Clear logs
- Incremental fetching

### Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                      React Frontend                          │
│                                                              │
│  ┌──────────────────┐           ┌──────────────────┐        │
│  │ useRealtimeStatus│           │  useLogStream    │        │
│  │                  │           │                  │        │
│  │ - Status events  │           │ - Log streaming  │        │
│  │ - Team channel   │           │ - Resource logs  │        │
│  │ - User channel   │           │ - Auto-scroll    │        │
│  └────────┬─────────┘           └─────────┬────────┘        │
│           │                               │                 │
│           └───────────┬───────────────────┘                 │
│                       │                                     │
│                ┌──────▼────────┐                            │
│                │  Laravel Echo  │                            │
│                │  (lib/echo.ts) │                            │
│                └──────┬────────┘                            │
│                       │                                     │
└───────────────────────┼─────────────────────────────────────┘
                        │
                        │ WebSocket (ws/wss)
                        │
┌───────────────────────▼─────────────────────────────────────┐
│              Pusher/Soketi Server                            │
│              (saturn-realtime container)                    │
│                                                              │
│  - Private channels: team.{id}, user.{id}                   │
│  - Channel authorization via /broadcasting/auth             │
└───────────────────────┬─────────────────────────────────────┘
                        │
                        │ Server-side events
                        │
┌───────────────────────▼─────────────────────────────────────┐
│                    Laravel Backend                           │
│                                                              │
│  ┌────────────────────────────────────────────────┐         │
│  │          Broadcast Events (app/Events/)         │         │
│  │                                                 │         │
│  │  - ApplicationStatusChanged                     │         │
│  │  - DatabaseStatusChanged                        │         │
│  │  - ServiceStatusChanged                         │         │
│  │  - ServerReachabilityChanged                    │         │
│  │  - DeploymentCreated                            │         │
│  │  - DeploymentFinished                           │         │
│  └────────────────────────────────────────────────┘         │
│                                                              │
│  Background Jobs → dispatch() → broadcast()                 │
└──────────────────────────────────────────────────────────────┘
```

## Usage Examples

### Basic Status Updates

```tsx
import { useRealtimeStatus } from '@/hooks';

function MyComponent() {
    const { isConnected, isPolling } = useRealtimeStatus({
        onApplicationStatusChange: (data) => {
            console.log('App status:', data.status);
        },
        onDeploymentFinished: (data) => {
            console.log('Deployment done:', data.status);
        },
    });

    return <div>Connected: {isConnected ? 'Yes' : 'No'}</div>;
}
```

### Log Streaming

```tsx
import { useLogStream } from '@/hooks';

function LogViewer({ deploymentId }) {
    const { logs, clearLogs, downloadLogs } = useLogStream({
        resourceType: 'deployment',
        resourceId: deploymentId,
    });

    return (
        <div>
            <button onClick={clearLogs}>Clear</button>
            <button onClick={downloadLogs}>Download</button>
            <div data-log-container>
                {logs.map(log => (
                    <div key={log.id}>{log.message}</div>
                ))}
            </div>
        </div>
    );
}
```

### Combined Example

See `/home/user/saturn-Saturn/resources/js/hooks/examples/DeploymentMonitor.example.tsx` for a complete working example combining both hooks.

## Environment Configuration

Add to `.env`:

```bash
# Broadcasting
BROADCAST_DRIVER=pusher

# Pusher/Soketi (Backend)
PUSHER_APP_ID=saturn
PUSHER_APP_KEY=saturn
PUSHER_APP_SECRET=saturn
PUSHER_BACKEND_HOST=saturn-realtime
PUSHER_BACKEND_PORT=6001
PUSHER_SCHEME=http

# Frontend (Vite)
VITE_PUSHER_HOST="${PUSHER_BACKEND_HOST}"
VITE_PUSHER_PORT="${PUSHER_BACKEND_PORT}"
VITE_PUSHER_SCHEME="${PUSHER_SCHEME}"
VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
```

## Testing WebSocket Connection

### 1. Verify Soketi is Running

```bash
docker ps | grep saturn-realtime
# Should show the Soketi container running on port 6001
```

### 2. Check Browser Console

Open browser DevTools and check for:
- WebSocket connection: `ws://localhost:6001/app/saturn`
- Channel subscriptions: `Subscribed to private-team.X`
- Event messages: `Received event: ApplicationStatusChanged`

### 3. Test in Component

```tsx
import { useRealtimeStatus } from '@/hooks';

function TestComponent() {
    const { isConnected, error } = useRealtimeStatus({
        onConnectionChange: (connected) => {
            console.log('WebSocket:', connected ? '✅ Connected' : '❌ Disconnected');
        },
    });

    return (
        <div>
            Status: {isConnected ? '🟢 Connected' : '🔴 Disconnected'}
            {error && <div>Error: {error.message}</div>}
        </div>
    );
}
```

## Polling Fallback

When WebSocket is unavailable (Soketi down, network issues, etc.), hooks automatically fall back to polling:

- **Status updates:** Poll every 5 seconds (configurable)
- **Log streaming:** Poll every 2 seconds (configurable)
- **Automatic retry:** Hooks attempt to reconnect to WebSocket periodically

## Integration with Existing Pages

### Pages that can use `useRealtimeStatus`:

1. **Dashboard** (`/home/user/saturn-Saturn/resources/js/pages/Dashboard.tsx`)
   - Real-time project status updates
   - Deployment notifications

2. **Projects/Show** (`/home/user/saturn-Saturn/resources/js/pages/Projects/Show.tsx`)
   - Application status on canvas nodes
   - Database status indicators
   - Service health updates

3. **Services/Show** (`/home/user/saturn-Saturn/resources/js/pages/Services/Show.tsx`)
   - Real-time service status
   - Deployment status updates

4. **Databases/Show** (`/home/user/saturn-Saturn/resources/js/pages/Databases/Show.tsx`)
   - Database connection status
   - Backup completion notifications

5. **Servers/Show** (`/home/user/saturn-Saturn/resources/js/pages/Servers/Show.tsx`)
   - Server reachability status
   - Resource metrics updates

### Pages that can use `useLogStream`:

1. **Services/Logs** (`/home/user/saturn-Saturn/resources/js/pages/Services/Logs.tsx`)
   - Replace mock log simulation
   - Real-time application logs

2. **Deployments/BuildLogs** (`/home/user/saturn-Saturn/resources/js/pages/Deployments/BuildLogs.tsx`)
   - Live deployment logs
   - Build step progress

3. **Databases/Logs** (if exists)
   - Database query logs
   - Error logs

## Next Steps

### To Complete Integration:

1. ✅ **WebSocket Setup** - Done
2. ✅ **Hooks Created** - Done
3. ✅ **Documentation** - Done
4. ⬜ **Update Existing Pages** - Integrate hooks into existing components
5. ⬜ **Backend API Endpoints** - Implement log streaming endpoints:
   - `GET /api/v1/applications/{uuid}/logs`
   - `GET /api/v1/deployments/{uuid}/logs`
   - `GET /api/v1/databases/{uuid}/logs`
   - `GET /api/v1/services/{uuid}/logs`
6. ⬜ **Backend Events** - Ensure events are dispatched:
   - Verify all status change events are broadcasting
   - Add log streaming events if needed
7. ⬜ **Testing** - Write tests for hooks:
   - Unit tests for connection logic
   - Integration tests with mock Echo
   - E2E tests with real WebSocket server

### Recommended First Integration:

Start with **Deployments/BuildLogs** page:
1. Replace mock log generation
2. Use `useLogStream` hook
3. Connect to `/api/v1/deployments/{uuid}/logs` endpoint
4. Test with real deployment

## Troubleshooting

### WebSocket not connecting?

1. Check Soketi container is running
2. Verify environment variables
3. Check browser console for errors
4. Ensure CSRF token meta tag exists in HTML

### Polling not working?

1. Check API endpoints exist and return data
2. Verify authentication tokens
3. Check CORS configuration

### High memory usage?

1. Reduce `maxLogEntries` in `useLogStream`
2. Clear logs periodically
3. Disable streaming when not visible

## Benefits

✅ **Real-time updates** - No page refresh needed
✅ **Better UX** - Instant feedback on status changes
✅ **Efficient** - WebSocket reduces server load vs constant polling
✅ **Resilient** - Automatic fallback to polling
✅ **Type-safe** - Full TypeScript support
✅ **Reusable** - Hooks can be used in any component
✅ **Scalable** - Designed for high-traffic cloud instance

## Related Files

- Backend Events: `/home/user/saturn-Saturn/app/Events/`
- Broadcasting Config: `/home/user/saturn-Saturn/config/broadcasting.php`
- Routes: `/home/user/saturn-Saturn/routes/channels.php` (for channel authorization)
- Frontend Hooks: `/home/user/saturn-Saturn/resources/js/hooks/`
- Documentation: `/home/user/saturn-Saturn/resources/js/hooks/README.md`

---

**Implementation Date:** 2026-01-03
**Status:** ✅ Complete (ready for integration into pages)
