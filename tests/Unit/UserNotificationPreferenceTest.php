<?php

namespace Tests\Unit;

use App\Models\UserNotificationPreference;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserNotificationPreferenceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_has_correct_fillable_attributes(): void
    {
        $preference = new UserNotificationPreference;

        $this->assertEquals([
            'user_id',
            'email_deployments',
            'email_team',
            'email_billing',
            'email_security',
            'in_app_deployments',
            'in_app_team',
            'in_app_billing',
            'in_app_security',
            'digest_frequency',
        ], $preference->getFillable());
    }

    #[Test]
    public function it_casts_boolean_fields_correctly(): void
    {
        $preference = new UserNotificationPreference;
        $casts = $preference->getCasts();

        $this->assertEquals('boolean', $casts['email_deployments']);
        $this->assertEquals('boolean', $casts['email_team']);
        $this->assertEquals('boolean', $casts['email_billing']);
        $this->assertEquals('boolean', $casts['email_security']);
        $this->assertEquals('boolean', $casts['in_app_deployments']);
        $this->assertEquals('boolean', $casts['in_app_team']);
        $this->assertEquals('boolean', $casts['in_app_billing']);
        $this->assertEquals('boolean', $casts['in_app_security']);
    }

    #[Test]
    public function it_converts_to_frontend_format_correctly(): void
    {
        $preference = new UserNotificationPreference([
            'email_deployments' => true,
            'email_team' => false,
            'email_billing' => true,
            'email_security' => false,
            'in_app_deployments' => false,
            'in_app_team' => true,
            'in_app_billing' => false,
            'in_app_security' => true,
            'digest_frequency' => 'daily',
        ]);

        $result = $preference->toFrontendFormat();

        $this->assertEquals([
            'email' => [
                'deployments' => true,
                'team' => false,
                'billing' => true,
                'security' => false,
            ],
            'inApp' => [
                'deployments' => false,
                'team' => true,
                'billing' => false,
                'security' => true,
            ],
            'digest' => 'daily',
        ], $result);
    }

    #[Test]
    public function it_updates_from_frontend_format_correctly(): void
    {
        $preference = Mockery::mock(UserNotificationPreference::class)->makePartial();
        $preference->shouldReceive('update')
            ->once()
            ->with([
                'email_deployments' => false,
                'email_team' => true,
                'in_app_deployments' => true,
                'digest_frequency' => 'weekly',
            ])
            ->andReturn(true);

        $frontendData = [
            'email' => [
                'deployments' => false,
                'team' => true,
            ],
            'inApp' => [
                'deployments' => true,
            ],
            'digest' => 'weekly',
        ];

        $result = $preference->updateFromFrontendFormat($frontendData);

        $this->assertTrue($result);
    }

    #[Test]
    public function it_ignores_invalid_digest_frequency(): void
    {
        $preference = Mockery::mock(UserNotificationPreference::class)->makePartial();
        $preference->shouldReceive('update')
            ->once()
            ->with([
                'email_deployments' => true,
            ])
            ->andReturn(true);

        $frontendData = [
            'email' => [
                'deployments' => true,
            ],
            'digest' => 'invalid_frequency',
        ];

        $result = $preference->updateFromFrontendFormat($frontendData);

        $this->assertTrue($result);
    }
}
