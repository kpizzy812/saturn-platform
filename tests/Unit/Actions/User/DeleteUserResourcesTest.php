<?php

use App\Actions\User\DeleteUserResources;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

beforeEach(function () {
    $this->user = Mockery::mock(User::class)->makePartial();
    $this->user->shouldReceive('getAttribute')->with('id')->andReturn(1);
    $this->user->shouldReceive('getAttribute')->with('email')->andReturn('test@example.com');
});

afterEach(function () {
    Mockery::close();
});

function createTeamMock(object $pivot, $members): Team
{
    $team = Mockery::mock(Team::class)->makePartial();
    $team->shouldReceive('getAttribute')->andReturnUsing(function ($key) use ($pivot, $members) {
        return match ($key) {
            'pivot' => $pivot,
            'members' => $members,
            default => null,
        };
    });
    $team->pivot = $pivot;
    $team->members = $members;

    return $team;
}

it('only collects resources from teams where user is the sole member', function () {
    $ownedTeamPivot = (object) ['role' => 'owner'];
    $ownedTeam = createTeamMock($ownedTeamPivot, collect([$this->user]));

    $memberTeamPivot = (object) ['role' => 'member'];
    $memberTeam = createTeamMock($memberTeamPivot, collect([$this->user]));

    // Mock servers for owned team
    $ownedServer = Mockery::mock(Server::class)->makePartial();
    $ownedServer->shouldReceive('applications')->andReturn(collect([
        (object) ['id' => 1, 'name' => 'app1'],
    ]));
    $ownedServer->shouldReceive('databases')->andReturn(collect([
        (object) ['id' => 1, 'name' => 'db1'],
    ]));
    $ownedServer->shouldReceive('services->get')->andReturn(collect([
        (object) ['id' => 1, 'name' => 'service1'],
    ]));

    // Mock teams relationship
    $teamsRelation = Mockery::mock(BelongsToMany::class);
    $teamsRelation->shouldReceive('get')->andReturn(collect([$ownedTeam, $memberTeam]));
    $this->user->shouldReceive('teams')->andReturn($teamsRelation);

    // Mock servers relationship for owned team
    $ownedServersRelation = Mockery::mock(HasMany::class);
    $ownedServersRelation->shouldReceive('get')->andReturn(collect([$ownedServer]));
    $ownedTeam->shouldReceive('servers')->andReturn($ownedServersRelation);

    $action = new DeleteUserResources($this->user, true);
    $preview = $action->getResourcesPreview();

    expect($preview['applications'])->toHaveCount(1);
    expect($preview['applications']->first()->id)->toBe(1);
    expect($preview['applications']->first()->name)->toBe('app1');

    expect($preview['databases'])->toHaveCount(1);
    expect($preview['databases']->first()->id)->toBe(1);

    expect($preview['services'])->toHaveCount(1);
    expect($preview['services']->first()->id)->toBe(1);
});

it('does not collect resources when user is owner but team has other members', function () {
    $otherUser = Mockery::mock(User::class)->makePartial();
    $otherUser->shouldReceive('getAttribute')->with('id')->andReturn(2);

    $ownedTeamPivot = (object) ['role' => 'owner'];
    $ownedTeam = createTeamMock($ownedTeamPivot, collect([$this->user, $otherUser]));

    $teamsRelation = Mockery::mock(BelongsToMany::class);
    $teamsRelation->shouldReceive('get')->andReturn(collect([$ownedTeam]));
    $this->user->shouldReceive('teams')->andReturn($teamsRelation);

    $action = new DeleteUserResources($this->user, true);
    $preview = $action->getResourcesPreview();

    expect($preview['applications'])->toBeEmpty();
    expect($preview['databases'])->toBeEmpty();
    expect($preview['services'])->toBeEmpty();
});

it('does not collect resources when user is only a member of teams', function () {
    $memberTeamPivot = (object) ['role' => 'member'];
    $memberTeam = createTeamMock($memberTeamPivot, collect([$this->user]));

    $teamsRelation = Mockery::mock(BelongsToMany::class);
    $teamsRelation->shouldReceive('get')->andReturn(collect([$memberTeam]));
    $this->user->shouldReceive('teams')->andReturn($teamsRelation);

    $action = new DeleteUserResources($this->user, true);
    $preview = $action->getResourcesPreview();

    expect($preview['applications'])->toBeEmpty();
    expect($preview['databases'])->toBeEmpty();
    expect($preview['services'])->toBeEmpty();
});

it('collects resources only from teams where user is sole member', function () {
    $ownedTeam1Pivot = (object) ['role' => 'owner'];
    $ownedTeam1 = createTeamMock($ownedTeam1Pivot, collect([$this->user]));

    $otherUser = Mockery::mock(User::class)->makePartial();
    $otherUser->shouldReceive('getAttribute')->with('id')->andReturn(2);

    $ownedTeam2Pivot = (object) ['role' => 'owner'];
    $ownedTeam2 = createTeamMock($ownedTeam2Pivot, collect([$this->user, $otherUser]));

    // Mock server for team 1
    $server1 = Mockery::mock(Server::class)->makePartial();
    $server1->shouldReceive('applications')->andReturn(collect([
        (object) ['id' => 1, 'name' => 'app1'],
    ]));
    $server1->shouldReceive('databases')->andReturn(collect([]));
    $server1->shouldReceive('services->get')->andReturn(collect([]));

    $teamsRelation = Mockery::mock(BelongsToMany::class);
    $teamsRelation->shouldReceive('get')->andReturn(collect([$ownedTeam1, $ownedTeam2]));
    $this->user->shouldReceive('teams')->andReturn($teamsRelation);

    $servers1Relation = Mockery::mock(HasMany::class);
    $servers1Relation->shouldReceive('get')->andReturn(collect([$server1]));
    $ownedTeam1->shouldReceive('servers')->andReturn($servers1Relation);

    $action = new DeleteUserResources($this->user, true);
    $preview = $action->getResourcesPreview();

    expect($preview['applications'])->toHaveCount(1);
    expect($preview['applications']->first()->id)->toBe(1);
});
