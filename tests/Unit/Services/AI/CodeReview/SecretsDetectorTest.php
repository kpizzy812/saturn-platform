<?php

use App\Services\AI\CodeReview\Detectors\SecretsDetector;
use App\Services\AI\CodeReview\DTOs\DiffLine;
use App\Services\AI\CodeReview\DTOs\DiffResult;

/**
 * Create a DiffResult with the given added lines.
 */
function createDiff(array $lines): DiffResult
{
    $diffLines = collect($lines)->map(
        fn ($content, $i) => new DiffLine(
            file: 'src/test.php',
            number: $i + 1,
            content: $content,
            type: 'added'
        )
    );

    return new DiffResult(
        commitSha: 'abc123',
        baseCommitSha: 'def456',
        files: collect([]),
        addedLines: $diffLines,
        totalAdditions: count($lines),
        totalDeletions: 0,
        rawDiff: '',
    );
}

describe('SecretsDetector', function () {
    beforeEach(function () {
        $this->detector = new SecretsDetector;
    });

    describe('GitHub tokens', function () {
        it('detects GitHub Personal Access Token (ghp_)', function () {
            $diff = createDiff(['const token = "ghp_1234567890abcdefghijklmnopqrstuvwx";']);

            $violations = $this->detector->detect($diff);

            expect($violations)->toHaveCount(1)
                ->and($violations->first()->ruleId)->toBe('SEC008')
                ->and($violations->first()->severity)->toBe('critical')
                ->and($violations->first()->containsSecret)->toBeTrue()
                ->and($violations->first()->confidence)->toBe(1.0);
        });

        it('detects GitHub OAuth Token (gho_)', function () {
            $diff = createDiff(['$token = "gho_1234567890abcdefghijklmnopqrstuvwx";']);

            $violations = $this->detector->detect($diff);

            expect($violations)->toHaveCount(1)
                ->and($violations->first()->ruleId)->toBe('SEC008');
        });

        it('detects GitHub fine-grained PAT', function () {
            $diff = createDiff(['const token = "github_pat_1234567890abcdefghij_1234567890abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrst";']);

            $violations = $this->detector->detect($diff);

            expect($violations)->toHaveCount(1)
                ->and($violations->first()->ruleId)->toBe('SEC008');
        });
    });

    describe('OpenAI/AI provider tokens', function () {
        it('detects OpenAI API key', function () {
            $diff = createDiff(['const apiKey = "sk-1234567890abcdefghijklmnopqrstuvwxyzabcdefgh";']);

            $violations = $this->detector->detect($diff);

            expect($violations)->toHaveCount(1)
                ->and($violations->first()->ruleId)->toBe('SEC008')
                ->and($violations->first()->message)->toContain('OpenAI');
        });
    });

    describe('AWS credentials', function () {
        it('detects AWS Access Key ID', function () {
            $diff = createDiff(['AWS_ACCESS_KEY = "AKIAIOSFODNN7EXAMPLE";']);

            $violations = $this->detector->detect($diff);

            expect($violations)->toHaveCount(1)
                ->and($violations->first()->ruleId)->toBe('SEC008')
                ->and($violations->first()->message)->toContain('AWS');
        });
    });

    describe('Generic API keys', function () {
        it('detects hardcoded api_key', function () {
            $diff = createDiff(['$api_key = "my-super-secret-api-key-12345678";']);

            $violations = $this->detector->detect($diff);

            expect($violations)->toHaveCount(1)
                ->and($violations->first()->ruleId)->toBe('SEC001');
        });

        it('detects hardcoded apiKey with camelCase', function () {
            $diff = createDiff(['const apiKey = "abcdefghijklmnopqrstuvwxyz1234";']);

            $violations = $this->detector->detect($diff);

            expect($violations)->toHaveCount(1)
                ->and($violations->first()->ruleId)->toBe('SEC001');
        });
    });

    describe('Passwords', function () {
        it('detects hardcoded password', function () {
            $diff = createDiff(['$password = "my-secure-password-123";']);

            $violations = $this->detector->detect($diff);

            expect($violations)->toHaveCount(1)
                ->and($violations->first()->ruleId)->toBe('SEC002');
        });

        it('detects hardcoded pwd', function () {
            $diff = createDiff(['const pwd = "secretpassword123";']);

            $violations = $this->detector->detect($diff);

            expect($violations)->toHaveCount(1)
                ->and($violations->first()->ruleId)->toBe('SEC002');
        });
    });

    describe('Private keys', function () {
        it('detects RSA private key', function () {
            $diff = createDiff(['-----BEGIN RSA PRIVATE KEY-----']);

            $violations = $this->detector->detect($diff);

            expect($violations)->toHaveCount(1)
                ->and($violations->first()->ruleId)->toBe('SEC007')
                ->and($violations->first()->severity)->toBe('critical');
        });

        it('detects generic private key', function () {
            $diff = createDiff(['-----BEGIN PRIVATE KEY-----']);

            $violations = $this->detector->detect($diff);

            expect($violations)->toHaveCount(1)
                ->and($violations->first()->ruleId)->toBe('SEC007');
        });
    });

    describe('Database connection strings', function () {
        it('detects MySQL connection string with password', function () {
            $diff = createDiff(['$dsn = "mysql://root:password123@localhost/db";']);

            $violations = $this->detector->detect($diff);

            expect($violations)->toHaveCount(1)
                ->and($violations->first()->ruleId)->toBe('SEC003');
        });

        it('detects PostgreSQL connection string', function () {
            $diff = createDiff(['DATABASE_URL = "postgres://user:secret@host:5432/db";']);

            $violations = $this->detector->detect($diff);

            expect($violations)->toHaveCount(1)
                ->and($violations->first()->ruleId)->toBe('SEC003');
        });
    });

    describe('Stripe tokens', function () {
        it('detects Stripe live secret key', function () {
            $diff = createDiff(['const key = "sk_live_1234567890abcdefghijklmn";']);

            $violations = $this->detector->detect($diff);

            expect($violations)->toHaveCount(1)
                ->and($violations->first()->ruleId)->toBe('SEC008')
                ->and($violations->first()->message)->toContain('Stripe');
        });

        it('detects Stripe test secret key', function () {
            $diff = createDiff(['STRIPE_KEY = "sk_test_1234567890abcdefghijklmn";']);

            $violations = $this->detector->detect($diff);

            expect($violations)->toHaveCount(1)
                ->and($violations->first()->ruleId)->toBe('SEC008');
        });
    });

    describe('False positive handling', function () {
        it('ignores placeholder values', function () {
            $diff = createDiff([
                'api_key = "your-api-key-here";',
                'API_KEY = "placeholder";',
                'const token = "xxx-xxx-xxx";',
            ]);

            $violations = $this->detector->detect($diff);

            expect($violations)->toBeEmpty();
        });

        it('ignores environment variable references', function () {
            $diff = createDiff([
                'api_key = process.env.API_KEY;',
                '$apiKey = env("API_KEY");',
                'key = getenv("SECRET_KEY");',
            ]);

            $violations = $this->detector->detect($diff);

            expect($violations)->toBeEmpty();
        });

        it('ignores test files', function () {
            $testDiffLine = new DiffLine(
                file: 'tests/ApiKeyTest.php',
                number: 1,
                content: 'const token = "ghp_1234567890abcdefghijklmnopqrstuvwx";',
                type: 'added'
            );

            $diff = new DiffResult(
                commitSha: 'abc123',
                baseCommitSha: null,
                files: collect([]),
                addedLines: collect([$testDiffLine]),
                totalAdditions: 1,
                totalDeletions: 0,
                rawDiff: '',
            );

            $violations = $this->detector->detect($diff);

            expect($violations)->toBeEmpty();
        });

        it('ignores comment lines', function () {
            $diff = createDiff([
                '// api_key = "ghp_1234567890abcdefghijklmnopqrstuvwx";',
                '# password = "secret123456789";',
            ]);

            $violations = $this->detector->detect($diff);

            expect($violations)->toBeEmpty();
        });

        it('ignores short values (likely not real secrets)', function () {
            $diff = createDiff([
                'api_key = "short";',
            ]);

            $violations = $this->detector->detect($diff);

            expect($violations)->toBeEmpty();
        });
    });

    describe('Secret masking', function () {
        it('masks detected secrets in snippet', function () {
            $diff = createDiff(['const token = "ghp_1234567890abcdefghijklmnopqrstuvwx";']);

            $violations = $this->detector->detect($diff);

            expect($violations->first()->snippet)->toContain('[REDACTED]')
                ->and($violations->first()->snippet)->not->toContain('ghp_1234567890abcdefghijklmnopqrstuvwx');
        });
    });

    describe('Detector metadata', function () {
        it('returns correct name', function () {
            expect($this->detector->getName())->toBe('SecretsDetector');
        });

        it('returns version string', function () {
            expect($this->detector->getVersion())->toMatch('/^\d+\.\d+\.\d+$/');
        });

        it('respects configuration for enabled state', function () {
            config(['ai.code_review.detectors.secrets' => true]);
            expect($this->detector->isEnabled())->toBeTrue();

            config(['ai.code_review.detectors.secrets' => false]);
            expect($this->detector->isEnabled())->toBeFalse();
        });
    });
});
