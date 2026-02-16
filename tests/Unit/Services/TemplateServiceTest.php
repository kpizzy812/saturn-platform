<?php

use App\Services\TemplateService;

describe('TemplateService', function () {
    beforeEach(function () {
        $this->service = new TemplateService;
    });

    describe('formatName', function () {
        it('returns special name for n8n', function () {
            $method = getPrivateMethod(TemplateService::class, 'formatName');
            expect($method->invoke($this->service, 'n8n'))->toBe('n8n');
        });

        it('returns special name for WordPress', function () {
            $method = getPrivateMethod(TemplateService::class, 'formatName');
            expect($method->invoke($this->service, 'wordpress'))->toBe('WordPress');
        });

        it('returns special name for PostgreSQL', function () {
            $method = getPrivateMethod(TemplateService::class, 'formatName');
            expect($method->invoke($this->service, 'postgresql'))->toBe('PostgreSQL');
        });

        it('returns special name for MySQL', function () {
            $method = getPrivateMethod(TemplateService::class, 'formatName');
            expect($method->invoke($this->service, 'mysql'))->toBe('MySQL');
        });

        it('returns special name for MongoDB', function () {
            $method = getPrivateMethod(TemplateService::class, 'formatName');
            expect($method->invoke($this->service, 'mongodb'))->toBe('MongoDB');
        });

        it('returns special name for Redis', function () {
            $method = getPrivateMethod(TemplateService::class, 'formatName');
            expect($method->invoke($this->service, 'redis'))->toBe('Redis');
        });

        it('returns special name for MinIO', function () {
            $method = getPrivateMethod(TemplateService::class, 'formatName');
            expect($method->invoke($this->service, 'minio'))->toBe('MinIO');
        });

        it('returns special name for GitLab', function () {
            $method = getPrivateMethod(TemplateService::class, 'formatName');
            expect($method->invoke($this->service, 'gitlab'))->toBe('GitLab');
        });

        it('converts kebab-case to Title Case', function () {
            $method = getPrivateMethod(TemplateService::class, 'formatName');
            expect($method->invoke($this->service, 'my-awesome-app'))->toBe('My Awesome App');
        });

        it('converts underscore to Title Case', function () {
            $method = getPrivateMethod(TemplateService::class, 'formatName');
            expect($method->invoke($this->service, 'my_awesome_app'))->toBe('My Awesome App');
        });

        it('handles single word', function () {
            $method = getPrivateMethod(TemplateService::class, 'formatName');
            expect($method->invoke($this->service, 'app'))->toBe('App');
        });
    });

    describe('transformTemplate', function () {
        it('transforms valid template with all fields', function () {
            $method = getPrivateMethod(TemplateService::class, 'transformTemplate');

            $template = [
                'slogan' => 'A great app',
                'category' => 'cms',
                'logo' => 'https://example.com/logo.png',
                'tags' => ['tag1', 'tag2'],
                'documentation' => 'https://docs.example.com',
                'port' => 8080,
                'minversion' => '1.0.0',
            ];

            $result = $method->invoke($this->service, $template, 'test-app');

            expect($result)->toBeArray()
                ->and($result['id'])->toBe('test-app')
                ->and($result['name'])->toBe('Test App')
                ->and($result['description'])->toBe('A great app')
                ->and($result['logo'])->toBe('https://example.com/logo.png')
                ->and($result['category'])->toBe('Web Apps')
                ->and($result['originalCategory'])->toBe('cms')
                ->and($result['tags'])->toBe(['tag1', 'tag2'])
                ->and($result['documentation'])->toBe('https://docs.example.com')
                ->and($result['port'])->toBe(8080)
                ->and($result['minversion'])->toBe('1.0.0')
                ->and($result['deployCount'])->toBeInt()
                ->and($result['featured'])->toBeBool();
        });

        it('returns null when slogan is missing', function () {
            $method = getPrivateMethod(TemplateService::class, 'transformTemplate');

            $template = [
                'category' => 'cms',
            ];

            $result = $method->invoke($this->service, $template, 'test-app');

            expect($result)->toBeNull();
        });

        it('maps automation category to APIs', function () {
            $method = getPrivateMethod(TemplateService::class, 'transformTemplate');

            $template = [
                'slogan' => 'Automation tool',
                'category' => 'automation',
            ];

            $result = $method->invoke($this->service, $template, 'n8n');

            expect($result['category'])->toBe('APIs');
        });

        it('maps cms category to Web Apps', function () {
            $method = getPrivateMethod(TemplateService::class, 'transformTemplate');

            $template = [
                'slogan' => 'CMS tool',
                'category' => 'cms',
            ];

            $result = $method->invoke($this->service, $template, 'wordpress');

            expect($result['category'])->toBe('Web Apps');
        });

        it('maps database category to Databases', function () {
            $method = getPrivateMethod(TemplateService::class, 'transformTemplate');

            $template = [
                'slogan' => 'Database',
                'category' => 'database',
            ];

            $result = $method->invoke($this->service, $template, 'postgresql');

            expect($result['category'])->toBe('Databases');
        });

        it('maps development category to Full Stack', function () {
            $method = getPrivateMethod(TemplateService::class, 'transformTemplate');

            $template = [
                'slogan' => 'Dev tool',
                'category' => 'development',
            ];

            $result = $method->invoke($this->service, $template, 'gitea');

            expect($result['category'])->toBe('Full Stack');
        });

        it('defaults to Web Apps for unknown category', function () {
            $method = getPrivateMethod(TemplateService::class, 'transformTemplate');

            $template = [
                'slogan' => 'Unknown tool',
                'category' => 'unknown-category',
            ];

            $result = $method->invoke($this->service, $template, 'test-app');

            expect($result['category'])->toBe('Web Apps');
        });

        it('defaults to tools category when category is missing', function () {
            $method = getPrivateMethod(TemplateService::class, 'transformTemplate');

            $template = [
                'slogan' => 'Some tool',
            ];

            $result = $method->invoke($this->service, $template, 'test-app');

            expect($result['originalCategory'])->toBe('tools');
        });

        it('marks featured templates correctly', function () {
            $method = getPrivateMethod(TemplateService::class, 'transformTemplate');

            $template = [
                'slogan' => 'Featured tool',
            ];

            $result = $method->invoke($this->service, $template, 'n8n');
            expect($result['featured'])->toBeTrue();

            $result = $method->invoke($this->service, $template, 'wordpress');
            expect($result['featured'])->toBeTrue();

            $result = $method->invoke($this->service, $template, 'unknown-app');
            expect($result['featured'])->toBeFalse();
        });

        it('provides default null values for optional fields', function () {
            $method = getPrivateMethod(TemplateService::class, 'transformTemplate');

            $template = [
                'slogan' => 'Minimal tool',
            ];

            $result = $method->invoke($this->service, $template, 'test-app');

            expect($result['logo'])->toBeNull()
                ->and($result['documentation'])->toBeNull()
                ->and($result['port'])->toBeNull()
                ->and($result['tags'])->toBe([])
                ->and($result['minversion'])->toBe('0.0.0');
        });
    });

    describe('generateDeployCount', function () {
        it('returns consistent count for same key', function () {
            $method = getPrivateMethod(TemplateService::class, 'generateDeployCount');

            $count1 = $method->invoke($this->service, 'test-app');
            $count2 = $method->invoke($this->service, 'test-app');

            expect($count1)->toBe($count2);
        });

        it('returns different counts for different keys', function () {
            $method = getPrivateMethod(TemplateService::class, 'generateDeployCount');

            $count1 = $method->invoke($this->service, 'app1');
            $count2 = $method->invoke($this->service, 'app2');

            expect($count1)->not->toBe($count2);
        });

        it('returns higher count for featured templates', function () {
            $method = getPrivateMethod(TemplateService::class, 'generateDeployCount');

            $featuredCount = $method->invoke($this->service, 'n8n');
            $normalCount = $method->invoke($this->service, 'some-random-app');

            expect($featuredCount)->toBeGreaterThan($normalCount);
        });

        it('returns count in reasonable range', function () {
            $method = getPrivateMethod(TemplateService::class, 'generateDeployCount');

            $count = $method->invoke($this->service, 'test-app');

            expect($count)->toBeInt()
                ->and($count)->toBeGreaterThan(0)
                ->and($count)->toBeLessThan(100000);
        });

        it('featured templates have 10x multiplier', function () {
            $method = getPrivateMethod(TemplateService::class, 'generateDeployCount');

            // n8n is featured - calculate base count
            $featuredCount = $method->invoke($this->service, 'n8n');
            expect($featuredCount % 10)->toBe(0); // Should be divisible by 10

            // Calculate expected base count
            $expectedBase = $featuredCount / 10;
            expect($expectedBase)->toBeGreaterThan(100)
                ->and($expectedBase)->toBeLessThan(5100);
        });
    });

    describe('Category Mappings', function () {
        it('maps all automation-related categories to APIs', function () {
            $method = getPrivateMethod(TemplateService::class, 'transformTemplate');

            $categories = ['automation', 'ai', 'email', 'iot', 'scheduling', 'search'];

            foreach ($categories as $category) {
                $template = ['slogan' => 'test', 'category' => $category];
                $result = $method->invoke($this->service, $template, 'test');
                expect($result['category'])->toBe('APIs', "Category {$category} should map to APIs");
            }
        });

        it('maps all web-related categories to Web Apps', function () {
            $method = getPrivateMethod(TemplateService::class, 'transformTemplate');

            $categories = ['cms', 'communication', 'crm', 'documentation', 'e-commerce', 'file-management', 'media', 'nocode', 'productivity', 'project-management', 'social', 'streaming', 'tools', 'url-shortener', 'wiki'];

            foreach ($categories as $category) {
                $template = ['slogan' => 'test', 'category' => $category];
                $result = $method->invoke($this->service, $template, 'test');
                expect($result['category'])->toBe('Web Apps', "Category {$category} should map to Web Apps");
            }
        });

        it('maps database and storage categories to Databases', function () {
            $method = getPrivateMethod(TemplateService::class, 'transformTemplate');

            $categories = ['database', 'storage'];

            foreach ($categories as $category) {
                $template = ['slogan' => 'test', 'category' => $category];
                $result = $method->invoke($this->service, $template, 'test');
                expect($result['category'])->toBe('Databases', "Category {$category} should map to Databases");
            }
        });

        it('maps infrastructure categories to Full Stack', function () {
            $method = getPrivateMethod(TemplateService::class, 'transformTemplate');

            $categories = ['development', 'hosting', 'logging', 'monitoring', 'networking', 'proxy', 'security', 'self-hosted', 'testing', 'vpn'];

            foreach ($categories as $category) {
                $template = ['slogan' => 'test', 'category' => $category];
                $result = $method->invoke($this->service, $template, 'test');
                expect($result['category'])->toBe('Full Stack', "Category {$category} should map to Full Stack");
            }
        });

        it('maps gaming category to Gaming', function () {
            $method = getPrivateMethod(TemplateService::class, 'transformTemplate');

            $template = ['slogan' => 'test', 'category' => 'gaming'];
            $result = $method->invoke($this->service, $template, 'test');
            expect($result['category'])->toBe('Gaming');
        });
    });

    describe('Featured Templates', function () {
        it('includes all expected featured templates', function () {
            $method = getPrivateMethod(TemplateService::class, 'transformTemplate');

            $featuredApps = ['n8n', 'nextcloud', 'wordpress', 'ghost', 'gitea', 'gitlab', 'minio', 'plausible', 'uptime-kuma', 'appwrite'];

            foreach ($featuredApps as $app) {
                $template = ['slogan' => 'test'];
                $result = $method->invoke($this->service, $template, $app);
                expect($result['featured'])->toBeTrue("App {$app} should be featured");
            }
        });
    });
});
