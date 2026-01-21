/**
 * Example: Real-time Deployment Monitor
 *
 * This component demonstrates how to use both useRealtimeStatus and useLogStream
 * hooks together to create a live deployment monitoring interface.
 *
 * Features:
 * - Real-time deployment status updates via WebSocket
 * - Live log streaming with auto-scroll
 * - Connection status indicators
 * - Polling fallback when WebSocket unavailable
 */

import { useState, useEffect } from 'react';
import { useRealtimeStatus, useLogStream } from '@/hooks';
import type { DeploymentStatus } from '@/types';

interface DeploymentMonitorProps {
    deploymentId: string;
    applicationId: number;
}

export function DeploymentMonitor({ deploymentId, applicationId }: DeploymentMonitorProps) {
    const [deploymentStatus, setDeploymentStatus] = useState<DeploymentStatus>('queued');
    const [startTime, setStartTime] = useState<Date>(new Date());
    const [duration, setDuration] = useState<string>('0s');

    // Real-time status updates
    const {
        isConnected: statusConnected,
        isPolling: statusPolling,
        error: statusError,
    } = useRealtimeStatus({
        // Handle deployment status changes
        onDeploymentCreated: (data) => {
            if (data.deploymentId === parseInt(deploymentId)) {
                setDeploymentStatus('in_progress');
                setStartTime(new Date());
            }
        },

        onDeploymentFinished: (data) => {
            if (data.deploymentId === parseInt(deploymentId)) {
                setDeploymentStatus(data.status);
            }
        },

        // Handle application status changes (when deployment completes)
        onApplicationStatusChange: (data) => {
            if (data.applicationId === applicationId) {
                console.log('Application status:', data.status);
            }
        },

        // Track connection status
        onConnectionChange: (connected) => {
            console.log('Status WebSocket:', connected ? 'connected' : 'disconnected');
        },
    });

    // Real-time log streaming
    const {
        logs,
        isStreaming,
        isConnected: logsConnected,
        isPolling: logsPolling,
        loading: logsLoading,
        error: logsError,
        clearLogs,
        toggleStreaming,
        downloadLogs,
    } = useLogStream({
        resourceType: 'deployment',
        resourceId: deploymentId,
        maxLogEntries: 500,
        autoScroll: true,

        // Filter to show all log levels
        filterLevel: 'all',

        // Handle new log entries
        onLogEntry: (entry) => {
            // Could show toast notifications for errors
            if (entry.level === 'error') {
                console.error('Deployment error:', entry.message);
            }
        },

        // Callbacks for streaming lifecycle
        onStreamStart: () => {
            console.log('Log streaming started');
        },

        onStreamStop: () => {
            console.log('Log streaming stopped');
        },
    });

    // Update duration timer
    useEffect(() => {
        if (deploymentStatus !== 'in_progress') {
            return;
        }

        const interval = setInterval(() => {
            const elapsed = Date.now() - startTime.getTime();
            const seconds = Math.floor(elapsed / 1000);
            const minutes = Math.floor(seconds / 60);
            const hours = Math.floor(minutes / 60);

            if (hours > 0) {
                setDuration(`${hours}h ${minutes % 60}m ${seconds % 60}s`);
            } else if (minutes > 0) {
                setDuration(`${minutes}m ${seconds % 60}s`);
            } else {
                setDuration(`${seconds}s`);
            }
        }, 1000);

        return () => clearInterval(interval);
    }, [deploymentStatus, startTime]);

    // Status badge color
    const getStatusColor = (status: DeploymentStatus) => {
        switch (status) {
            case 'queued':
                return 'bg-gray-500';
            case 'in_progress':
                return 'bg-blue-500 animate-pulse';
            case 'finished':
                return 'bg-green-500';
            case 'failed':
                return 'bg-red-500';
            case 'cancelled':
                return 'bg-yellow-500';
            default:
                return 'bg-gray-500';
        }
    };

    return (
        <div className="deployment-monitor space-y-4">
            {/* Header */}
            <div className="flex items-center justify-between p-4 bg-gray-800 rounded-lg">
                <div className="flex items-center gap-4">
                    <div className="flex flex-col">
                        <h2 className="text-xl font-bold text-white">
                            Deployment {deploymentId}
                        </h2>
                        <div className="flex items-center gap-2 mt-1">
                            <span
                                className={`px-2 py-1 text-xs font-semibold text-white rounded ${getStatusColor(
                                    deploymentStatus
                                )}`}
                            >
                                {deploymentStatus.toUpperCase()}
                            </span>
                            {deploymentStatus === 'in_progress' && (
                                <span className="text-sm text-gray-400">
                                    {duration}
                                </span>
                            )}
                        </div>
                    </div>
                </div>

                {/* Connection Indicators */}
                <div className="flex items-center gap-4">
                    <div className="flex items-center gap-2">
                        <div
                            className={`w-2 h-2 rounded-full ${
                                statusConnected ? 'bg-green-500' : 'bg-red-500'
                            } ${statusConnected ? 'animate-pulse' : ''}`}
                        />
                        <span className="text-sm text-gray-400">
                            Status {statusPolling ? '(polling)' : ''}
                        </span>
                    </div>

                    <div className="flex items-center gap-2">
                        <div
                            className={`w-2 h-2 rounded-full ${
                                logsConnected ? 'bg-green-500' : 'bg-red-500'
                            } ${logsConnected ? 'animate-pulse' : ''}`}
                        />
                        <span className="text-sm text-gray-400">
                            Logs {logsPolling ? '(polling)' : ''}
                        </span>
                    </div>
                </div>
            </div>

            {/* Error Messages */}
            {(statusError || logsError) && (
                <div className="p-4 bg-red-900/20 border border-red-500 rounded-lg">
                    <p className="text-sm text-red-400">
                        {statusError?.message || logsError?.message}
                    </p>
                </div>
            )}

            {/* Log Viewer */}
            <div className="bg-gray-900 rounded-lg overflow-hidden">
                {/* Log Controls */}
                <div className="flex items-center justify-between p-3 bg-gray-800 border-b border-gray-700">
                    <div className="flex items-center gap-2">
                        <h3 className="text-sm font-semibold text-white">
                            Build Logs
                        </h3>
                        {logsLoading && (
                            <span className="text-xs text-gray-400">Loading...</span>
                        )}
                    </div>

                    <div className="flex items-center gap-2">
                        <button
                            onClick={toggleStreaming}
                            className="px-3 py-1 text-xs font-medium text-white bg-gray-700 rounded hover:bg-gray-600 transition-colors"
                        >
                            {isStreaming ? 'Pause' : 'Resume'}
                        </button>
                        <button
                            onClick={clearLogs}
                            className="px-3 py-1 text-xs font-medium text-white bg-gray-700 rounded hover:bg-gray-600 transition-colors"
                        >
                            Clear
                        </button>
                        <button
                            onClick={downloadLogs}
                            className="px-3 py-1 text-xs font-medium text-white bg-gray-700 rounded hover:bg-gray-600 transition-colors"
                        >
                            Download
                        </button>
                    </div>
                </div>

                {/* Log Content */}
                <div
                    className="p-4 h-96 overflow-y-auto font-mono text-sm bg-black"
                    data-log-container
                >
                    {logs.length === 0 && !logsLoading ? (
                        <div className="flex items-center justify-center h-full text-gray-500">
                            No logs yet
                        </div>
                    ) : (
                        logs.map((log) => (
                            <div
                                key={log.id}
                                className={`py-1 ${
                                    log.level === 'error'
                                        ? 'text-red-400'
                                        : log.level === 'warning'
                                        ? 'text-yellow-400'
                                        : log.level === 'debug'
                                        ? 'text-gray-500'
                                        : 'text-gray-300'
                                }`}
                            >
                                <span className="text-gray-600 mr-2">
                                    {new Date(log.timestamp).toLocaleTimeString()}
                                </span>
                                {log.level && (
                                    <span className="mr-2 font-semibold">
                                        [{log.level.toUpperCase()}]
                                    </span>
                                )}
                                <span>{log.message}</span>
                            </div>
                        ))
                    )}
                </div>
            </div>

            {/* Status Summary */}
            <div className="grid grid-cols-3 gap-4">
                <div className="p-4 bg-gray-800 rounded-lg">
                    <p className="text-sm text-gray-400">Status Updates</p>
                    <p className="text-lg font-semibold text-white">
                        {statusConnected ? 'Real-time' : 'Polling'}
                    </p>
                </div>

                <div className="p-4 bg-gray-800 rounded-lg">
                    <p className="text-sm text-gray-400">Log Entries</p>
                    <p className="text-lg font-semibold text-white">{logs.length}</p>
                </div>

                <div className="p-4 bg-gray-800 rounded-lg">
                    <p className="text-sm text-gray-400">Duration</p>
                    <p className="text-lg font-semibold text-white">{duration}</p>
                </div>
            </div>
        </div>
    );
}

/**
 * Usage in a page component:
 *
 * import { DeploymentMonitor } from '@/hooks/examples/DeploymentMonitor.example';
 *
 * function DeploymentPage({ deployment }) {
 *     return (
 *         <DeploymentMonitor
 *             deploymentId={deployment.uuid}
 *             applicationId={deployment.application_id}
 *         />
 *     );
 * }
 */
