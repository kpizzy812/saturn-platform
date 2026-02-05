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

    #[Test]
    public function to_frontend_array_converts_legacy_livewire_deployment_url(): void
    {
        $notification = new UserNotification;
        $notification->id = 'test-uuid';
        $notification->type = 'deployment_success';
        $notification->title = 'Deployment: frontend';
        $notification->description = 'Environment: development';
        $notification->action_url = 'http://157.180.57.47:8000/project/oc80wckk804k0g4kswok4k40/environment/nw0g00cokc0k4o8gw4koks8w/application/20491441-0d69-481d-998c-461bdb06c992/deployment/de3ae2e2-b757-4cae-8868-a7843b24b4be';
        $notification->is_read = false;
        $notification->created_at = now();

        $result = $notification->toFrontendArray();

        $this->assertEquals('/deployments/de3ae2e2-b757-4cae-8868-a7843b24b4be', $result['actionUrl']);
    }

    #[Test]
    public function to_frontend_array_converts_relative_legacy_url(): void
    {
        $notification = new UserNotification;
        $notification->id = 'test-uuid';
        $notification->type = 'deployment_failure';
        $notification->title = 'Deployment: backend';
        $notification->action_url = '/project/abc123/environment/def456/application/ghi789/deployment/xyz-deployment-uuid';
        $notification->is_read = false;
        $notification->created_at = now();

        $result = $notification->toFrontendArray();

        $this->assertEquals('/deployments/xyz-deployment-uuid', $result['actionUrl']);
    }
}
