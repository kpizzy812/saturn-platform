<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

class GitController extends Controller
{
    /**
     * Parse a git repository URL and extract platform, owner, and repo.
     */
    private function parseRepositoryUrl(string $url): ?array
    {
        // Remove trailing .git if present
        $url = preg_replace('/\.git$/', '', $url);

        // GitHub: https://github.com/owner/repo
        if (preg_match('#^https?://github\.com/([^/]+)/([^/]+)#', $url, $matches)) {
            return [
                'platform' => 'github',
                'owner' => $matches[1],
                'repo' => $matches[2],
                'api_url' => 'https://api.github.com',
            ];
        }

        // GitLab: https://gitlab.com/owner/repo
        if (preg_match('#^https?://gitlab\.com/([^/]+)/([^/]+)#', $url, $matches)) {
            return [
                'platform' => 'gitlab',
                'owner' => $matches[1],
                'repo' => $matches[2],
                'api_url' => 'https://gitlab.com/api/v4',
            ];
        }

        // Bitbucket: https://bitbucket.org/owner/repo
        if (preg_match('#^https?://bitbucket\.org/([^/]+)/([^/]+)#', $url, $matches)) {
            return [
                'platform' => 'bitbucket',
                'owner' => $matches[1],
                'repo' => $matches[2],
                'api_url' => 'https://api.bitbucket.org/2.0',
            ];
        }

        return null;
    }

    /**
     * Sort branches so the default branch comes first, then alphabetically.
     */
    private function sortBranchesByDefault(array $branches): array
    {
        usort($branches, function ($a, $b) {
            if ($a['is_default'] && ! $b['is_default']) {
                return -1;
            }
            if (! $a['is_default'] && $b['is_default']) {
                return 1;
            }

            return strcasecmp($a['name'], $b['name']);
        });

        return $branches;
    }

