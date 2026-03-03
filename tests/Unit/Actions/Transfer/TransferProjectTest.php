<?php

namespace Tests\Unit\Actions\Transfer;

use App\Actions\Transfer\TransferProject;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Mockery;
use ReflectionClass;
use Tests\TestCase;

class TransferProjectTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_can_be_instantiated(): void
    {
        $this->assertInstanceOf(TransferProject::class, new TransferProject);
    }

    public function test_create_snapshot_returns_correct_structure(): void
    {
        $team = (object) ['id' => 5, 'name' => 'My Team'];

        $env1 = $this->makeEnv(1, 'production', 2, 1);
        $env2 = $this->makeEnv(2, 'staging', 0, 0);
        $environments = collect([$env1, $env2]);

        $project = Mockery::mock(Project::class)->makePartial();
        $project->name = 'Test Project';
        $project->description = 'A description';
        $project->team_id = 5;
        $project->shouldReceive('getAttribute')->with('team')->andReturn($team);
        $project->shouldReceive('getAttribute')->with('environments')->andReturn($environments);
        $project->shouldReceive('load')->andReturnSelf();

        $method = $this->privateMethod(new TransferProject, 'createSnapshot');
        $snapshot = $method->invoke(new TransferProject, $project);

        $this->assertEquals('Test Project', $snapshot['name']);
        $this->assertEquals(5, $snapshot['team_id']);
        $this->assertEquals('My Team', $snapshot['team_name']);
        $this->assertEquals(2, $snapshot['environments_count']);
        $this->assertEquals(2, $snapshot['applications_count']);
        $this->assertEquals(1, $snapshot['services_count']);
        $this->assertCount(2, $snapshot['environments']);
        $this->assertArrayHasKey('snapshot_at', $snapshot);
    }

    public function test_record_related_resources_maps_environments_and_apps(): void
    {
        $env = $this->makeEnv(1, 'production', 1, 0);
        $project = Mockery::mock(Project::class)->makePartial();
        $project->shouldReceive('getAttribute')->with('environments')->andReturn(collect([$env]));

        $method = $this->privateMethod(new TransferProject, 'recordRelatedResources');
        $related = $method->invoke(new TransferProject, $project);

        $this->assertArrayHasKey('environments', $related);
        $this->assertCount(1, $related['environments']);
        $this->assertEquals('production', $related['environments'][0]['name']);
        $this->assertArrayHasKey('applications', $related);
        $this->assertCount(1, $related['applications']);
        $this->assertEquals('production', $related['applications'][0]['environment']);
    }

    public function test_record_related_resources_handles_empty_project(): void
    {
        $project = Mockery::mock(Project::class)->makePartial();
        $project->shouldReceive('getAttribute')->with('environments')->andReturn(collect([]));

        $method = $this->privateMethod(new TransferProject, 'recordRelatedResources');
        $related = $method->invoke(new TransferProject, $project);

        $this->assertEmpty($related);
    }

    public function test_sync_project_users_attaches_new_team_admins(): void
    {
        $admin = (object) ['id' => 42];

        // Project members relation: user not yet a member
        $projectMembers = Mockery::mock(BelongsToMany::class);
        $projectMembers->shouldReceive('where')->with('user_id', 42)->andReturnSelf();
        $projectMembers->shouldReceive('exists')->andReturn(false);
        $projectMembers->shouldReceive('attach')->once()->with(42, ['role' => 'admin']);

        $project = Mockery::mock(Project::class)->makePartial();
        $project->shouldReceive('members')->andReturn($projectMembers);

        // Team members relation returning one admin
        $teamMembers = Mockery::mock(BelongsToMany::class);
        $teamMembers->shouldReceive('wherePivotIn')->with('role', ['owner', 'admin'])->andReturnSelf();
        $teamMembers->shouldReceive('get')->andReturn(collect([$admin]));

        $targetTeam = Mockery::mock(Team::class)->makePartial();
        $targetTeam->shouldReceive('members')->andReturn($teamMembers);

        $method = $this->privateMethod(new TransferProject, 'syncProjectUsers');
        $method->invoke(new TransferProject, $project, $targetTeam);

        $this->addToAssertionCount(1); // attach()->once() verified by Mockery on tearDown
    }

    public function test_sync_project_users_skips_existing_members(): void
    {
        $admin = (object) ['id' => 42];

        $projectMembers = Mockery::mock(BelongsToMany::class);
        $projectMembers->shouldReceive('where')->with('user_id', 42)->andReturnSelf();
        $projectMembers->shouldReceive('exists')->andReturn(true); // already a member
        $projectMembers->shouldReceive('attach')->never();

        $project = Mockery::mock(Project::class)->makePartial();
        $project->shouldReceive('members')->andReturn($projectMembers);

        $teamMembers = Mockery::mock(BelongsToMany::class);
        $teamMembers->shouldReceive('wherePivotIn')->andReturnSelf();
        $teamMembers->shouldReceive('get')->andReturn(collect([$admin]));

        $targetTeam = Mockery::mock(Team::class)->makePartial();
        $targetTeam->shouldReceive('members')->andReturn($teamMembers);

        $method = $this->privateMethod(new TransferProject, 'syncProjectUsers');
        $method->invoke(new TransferProject, $project, $targetTeam);

        $this->addToAssertionCount(1); // attach()->never() verified by Mockery on tearDown
    }

    // --- Helpers ---

    private function privateMethod(object $instance, string $name): \ReflectionMethod
    {
        $reflection = new ReflectionClass($instance);
        $method = $reflection->getMethod($name);
        $method->setAccessible(true);

        return $method;
    }

    /** Build a plain-object environment stub with apps/services collections. */
    private function makeEnv(int $id, string $name, int $appsCount, int $servicesCount): object
    {
        $apps = $appsCount > 0
            ? collect(array_map(fn ($i) => (object) ['id' => $i, 'name' => "app-{$i}"], range(1, $appsCount)))
            : collect([]);
        $services = $servicesCount > 0
            ? collect(array_map(fn ($i) => (object) ['id' => $i, 'name' => "svc-{$i}"], range(1, $servicesCount)))
            : collect([]);

        $dbTypes = ['postgresqls', 'mysqls', 'mariadbs', 'mongodbs', 'redis', 'keydbs', 'dragonflies', 'clickhouses'];

        $env = new \stdClass;
        $env->id = $id;
        $env->name = $name;
        $env->type = $name;
        $env->applications = $apps;
        $env->services = $services;
        foreach ($dbTypes as $type) {
            $env->$type = collect([]);
        }

        return $env;
    }
}
