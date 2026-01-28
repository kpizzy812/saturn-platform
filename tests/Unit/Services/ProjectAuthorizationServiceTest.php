<?php

/**
 * Unit tests for ProjectAuthorizationService.
 *
 * Tests authorization logic for projects, environments, and deployments.
 * Uses Mockery to mock models for true unit testing without database.
 */

use App\Models\Environment;
use App\Models\Project;
use App\Models\User;
use App\Services\Authorization\ProjectAuthorizationService;
use Mockery as m;

beforeEach(function () {
    $this->service = new ProjectAuthorizationService;
});

afterEach(function () {
    m::close();
});

describe('canViewProject', function () {
    it('allows platform admin to view any project', function () {
        $user = m::mock(User::class)->makePartial();
        $user->shouldReceive('isPlatformAdmin')->andReturn(true);
        $user->shouldReceive('isSuperAdmin')->andReturn(false);

        $project = m::mock(Project::class)->makePartial();

        expect($this->service->canViewProject($user, $project))->toBeTrue();
    });

    it('allows super admin to view any project', function () {
        $user = m::mock(User::class)->makePartial();
        $user->shouldReceive('isPlatformAdmin')->andReturn(false);
        $user->shouldReceive('isSuperAdmin')->andReturn(true);

        $project = m::mock(Project::class)->makePartial();

        expect($this->service->canViewProject($user, $project))->toBeTrue();
    });

    it('allows direct project member to view project', function () {
        $user = m::mock(User::class)->makePartial();
        $user->shouldReceive('isPlatformAdmin')->andReturn(false);
        $user->shouldReceive('isSuperAdmin')->andReturn(false);

        $projectMemberships = m::mock();
        $projectMemberships->shouldReceive('where')->with('project_id', 1)->andReturnSelf();
        $projectMemberships->shouldReceive('exists')->andReturn(true);
        $user->shouldReceive('projectMemberships')->andReturn($projectMemberships);

        $project = m::mock(Project::class)->makePartial();
        $project->shouldReceive('getAttribute')->with('id')->andReturn(1);

        expect($this->service->canViewProject($user, $project))->toBeTrue();
    });

    it('allows team member to view project', function () {
        $user = m::mock(User::class)->makePartial();
        $user->shouldReceive('isPlatformAdmin')->andReturn(false);
        $user->shouldReceive('isSuperAdmin')->andReturn(false);

        $projectMemberships = m::mock();
        $projectMemberships->shouldReceive('where')->with('project_id', 1)->andReturnSelf();
        $projectMemberships->shouldReceive('exists')->andReturn(false);
        $user->shouldReceive('projectMemberships')->andReturn($projectMemberships);

        $teams = m::mock();
        $teams->shouldReceive('where')->with('team_id', 10)->andReturnSelf();
        $teams->shouldReceive('exists')->andReturn(true);
        $user->shouldReceive('teams')->andReturn($teams);

        $project = m::mock(Project::class)->makePartial();
        $project->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $project->shouldReceive('getAttribute')->with('team_id')->andReturn(10);

        expect($this->service->canViewProject($user, $project))->toBeTrue();
    });

    it('denies non-member from viewing project', function () {
        $user = m::mock(User::class)->makePartial();
        $user->shouldReceive('isPlatformAdmin')->andReturn(false);
        $user->shouldReceive('isSuperAdmin')->andReturn(false);

        $projectMemberships = m::mock();
        $projectMemberships->shouldReceive('where')->with('project_id', 1)->andReturnSelf();
        $projectMemberships->shouldReceive('exists')->andReturn(false);
        $user->shouldReceive('projectMemberships')->andReturn($projectMemberships);

        $teams = m::mock();
        $teams->shouldReceive('where')->with('team_id', 10)->andReturnSelf();
        $teams->shouldReceive('exists')->andReturn(false);
        $user->shouldReceive('teams')->andReturn($teams);

        $project = m::mock(Project::class)->makePartial();
        $project->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $project->shouldReceive('getAttribute')->with('team_id')->andReturn(10);

        expect($this->service->canViewProject($user, $project))->toBeFalse();
    });
});