    /**
     * Fetch branches from GitHub API.
     * When $githubAppId is provided, uses the GitHub App installation token for private repos.
     */
    private function fetchGitHubBranches(string $owner, string $repo, ?int $githubAppId = null): array
    {
        $headers = [
            'Accept' => 'application/vnd.github.v3+json',
            'User-Agent' => 'Saturn-Platform',
        ];

        // Authenticate via GitHub App for private repositories
        if ($githubAppId) {
            try {
                $githubApp = \App\Models\GithubApp::findOrFail($githubAppId);
                $token = generateGithubInstallationToken($githubApp);
                $headers['Authorization'] = "token {$token}";
            } catch (\Exception $e) {
                Log::warning('GitHub App token generation failed, falling back to unauthenticated request', [
                    'github_app_id' => $githubAppId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $response = Http::withHeaders($headers)
            ->timeout(15)
            ->retry(2, 100, throw: false)
            ->get("https://api.github.com/repos/{$owner}/{$repo}/branches", [
                'per_page' => 100,
            ]);

        if ($response->failed()) {
            return [
                'success' => false,
                'error' => $response->json('message', 'Failed to fetch branches'),
                'status' => $response->status(),
            ];
        }

        $branches = collect($response->json())->map(fn ($branch) => [
            'name' => $branch['name'],
            'is_default' => false,
        ])->toArray();

        // Try to get default branch info
        $repoResponse = Http::withHeaders($headers)
            ->timeout(10)
            ->get("https://api.github.com/repos/{$owner}/{$repo}");

        $defaultBranch = $repoResponse->json('default_branch', 'main');

        // Mark default branch
        foreach ($branches as &$branch) {
            if ($branch['name'] === $defaultBranch) {
                $branch['is_default'] = true;
            }
        }

        $branches = $this->sortBranchesByDefault($branches);

        return [
            'success' => true,
            'branches' => $branches,
            'default_branch' => $defaultBranch,
        ];
    }

    /**
     * Fetch branches from GitLab API.
     */
    private function fetchGitLabBranches(string $owner, string $repo): array
    {
        $projectPath = urlencode("{$owner}/{$repo}");

        $response = Http::withHeaders([
            'User-Agent' => 'Saturn-Platform',
        ])
            ->timeout(15)
            ->retry(2, 100, throw: false)
            ->get("https://gitlab.com/api/v4/projects/{$projectPath}/repository/branches", [
                'per_page' => 100,
            ]);

        if ($response->failed()) {
            return [
                'success' => false,
                'error' => $response->json('message', 'Failed to fetch branches'),
                'status' => $response->status(),
            ];
        }

        // Get project info for default branch
        $projectResponse = Http::withHeaders([
            'User-Agent' => 'Saturn-Platform',
        ])
            ->timeout(10)
            ->get("https://gitlab.com/api/v4/projects/{$projectPath}");

        $defaultBranch = $projectResponse->json('default_branch', 'main');

        $branches = collect($response->json())->map(fn ($branch) => [
            'name' => $branch['name'],
            'is_default' => $branch['name'] === $defaultBranch,
        ])->toArray();

        $branches = $this->sortBranchesByDefault($branches);

        return [
            'success' => true,
            'branches' => $branches,
            'default_branch' => $defaultBranch,
        ];
    }

    /**
     * Fetch branches from Bitbucket API.
     */
    private function fetchBitbucketBranches(string $owner, string $repo): array
    {
        $response = Http::withHeaders([
            'User-Agent' => 'Saturn-Platform',
        ])
            ->timeout(15)
            ->retry(2, 100, throw: false)
            ->get("https://api.bitbucket.org/2.0/repositories/{$owner}/{$repo}/refs/branches", [
                'pagelen' => 100,
            ]);

        if ($response->failed()) {
            return [
                'success' => false,
                'error' => $response->json('error.message', 'Failed to fetch branches'),
                'status' => $response->status(),
            ];
        }

        // Get repo info for default branch
        $repoResponse = Http::withHeaders([
            'User-Agent' => 'Saturn-Platform',
        ])
            ->timeout(10)
            ->get("https://api.bitbucket.org/2.0/repositories/{$owner}/{$repo}");

        $defaultBranch = $repoResponse->json('mainbranch.name', 'main');

        $branches = collect($response->json('values', []))->map(fn ($branch) => [
            'name' => $branch['name'],
            'is_default' => $branch['name'] === $defaultBranch,
        ])->toArray();

        $branches = $this->sortBranchesByDefault($branches);

        return [
            'success' => true,
            'branches' => $branches,
            'default_branch' => $defaultBranch,
        ];
    }

    /**
     * Get branches for a public git repository.
     */
    #[OA\Get(
        path: '/git/branches',
        operationId: 'getGitBranches',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Git'],
        summary: 'Get Repository Branches',
        description: 'Fetch branches from a public git repository (GitHub, GitLab, or Bitbucket).',
        parameters: [
            new OA\Parameter(
                name: 'repository_url',
                in: 'query',
                required: true,
                description: 'The full repository URL (e.g., https://github.com/owner/repo)',
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of branches',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'branches', type: 'array', items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'name', type: 'string'),
                                new OA\Property(property: 'is_default', type: 'boolean'),
                            ]
                        )),
                        new OA\Property(property: 'default_branch', type: 'string'),
                        new OA\Property(property: 'platform', type: 'string'),
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Invalid repository URL'
            ),
            new OA\Response(
                response: 404,
                description: 'Repository not found'
            ),
        ]
    )]
    public function branches(Request $request)
    {
        $repositoryUrl = $request->query('repository_url');
        $githubAppId = $request->query('github_app_id') ? (int) $request->query('github_app_id') : null;

        if (empty($repositoryUrl)) {
            return response()->json([
                'message' => 'Repository URL is required',
            ], 400);
        }

        $parsed = $this->parseRepositoryUrl($repositoryUrl);

        if (! $parsed) {
            return response()->json([
                'message' => 'Invalid repository URL. Supported platforms: GitHub, GitLab, Bitbucket',
            ], 400);
        }

        // Cache key based on repository URL (include github_app_id for authenticated requests)
        $cacheKey = 'git_branches_'.md5($repositoryUrl.($githubAppId ? '_app_'.$githubAppId : ''));

        // Try to get from cache (5 minutes TTL)
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return response()->json($cached);
        }

        // Fetch branches based on platform (pass github_app_id for GitHub private repos)
        $result = match ($parsed['platform']) {
            'github' => $this->fetchGitHubBranches($parsed['owner'], $parsed['repo'], $githubAppId),
            'gitlab' => $this->fetchGitLabBranches($parsed['owner'], $parsed['repo']),
            'bitbucket' => $this->fetchBitbucketBranches($parsed['owner'], $parsed['repo']),
            default => ['success' => false, 'error' => 'Unsupported platform'],
        };

        if (! $result['success']) {
            $status = $result['status'] ?? 500;
            if ($status === 404) {
                return response()->json([
                    'message' => 'Repository not found or is private',
                ], 404);
            }

            return response()->json([
                'message' => $result['error'] ?? 'Failed to fetch branches',
            ], $status);
        }

        $responseData = [
            'branches' => $result['branches'],
            'default_branch' => $result['default_branch'],
            'platform' => $parsed['platform'],
        ];

        // Cache the result for 5 minutes
        Cache::put($cacheKey, $responseData, 300);

        return response()->json($responseData);
    }
}
