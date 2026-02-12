<?php

use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

// =============================================================================
// RELATIONSHIPS
// =============================================================================

test('teams relationship returns BelongsToMany', function () {
    $user = new User;
    $relation = $user->teams();

    expect($relation)->toBeInstanceOf(BelongsToMany::class);
    expect($relation->getRelated())->toBeInstanceOf(Team::class);
});

test('teams relationship has role pivot', function () {
    $user = new User;
    $relation = $user->teams();

    expect($relation->getPivotColumns())->toContain('role');
});

test('projectMemberships relationship returns BelongsToMany', function () {
    $user = new User;
    $relation = $user->projectMemberships();

    expect($relation)->toBeInstanceOf(BelongsToMany::class);
    expect($relation->getRelated())->toBeInstanceOf(Project::class);
    expect($relation->getTable())->toBe('project_user');
});

test('projectMemberships relationship has role and environment_permissions pivot', function () {
    $user = new User;
    $relation = $user->projectMemberships();

    $pivotColumns = $relation->getPivotColumns();
    expect($pivotColumns)->toContain('role');
    expect($pivotColumns)->toContain('environment_permissions');
});

test('changelogReads relationship returns HasMany', function () {
    $user = new User;
    $relation = $user->changelogReads();

    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(App\Models\UserChangelogRead::class);
});

// =============================================================================
// FILLABLE ATTRIBUTES & SECURITY
// =============================================================================

test('fillable does not contain is_superadmin', function () {
    $user = new User;

    expect($user->getFillable())->not->toContain('is_superadmin');
});

test('fillable does not contain platform_role', function () {
    $user = new User;

    expect($user->getFillable())->not->toContain('platform_role');
});

test('fillable is not empty', function () {
    $user = new User;

    expect($user->getFillable())->not->toBeEmpty();
});

test('fillable contains expected safe attributes', function () {
    $user = new User;
    $fillable = $user->getFillable();

    expect($fillable)->toContain('name');
    expect($fillable)->toContain('email');
    expect($fillable)->toContain('password');
});

test('fillable contains status and suspended_at for user management', function () {
    $user = new User;
    $fillable = $user->getFillable();

    expect($fillable)->toContain('status');
    expect($fillable)->toContain('suspended_at');
    expect($fillable)->toContain('suspended_by');
    expect($fillable)->toContain('suspension_reason');
});

// =============================================================================
// HIDDEN ATTRIBUTES
// =============================================================================

test('password is hidden', function () {
    $user = new User;

    expect($user->getHidden())->toContain('password');
});

test('remember_token is hidden', function () {
    $user = new User;

    expect($user->getHidden())->toContain('remember_token');
});

test('two_factor_recovery_codes is hidden', function () {
    $user = new User;

    expect($user->getHidden())->toContain('two_factor_recovery_codes');
});

test('two_factor_secret is hidden', function () {
    $user = new User;

    expect($user->getHidden())->toContain('two_factor_secret');
});

// =============================================================================
// CASTS
// =============================================================================

test('email_verified_at is cast to datetime', function () {
    $user = new User;

    expect($user->getCasts())->toHaveKey('email_verified_at', 'datetime');
});

test('force_password_reset is cast to boolean', function () {
    $user = new User;

    expect($user->getCasts())->toHaveKey('force_password_reset', 'boolean');
});

test('show_boarding is cast to boolean', function () {
    $user = new User;

    expect($user->getCasts())->toHaveKey('show_boarding', 'boolean');
});

test('is_superadmin is cast to boolean', function () {
    $user = new User;

    expect($user->getCasts())->toHaveKey('is_superadmin', 'boolean');
});

test('platform_role is cast to string', function () {
    $user = new User;

    expect($user->getCasts())->toHaveKey('platform_role', 'string');
});

test('suspended_at is cast to datetime', function () {
    $user = new User;

    expect($user->getCasts())->toHaveKey('suspended_at', 'datetime');
});

test('last_login_at is cast to datetime', function () {
    $user = new User;

    expect($user->getCasts())->toHaveKey('last_login_at', 'datetime');
});

