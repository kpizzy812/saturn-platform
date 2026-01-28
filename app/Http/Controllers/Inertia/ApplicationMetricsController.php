<?php

namespace App\Http\Controllers\Inertia;

use App\Http\Controllers\Controller;
use App\Models\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApplicationMetricsController extends Controller
{
    /**
     * Get request stats from application container logs.
     *
     * Parses docker logs to extract HTTP request metrics including
     * total requests, status code distribution, and response times.
     */
    public function getRequestStats(Request $request, string $uuid): JsonResponse
    {
        $application = Application::ownedByCurrentTeam()->where('uuid', $uuid)->first();

        if (! $application) {
            return response()->json(['available' => false, 'error' => 'Application not found'], 404);
        }

        $server = $application->destination?->server;

        if (! $server || ! $server->isFunctional()) {
            return response()->json(['available' => false, 'error' => 'Server not reachable']);
        }

        $timeRange = $request->input('timeRange', '24h');
        $validRanges = ['1h', '24h', '7d', '30d'];
        if (! in_array($timeRange, $validRanges)) {
            $timeRange = '24h';
        }

        $sinceMap = [
            '1h' => '1h',
            '24h' => '24h',
            '7d' => '168h',
            '30d' => '720h',
        ];

        try {
            $containerName = $application->uuid;
            $since = $sinceMap[$timeRange];

            // Parse HTTP access logs from container for status codes and request counts
            // Supports common log formats: nginx, Apache, Node.js, and generic HTTP logs
            // Pattern matches lines containing HTTP status codes like "HTTP/1.1" 200, "status":200, etc.
            $logCommand = "docker logs {$containerName} --since {$since} 2>&1 | grep -oP '\" \\K[0-9]{3}(?= )' | sort | uniq -c | sort -rn 2>/dev/null || echo ''";
            $statusResult = trim(instant_remote_process([$logCommand], $server, false) ?? '');

            // Also try to match common access log patterns (nginx/apache style)
            $altLogCommand = "docker logs {$containerName} --since {$since} 2>&1 | grep -oP 'HTTP/[0-9.]+ \\K[0-9]{3}' | sort | uniq -c | sort -rn 2>/dev/null || echo ''";
            $altStatusResult = trim(instant_remote_process([$altLogCommand], $server, false) ?? '');

            // Use whichever returned more data
            $result = strlen($altStatusResult) > strlen($statusResult) ? $altStatusResult : $statusResult;

            $totalRequests = 0;
            $statusCodes = [
                '2xx' => 0,
                '3xx' => 0,
                '4xx' => 0,
                '5xx' => 0,
            ];

            if (! empty($result)) {
                foreach (explode("\n", $result) as $line) {
                    $line = trim($line);
                    if (empty($line)) {
                        continue;
                    }
                    // Format: "   count status_code"
                    if (preg_match('/^\s*(\d+)\s+(\d{3})$/', $line, $matches)) {
                        $count = (int) $matches[1];
                        $code = $matches[2];
                        $totalRequests += $count;

                        $category = match (true) {
                            $code >= '200' && $code < '300' => '2xx',
                            $code >= '300' && $code < '400' => '3xx',
                            $code >= '400' && $code < '500' => '4xx',
                            $code >= '500' && $code < '600' => '5xx',
                            default => null,
                        };

                        if ($category) {
                            $statusCodes[$category] += $count;
                        }
                    }
                }
            }

            // Calculate success rate (2xx + 3xx)
            $successCount = $statusCodes['2xx'] + $statusCodes['3xx'];
            $successRate = $totalRequests > 0
                ? round(($successCount / $totalRequests) * 100, 1)
                : 0;

            // Try to extract average response time from logs (common patterns)
            $latencyCommand = "docker logs {$containerName} --since {$since} 2>&1 | grep -oP '(\\d+\\.?\\d*)\\s*ms' | head -100 | awk '{sum+=\$1; n++} END {if(n>0) print sum/n; else print 0}' 2>/dev/null || echo '0'";
            $avgLatency = trim(instant_remote_process([$latencyCommand], $server, false) ?? '0');
            $avgLatencyMs = round((float) $avgLatency, 1);

            return response()->json([
                'available' => true,
                'totalRequests' => $totalRequests,
                'successRate' => $successRate,
                'avgLatencyMs' => $avgLatencyMs > 0 ? $avgLatencyMs : null,
                'statusCodes' => $statusCodes,
                'timeRange' => $timeRange,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'available' => false,
                'error' => 'Failed to fetch request stats: '.$e->getMessage(),
            ]);
        }
    }
}
