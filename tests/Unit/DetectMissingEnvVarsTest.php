<?php

use App\Traits\Deployment\HandlesHealthCheck;

/**
 * Tests for detectMissingEnvVars() in HandlesHealthCheck trait.
 *
 * The method parses container logs to extract missing environment variable names
 * from various framework error formats (Pydantic, Node.js, Django, etc.).
 */
describe('detectMissingEnvVars', function () {
    // Use reflection to access the private method
    function callDetectMissingEnvVars(string $logs): array
    {
        $mock = new class
        {
            use HandlesHealthCheck {
                detectMissingEnvVars as public;
            }
        };

        return $mock->detectMissingEnvVars($logs);
    }

    it('detects Pydantic v2 validation errors with newline format', function () {
        $logs = <<<'LOG'
pydantic_core._pydantic_core.ValidationError: 1 validation error for Settings
ADMIN_CHAT_ID
  Field required [type=missing, input_value={'DATABASE_URL': 'postgre...token', 'DEBUG': 'true'}, input_type=dict]
    For further information visit https://errors.pydantic.dev/2.12/v/missing
LOG;

        $result = callDetectMissingEnvVars($logs);

        expect($result)->toContain('ADMIN_CHAT_ID');
        expect($result)->not->toContain('Field');
    });

    it('detects multiple Pydantic missing fields', function () {
        $logs = <<<'LOG'
pydantic_core._pydantic_core.ValidationError: 3 validation errors for Settings
DATABASE_URL
  Field required [type=missing, input_value={}, input_type=dict]
SECRET_KEY
  Field required [type=missing, input_value={}, input_type=dict]
REDIS_URL
  Field required [type=missing, input_value={}, input_type=dict]
LOG;

        $result = callDetectMissingEnvVars($logs);

        expect($result)->toContain('DATABASE_URL');
        expect($result)->toContain('SECRET_KEY');
        expect($result)->toContain('REDIS_URL');
        expect($result)->not->toContain('Field');
    });

    it('does not return "Field" as a false positive', function () {
        $logs = <<<'LOG'
Some error message
Field required for something
LOG;

        $result = callDetectMissingEnvVars($logs);

        expect($result)->not->toContain('Field');
    });

    it('detects "VAR_NAME must be defined" pattern', function () {
        $logs = 'Error: API_KEY must be defined before starting the server';

        $result = callDetectMissingEnvVars($logs);

        expect($result)->toContain('API_KEY');
    });

    it('detects "VAR_NAME is required" pattern', function () {
        $logs = 'DATABASE_URL is required';

        $result = callDetectMissingEnvVars($logs);

        expect($result)->toContain('DATABASE_URL');
    });

    it('detects "Missing environment variable: VAR_NAME" pattern', function () {
        $logs = 'Missing environment variable: JWT_SECRET';

        $result = callDetectMissingEnvVars($logs);

        expect($result)->toContain('JWT_SECRET');
    });

    it('detects "process.env.VAR_NAME is undefined" pattern', function () {
        $logs = 'TypeError: process.env.STRIPE_KEY is undefined';

        $result = callDetectMissingEnvVars($logs);

        expect($result)->toContain('STRIPE_KEY');
    });

    it('detects Django "Set the VAR_NAME environment variable" pattern', function () {
        $logs = 'django.core.exceptions.ImproperlyConfigured: Set the SECRET_KEY environment variable';

        $result = callDetectMissingEnvVars($logs);

        expect($result)->toContain('SECRET_KEY');
    });

    it('returns empty array when no missing vars detected', function () {
        $logs = 'Server started successfully on port 8000';

        $result = callDetectMissingEnvVars($logs);

        expect($result)->toBeEmpty();
    });

    it('deduplicates variable names across patterns', function () {
        $logs = <<<'LOG'
DATABASE_URL is required
Missing environment variable: DATABASE_URL
LOG;

        $result = callDetectMissingEnvVars($logs);

        expect($result)->toHaveCount(1);
        expect($result)->toContain('DATABASE_URL');
    });

    it('does not return "is" as a false positive from "something is not set"', function () {
        $logs = 'Error: the required configuration is not set';

        $result = callDetectMissingEnvVars($logs);

        expect($result)->not->toContain('is');
        expect($result)->toBeEmpty();
    });

    it('does not return lowercase words as variable names', function () {
        $logs = <<<'LOG'
configuration is required
something is missing
value is undefined
LOG;

        $result = callDetectMissingEnvVars($logs);

        expect($result)->toBeEmpty();
    });

    it('detects quoted variable names with double quotes', function () {
        $logs = 'Error: "DATABASE_URL" is not set';

        $result = callDetectMissingEnvVars($logs);

        expect($result)->toContain('DATABASE_URL');
    });

    it('detects quoted variable names with single quotes', function () {
        $logs = "Error: 'API_KEY' is required";

        $result = callDetectMissingEnvVars($logs);

        expect($result)->toContain('API_KEY');
    });

    it('detects missing quoted variable after error keyword', function () {
        $logs = 'Missing environment variable "REDIS_URL"';

        $result = callDetectMissingEnvVars($logs);

        expect($result)->toContain('REDIS_URL');
    });

    it('does not false-positive on common ALL-CAPS words', function () {
        $logs = <<<'LOG'
ERROR is not defined
NULL is missing
TRUE is required
LOG;

        $result = callDetectMissingEnvVars($logs);

        expect($result)->toBeEmpty();
    });

    it('still detects uppercase vars alongside noise text', function () {
        $logs = <<<'LOG'
Starting application...
Error: the required configuration is not set
STRIPE_SECRET_KEY is not defined
something is missing
LOG;

        $result = callDetectMissingEnvVars($logs);

        expect($result)->toHaveCount(1);
        expect($result)->toContain('STRIPE_SECRET_KEY');
        expect($result)->not->toContain('is');
    });
});