test('email_change_code_expires_at is cast to datetime', function () {
    $user = new User;

    expect($user->getCasts())->toHaveKey('email_change_code_expires_at', 'datetime');
});

// =============================================================================
// MUTATORS & ACCESSORS
// =============================================================================

test('setEmailAttribute converts email to lowercase', function () {
    $user = new User;
    $user->email = 'TEST@EXAMPLE.COM';

    expect($user->email)->toBe('test@example.com');
});

test('setEmailAttribute handles mixed case email', function () {
    $user = new User;
    $user->email = 'TeSt@ExAmPlE.CoM';

    expect($user->email)->toBe('test@example.com');
});

test('setPendingEmailAttribute converts email to lowercase', function () {
    $user = new User;
    $user->pending_email = 'NEW@EXAMPLE.COM';

    expect($user->pending_email)->toBe('new@example.com');
});

test('setPendingEmailAttribute handles null value', function () {
    $user = new User;
    $user->pending_email = null;

    expect($user->pending_email)->toBeNull();
});

// =============================================================================
// TEAM ROLE METHODS
// =============================================================================

test('isAdminOfTeam returns true when user has admin role', function () {
    $user = new User;
    $user->setRawAttributes(['id' => 1], true);

    $team1 = new stdClass;
    $team1->id = 1;
    $team1->pivot = (object) ['role' => 'admin'];

    $team2 = new stdClass;
    $team2->id = 2;
    $team2->pivot = (object) ['role' => 'member'];

    $user->setRelation('teams', collect([$team1, $team2]));

    expect($user->isAdminOfTeam(1))->toBeTrue();
});

test('isAdminOfTeam returns true when user has owner role', function () {
    $user = new User;
    $user->setRawAttributes(['id' => 1], true);

    $team = new stdClass;
    $team->id = 1;
    $team->pivot = (object) ['role' => 'owner'];

    $user->setRelation('teams', collect([$team]));

    expect($user->isAdminOfTeam(1))->toBeTrue();
});

test('isAdminOfTeam returns false when user has member role', function () {
    $user = new User;
    $user->setRawAttributes(['id' => 1], true);

    $team = new stdClass;
    $team->id = 1;
    $team->pivot = (object) ['role' => 'member'];

    $user->setRelation('teams', collect([$team]));

    expect($user->isAdminOfTeam(1))->toBeFalse();
});

test('isAdminOfTeam returns false when user is not in team', function () {
    $user = new User;
    $user->setRawAttributes(['id' => 1], true);

    $team = new stdClass;
    $team->id = 1;
    $team->pivot = (object) ['role' => 'owner'];

    $user->setRelation('teams', collect([$team]));

    expect($user->isAdminOfTeam(999))->toBeFalse();
});

test('canAccessSystemResources returns true for admin of root team', function () {
    $user = new User;
    $user->setRawAttributes(['id' => 1], true);

    $rootTeam = new stdClass;
    $rootTeam->id = 0;
    $rootTeam->pivot = (object) ['role' => 'admin'];

    $user->setRelation('teams', collect([$rootTeam]));

    expect($user->canAccessSystemResources())->toBeTrue();
});

test('canAccessSystemResources returns false when user not in root team', function () {
    $user = new User;
    $user->setRawAttributes(['id' => 1], true);

    $team = new stdClass;
    $team->id = 5;
    $team->pivot = (object) ['role' => 'owner'];

    $user->setRelation('teams', collect([$team]));

    expect($user->canAccessSystemResources())->toBeFalse();
});

test('canAccessSystemResources returns false when user is member of root team', function () {
    $user = new User;
    $user->setRawAttributes(['id' => 1], true);

    $rootTeam = new stdClass;
    $rootTeam->id = 0;
    $rootTeam->pivot = (object) ['role' => 'member'];

    $user->setRelation('teams', collect([$rootTeam]));

    expect($user->canAccessSystemResources())->toBeFalse();
});

