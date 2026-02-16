<?php

use App\Services\ChangelogService;

describe('ChangelogService', function () {
    beforeEach(function () {
        $this->service = new ChangelogService;
    });

    describe('validateEntryData', function () {
        it('returns true for valid entry with all required fields', function () {
            $method = getPrivateMethod(ChangelogService::class, 'validateEntryData');

            $data = [
                'tag_name' => 'v1.0.0',
                'title' => 'Release 1.0.0',
                'content' => 'This is a release',
                'published_at' => '2024-01-01 00:00:00',
            ];

            expect($method->invoke($this->service, $data))->toBeTrue();
        });

        it('returns false when tag_name is missing', function () {
            $method = getPrivateMethod(ChangelogService::class, 'validateEntryData');

            $data = [
                'title' => 'Release 1.0.0',
                'content' => 'This is a release',
                'published_at' => '2024-01-01 00:00:00',
            ];

            expect($method->invoke($this->service, $data))->toBeFalse();
        });

        it('returns false when title is missing', function () {
            $method = getPrivateMethod(ChangelogService::class, 'validateEntryData');

            $data = [
                'tag_name' => 'v1.0.0',
                'content' => 'This is a release',
                'published_at' => '2024-01-01 00:00:00',
            ];

            expect($method->invoke($this->service, $data))->toBeFalse();
        });

        it('returns false when content is missing', function () {
            $method = getPrivateMethod(ChangelogService::class, 'validateEntryData');

            $data = [
                'tag_name' => 'v1.0.0',
                'title' => 'Release 1.0.0',
                'published_at' => '2024-01-01 00:00:00',
            ];

            expect($method->invoke($this->service, $data))->toBeFalse();
        });

        it('returns false when published_at is missing', function () {
            $method = getPrivateMethod(ChangelogService::class, 'validateEntryData');

            $data = [
                'tag_name' => 'v1.0.0',
                'title' => 'Release 1.0.0',
                'content' => 'This is a release',
            ];

            expect($method->invoke($this->service, $data))->toBeFalse();
        });

        it('returns false when tag_name is empty string', function () {
            $method = getPrivateMethod(ChangelogService::class, 'validateEntryData');

            $data = [
                'tag_name' => '',
                'title' => 'Release 1.0.0',
                'content' => 'This is a release',
                'published_at' => '2024-01-01 00:00:00',
            ];

            expect($method->invoke($this->service, $data))->toBeFalse();
        });

        it('returns false when title is empty string', function () {
            $method = getPrivateMethod(ChangelogService::class, 'validateEntryData');

            $data = [
                'tag_name' => 'v1.0.0',
                'title' => '',
                'content' => 'This is a release',
                'published_at' => '2024-01-01 00:00:00',
            ];

            expect($method->invoke($this->service, $data))->toBeFalse();
        });

        it('returns false when content is empty string', function () {
            $method = getPrivateMethod(ChangelogService::class, 'validateEntryData');

            $data = [
                'tag_name' => 'v1.0.0',
                'title' => 'Release 1.0.0',
                'content' => '',
                'published_at' => '2024-01-01 00:00:00',
            ];

            expect($method->invoke($this->service, $data))->toBeFalse();
        });

        it('returns false when published_at is empty string', function () {
            $method = getPrivateMethod(ChangelogService::class, 'validateEntryData');

            $data = [
                'tag_name' => 'v1.0.0',
                'title' => 'Release 1.0.0',
                'content' => 'This is a release',
                'published_at' => '',
            ];

            expect($method->invoke($this->service, $data))->toBeFalse();
        });

        it('returns true with additional fields', function () {
            $method = getPrivateMethod(ChangelogService::class, 'validateEntryData');

            $data = [
                'tag_name' => 'v1.0.0',
                'title' => 'Release 1.0.0',
                'content' => 'This is a release',
                'published_at' => '2024-01-01 00:00:00',
                'author' => 'John Doe',
                'extra_field' => 'extra value',
            ];

            expect($method->invoke($this->service, $data))->toBeTrue();
        });
    });

    describe('applyCustomStyling', function () {
        it('adds dark mode classes to h1 tags', function () {
            $method = getPrivateMethod(ChangelogService::class, 'applyCustomStyling');

            $html = '<h1>Heading 1</h1>';
            $result = $method->invoke($this->service, $html);

            expect($result)->toContain('class="text-xl font-bold dark:text-white mb-2"');
        });

        it('adds dark mode classes to h2 tags', function () {
            $method = getPrivateMethod(ChangelogService::class, 'applyCustomStyling');

            $html = '<h2>Heading 2</h2>';
            $result = $method->invoke($this->service, $html);

            expect($result)->toContain('class="text-lg font-semibold dark:text-white mb-2"');
        });

        it('adds dark mode classes to h3 tags', function () {
            $method = getPrivateMethod(ChangelogService::class, 'applyCustomStyling');

            $html = '<h3>Heading 3</h3>';
            $result = $method->invoke($this->service, $html);

            expect($result)->toContain('class="text-md font-semibold dark:text-white mb-1"');
        });

        it('adds classes to p tags', function () {
            $method = getPrivateMethod(ChangelogService::class, 'applyCustomStyling');

            $html = '<p>Paragraph text</p>';
            $result = $method->invoke($this->service, $html);

            expect($result)->toContain('class="mb-2 dark:text-neutral-300"');
        });

        it('adds classes to ul tags', function () {
            $method = getPrivateMethod(ChangelogService::class, 'applyCustomStyling');

            $html = '<ul><li>Item</li></ul>';
            $result = $method->invoke($this->service, $html);

            expect($result)->toContain('class="mb-2 ml-4 list-disc"');
        });

        it('adds classes to ol tags', function () {
            $method = getPrivateMethod(ChangelogService::class, 'applyCustomStyling');

            $html = '<ol><li>Item</li></ol>';
            $result = $method->invoke($this->service, $html);

            expect($result)->toContain('class="mb-2 ml-4 list-decimal"');
        });

        it('adds classes to li tags', function () {
            $method = getPrivateMethod(ChangelogService::class, 'applyCustomStyling');

            $html = '<li>List item</li>';
            $result = $method->invoke($this->service, $html);

            expect($result)->toContain('class="dark:text-neutral-300"');
        });

        it('adds classes to code tags', function () {
            $method = getPrivateMethod(ChangelogService::class, 'applyCustomStyling');

            $html = '<code>inline code</code>';
            $result = $method->invoke($this->service, $html);

            expect($result)->toContain('class="bg-gray-100 dark:bg-coolgray-300 px-1 py-0.5 rounded text-sm"');
        });

        it('adds target="_blank" and classes to links', function () {
            $method = getPrivateMethod(ChangelogService::class, 'applyCustomStyling');

            $html = '<a href="https://example.com">Link</a>';
            $result = $method->invoke($this->service, $html);

            expect($result)->toContain('target="_blank"')
                ->and($result)->toContain('rel="noopener"')
                ->and($result)->toContain('class="text-blue-500 hover:text-blue-600 underline"');
        });

        it('adds classes to strong tags', function () {
            $method = getPrivateMethod(ChangelogService::class, 'applyCustomStyling');

            $html = '<strong>Bold text</strong>';
            $result = $method->invoke($this->service, $html);

            expect($result)->toContain('class="font-semibold dark:text-white"');
        });

        it('adds classes to em tags', function () {
            $method = getPrivateMethod(ChangelogService::class, 'applyCustomStyling');

            $html = '<em>Italic text</em>';
            $result = $method->invoke($this->service, $html);

            expect($result)->toContain('class="italic dark:text-neutral-300"');
        });

        it('handles multiple elements', function () {
            $method = getPrivateMethod(ChangelogService::class, 'applyCustomStyling');

            $html = '<h1>Title</h1><p>Text</p><ul><li>Item</li></ul>';
            $result = $method->invoke($this->service, $html);

            expect($result)->toContain('class="text-xl font-bold dark:text-white mb-2"')
                ->and($result)->toContain('class="mb-2 dark:text-neutral-300"')
                ->and($result)->toContain('class="mb-2 ml-4 list-disc"')
                ->and($result)->toContain('class="dark:text-neutral-300"');
        });

        it('handles tags with existing attributes', function () {
            $method = getPrivateMethod(ChangelogService::class, 'applyCustomStyling');

            $html = '<h1 id="title">Heading</h1>';
            $result = $method->invoke($this->service, $html);

            expect($result)->toContain('class="text-xl font-bold dark:text-white mb-2"');
        });

        it('preserves content inside tags', function () {
            $method = getPrivateMethod(ChangelogService::class, 'applyCustomStyling');

            $html = '<p>This is <strong>important</strong> text</p>';
            $result = $method->invoke($this->service, $html);

            expect($result)->toContain('This is')
                ->and($result)->toContain('important')
                ->and($result)->toContain('text');
        });

        it('converts plain URLs to clickable links', function () {
            $method = getPrivateMethod(ChangelogService::class, 'applyCustomStyling');

            $html = '<p>Visit https://example.com for more info</p>';
            $result = $method->invoke($this->service, $html);

            expect($result)->toContain('<a href="https://example.com"')
                ->and($result)->toContain('target="_blank"')
                ->and($result)->toContain('rel="noopener"');
        });

        it('handles empty string', function () {
            $method = getPrivateMethod(ChangelogService::class, 'applyCustomStyling');

            $html = '';
            $result = $method->invoke($this->service, $html);

            expect($result)->toBe('');
        });
    });
});
