<?php

namespace Tests\Unit;

use App\Models\UserNotification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserNotificationUrlTest extends TestCase
{
    #[Test]
    public function to_frontend_array_converts_full_url_to_relative(): void
    {
        $notification = new UserNotification;
        $notification->id = 'test-uuid';
        $notification->type = 'deployment_success';
        $notification->title = 'Deployment: test';
        $notification->description = 'Environment: dev';
        $notification->action_url = 'https://saturn.example.com/deployments/abc-123';
        $notification->is_read = false;
        $notification->created_at = now();

        $result = $notification->toFrontendArray();

        $this->assertEquals('/deployments/abc-123', $result['actionUrl']);
    }

    #[Test]
    public function to_frontend_array_keeps_relative_url_as_is(): void
    {
        $notification = new UserNotification;
        $notification->id = 'test-uuid';
        $notification->type = 'deployment_success';
        $notification->title = 'Deployment: test';
        $notification->action_url = '/deployments/abc-123';
        $notification->is_read = false;
        $notification->created_at = now();

        $result = $notification->toFrontendArray();

        $this->assertEquals('/deployments/abc-123', $result['actionUrl']);
    }

    #[Test]
    public function to_frontend_array_handles_null_action_url(): void
    {
        $notification = new UserNotification;
        $notification->id = 'test-uuid';
        $notification->type = 'info';
        $notification->title = 'Test';
        $notification->action_url = null;
        $notification->is_read = true;
        $notification->created_at = now();

        $result = $notification->toFrontendArray();

        $this->assertNull($result['actionUrl']);
    }

    #[Test]
    public function to_frontend_array_strips_localhost_with_port(): void
    {
        $notification = new UserNotification;
        $notification->id = 'test-uuid';
        $notification->type = 'deployment_failure';
        $notification->title = 'Deployment: test';
        $notification->action_url = 'http://localhost:8000/deployments/xyz-789';
        $notification->is_read = false;
        $notification->created_at = now();

        $result = $notification->toFrontendArray();

        $this->assertEquals('/deployments/xyz-789', $result['actionUrl']);
    }
}