// =============================================================================
// ROLE HELPER METHODS (TEAM CONTEXT)
// =============================================================================

test('isOwner returns true when role is owner', function () {
    $user = new User;
    $user->setRawAttributes(['id' => 1], true);
    $user->pivot = (object) ['role' => 'owner'];

    expect($user->isOwner())->toBeTrue();
});

test('isOwner returns false when role is admin', function () {
    $user = new User;
    $user->setRawAttributes(['id' => 1], true);
    $user->pivot = (object) ['role' => 'admin'];

    expect($user->isOwner())->toBeFalse();
});

test('isAdmin returns true when role is owner', function () {
    $user = new User;
    $user->setRawAttributes(['id' => 1], true);
    $user->pivot = (object) ['role' => 'owner'];

    expect($user->isAdmin())->toBeTrue();
});

test('isAdmin returns true when role is admin', function () {
    $user = new User;
    $user->setRawAttributes(['id' => 1], true);
    $user->pivot = (object) ['role' => 'admin'];

    expect($user->isAdmin())->toBeTrue();
});

test('isAdmin returns true when role is developer', function () {
    $user = new User;
    $user->setRawAttributes(['id' => 1], true);
    $user->pivot = (object) ['role' => 'developer'];

    expect($user->isAdmin())->toBeTrue();
});

test('isAdmin returns false when role is member', function () {
    $user = new User;
    $user->setRawAttributes(['id' => 1], true);
    $user->pivot = (object) ['role' => 'member'];

    expect($user->isAdmin())->toBeFalse();
});

test('isMember returns true when role is member', function () {
    $user = new User;
    $user->setRawAttributes(['id' => 1], true);
    $user->pivot = (object) ['role' => 'member'];

    expect($user->isMember())->toBeTrue();
});

test('isMember returns false when role is owner', function () {
    $user = new User;
    $user->setRawAttributes(['id' => 1], true);
    $user->pivot = (object) ['role' => 'owner'];

    expect($user->isMember())->toBeFalse();
});

test('isDeveloper returns true when role is developer', function () {
    $user = new User;
    $user->setRawAttributes(['id' => 1], true);
    $user->pivot = (object) ['role' => 'developer'];

    expect($user->isDeveloper())->toBeTrue();
});

test('isDeveloper returns false when role is member', function () {
    $user = new User;
    $user->setRawAttributes(['id' => 1], true);
    $user->pivot = (object) ['role' => 'member'];

    expect($user->isDeveloper())->toBeFalse();
});

test('isViewer returns true when role is viewer', function () {
    $user = new User;
    $user->setRawAttributes(['id' => 1], true);
    $user->pivot = (object) ['role' => 'viewer'];

    expect($user->isViewer())->toBeTrue();
});

test('isViewer returns false when role is member', function () {
    $user = new User;
    $user->setRawAttributes(['id' => 1], true);
    $user->pivot = (object) ['role' => 'member'];

    expect($user->isViewer())->toBeFalse();
});

// =============================================================================
// PROJECT PERMISSIONS
// =============================================================================

test('roleInProject returns owner when user has owner role', function () {
    $user = Mockery::mock(User::class)->makePartial();
    $user->setRawAttributes(['id' => 1], true);

    $project = Mockery::mock(Project::class)->makePartial();
    $project->shouldReceive('getAttribute')->with('id')->andReturn(1);

    $membership = new stdClass;
    $membership->pivot = (object) ['role' => 'owner'];

    $relation = Mockery::mock(BelongsToMany::class);
    $relation->shouldReceive('where')->with('project_id', 1)->andReturnSelf();
    $relation->shouldReceive('first')->andReturn($membership);

    $user->shouldReceive('projectMemberships')->andReturn($relation);

    expect($user->roleInProject($project))->toBe('owner');
});