describe('canManageProject', function () {
    it('allows platform admin to manage any project', function () {
        $user = m::mock(User::class)->makePartial();
        $user->shouldReceive('isPlatformAdmin')->andReturn(true);
        $user->shouldReceive('isSuperAdmin')->andReturn(false);

        $project = m::mock(Project::class)->makePartial();

        expect($this->service->canManageProject($user, $project))->toBeTrue();
    });

    it('allows project owner to manage project', function () {
        $user = m::mock(User::class)->makePartial();
        $user->shouldReceive('isPlatformAdmin')->andReturn(false);
        $user->shouldReceive('isSuperAdmin')->andReturn(false);

        $project = m::mock(Project::class)->makePartial();
        $user->shouldReceive('roleInProject')->with($project)->andReturn('owner');

        expect($this->service->canManageProject($user, $project))->toBeTrue();
    });

    it('allows project admin to manage project', function () {
        $user = m::mock(User::class)->makePartial();
        $user->shouldReceive('isPlatformAdmin')->andReturn(false);
        $user->shouldReceive('isSuperAdmin')->andReturn(false);

        $project = m::mock(Project::class)->makePartial();
        $user->shouldReceive('roleInProject')->with($project)->andReturn('admin');

        expect($this->service->canManageProject($user, $project))->toBeTrue();
    });

    it('denies developer from managing project', function () {
        $user = m::mock(User::class)->makePartial();
        $user->shouldReceive('isPlatformAdmin')->andReturn(false);
        $user->shouldReceive('isSuperAdmin')->andReturn(false);

        $project = m::mock(Project::class)->makePartial();
        $project->shouldReceive('getAttribute')->with('team_id')->andReturn(10);

        $user->shouldReceive('roleInProject')->with($project)->andReturn('developer');

        $teams = m::mock();
        $teams->shouldReceive('where')->with('team_id', 10)->andReturnSelf();
        $teams->shouldReceive('first')->andReturn(null);
        $user->shouldReceive('teams')->andReturn($teams);

        expect($this->service->canManageProject($user, $project))->toBeFalse();
    });

    it('falls back to team admin role for manage permissions', function () {
        $user = m::mock(User::class)->makePartial();
        $user->shouldReceive('isPlatformAdmin')->andReturn(false);
        $user->shouldReceive('isSuperAdmin')->andReturn(false);

        $project = m::mock(Project::class)->makePartial();
        $project->shouldReceive('getAttribute')->with('team_id')->andReturn(10);

        $user->shouldReceive('roleInProject')->with($project)->andReturn(null);

        $teamMembership = new \stdClass;
        $teamMembership->pivot = new \stdClass;
        $teamMembership->pivot->role = 'admin';

        $teams = m::mock();
        $teams->shouldReceive('where')->with('team_id', 10)->andReturnSelf();
        $teams->shouldReceive('first')->andReturn($teamMembership);
        $user->shouldReceive('teams')->andReturn($teams);

        expect($this->service->canManageProject($user, $project))->toBeTrue();
    });
});

describe('canDeleteProject', function () {
    it('allows platform owner to delete any project', function () {
        $user = m::mock(User::class)->makePartial();
        $user->shouldReceive('isPlatformOwner')->andReturn(true);
        $user->shouldReceive('isSuperAdmin')->andReturn(false);

        $project = m::mock(Project::class)->makePartial();

        expect($this->service->canDeleteProject($user, $project))->toBeTrue();
    });

    it('allows project owner to delete project', function () {
        $user = m::mock(User::class)->makePartial();
        $user->shouldReceive('isPlatformOwner')->andReturn(false);
        $user->shouldReceive('isSuperAdmin')->andReturn(false);

        $project = m::mock(Project::class)->makePartial();
        $user->shouldReceive('roleInProject')->with($project)->andReturn('owner');

        expect($this->service->canDeleteProject($user, $project))->toBeTrue();
    });

    it('denies project admin from deleting project', function () {
        $user = m::mock(User::class)->makePartial();
        $user->shouldReceive('isPlatformOwner')->andReturn(false);
        $user->shouldReceive('isSuperAdmin')->andReturn(false);

        $project = m::mock(Project::class)->makePartial();
        $project->shouldReceive('getAttribute')->with('team_id')->andReturn(10);

        $user->shouldReceive('roleInProject')->with($project)->andReturn('admin');

        $teams = m::mock();
        $teams->shouldReceive('where')->with('team_id', 10)->andReturnSelf();
        $teams->shouldReceive('first')->andReturn(null);
        $user->shouldReceive('teams')->andReturn($teams);

        expect($this->service->canDeleteProject($user, $project))->toBeFalse();
    });
});

