<?php

use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Notifications\Channels\InAppChannel;
use Mockery as m;

describe('InAppChannel determineType', function () {
    afterEach(function () {
        m::close();
    });

    it('correctly identifies DeploymentApprovalRequired type', function () {
        $channel = new InAppChannel;

        // Use reflection to test protected method
        $reflection = new ReflectionClass($channel);
        $method = $reflection->getMethod('determineType');

        $type = $method->invoke($channel, 'App\Notifications\Application\DeploymentApprovalRequired');

        expect($type)->toBe('deployment_approval');
    });

    it('correctly identifies DeploymentSuccess type', function () {
        $channel = new InAppChannel;

        $reflection = new ReflectionClass($channel);
        $method = $reflection->getMethod('determineType');

        $type = $method->invoke($channel, 'App\Notifications\Application\DeploymentSuccess');

        expect($type)->toBe('deployment_success');
    });

    it('correctly identifies DeploymentApproved type', function () {
        $channel = new InAppChannel;

        $reflection = new ReflectionClass($channel);
        $method = $reflection->getMethod('determineType');

        $type = $method->invoke($channel, 'App\Notifications\Application\DeploymentApproved');

        expect($type)->toBe('deployment_success');
    });

    it('correctly identifies DeploymentRejected type', function () {
        $channel = new InAppChannel;

        $reflection = new ReflectionClass($channel);
        $method = $reflection->getMethod('determineType');

        $type = $method->invoke($channel, 'App\Notifications\Application\DeploymentRejected');

        expect($type)->toBe('deployment_failure');
    });

    it('returns info for unknown notification types', function () {
        $channel = new InAppChannel;

        $reflection = new ReflectionClass($channel);
        $method = $reflection->getMethod('determineType');

        $type = $method->invoke($channel, 'App\Notifications\SomeUnknownNotification');

        expect($type)->toBe('info');
    });
});

describe('InAppChannel typeMapping', function () {
    it('has deployment_approval_required mapping', function () {
        $channel = new InAppChannel;

        $reflection = new ReflectionClass($channel);
        $property = $reflection->getProperty('typeMapping');
        $mapping = $property->getValue($channel);

        expect($mapping)->toHaveKey('deployment_approval_required');
        expect($mapping['deployment_approval_required'])->toBe('deployment_approval');
    });
});

describe('Project getApprovers', function () {
    afterEach(function () {
        m::close();
    });

    it('returns project admins and team admins combined and deduplicated', function () {
        $user1 = m::mock(User::class)->makePartial();
        $user1->id = 1;

        $user2 = m::mock(User::class)->makePartial();
        $user2->id = 2;

        $user3 = m::mock(User::class)->makePartial();
        $user3->id = 3;

        // User 1 is both project admin and team admin (should be deduplicated)
        $projectAdmins = collect([$user1, $user2]);
        $teamAdmins = collect([$user1, $user3]);

        $team = m::mock(Team::class)->makePartial();
        $teamMembersRelation = m::mock();
        $teamMembersRelation->shouldReceive('wherePivotIn')
            ->with('role', ['owner', 'admin'])
            ->andReturnSelf();
        $teamMembersRelation->shouldReceive('get')
            ->andReturn($teamAdmins);
        $team->shouldReceive('members')->andReturn($teamMembersRelation);

        $project = m::mock(Project::class)->makePartial();
        $adminsRelation = m::mock();
        $adminsRelation->shouldReceive('get')->andReturn($projectAdmins);
        $project->shouldReceive('admins')->andReturn($adminsRelation);
        $project->shouldReceive('getAttribute')->with('team')->andReturn($team);

        $approvers = $project->getApprovers();

        // Should have 3 unique users (user1 deduplicated)
        expect($approvers)->toHaveCount(3);
        expect($approvers->pluck('id')->toArray())->toContain(1);
        expect($approvers->pluck('id')->toArray())->toContain(2);
        expect($approvers->pluck('id')->toArray())->toContain(3);
    });
});

describe('HasNotificationSettings inAppEvents', function () {
    it('includes deployment_approval_required in inAppEvents', function () {
        // Create anonymous class using the trait
        $traitUser = new class
        {
            use \App\Traits\HasNotificationSettings;

            public function getInAppEvents(): array
            {
                return $this->inAppEvents;
            }
        };

        $events = $traitUser->getInAppEvents();

        expect($events)->toContain('deployment_approval_required');
        expect($events)->toContain('deployment_success');
        expect($events)->toContain('deployment_failure');
    });
});

describe('UserNotification types', function () {
    it('includes deployment_approval in valid types', function () {
        $types = \App\Models\UserNotification::TYPES;

        expect($types)->toContain('deployment_approval');
        expect($types)->toContain('deployment_success');
        expect($types)->toContain('deployment_failure');
    });
});