test('roleInProject returns admin when user has admin role', function () {
    $user = Mockery::mock(User::class)->makePartial();
    $user->setRawAttributes(['id' => 1], true);

    $project = Mockery::mock(Project::class)->makePartial();
    $project->shouldReceive('getAttribute')->with('id')->andReturn(1);

    $membership = new stdClass;
    $membership->pivot = (object) ['role' => 'admin'];

    $relation = Mockery::mock(BelongsToMany::class);
    $relation->shouldReceive('where')->with('project_id', 1)->andReturnSelf();
    $relation->shouldReceive('first')->andReturn($membership);

    $user->shouldReceive('projectMemberships')->andReturn($relation);

    expect($user->roleInProject($project))->toBe('admin');
});

test('roleInProject returns null when user not project member', function () {
    $user = Mockery::mock(User::class)->makePartial();
    $user->setRawAttributes(['id' => 1], true);

    $project = Mockery::mock(Project::class)->makePartial();
    $project->shouldReceive('getAttribute')->with('id')->andReturn(1);

    $relation = Mockery::mock(BelongsToMany::class);
    $relation->shouldReceive('where')->with('project_id', 1)->andReturnSelf();
    $relation->shouldReceive('first')->andReturn(null);

    $user->shouldReceive('projectMemberships')->andReturn($relation);

    expect($user->roleInProject($project))->toBeNull();
});

// =============================================================================
// Platform Role Tests
test('platformRole returns platform_role when set', function () {
    $user = new User;
    $user->platform_role = 'admin';

    expect($user->platformRole())->toBe('admin');
});

test('platformRole returns member when platform_role is null', function () {
    $user = new User;
    $user->platform_role = null;

    expect($user->platformRole())->toBe('member');
});

// isPlatformOwner Tests
test('isPlatformOwner returns true for user with id 0', function () {
    $user = new User;
    $user->id = 0;
    $user->platform_role = 'member';

    expect($user->isPlatformOwner())->toBeTrue();
});

test('isPlatformOwner returns true for user with owner role', function () {
    $user = new User;
    $user->id = 5;
    $user->platform_role = 'owner';

    expect($user->isPlatformOwner())->toBeTrue();
});

test('isPlatformOwner returns false for regular user', function () {
    $user = new User;
    $user->id = 5;
    $user->platform_role = 'admin';

    expect($user->isPlatformOwner())->toBeFalse();
});

// isPlatformAdmin Tests
test('isPlatformAdmin returns true for owner', function () {
    $user = new User;
    $user->platform_role = 'owner';

    expect($user->isPlatformAdmin())->toBeTrue();
});

test('isPlatformAdmin returns true for admin', function () {
    $user = new User;
    $user->platform_role = 'admin';

    expect($user->isPlatformAdmin())->toBeTrue();
});

test('isPlatformAdmin returns false for member', function () {
    $user = new User;
    $user->platform_role = 'member';

    expect($user->isPlatformAdmin())->toBeFalse();
});

test('isPlatformAdmin returns false for developer', function () {
    $user = new User;
    $user->platform_role = 'developer';

    expect($user->isPlatformAdmin())->toBeFalse();
});

// isSuperAdmin Tests
test('isSuperAdmin returns true for user id 0', function () {
    $user = new User;
    $user->id = 0;
    $user->is_superadmin = false;

    expect($user->isSuperAdmin())->toBeTrue();
});

test('isSuperAdmin returns true when is_superadmin flag is set', function () {
    $user = new User;
    $user->id = 42;
    $user->is_superadmin = true;

    expect($user->isSuperAdmin())->toBeTrue();
});

test('isSuperAdmin returns false for regular user', function () {
    $user = new User;
    $user->id = 42;
    $user->is_superadmin = false;

    expect($user->isSuperAdmin())->toBeFalse();
});

// Status Check Tests
test('isActive returns true when status is active', function () {
    $user = new User;
    $user->status = 'active';

    expect($user->isActive())->toBeTrue();
});

test('isActive returns false when status is not active', function () {
    $user = new User;
    $user->status = 'suspended';

    expect($user->isActive())->toBeFalse();
});

test('isSuspended returns true when status is suspended', function () {
    $user = new User;
    $user->status = 'suspended';

    expect($user->isSuspended())->toBeTrue();
});

