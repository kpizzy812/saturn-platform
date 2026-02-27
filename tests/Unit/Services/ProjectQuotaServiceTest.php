<?php

namespace Tests\Unit\Services;

use App\Models\Project;
use App\Services\ProjectQuotaService;
use Tests\TestCase;

/**
 * Unit tests for ProjectQuotaService.
 *
 * Enforces per-project resource quotas (max applications, services,
 * databases, environments). Business-critical for multi-tenancy.
 *
 * Uses a testable subclass that overrides getCurrentCount() to avoid
 * Eloquent relationship type constraints, plus source-level assertions
 * for the match expressions and aggregation logic.
 */
class ProjectQuotaServiceTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        parent::setUp();
        $this->source = file_get_contents(app_path('Services/ProjectQuotaService.php'));
    }

    // =========================================================================
    // canCreate() — source-level: limit resolution
    // =========================================================================

    public function test_uses_match_expression_for_type_to_limit_mapping(): void
    {
        $this->assertStringContainsString('match ($type)', $this->source);
    }

    public function test_maps_application_type_to_max_applications(): void
    {
        $this->assertStringContainsString("'application' => \$settings->max_applications", $this->source);
    }

    public function test_maps_service_type_to_max_services(): void
    {
        $this->assertStringContainsString("'service' => \$settings->max_services", $this->source);
    }

    public function test_maps_database_type_to_max_databases(): void
    {
        $this->assertStringContainsString("'database' => \$settings->max_databases", $this->source);
    }

    public function test_maps_environment_type_to_max_environments(): void
    {
        $this->assertStringContainsString("'environment' => \$settings->max_environments", $this->source);
    }

    public function test_null_limit_means_unlimited(): void
    {
        // null = unlimited — explicitly documented in source
        $this->assertStringContainsString('// null = unlimited', $this->source);
    }

    public function test_returns_true_when_no_settings(): void
    {
        $this->assertStringContainsString('if (! $settings)', $this->source);
        $this->assertStringContainsString('return true', $this->source);
    }

    // =========================================================================
    // getCurrentCount() — aggregates all 8 database types
    // =========================================================================

    public function test_database_count_includes_postgresqls(): void
    {
        $this->assertStringContainsString('$project->postgresqls()->count()', $this->source);
    }

    public function test_database_count_includes_mysqls(): void
    {
        $this->assertStringContainsString('$project->mysqls()->count()', $this->source);
    }

    public function test_database_count_includes_mariadbs(): void
    {
        $this->assertStringContainsString('$project->mariadbs()->count()', $this->source);
    }

    public function test_database_count_includes_mongodbs(): void
    {
        $this->assertStringContainsString('$project->mongodbs()->count()', $this->source);
    }

    public function test_database_count_includes_redis(): void
    {
        $this->assertStringContainsString('$project->redis()->count()', $this->source);
    }

    public function test_database_count_includes_keydbs(): void
    {
        $this->assertStringContainsString('$project->keydbs()->count()', $this->source);
    }

    public function test_database_count_includes_dragonflies(): void
    {
        $this->assertStringContainsString('$project->dragonflies()->count()', $this->source);
    }

    public function test_database_count_includes_clickhouses(): void
    {
        $this->assertStringContainsString('$project->clickhouses()->count()', $this->source);
    }

    public function test_unknown_type_returns_zero(): void
    {
        $this->assertStringContainsString('default => 0', $this->source);
    }

    // =========================================================================
    // canCreate() — pure logic via testable subclass
    // =========================================================================

    public function test_can_create_returns_true_when_below_limit(): void
    {
        $service = $this->makeService(['application' => 2]);
        $project = $this->makeProjectWithSettings(['max_applications' => 5]);

        $this->assertTrue($service->canCreate($project, 'application'));
    }

    public function test_can_create_returns_false_when_at_limit(): void
    {
        $service = $this->makeService(['application' => 5]);
        $project = $this->makeProjectWithSettings(['max_applications' => 5]);

        $this->assertFalse($service->canCreate($project, 'application'));
    }

    public function test_can_create_returns_false_when_over_limit(): void
    {
        $service = $this->makeService(['application' => 7]);
        $project = $this->makeProjectWithSettings(['max_applications' => 5]);

        $this->assertFalse($service->canCreate($project, 'application'));
    }

    public function test_can_create_returns_true_when_limit_is_null(): void
    {
        $service = $this->makeService(['application' => 999]);
        $project = $this->makeProjectWithSettings(['max_applications' => null]);

        $this->assertTrue($service->canCreate($project, 'application'));
    }

    public function test_can_create_returns_true_when_settings_is_null(): void
    {
        $service = $this->makeService([]);
        $project = $this->makeProjectWithSettings(null);

        $this->assertTrue($service->canCreate($project, 'application'));
    }

    public function test_can_create_service_respects_its_own_limit(): void
    {
        $service = $this->makeService(['service' => 3]);
        $project = $this->makeProjectWithSettings(['max_services' => 3]);

        $this->assertFalse($service->canCreate($project, 'service'));
    }

    public function test_can_create_database_respects_database_limit(): void
    {
        $service = $this->makeService(['database' => 4]);
        $project = $this->makeProjectWithSettings(['max_databases' => 5]);

        $this->assertTrue($service->canCreate($project, 'database'));
    }

    public function test_can_create_environment_respects_environment_limit(): void
    {
        $service = $this->makeService(['environment' => 10]);
        $project = $this->makeProjectWithSettings(['max_environments' => 10]);

        $this->assertFalse($service->canCreate($project, 'environment'));
    }

    // =========================================================================
    // getUsage() — structure via testable subclass
    // =========================================================================

    public function test_get_usage_returns_all_four_resource_types(): void
    {
        $service = $this->makeService([
            'application' => 2, 'service' => 1, 'database' => 3, 'environment' => 4,
        ]);
        $project = $this->makeProjectWithSettings([
            'max_applications' => 10, 'max_services' => 5, 'max_databases' => null, 'max_environments' => 20,
        ]);

        $usage = $service->getUsage($project);

        $this->assertArrayHasKey('application', $usage);
        $this->assertArrayHasKey('service', $usage);
        $this->assertArrayHasKey('database', $usage);
        $this->assertArrayHasKey('environment', $usage);
    }

    public function test_get_usage_each_entry_has_current_and_limit(): void
    {
        $service = $this->makeService(['application' => 2, 'service' => 0, 'database' => 0, 'environment' => 0]);
        $project = $this->makeProjectWithSettings([
            'max_applications' => 10, 'max_services' => null, 'max_databases' => null, 'max_environments' => null,
        ]);

        $usage = $service->getUsage($project);

        $this->assertArrayHasKey('current', $usage['application']);
        $this->assertArrayHasKey('limit', $usage['application']);
        $this->assertSame(2, $usage['application']['current']);
        $this->assertSame(10, $usage['application']['limit']);
    }

    public function test_get_usage_null_limit_preserved_in_output(): void
    {
        $service = $this->makeService(['application' => 0, 'service' => 0, 'database' => 0, 'environment' => 0]);
        $project = $this->makeProjectWithSettings([
            'max_applications' => null, 'max_services' => null, 'max_databases' => null, 'max_environments' => null,
        ]);

        $usage = $service->getUsage($project);

        $this->assertNull($usage['application']['limit']);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /** Build a ProjectQuotaService with a fixed getCurrentCount() implementation. */
    private function makeService(array $counts): ProjectQuotaService
    {
        return new class($counts) extends ProjectQuotaService
        {
            public function __construct(private readonly array $testCounts) {}

            protected function getCurrentCount(Project $project, string $type): int
            {
                return $this->testCounts[$type] ?? 0;
            }
        };
    }

    /** Build a project stub with mocked settings. */
    private function makeProjectWithSettings(?array $settings): Project
    {
        $settingsObj = $settings !== null ? (object) $settings : null;

        $project = \Mockery::mock(Project::class)->makePartial();
        $project->shouldReceive('getAttribute')->with('settings')->andReturn($settingsObj);

        return $project;
    }
}
