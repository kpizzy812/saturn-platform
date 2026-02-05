<?php

namespace Tests\Unit\Notifications;

use App\Notifications\Channels\InAppChannel;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InAppChannelUrlTest extends TestCase
{
    private InAppChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->channel = new InAppChannel;
    }

    #[Test]
    public function to_relative_path_strips_full_url(): void
    {
        $method = new \ReflectionMethod(InAppChannel::class, 'toRelativePath');

        $result = $method->invoke($this->channel, 'https://saturn.example.com/deployments/abc-123');
        $this->assertEquals('/deployments/abc-123', $result);
    }

    #[Test]
    public function to_relative_path_preserves_relative_paths(): void
    {
        $method = new \ReflectionMethod(InAppChannel::class, 'toRelativePath');

        $result = $method->invoke($this->channel, '/deployments/abc-123');
        $this->assertEquals('/deployments/abc-123', $result);
    }

    #[Test]
    public function to_relative_path_preserves_query_params(): void
    {
        $method = new \ReflectionMethod(InAppChannel::class, 'toRelativePath');

        $result = $method->invoke($this->channel, 'https://saturn.example.com/deployments/abc?tab=logs');
        $this->assertEquals('/deployments/abc?tab=logs', $result);
    }

    #[Test]
    public function to_relative_path_handles_url_with_port(): void
    {
        $method = new \ReflectionMethod(InAppChannel::class, 'toRelativePath');

        $result = $method->invoke($this->channel, 'http://localhost:8000/deployments/abc-123');
        $this->assertEquals('/deployments/abc-123', $result);
    }

    #[Test]
    public function to_relative_path_handles_ip_address(): void
    {
        $method = new \ReflectionMethod(InAppChannel::class, 'toRelativePath');

        $result = $method->invoke($this->channel, 'http://192.168.1.1:8000/deployments/abc-123');
        $this->assertEquals('/deployments/abc-123', $result);
    }
}