test('isSuspended returns false when status is active', function () {
    $user = new User;
    $user->status = 'active';

    expect($user->isSuspended())->toBeFalse();
});

test('isBanned returns true when status is banned', function () {
    $user = new User;
    $user->status = 'banned';

    expect($user->isBanned())->toBeTrue();
});

test('isBanned returns false when status is active', function () {
    $user = new User;
    $user->status = 'active';

    expect($user->isBanned())->toBeFalse();
});

test('isPending returns true when status is pending', function () {
    $user = new User;
    $user->status = 'pending';

    expect($user->isPending())->toBeTrue();
});

test('isPending returns false when status is active', function () {
    $user = new User;
    $user->status = 'active';

    expect($user->isPending())->toBeFalse();
});

// hasPassword Tests
test('hasPassword returns true when password is set', function () {
    $user = new User;
    $user->password = '$2y$10$hashed_password_here';

    expect($user->hasPassword())->toBeTrue();
});

test('hasPassword returns false when password is null', function () {
    $user = new User;
    $user->password = null;

    expect($user->hasPassword())->toBeFalse();
});

test('hasPassword returns false when password is empty string', function () {
    $user = new User;
    $user->password = '';

    expect($user->hasPassword())->toBeFalse();
});

// hasEmailChangeRequest Tests
test('hasEmailChangeRequest returns true when valid request exists', function () {
    $user = new User;
    $user->pending_email = 'new@example.com';
    $user->email_change_code = '123456';
    $user->email_change_code_expires_at = Carbon::now()->addMinutes(10);

    expect($user->hasEmailChangeRequest())->toBeTrue();
});

test('hasEmailChangeRequest returns false when no pending email', function () {
    $user = new User;
    $user->pending_email = null;
    $user->email_change_code = '123456';
    $user->email_change_code_expires_at = Carbon::now()->addMinutes(10);

    expect($user->hasEmailChangeRequest())->toBeFalse();
});

test('hasEmailChangeRequest returns false when code expired', function () {
    $user = new User;
    $user->pending_email = 'new@example.com';
    $user->email_change_code = '123456';
    $user->email_change_code_expires_at = Carbon::now()->subMinutes(5);

    expect($user->hasEmailChangeRequest())->toBeFalse();
});

test('hasEmailChangeRequest returns false when no code', function () {
    $user = new User;
    $user->pending_email = 'new@example.com';
    $user->email_change_code = null;
    $user->email_change_code_expires_at = Carbon::now()->addMinutes(10);

    expect($user->hasEmailChangeRequest())->toBeFalse();
});

// isEmailChangeCodeValid Tests
test('isEmailChangeCodeValid returns true for matching code within expiry', function () {
    $user = new User;
    $user->email_change_code = '123456';
    $user->email_change_code_expires_at = Carbon::now()->addMinutes(10);

    expect($user->isEmailChangeCodeValid('123456'))->toBeTrue();
});

test('isEmailChangeCodeValid returns false for wrong code', function () {
    $user = new User;
    $user->email_change_code = '123456';
    $user->email_change_code_expires_at = Carbon::now()->addMinutes(10);

    expect($user->isEmailChangeCodeValid('654321'))->toBeFalse();
});

test('isEmailChangeCodeValid returns false for expired code', function () {
    $user = new User;
    $user->email_change_code = '123456';
    $user->email_change_code_expires_at = Carbon::now()->subMinutes(1);

    expect($user->isEmailChangeCodeValid('123456'))->toBeFalse();
});

test('isEmailChangeCodeValid returns false when no expiry set', function () {
    $user = new User;
    $user->email_change_code = '123456';
    $user->email_change_code_expires_at = null;

    expect($user->isEmailChangeCodeValid('123456'))->toBeFalse();
});

// getRecipients Tests
test('getRecipients returns array with user email', function () {
    $user = new User;
    $user->email = 'test@example.com';

    expect($user->getRecipients())->toBe(['test@example.com']);
});
