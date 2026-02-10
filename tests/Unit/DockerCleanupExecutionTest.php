<?php

namespace Tests\Unit;

use App\Models\DockerCleanupExecution;
use Tests\TestCase;

class DockerCleanupExecutionTest extends TestCase
{
    public function test_fillable_includes_all_required_fields(): void
    {
        $model = new DockerCleanupExecution;

        $requiredFields = ['server_id', 'status', 'message', 'cleanup_log', 'finished_at'];

        foreach ($requiredFields as $field) {
            $this->assertTrue(
                $model->isFillable($field),
                "Field '{$field}' should be fillable on DockerCleanupExecution"
            );
        }
    }

    public function test_non_fillable_fields_are_protected(): void
    {
        $model = new DockerCleanupExecution;

        $this->assertFalse($model->isFillable('id'));
        $this->assertFalse($model->isFillable('uuid'));
    }
}