describe('hasMinimumRole', function () {
    it('returns true when user has exact required role', function () {
        $project = m::mock(Project::class)->makePartial();
        $project->shouldReceive('getAttribute')->with('team_id')->andReturn(1);

        $user = m::mock(User::class)->makePartial();
        $user->shouldReceive('roleInProject')->with($project)->andReturn('developer');

        $teams = m::mock();
        $teams->shouldReceive('where')->with('team_id', 1)->andReturnSelf();
        $teams->shouldReceive('first')->andReturn(null);
        $user->shouldReceive('teams')->andReturn($teams);

        expect($this->service->hasMinimumRole($user, $project, 'developer'))->toBeTrue();
    });

    it('returns true when user has higher role', function () {
        $project = m::mock(Project::class)->makePartial();
        $project->shouldReceive('getAttribute')->with('team_id')->andReturn(1);

        $user = m::mock(User::class)->makePartial();
        $user->shouldReceive('roleInProject')->with($project)->andReturn('admin');

        $teams = m::mock();
        $teams->shouldReceive('where')->with('team_id', 1)->andReturnSelf();
        $teams->shouldReceive('first')->andReturn(null);
        $user->shouldReceive('teams')->andReturn($teams);

        expect($this->service->hasMinimumRole($user, $project, 'developer'))->toBeTrue();
    });

    it('returns false when user has lower role', function () {
        $project = m::mock(Project::class)->makePartial();
        $project->shouldReceive('getAttribute')->with('team_id')->andReturn(1);

        $user = m::mock(User::class)->makePartial();
        $user->shouldReceive('roleInProject')->with($project)->andReturn('viewer');

        $teams = m::mock();
        $teams->shouldReceive('where')->with('team_id', 1)->andReturnSelf();
        $teams->shouldReceive('first')->andReturn(null);
        $user->shouldReceive('teams')->andReturn($teams);

        expect($this->service->hasMinimumRole($user, $project, 'developer'))->toBeFalse();
    });

    it('returns false when user has no role in project', function () {
        $project = m::mock(Project::class)->makePartial();
        $project->shouldReceive('getAttribute')->with('team_id')->andReturn(1);

        $user = m::mock(User::class)->makePartial();
        $user->shouldReceive('roleInProject')->with($project)->andReturn(null);

        $teams = m::mock();
        $teams->shouldReceive('where')->with('team_id', 1)->andReturnSelf();
        $teams->shouldReceive('first')->andReturn(null);
        $user->shouldReceive('teams')->andReturn($teams);

        expect($this->service->hasMinimumRole($user, $project, 'viewer'))->toBeFalse();
    });
});

describe('canApproveDeployment', function () {
    it('allows platform admin to approve any deployment', function () {
        $user = m::mock(User::class)->makePartial();
        $user->shouldReceive('isPlatformAdmin')->andReturn(true);
        $user->shouldReceive('isSuperAdmin')->andReturn(false);

        $environment = m::mock(Environment::class)->makePartial();

        expect($this->service->canApproveDeployment($user, $environment))->toBeTrue();
    });

    it('allows project admin to approve deployment', function () {
        $user = m::mock(User::class)->makePartial();
        $user->shouldReceive('isPlatformAdmin')->andReturn(false);
        $user->shouldReceive('isSuperAdmin')->andReturn(false);

        $project = m::mock(Project::class)->makePartial();
        $project->shouldReceive('getAttribute')->with('team_id')->andReturn(10);

        $environment = m::mock(Environment::class)->makePartial();
        $environment->shouldReceive('getAttribute')->with('project')->andReturn($project);

        $user->shouldReceive('roleInProject')->with($project)->andReturn('admin');

        expect($this->service->canApproveDeployment($user, $environment))->toBeTrue();
    });

    it('denies developer from approving deployment', function () {
        $user = m::mock(User::class)->makePartial();
        $user->shouldReceive('isPlatformAdmin')->andReturn(false);
        $user->shouldReceive('isSuperAdmin')->andReturn(false);

        $project = m::mock(Project::class)->makePartial();
        $project->shouldReceive('getAttribute')->with('team_id')->andReturn(10);

        $environment = m::mock(Environment::class)->makePartial();
        $environment->shouldReceive('getAttribute')->with('project')->andReturn($project);

        $user->shouldReceive('roleInProject')->with($project)->andReturn('developer');

        $teams = m::mock();
        $teams->shouldReceive('where')->with('team_id', 10)->andReturnSelf();
        $teams->shouldReceive('first')->andReturn(null);
        $user->shouldReceive('teams')->andReturn($teams);

        expect($this->service->canApproveDeployment($user, $environment))->toBeFalse();
    });
});
