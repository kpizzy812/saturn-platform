<?php

namespace Tests\Unit\Deployment;

use Tests\TestCase;

/**
 * Tests for git ls-remote SHA extraction logic used in deployment.
 * Validates the regex pattern handles various git output formats.
 */
class GitShaParsingTest extends TestCase
{
    private const VALID_SHA = 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2'; // exactly 40 hex chars

    /**
     * Extract commit SHA using the same regex pattern as HandlesGitOperations.
     */
    private function extractSha(string $output): ?string
    {
        preg_match('/\b([0-9a-fA-F]{40})(?=\s*\t)/', $output, $matches);

        return $matches[1] ?? null;
    }

    public function test_parses_standard_ls_remote_output(): void
    {
        $output = self::VALID_SHA."\trefs/heads/main";
        $this->assertEquals(self::VALID_SHA, $this->extractSha($output));
    }

    public function test_parses_output_with_warning_prefix(): void
    {
        $output = "Warning: Permanently added 'github.com' to known hosts.\n".self::VALID_SHA."\trefs/heads/main";
        $this->assertEquals(self::VALID_SHA, $this->extractSha($output));
    }

    public function test_parses_output_with_warning_on_same_line(): void
    {
        // Git sometimes outputs warning glued to the result
        $output = 'Warning: something here '.self::VALID_SHA."\trefs/heads/main";
        $this->assertEquals(self::VALID_SHA, $this->extractSha($output));
    }

    public function test_returns_null_for_output_without_sha(): void
    {
        $output = "Warning: connection reset\t";
        $this->assertNull($this->extractSha($output));
    }

    public function test_returns_null_for_empty_output(): void
    {
        $this->assertNull($this->extractSha(''));
    }

    public function test_returns_null_for_partial_sha(): void
    {
        // Only 20 hex chars, not 40
        $output = "a1b2c3d4e5f6a1b2c3d4\trefs/heads/main";
        $this->assertNull($this->extractSha($output));
    }

    public function test_returns_null_for_sha_without_tab(): void
    {
        // SHA present but no tab — regex requires tab lookahead
        $output = self::VALID_SHA.' refs/heads/main';
        $this->assertNull($this->extractSha($output));
    }

    public function test_parses_sha_with_spaces_before_tab(): void
    {
        $output = self::VALID_SHA."  \trefs/heads/main";
        $this->assertEquals(self::VALID_SHA, $this->extractSha($output));
    }

    public function test_handles_uppercase_sha(): void
    {
        $sha = 'A1B2C3D4E5F6A1B2C3D4E5F6A1B2C3D4E5F6A1B2';
        $output = $sha."\trefs/heads/main";
        $this->assertEquals($sha, $this->extractSha($output));
    }
}
