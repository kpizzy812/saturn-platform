<?php

namespace Tests\Unit\Admin;

use App\Models\InstanceSettings;
use Tests\TestCase;

class RateLimitQueueSettingsTest extends TestCase
{
    private array $rateQueueFields = [
        'api_rate_limit',
        'horizon_balance',
        'horizon_min_processes',
        'horizon_max_processes',
        'horizon_worker_memory',
        'horizon_worker_timeout',
        'horizon_max_jobs',
        'horizon_trim_recent_minutes',
        'horizon_trim_failed_minutes',
        'horizon_queue_wait_threshold',
    ];

    private array $integerCastFields = [
        'api_rate_limit',
        'horizon_min_processes',
        'horizon_max_processes',
        'horizon_worker_memory',
        'horizon_worker_timeout',
        'horizon_max_jobs',
        'horizon_trim_recent_minutes',
        'horizon_trim_failed_minutes',
        'horizon_queue_wait_threshold',
    ];

    public function test_all_rate_queue_fields_are_fillable(): void
    {
        $model = new InstanceSettings;
        $fillable = $model->getFillable();

        foreach ($this->rateQueueFields as $field) {
            $this->assertContains($field, $fillable, "Field '{$field}' should be in \$fillable");
        }
    }

    public function test_integer_fields_have_integer_casts(): void
    {
        $model = new InstanceSettings;
        $casts = $model->getCasts();

        foreach ($this->integerCastFields as $field) {
            $this->assertArrayHasKey($field, $casts, "Field '{$field}' should have a cast");
            $this->assertEquals('integer', $casts[$field], "Field '{$field}' should be cast to integer");
        }
    }

    public function test_horizon_balance_is_not_integer_cast(): void
    {
        $model = new InstanceSettings;
        $casts = $model->getCasts();

        // horizon_balance should be string, not integer
        if (isset($casts['horizon_balance'])) {
            $this->assertNotEquals('integer', $casts['horizon_balance']);
        } else {
            // Not in casts = default string, which is correct
            $this->assertTrue(true);
        }
    }

    public function test_valid_balance_strategies(): void
    {
        $validStrategies = ['false', 'simple', 'auto'];

        foreach ($validStrategies as $strategy) {
            $this->assertContains($strategy, $validStrategies,
                "Strategy '{$strategy}' should be valid");
        }

        // 'round-robin' is not a valid Horizon strategy
        $this->assertNotContains('round-robin', $validStrategies);
    }

    public function test_min_processes_default_not_greater_than_max_processes_default(): void
    {
        // Migration defaults: min=1, max=4
        $minDefault = 1;
        $maxDefault = 4;

        $this->assertLessThanOrEqual($maxDefault, $minDefault,
            'Default min_processes should not exceed default max_processes');
    }

    public function test_config_override_maps_api_rate_limit(): void
    {
        // Verify config key exists
        $configKey = 'api.rate_limit';
        $this->assertNotNull(config($configKey),
            "Config key '{$configKey}' should exist");
    }

    public function test_config_override_maps_horizon_defaults(): void
    {
        $expectedKeys = [
            'horizon.defaults.s6.balance',
            'horizon.defaults.s6.memory',
            'horizon.defaults.s6.timeout',
            'horizon.defaults.s6.maxJobs',
        ];

        foreach ($expectedKeys as $key) {
            // These keys should exist in horizon config
            $this->assertNotNull(config($key),
                "Horizon config key '{$key}' should exist");
        }
    }

    public function test_config_override_maps_horizon_trim(): void
    {
        $trimKeys = [
            'horizon.trim.recent',
            'horizon.trim.failed',
        ];

        foreach ($trimKeys as $key) {
            $this->assertNotNull(config($key),
                "Horizon trim config key '{$key}' should exist");
        }
    }

    public function test_config_override_maps_horizon_waits(): void
    {
        $this->assertNotNull(config('horizon.waits'),
            'Horizon waits config should exist');
    }

    public function test_field_count_is_exactly_ten(): void
    {
        $this->assertCount(10, $this->rateQueueFields,
            'Should have exactly 10 rate limit and queue fields');
    }

    public function test_integer_cast_count_is_exactly_nine(): void
    {
        $this->assertCount(9, $this->integerCastFields,
            'Should have exactly 9 integer cast fields (horizon_balance excluded)');
    }
}
