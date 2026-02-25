<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Server;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class SentinelMetricsController extends Controller
{
    /**
     * Time range configuration mapping
     * Maps frontend time range values to minutes for API queries
     */
    private const TIME_RANGE_MINUTES = [
        '1h' => 60,
        '24h' => 1440,
        '7d' => 10080,
        '30d' => 43200,
    ];

    /**
     * Data point intervals for each time range
     */
    private const TIME_RANGE_INTERVALS = [
        '1h' => 12,   // 5-minute intervals
        '24h' => 24,  // 1-hour intervals
        '7d' => 28,   // 6-hour intervals
        '30d' => 30,  // 1-day intervals
    ];

    #[OA\Get(
        summary: 'Get Sentinel Metrics',
        description: 'Get server metrics from Sentinel agent including CPU, memory, disk usage and optional process/container data.',
        path: '/servers/{uuid}/sentinel/metrics',
        operationId: 'get-sentinel-metrics',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Servers'],
        parameters: [
            new OA\Parameter(
                name: 'uuid',
                in: 'path',
                required: true,
                description: 'Server UUID',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'timeRange',
                in: 'query',
                required: false,
                description: 'Time range for historical data',
                schema: new OA\Schema(type: 'string', enum: ['1h', '24h', '7d', '30d'], default: '24h')
            ),
            new OA\Parameter(
                name: 'includeProcesses',
                in: 'query',
                required: false,
                description: 'Include process list',
                schema: new OA\Schema(type: 'boolean', default: false)
            ),
            new OA\Parameter(
                name: 'includeContainers',
                in: 'query',
                required: false,
                description: 'Include container stats',
                schema: new OA\Schema(type: 'boolean', default: false)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Server metrics data',
                content: new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(
                        type: 'object',
                        properties: [
                            'metrics' => new OA\Property(
                                property: 'metrics',
                                type: 'object',
                                description: 'Current server metrics'
                            ),
                            'historicalData' => new OA\Property(
                                property: 'historicalData',
                                type: 'object',
                                description: 'Historical metrics data for charts'
                            ),
                            'alerts' => new OA\Property(
                                property: 'alerts',
                                type: 'array',
                                items: new OA\Items(type: 'object'),
                                description: 'Active alerts'
                            ),
                            'processes' => new OA\Property(
                                property: 'processes',
                                type: 'array',
                                items: new OA\Items(type: 'object'),
                                description: 'Process list (if requested)'
                            ),
                            'containers' => new OA\Property(
                                property: 'containers',
                                type: 'array',
                                items: new OA\Items(type: 'object'),
                                description: 'Container stats (if requested)'
                            ),
                        ]
                    )
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/401'),
            new OA\Response(response: 404, ref: '#/components/responses/404'),
            new OA\Response(response: 503, description: 'Sentinel not available'),
        ]
    )]
    public function metrics(Request $request, string $uuid): JsonResponse
    {
        // Support both API token and session-based authentication (SPA)
        $teamId = null;

        try {
            $teamId = getTeamIdFromToken();
        } catch (\Throwable) {
            // Token-based auth failed, try session
        }

        if (is_null($teamId)) {
            // Try session-based auth for SPA frontend
            try {
                $currentTeam = currentTeam();
                if ($currentTeam) {
                    $teamId = $currentTeam->id;
                }
            } catch (\Throwable) {
                // Session auth also failed
            }
        }

        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $server = Server::whereTeamId($teamId)->whereUuid($uuid)->first();
        if (! $server) {
            return response()->json(['message' => 'Server not found.'], 404);
        }

        // Validate time range parameter
        $timeRange = $request->query('timeRange', '24h');
        if (! array_key_exists($timeRange, self::TIME_RANGE_MINUTES)) {
            $timeRange = '24h';
        }

        $includeProcesses = filter_var($request->query('includeProcesses', false), FILTER_VALIDATE_BOOLEAN);
        $includeContainers = filter_var($request->query('includeContainers', false), FILTER_VALIDATE_BOOLEAN);

        // Check if Sentinel is enabled and server is functional
        if (! $server->isFunctional()) {
            return response()->json([
                'message' => 'Server is not reachable.',
                'sentinel_enabled' => false,
            ], 503);
        }

        if (! $server->isServerApiEnabled()) {
            return response()->json([
                'message' => 'Sentinel is not enabled on this server.',
                'sentinel_enabled' => false,
            ], 503);
        }

        try {
            $minutes = self::TIME_RANGE_MINUTES[$timeRange];

            // Fetch metrics from Sentinel
            $cpuData = $server->getCpuMetrics($minutes);
            $memoryData = $server->getMemoryMetrics($minutes);
            $diskUsage = $server->getDiskUsage();
            $networkStats = $this->getNetworkStats($server);

            // Build response
            $response = [
                'metrics' => $this->buildCurrentMetrics($server, $cpuData, $memoryData, $diskUsage, $networkStats),
                'historicalData' => $this->buildHistoricalData($cpuData, $memoryData, $diskUsage, $timeRange, $networkStats),
                'alerts' => $this->buildAlerts($cpuData, $memoryData, $diskUsage),
            ];

            // Add optional data
            if ($includeProcesses) {
                $response['processes'] = $this->getProcesses($server);
            }

            if ($includeContainers) {
                $response['containers'] = $this->getContainers($server);
            }

            return response()->json($response);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch metrics from Sentinel.',
                'error' => $e->getMessage(),
            ], 503);
        }
    }

    /**
     * Build current metrics object for frontend
     */
    private function buildCurrentMetrics(Server $server, $cpuData, $memoryData, $diskUsage, ?array $networkStats = null): array
    {
        // Get latest values from historical data
        $cpuPercent = 0;
        $memoryPercent = 0;

        if ($cpuData && $cpuData->isNotEmpty()) {
            $cpuPercent = $cpuData->last()[1] ?? 0;
        }

        if ($memoryData && count($memoryData) > 0) {
            $lastMemory = end($memoryData);
            $memoryPercent = $lastMemory[1] ?? 0;
        }

        $diskPercent = (int) ($diskUsage ?? 0);

        // Get actual total memory from latest health check
        $memoryTotalBytes = $server->healthChecks()
            ->whereNotNull('memory_total_bytes')
            ->latest('checked_at')
            ->value('memory_total_bytes');

        $memoryTotalBytes = $memoryTotalBytes ?: 0;
        $memoryUsedBytes = $memoryTotalBytes > 0
            ? (int) ($memoryPercent / 100 * $memoryTotalBytes)
            : 0;

        return [
            'cpu' => [
                'current' => round($cpuPercent, 1).'%',
                'percentage' => round($cpuPercent, 1),
                'trend' => $cpuData ? $cpuData->pluck(1)->take(-20)->values()->toArray() : [],
            ],
            'memory' => [
                'current' => $memoryTotalBytes > 0 ? $this->formatBytes($memoryUsedBytes) : 'N/A',
                'percentage' => round($memoryPercent, 1),
                'total' => $memoryTotalBytes > 0 ? $this->formatBytes($memoryTotalBytes) : 'N/A',
                'trend' => $memoryData ? collect($memoryData)->map(fn ($item) => $item[1] ?? 0)->take(-20)->values()->toArray() : [],
            ],
            'disk' => [
                'current' => $diskPercent.'%',
                'percentage' => $diskPercent,
                'total' => '100%',
                'trend' => array_fill(0, 20, $diskPercent), // Disk doesn't change much
            ],
            'network' => [
                'current' => $networkStats ? ($networkStats['in'].' / '.$networkStats['out']) : 'N/A',
                'in' => $networkStats['in'] ?? 'N/A',
                'out' => $networkStats['out'] ?? 'N/A',
            ],
        ];
    }

    /**
     * Build historical data for charts
     */
    private function buildHistoricalData($cpuData, $memoryData, $diskUsage, string $timeRange, ?array $networkStats = null): array
    {
        $interval = self::TIME_RANGE_INTERVALS[$timeRange];

        // Process CPU data
        $cpuHistorical = $this->processHistoricalData($cpuData, $interval);

        // Process Memory data
        $memoryHistorical = $this->processHistoricalData(
            $memoryData ? collect($memoryData) : collect([]),
            $interval
        );

        // Disk historical (static)
        $diskPercent = (int) ($diskUsage ?? 0);
        $diskHistorical = [
            'data' => array_map(fn ($i) => ['label' => (string) $i, 'value' => $diskPercent], range(0, $interval - 1)),
            'average' => $diskPercent.'%',
            'peak' => $diskPercent.'%',
        ];

        // Network - show total transferred
        $networkHistorical = [
            'data' => [],
            'average' => $networkStats['in'] ?? 'N/A',
            'peak' => $networkStats['out'] ?? 'N/A',
        ];

        return [
            'cpu' => $cpuHistorical,
            'memory' => $memoryHistorical,
            'disk' => $diskHistorical,
            'network' => $networkHistorical,
        ];
    }

    /**
     * Process raw metric data into historical format
     */
    private function processHistoricalData($data, int $targetPoints): array
    {
        if (! $data || $data->isEmpty()) {
            return [
                'data' => [],
                'average' => '0%',
                'peak' => '0%',
            ];
        }

        $values = $data->pluck('1')->toArray();

        // Resample data to target number of points
        $resampled = $this->resampleData($values, $targetPoints);

        $dataPoints = array_map(function ($value, $index) {
            return [
                'label' => (string) $index,
                'value' => round($value, 1),
            ];
        }, $resampled, array_keys($resampled));

        $average = count($values) > 0 ? array_sum($values) / count($values) : 0;
        $peak = count($values) > 0 ? max($values) : 0;

        return [
            'data' => $dataPoints,
            'average' => round($average, 1).'%',
            'peak' => round($peak, 1).'%',
        ];
    }

    /**
     * Resample data array to target number of points
     */
    private function resampleData(array $data, int $targetPoints): array
    {
        $count = count($data);

        if ($count === 0) {
            return array_fill(0, $targetPoints, 0);
        }

        if ($count <= $targetPoints) {
            // Pad with last value if not enough data
            return array_pad($data, $targetPoints, end($data));
        }

        // Downsample by averaging chunks
        $chunkSize = ceil($count / $targetPoints);
        $result = [];

        for ($i = 0; $i < $targetPoints; $i++) {
            $start = (int) ($i * $chunkSize);
            $chunk = array_slice($data, $start, (int) $chunkSize);
            $result[] = count($chunk) > 0 ? array_sum($chunk) / count($chunk) : 0;
        }

        return $result;
    }

    /**
     * Build alerts based on current metrics
     */
    private function buildAlerts($cpuData, $memoryData, $diskUsage): array
    {
        $alerts = [];
        $alertId = 1;

        // Check CPU alerts
        if ($cpuData && $cpuData->isNotEmpty()) {
            $cpuPercent = $cpuData->last()[1] ?? 0;

            if ($cpuPercent > 90) {
                $alerts[] = [
                    'id' => $alertId++,
                    'title' => 'Critical CPU Usage',
                    'message' => "CPU usage is at {$cpuPercent}%, which is critically high.",
                    'severity' => 'critical',
                    'timestamp' => now()->toIso8601String(),
                ];
            } elseif ($cpuPercent > 75) {
                $alerts[] = [
                    'id' => $alertId++,
                    'title' => 'High CPU Usage',
                    'message' => "CPU usage is at {$cpuPercent}%, consider investigating.",
                    'severity' => 'warning',
                    'timestamp' => now()->toIso8601String(),
                ];
            }
        }

        // Check Memory alerts
        if ($memoryData && count($memoryData) > 0) {
            $lastMemory = end($memoryData);
            $memoryPercent = $lastMemory[1] ?? 0;

            if ($memoryPercent > 90) {
                $alerts[] = [
                    'id' => $alertId++,
                    'title' => 'Critical Memory Usage',
                    'message' => "Memory usage is at {$memoryPercent}%, which is critically high.",
                    'severity' => 'critical',
                    'timestamp' => now()->toIso8601String(),
                ];
            } elseif ($memoryPercent > 80) {
                $alerts[] = [
                    'id' => $alertId++,
                    'title' => 'High Memory Usage',
                    'message' => "Memory usage is at {$memoryPercent}%, consider investigating.",
                    'severity' => 'warning',
                    'timestamp' => now()->toIso8601String(),
                ];
            }
        }

        // Check Disk alerts
        $diskPercent = (int) ($diskUsage ?? 0);

        if ($diskPercent > 90) {
            $alerts[] = [
                'id' => $alertId++,
                'title' => 'Critical Disk Usage',
                'message' => "Disk usage is at {$diskPercent}%, free up space immediately.",
                'severity' => 'critical',
                'timestamp' => now()->toIso8601String(),
            ];
        } elseif ($diskPercent > 80) {
            $alerts[] = [
                'id' => $alertId++,
                'title' => 'High Disk Usage',
                'message' => "Disk usage is at {$diskPercent}%, consider cleaning up.",
                'severity' => 'warning',
                'timestamp' => now()->toIso8601String(),
            ];
        }

        return $alerts;
    }

    /**
     * Get network I/O stats from server via /proc/net/dev
     * Returns total RX/TX bytes across all physical interfaces
     */
    private function getNetworkStats(Server $server): ?array
    {
        try {
            // Read /proc/net/dev - sum RX (col 2) and TX (col 10) for non-loopback interfaces
            $output = instant_remote_process([
                "cat /proc/net/dev | awk 'NR>2 && !/lo:/{rx+=$2; tx+=$10} END{print rx, tx}'",
            ], $server, false);

            $parts = preg_split('/\s+/', trim($output ?? ''));
            if (count($parts) < 2) {
                return null;
            }

            $rxBytes = (float) $parts[0];
            $txBytes = (float) $parts[1];

            return [
                'in' => $this->formatBytes($rxBytes),
                'out' => $this->formatBytes($txBytes),
                'rx_bytes' => $rxBytes,
                'tx_bytes' => $txBytes,
            ];
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Get process list from server
     */
    private function getProcesses(Server $server): array
    {
        try {
            $output = instant_remote_process([
                "ps aux --sort=-%cpu | head -11 | tail -10 | awk '{print $2,$1,$3,$4,$11}'",
            ], $server, false);

            $processes = [];
            $lines = explode("\n", trim($output));

            foreach ($lines as $line) {
                $parts = preg_split('/\s+/', trim($line), 5);
                if (count($parts) >= 5) {
                    $processes[] = [
                        'pid' => (int) $parts[0],
                        'user' => $parts[1],
                        'cpu' => (float) $parts[2],
                        'memory' => (float) $parts[3],
                        'name' => $parts[4],
                    ];
                }
            }

            return $processes;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get container stats from server
     */
    private function getContainers(Server $server): array
    {
        try {
            $output = instant_remote_process([
                "docker stats --no-stream --format '{{json .}}'",
            ], $server, false);

            $containers = [];
            $lines = explode("\n", trim($output));

            foreach ($lines as $line) {
                if (empty($line)) {
                    continue;
                }

                $data = json_decode($line, true);
                if (! $data) {
                    continue;
                }

                $containers[] = [
                    'name' => $data['Name'] ?? 'unknown',
                    'cpu' => (float) str_replace('%', '', $data['CPUPerc'] ?? '0'),
                    'memory' => (float) str_replace('%', '', $data['MemPerc'] ?? '0'),
                    'network_in' => $this->parseNetworkIO($data['NetIO'] ?? '0B / 0B', 'in'),
                    'network_out' => $this->parseNetworkIO($data['NetIO'] ?? '0B / 0B', 'out'),
                    'status' => 'running',
                ];
            }

            return $containers;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Parse network I/O string from docker stats
     */
    private function parseNetworkIO(string $netIO, string $direction): string
    {
        $parts = explode(' / ', $netIO);

        if ($direction === 'in') {
            return trim($parts[0]);
        }

        if ($direction === 'out' && isset($parts[1])) {
            return trim($parts[1]);
        }

        return '0B';
    }

    /**
     * Format bytes to human readable string
     */
    private function formatBytes(float $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 1).' '.$units[$pow];
    }
}
