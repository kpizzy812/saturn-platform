<?php

namespace App\Services\AI\CodeReview\Detectors;

use App\Services\AI\CodeReview\Contracts\StaticDetectorInterface;
use App\Services\AI\CodeReview\DTOs\DiffResult;
use App\Services\AI\CodeReview\DTOs\Violation;
use Illuminate\Support\Collection;

/**
 * Detects dangerous function calls in code changes.
 *
 * This is a deterministic detector (regex-based) but uses 'high' severity
 * instead of 'critical' because context matters for these functions.
 * MVP: warn-only mode, no blocking.
 */
class DangerousFunctionsDetector implements StaticDetectorInterface
{
    private const VERSION = '1.0.0';

    /**
     * Dangerous function patterns organized by rule.
     */
    private array $rules = [
        'SEC004' => [
            'name' => 'Shell Command Execution',
            'severity' => 'high',
            'language' => 'php',
            'patterns' => [
                '/\b(exec|system|shell_exec|passthru|popen|proc_open)\s*\(/' => [
                    'function' => '$1',
                    'message' => 'Shell execution function detected. Ensure user input is properly sanitized.',
                ],
                '/`[^`]*\$[^`]+`/' => [
                    'function' => 'backtick operator',
                    'message' => 'Backtick shell execution with variable detected. This is vulnerable to command injection.',
                ],
            ],
        ],
        'SEC005' => [
            'name' => 'Code Evaluation',
            'severity' => 'high',
            'language' => 'php',
            'patterns' => [
                '/\beval\s*\(/' => [
                    'function' => 'eval',
                    'message' => 'eval() detected. This is dangerous and should be avoided.',
                ],
                '/\bcreate_function\s*\(/' => [
                    'function' => 'create_function',
                    'message' => 'create_function() is deprecated and dangerous. Use closures instead.',
                ],
                '/\bpreg_replace\s*\(\s*["\'][^"\']*\/e["\']/' => [
                    'function' => 'preg_replace /e',
                    'message' => 'preg_replace with /e modifier is deprecated and dangerous. Use preg_replace_callback instead.',
                ],
                '/\bassert\s*\(\s*\$/' => [
                    'function' => 'assert',
                    'message' => 'assert() with variable is dangerous. It can execute arbitrary code.',
                ],
            ],
        ],
        'SEC006' => [
            'name' => 'Unsafe Deserialization',
            'severity' => 'high',
            'language' => 'php',
            'patterns' => [
                '/\bunserialize\s*\(\s*\$/' => [
                    'function' => 'unserialize',
                    'message' => 'unserialize() with user-controlled input is vulnerable to object injection attacks.',
                ],
            ],
        ],
        'SEC009' => [
            'name' => 'SQL Injection Risk',
            'severity' => 'medium',
            'language' => 'php',
            'patterns' => [
                '/\bquery\s*\(\s*["\'][^"\']*\$/' => [
                    'function' => 'raw SQL',
                    'message' => 'Raw SQL query with variable interpolation detected. Use parameterized queries.',
                ],
                '/DB::raw\s*\(\s*["\'][^"\']*\$/' => [
                    'function' => 'DB::raw',
                    'message' => 'DB::raw with variable detected. Ensure proper escaping or use bindings.',
                ],
                '/whereRaw\s*\(\s*["\'][^"\']*\$/' => [
                    'function' => 'whereRaw',
                    'message' => 'whereRaw with variable detected. Use parameter bindings for user input.',
                ],
            ],
        ],
        'SEC010' => [
            'name' => 'File Inclusion Risk',
            'severity' => 'high',
            'language' => 'php',
            'patterns' => [
                '/\b(include|include_once|require|require_once)\s*\(\s*\$/' => [
                    'function' => '$1',
                    'message' => 'Dynamic file inclusion with variable detected. This is vulnerable to LFI/RFI attacks.',
                ],
            ],
        ],
        'SEC011' => [
            'name' => 'Dangerous JavaScript',
            'severity' => 'high',
            'language' => 'javascript',
            'patterns' => [
                '/\beval\s*\(/' => [
                    'function' => 'eval',
                    'message' => 'JavaScript eval() detected. This is dangerous and should be avoided.',
                ],
                '/new\s+Function\s*\(/' => [
                    'function' => 'Function constructor',
                    'message' => 'Function constructor detected. This is similar to eval() and should be avoided.',
                ],
                '/innerHTML\s*=\s*[^;]*\+/' => [
                    'function' => 'innerHTML concatenation',
                    'message' => 'innerHTML with string concatenation is vulnerable to XSS. Use textContent or proper sanitization.',
                ],
                '/document\.write\s*\(/' => [
                    'function' => 'document.write',
                    'message' => 'document.write() detected. This is deprecated and potentially dangerous.',
                ],
            ],
        ],
    ];

