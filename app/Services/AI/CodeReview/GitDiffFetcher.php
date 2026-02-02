<?php

namespace App\Services\AI\CodeReview;

use App\Models\Application;
use App\Models\GithubApp;
use App\Services\AI\CodeReview\DTOs\DiffFile;
use App\Services\AI\CodeReview\DTOs\DiffLine;
use App\Services\AI\CodeReview\DTOs\DiffResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Fetches git diff from GitHub API.
 *
 * Supports both commit comparison and single commit diffs.
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

        if (! $source instanceof GithubApp) {
            throw new \InvalidArgumentException('Code review currently only supports GitHub repositories');
        }

        // Get repository info
        $repository = $application->git_repository;
        if (empty($repository)) {
            throw new \InvalidArgumentException('Application has no git repository configured');
        }

        // Determine base commit
        if ($baseCommit === null) {
            $baseCommit = $this->getParentCommit($source, $repository, $commitSha);
        }

        // Fetch the comparison
        if ($baseCommit) {
            return $this->fetchComparison($source, $repository, $baseCommit, $commitSha);
        }

        // For initial commits, get the commit itself
        return $this->fetchSingleCommit($source, $repository, $commitSha);
    }

    /**
     * Get parent commit SHA.
     */
    private function getParentCommit(GithubApp $source, string $repository, string $commitSha): ?string
    {
        try {
            $response = $this->makeApiRequest($source, "/repos/{$repository}/commits/{$commitSha}");

            $parents = data_get($response, 'data.parents', []);
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
    private function fetchComparison(GithubApp $source, string $repository, string $base, string $head): DiffResult
    {
        $response = $this->makeApiRequest($source, "/repos/{$repository}/compare/{$base}...{$head}");
        $data = $response['data'];

        $files = collect($data->get('files', []));

        return $this->buildDiffResult(
            commitSha: $head,
            baseCommitSha: $base,
            files: $files,
        );
    }

    /**
     * Fetch a single commit (for initial commits without parent).
     */
    private function fetchSingleCommit(GithubApp $source, string $repository, string $commitSha): DiffResult
    {
        $response = $this->makeApiRequest($source, "/repos/{$repository}/commits/{$commitSha}");
        $data = $response['data'];

        $files = collect($data->get('files', []));

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
     * Make authenticated API request to GitHub.
     */
    private function makeApiRequest(GithubApp $source, string $endpoint): array
    {
        // Use the existing githubApi helper
        return githubApi($source, $endpoint);
    }
}
