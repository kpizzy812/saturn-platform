<?php

namespace App\Services\AI\CodeReview;

use App\Models\Application;
use App\Models\GithubApp;
use App\Services\AI\CodeReview\DTOs\DiffFile;
use App\Services\AI\CodeReview\DTOs\DiffLine;
use App\Services\AI\CodeReview\DTOs\DiffResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fetches git diff from GitHub API.
 *
 * Supports both GitHub App (authenticated) and public repositories.
 */
class GitDiffFetcher
{
    /**
     * File patterns to exclude from analysis.
     */
    private array $excludePatterns;

    /**
     * Maximum diff lines to process.
     */
    private int $maxDiffLines;

    public function __construct()
    {
        $this->excludePatterns = config('ai.code_review.exclude_patterns', [
            'vendor/*',
            'node_modules/*',
            '*.lock',
            'package-lock.json',
            'composer.lock',
            'yarn.lock',
            'pnpm-lock.yaml',
            '*.min.js',
            '*.min.css',
            'public/build/*',
            'dist/*',
        ]);

        $this->maxDiffLines = config('ai.code_review.max_diff_lines', 3000);
    }

    /**
     * Fetch diff for a commit.
     *
     * @param  string|null  $baseCommit  If null, compares against parent commit
     */
    public function fetch(Application $application, string $commitSha, ?string $baseCommit = null): DiffResult
    {
        $source = $application->source;

        // Get repository path (owner/repo format)
        $repository = $this->resolveRepository($application);

        if (empty($repository)) {
            throw new \InvalidArgumentException('Application has no git repository configured');
        }

        // Determine if we use authenticated or public API
        $useAuthenticatedApi = $source instanceof GithubApp;

        // Determine base commit
        if ($baseCommit === null) {
            $baseCommit = $this->getParentCommit($source, $repository, $commitSha, $useAuthenticatedApi);
        }

        // Fetch the comparison
        if ($baseCommit) {
            return $this->fetchComparison($source, $repository, $baseCommit, $commitSha, $useAuthenticatedApi);
        }

        // For initial commits, get the commit itself
        return $this->fetchSingleCommit($source, $repository, $commitSha, $useAuthenticatedApi);
    }

    /**
     * Resolve repository path from application.
     *
     * Returns owner/repo format (e.g., "kpizzy812/pixelpets")
     */
    private function resolveRepository(Application $application): ?string
    {
        $gitRepository = $application->git_repository;

        if (empty($gitRepository)) {
            return null;
        }

        // If already in owner/repo format
        if (preg_match('#^[\w.-]+/[\w.-]+$#', $gitRepository)) {
            return $gitRepository;
        }

        // Parse GitHub URL (https://github.com/owner/repo or https://github.com/owner/repo.git)
        if (preg_match('#^https?://github\.com/([^/]+)/([^/]+?)(?:\.git)?$#', $gitRepository, $matches)) {
            return $matches[1].'/'.$matches[2];
        }

        // Parse git@ URL (git@github.com:owner/repo.git)
        if (preg_match('#^git@github\.com:([^/]+)/([^/]+?)(?:\.git)?$#', $gitRepository, $matches)) {
            return $matches[1].'/'.$matches[2];
        }

        Log::warning('Could not parse GitHub repository URL', [
            'git_repository' => $gitRepository,
        ]);

        return null;
    }