    /**
     * File patterns to skip.
     */
    private array $skipPatterns = [
        '/\.test\.(ts|js|php)$/',
        '/\.spec\.(ts|js|php)$/',
        '/tests?\//',
        '/vendor\//',
        '/node_modules\//',
        '/\.d\.ts$/',
    ];

    public function detect(DiffResult $diff): Collection
    {
        $violations = collect();

        foreach ($diff->addedLines as $line) {
            // Skip irrelevant files
            if ($this->shouldSkipFile($line->file)) {
                continue;
            }

            // Skip comments
            if ($this->isCommentLine($line->content)) {
                continue;
            }

            // Determine language from file extension
            $language = $this->detectLanguage($line->file);

            foreach ($this->rules as $ruleId => $rule) {
                // Skip rules for other languages
                if (isset($rule['language']) && $rule['language'] !== $language && $rule['language'] !== 'any') {
                    continue;
                }

                foreach ($rule['patterns'] as $pattern => $info) {
                    if (preg_match($pattern, $line->content, $matches)) {
                        // Check for false positives
                        if ($this->isFalsePositive($line->content, $ruleId, $matches)) {
                            continue;
                        }

                        $function = $info['function'];
                        if (str_contains($function, '$1') && isset($matches[1])) {
                            $function = $matches[1];
                        }

                        $violations->push(new Violation(
                            ruleId: $ruleId,
                            source: 'regex',
                            severity: $rule['severity'],
                            confidence: 1.0, // Deterministic
                            file: $line->file,
                            line: $line->number,
                            message: "{$info['message']} ({$function})",
                            snippet: $line->content,
                            containsSecret: false,
                        ));

                        // One violation per line per rule category is enough
                        break;
                    }
                }
            }
        }

        return $violations;
    }

    public function getName(): string
    {
        return 'DangerousFunctionsDetector';
    }

    public function getVersion(): string
    {
        return self::VERSION;
    }

    public function isEnabled(): bool
    {
        return config('ai.code_review.detectors.dangerous_functions', true);
    }

    /**
     * Detect language from file extension.
     */
    private function detectLanguage(string $filePath): string
    {
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);

        return match ($extension) {
            'php', 'phtml' => 'php',
            'js', 'jsx', 'ts', 'tsx', 'mjs', 'cjs' => 'javascript',
            'vue', 'svelte' => 'javascript',
            'blade.php' => 'php',
            default => 'unknown',
        };
    }

    /**
     * Check if file should be skipped.
     */
    private function shouldSkipFile(string $filePath): bool
    {
        foreach ($this->skipPatterns as $pattern) {
            if (preg_match($pattern, $filePath)) {
                return true;
            }
        }

        // Only analyze code files
        $codeExtensions = ['php', 'js', 'jsx', 'ts', 'tsx', 'vue', 'svelte', 'mjs', 'cjs'];
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);

        // Handle blade.php
        if (str_ends_with($filePath, '.blade.php')) {
            return false;
        }

        return ! in_array($extension, $codeExtensions);
    }

    /**
     * Check if line is a comment.
     */
    private function isCommentLine(string $content): bool
    {
        $trimmed = trim($content);

        // Single-line comments
        if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '#')) {
            return true;
        }

        // Multi-line comment markers
        if (str_starts_with($trimmed, '/*') || str_starts_with($trimmed, '*') || str_ends_with($trimmed, '*/')) {
            return true;
        }

        return false;
    }

    /**
     * Check for false positives based on context.
     */
    private function isFalsePositive(string $content, string $ruleId, array $matches): bool
    {
        $lowerContent = strtolower($content);

        // Skip if it's in a string description or comment
        if (str_contains($lowerContent, 'disabled') || str_contains($lowerContent, '// ')) {
            return true;
        }

        // SEC004: Shell execution - allow in certain contexts
        if ($ruleId === 'SEC004') {
            // Allow in Process class methods (Laravel's safe wrapper)
            if (str_contains($content, 'Process::') || str_contains($content, '$process->')) {
                return true;
            }

            // Allow Symfony Process
            if (str_contains($content, 'new Process(')) {
                return true;
            }
        }

        // SEC009: SQL - allow when using bindings
        if ($ruleId === 'SEC009') {
            // Allow when there's a binding array
            if (preg_match('/\[\s*\$|\$[a-zA-Z_]+\s*\]/', $content)) {
                return true;
            }
        }

        return false;
    }
}
