<?php

use App\Services\EnvExampleParser;

describe('EnvExampleParser::parse', function () {
    it('parses standard KEY=value format', function () {
        $content = <<<'ENV'
APP_NAME=MyApp
APP_ENV=local
APP_PORT=3000
ENV;

        $result = EnvExampleParser::parse($content);

        expect($result)->toHaveCount(3);
        expect($result[0])->toMatchArray(['key' => 'APP_NAME', 'value' => 'MyApp', 'is_required' => false]);
        expect($result[1])->toMatchArray(['key' => 'APP_ENV', 'value' => 'local', 'is_required' => false]);
        expect($result[2])->toMatchArray(['key' => 'APP_PORT', 'value' => '3000', 'is_required' => false]);
    });

    it('parses empty values as required', function () {
        $content = <<<'ENV'
DATABASE_URL=
SECRET_KEY=
ENV;

        $result = EnvExampleParser::parse($content);

        expect($result)->toHaveCount(2);
        expect($result[0])->toMatchArray(['key' => 'DATABASE_URL', 'value' => null, 'is_required' => true]);
        expect($result[1])->toMatchArray(['key' => 'SECRET_KEY', 'value' => null, 'is_required' => true]);
    });

    it('parses double-quoted values', function () {
        $content = <<<'ENV'
APP_NAME="My Application"
GREETING="Hello \"World\""
ENV;

        $result = EnvExampleParser::parse($content);

        expect($result)->toHaveCount(2);
        expect($result[0]['value'])->toBe('My Application');
        expect($result[1]['value'])->toBe('Hello "World"');
    });

    it('parses single-quoted values', function () {
        $content = "APP_NAME='My App'\n";

        $result = EnvExampleParser::parse($content);

        expect($result)->toHaveCount(1);
        expect($result[0]['value'])->toBe('My App');
    });

    it('skips comment lines', function () {
        $content = <<<'ENV'
# This is a comment
APP_NAME=test
# Another comment
APP_ENV=local
ENV;

        $result = EnvExampleParser::parse($content);

        expect($result)->toHaveCount(2);
    });

    it('captures preceding comment for variable', function () {
        $content = <<<'ENV'
# The application name
APP_NAME=test
ENV;

        $result = EnvExampleParser::parse($content);

        expect($result[0]['comment'])->toBe('The application name');
    });

    it('handles empty content', function () {
        expect(EnvExampleParser::parse(''))->toBe([]);
        expect(EnvExampleParser::parse('   '))->toBe([]);
    });

    it('handles content with only comments', function () {
        $content = <<<'ENV'
# Just a comment
# Another comment
ENV;

        expect(EnvExampleParser::parse($content))->toBe([]);
    });

    it('skips malformed lines without equals sign', function () {
        $content = <<<'ENV'
VALID_KEY=value
this is not valid
ANOTHER_KEY=value2
ENV;

        $result = EnvExampleParser::parse($content);

        expect($result)->toHaveCount(2);
    });

    it('skips keys with invalid characters', function () {
        $content = <<<'ENV'
VALID_KEY=value
123_INVALID=value
-BAD=value
GOOD_KEY=value2
ENV;

        $result = EnvExampleParser::parse($content);

        expect($result)->toHaveCount(2);
        expect($result[0]['key'])->toBe('VALID_KEY');
        expect($result[1]['key'])->toBe('GOOD_KEY');
    });

    it('strips inline comments from unquoted values', function () {
        $content = "APP_PORT=3000 # The application port\n";

        $result = EnvExampleParser::parse($content);

        expect($result[0]['value'])->toBe('3000');
    });

    it('preserves # inside quoted values', function () {
        $content = 'APP_NAME="My #1 App"'."\n";

        $result = EnvExampleParser::parse($content);

        expect($result[0]['value'])->toBe('My #1 App');
    });

    it('handles duplicate keys by keeping all occurrences', function () {
        $content = <<<'ENV'
APP_KEY=first
APP_KEY=second
ENV;

        $result = EnvExampleParser::parse($content);

        expect($result)->toHaveCount(2);
        expect($result[0]['value'])->toBe('first');
        expect($result[1]['value'])->toBe('second');
    });
});

describe('EnvExampleParser::isPlaceholder', function () {
    it('returns true for null and empty values', function () {
        expect(EnvExampleParser::isPlaceholder(null))->toBeTrue();
        expect(EnvExampleParser::isPlaceholder(''))->toBeTrue();
    });

    it('returns true for common placeholder patterns', function () {
        expect(EnvExampleParser::isPlaceholder('CHANGE_ME'))->toBeTrue();
        expect(EnvExampleParser::isPlaceholder('changeme'))->toBeTrue();
        expect(EnvExampleParser::isPlaceholder('your_secret_here'))->toBeTrue();
        expect(EnvExampleParser::isPlaceholder('xxx'))->toBeTrue();
        expect(EnvExampleParser::isPlaceholder('TODO'))->toBeTrue();
        expect(EnvExampleParser::isPlaceholder('REPLACE_WITH_YOUR_KEY'))->toBeTrue();
        expect(EnvExampleParser::isPlaceholder('placeholder_value'))->toBeTrue();
        expect(EnvExampleParser::isPlaceholder('example_value'))->toBeTrue();
    });

    it('returns false for real values', function () {
        expect(EnvExampleParser::isPlaceholder('production'))->toBeFalse();
        expect(EnvExampleParser::isPlaceholder('localhost'))->toBeFalse();
        expect(EnvExampleParser::isPlaceholder('3000'))->toBeFalse();
        expect(EnvExampleParser::isPlaceholder('true'))->toBeFalse();
        expect(EnvExampleParser::isPlaceholder('redis://localhost:6379'))->toBeFalse();
    });
});

describe('EnvExampleParser::detectFramework', function () {
    it('detects Laravel', function () {
        $keys = ['APP_KEY', 'APP_ENV', 'DB_CONNECTION', 'CACHE_DRIVER', 'QUEUE_CONNECTION'];
        expect(EnvExampleParser::detectFramework($keys))->toBe('laravel');
    });

    it('detects Next.js', function () {
        $keys = ['NEXT_PUBLIC_API_URL', 'NEXT_PUBLIC_SITE_URL', 'NEXTAUTH_SECRET'];
        expect(EnvExampleParser::detectFramework($keys))->toBe('nextjs');
    });

    it('detects Django', function () {
        $keys = ['DJANGO_SECRET_KEY', 'DJANGO_SETTINGS_MODULE', 'DJANGO_DEBUG'];
        expect(EnvExampleParser::detectFramework($keys))->toBe('django');
    });

    it('detects Rails', function () {
        $keys = ['RAILS_ENV', 'SECRET_KEY_BASE', 'RAILS_MASTER_KEY'];
        expect(EnvExampleParser::detectFramework($keys))->toBe('rails');
    });

    it('returns null for unknown framework', function () {
        $keys = ['CUSTOM_VAR_1', 'CUSTOM_VAR_2'];
        expect(EnvExampleParser::detectFramework($keys))->toBeNull();
    });

    it('returns null for empty keys', function () {
        expect(EnvExampleParser::detectFramework([]))->toBeNull();
    });

    it('picks highest scoring framework', function () {
        // Has both NODE_ENV (express) and multiple Laravel keys
        $keys = ['APP_KEY', 'APP_ENV', 'DB_CONNECTION', 'NODE_ENV'];
        expect(EnvExampleParser::detectFramework($keys))->toBe('laravel');
    });
});