    /**
     * Get parent commit SHA.
     */
    private function getParentCommit(?GithubApp $source, string $repository, string $commitSha, bool $useAuthenticatedApi): ?string
    {
        try {
            $response = $this->makeApiRequest(
                $source,
                "/repos/{$repository}/commits/{$commitSha}",
                $useAuthenticatedApi
            );

            $parents = data_get($response, 'parents', []);
            if (! empty($parents)) {
                return $parents[0]['sha'] ?? null;
            }

            return null;
        } catch (\Exception $e) {
            Log::warning('Failed to get parent commit', [
                'repository' => $repository,
                'commit' => $commitSha,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Fetch comparison between two commits.
     */
    private function fetchComparison(?GithubApp $source, string $repository, string $base, string $head, bool $useAuthenticatedApi): DiffResult
    {
        $data = $this->makeApiRequest(
            $source,
            "/repos/{$repository}/compare/{$base}...{$head}",
            $useAuthenticatedApi
        );

        $files = collect($data['files'] ?? []);

        return $this->buildDiffResult(
            commitSha: $head,
            baseCommitSha: $base,
            files: $files,
        );
    }

    /**
     * Fetch a single commit (for initial commits without parent).
     */
    private function fetchSingleCommit(?GithubApp $source, string $repository, string $commitSha, bool $useAuthenticatedApi): DiffResult
    {
        $data = $this->makeApiRequest(
            $source,
            "/repos/{$repository}/commits/{$commitSha}",
            $useAuthenticatedApi
        );

        $files = collect($data['files'] ?? []);

        return $this->buildDiffResult(
            commitSha: $commitSha,
            baseCommitSha: null,
            files: $files,
        );
    }

    /**
     * Build DiffResult from API response files.
     */
    private function buildDiffResult(string $commitSha, ?string $baseCommitSha, Collection $apiFiles): DiffResult
    {
        $files = collect();
        $allAddedLines = collect();
        $totalAdditions = 0;
        $totalDeletions = 0;
        $rawDiffParts = [];

        foreach ($apiFiles as $file) {
            $filePath = $file['filename'];

            // Skip excluded files
            if ($this->shouldExcludeFile($filePath)) {
                continue;
            }

            $status = $file['status'];
            $previousPath = $file['previous_filename'] ?? null;
            $patch = $file['patch'] ?? '';

            $totalAdditions += $file['additions'] ?? 0;
            $totalDeletions += $file['deletions'] ?? 0;

            // Parse patch to extract lines
            $lines = $this->parsePatch($filePath, $patch);

            $diffFile = new DiffFile(
                path: $filePath,
                status: $status,
                previousPath: $previousPath,
                lines: $lines,
            );

            $files->push($diffFile);
            $allAddedLines = $allAddedLines->merge($diffFile->getAddedLines());

            // Build raw diff for LLM
            $rawDiffParts[] = "--- a/{$filePath}";
            $rawDiffParts[] = "+++ b/{$filePath}";
            $rawDiffParts[] = $patch;
        }

        // Check limits
        $totalChanges = $totalAdditions + $totalDeletions;
        if ($totalChanges > $this->maxDiffLines) {
            Log::warning('Diff exceeds max lines limit', [
                'commit' => $commitSha,
                'total_changes' => $totalChanges,
                'max_lines' => $this->maxDiffLines,
            ]);
        }

        return new DiffResult(
            commitSha: $commitSha,
            baseCommitSha: $baseCommitSha,
            files: $files,
            addedLines: $allAddedLines,
            totalAdditions: $totalAdditions,
            totalDeletions: $totalDeletions,
            rawDiff: implode("\n", $rawDiffParts),
        );
    }

    /**
     * Parse a patch string into DiffLines.
     *
     * @return Collection<int, DiffLine>
     */
    private function parsePatch(string $filePath, string $patch): Collection
    {
        $lines = collect();

        if (empty($patch)) {
            return $lines;
        }

        $patchLines = explode("\n", $patch);
        $currentLineNumber = 0;

        foreach ($patchLines as $line) {
            // Parse hunk header to get line numbers
            if (preg_match('/^@@\s*-\d+(?:,\d+)?\s*\+(\d+)(?:,\d+)?\s*@@/', $line, $matches)) {
                $currentLineNumber = (int) $matches[1];

                continue;
            }

            // Skip empty lines at the end
            if ($line === '') {
                continue;
            }

            $firstChar = $line[0] ?? '';
            $content = substr($line, 1);

            if ($firstChar === '+') {
                $lines->push(new DiffLine(
                    file: $filePath,
                    number: $currentLineNumber,
                    content: $content,
                    type: 'added',
                ));
                $currentLineNumber++;
            } elseif ($firstChar === '-') {
                $lines->push(new DiffLine(
                    file: $filePath,
                    number: $currentLineNumber,
                    content: $content,
                    type: 'removed',
                ));
                // Don't increment line number for removed lines
            } else {
                // Context line
                $lines->push(new DiffLine(
                    file: $filePath,
                    number: $currentLineNumber,
                    content: $content,
                    type: 'context',
                ));
                $currentLineNumber++;
            }
        }

        return $lines;
    }

    /**
     * Check if file should be excluded from analysis.
     */
    private function shouldExcludeFile(string $filePath): bool
    {
        foreach ($this->excludePatterns as $pattern) {
            // Convert glob pattern to regex
            $regex = str_replace(
                ['*', '?'],
                ['.*', '.'],
                preg_quote($pattern, '/')
            );

            if (preg_match("/^{$regex}$/", $filePath)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Make API request to GitHub.
     *
     * Uses authenticated API for GitHub App sources, public API otherwise.
     */
    private function makeApiRequest(?GithubApp $source, string $endpoint, bool $useAuthenticatedApi): array
    {
        if ($useAuthenticatedApi && $source instanceof GithubApp) {
            // Use authenticated GitHub API
            $response = githubApi($source, $endpoint);

            // githubApi returns ['data' => Collection], normalize to array
            $data = $response['data'] ?? $response;

            return $data instanceof Collection ? $data->toArray() : (array) $data;
        }

        // Use public GitHub API (rate limited to 60 requests/hour without auth)
        $response = Http::withHeaders([
            'Accept' => 'application/vnd.github.v3+json',
            'User-Agent' => 'Saturn-Platform',
        ])
            ->timeout(30)
            ->retry(2, 500, throw: false)
            ->get("https://api.github.com{$endpoint}");

        if ($response->failed()) {
            $status = $response->status();
            $message = $response->json('message', 'GitHub API request failed');

            if ($status === 404) {
                throw new \InvalidArgumentException("Repository or commit not found: {$message}");
            }

            if ($status === 403) {
                throw new \RuntimeException("GitHub API rate limit exceeded or access denied: {$message}");
            }

            throw new \RuntimeException("GitHub API error ({$status}): {$message}");
        }

        return $response->json();
    }
}
