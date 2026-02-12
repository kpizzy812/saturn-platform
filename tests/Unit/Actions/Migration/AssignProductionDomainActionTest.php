<?php

namespace Tests\Unit\Actions\Migration;

use App\Actions\Migration\AssignProductionDomainAction;
use App\Models\Application;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AssignProductionDomainActionTest extends TestCase
{
    #[Test]
    public function action_class_exists(): void
    {
        $this->assertTrue(class_exists(AssignProductionDomainAction::class));
    }

    #[Test]
    public function action_has_handle_method(): void
    {
        $class = new \ReflectionClass(AssignProductionDomainAction::class);
        $this->assertTrue($class->hasMethod('handle'));
    }

    #[Test]
    public function handle_requires_model_and_fqdn(): void
    {
        $method = new \ReflectionMethod(AssignProductionDomainAction::class, 'handle');
        $params = $method->getParameters();

        $this->assertCount(2, $params);
        $this->assertEquals('resource', $params[0]->getName());
        $this->assertEquals('fqdn', $params[1]->getName());
    }

    #[Test]
    public function rejects_non_application_models(): void
    {
        $action = new AssignProductionDomainAction;

        $plainModel = new class extends Model
        {
            public function __construct() {}
        };

        $result = $action->handle($plainModel, 'https://app.saturn.ac');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('only supported for applications', $result['error']);
    }

    #[Test]
    public function normalize_fqdn_adds_https(): void
    {
        $action = new AssignProductionDomainAction;
        $method = new \ReflectionMethod($action, 'normalizeFqdn');

        $this->assertEquals('https://app.saturn.ac', $method->invoke($action, 'app.saturn.ac'));
        $this->assertEquals('https://app.saturn.ac', $method->invoke($action, 'https://app.saturn.ac'));
        $this->assertEquals('http://app.saturn.ac', $method->invoke($action, 'http://app.saturn.ac'));
        $this->assertEquals('https://app.saturn.ac', $method->invoke($action, '  app.saturn.ac  '));
        $this->assertEquals('https://app.saturn.ac', $method->invoke($action, 'app.saturn.ac/'));
    }

    #[Test]
    public function validate_fqdn_accepts_valid_domains(): void
    {
        $action = new AssignProductionDomainAction;
        $method = new \ReflectionMethod($action, 'isValidFqdn');

        $this->assertTrue($method->invoke($action, 'https://app.saturn.ac'));
        $this->assertTrue($method->invoke($action, 'https://myapp.saturn.ac'));
        $this->assertTrue($method->invoke($action, 'https://app.company.com'));
        $this->assertTrue($method->invoke($action, 'https://my-app.example.org'));
        $this->assertTrue($method->invoke($action, 'http://app.test.co'));
    }

    #[Test]
    public function validate_fqdn_rejects_invalid_domains(): void
    {
        $action = new AssignProductionDomainAction;
        $method = new \ReflectionMethod($action, 'isValidFqdn');

        // No scheme
        $this->assertFalse($method->invoke($action, 'app.saturn.ac'));
        // IP address
        $this->assertFalse($method->invoke($action, 'https://192.168.1.1'));
        // No TLD
        $this->assertFalse($method->invoke($action, 'https://localhost'));
        // Empty
        $this->assertFalse($method->invoke($action, ''));
    }

    #[Test]
    public function update_proxy_labels_replaces_host(): void
    {
        $action = new AssignProductionDomainAction;
        $method = new \ReflectionMethod($action, 'updateProxyLabels');

        // Create mock application
        $app = new class extends Application
        {
            public ?string $custom_labels = 'traefik.http.routers.myapp.rule=Host(`app-uat.company.com`)';

            public array $updated = [];

            public function __construct() {}

            protected static function boot() {}

            protected static function booting() {}

            protected static function booted() {}

            public function update(array $attributes = [], array $options = []): bool
            {
                $this->updated = $attributes;

                return true;
            }
        };

        $method->invoke($action, $app, 'https://app-uat.company.com', 'https://app.company.com');

        $this->assertArrayHasKey('custom_labels', $app->updated);
        $this->assertStringContainsString('app.company.com', $app->updated['custom_labels']);
        $this->assertStringNotContainsString('app-uat.company.com', $app->updated['custom_labels']);
    }

    #[Test]
    public function update_proxy_labels_skips_when_no_custom_labels(): void
    {
        $action = new AssignProductionDomainAction;
        $method = new \ReflectionMethod($action, 'updateProxyLabels');

        $app = new class extends Application
        {
            public ?string $custom_labels = '';

            public array $updated = [];

            public function __construct() {}

            protected static function boot() {}

            protected static function booting() {}

            protected static function booted() {}

            public function update(array $attributes = [], array $options = []): bool
            {
                $this->updated = $attributes;

                return true;
            }
        };

        $method->invoke($action, $app, null, 'https://app.company.com');

        // Should not call update since labels are empty
        $this->assertEmpty($app->updated);
    }

    #[Test]
    public function action_uses_as_action_trait(): void
    {
        $action = new AssignProductionDomainAction;
        $traits = class_uses_recursive($action);

        $this->assertArrayHasKey(\Lorisleiva\Actions\Concerns\AsAction::class, $traits);
    }
}
