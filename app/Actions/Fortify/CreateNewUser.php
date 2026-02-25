<?php

namespace App\Actions\Fortify;

use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $settings = instanceSettings();

        // Check for valid root team invitation to bypass registration lock
        $invitation = $this->resolveRootTeamInvitation($input);

        if (! $settings->is_registration_enabled && ! $invitation) {
            abort(403);
        }

        // If registering via invitation, enforce email match
        $emailRules = [
            'required', 'string', 'email', 'max:255',
            Rule::unique(User::class),
        ];
        if ($invitation) {
            $emailRules[] = function (string $attribute, mixed $value, \Closure $fail) use ($invitation) {
                if (strtolower($value) !== strtolower($invitation->email)) {
                    $fail('The email must match the invitation email.');
                }
            };
        }

        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => $emailRules,
            'password' => ['required', Password::defaults(), 'confirmed'],
        ])->validate();

        if (User::count() == 0) {
            // If this is the first user, make them the root user
            // Team is already created in the database/seeders/ProductionSeeder.php
            $user = User::create([
                'id' => 0,
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => Hash::make($input['password']),
            ]);
            $team = $user->teams()->first();

            // Disable registration after first user is created
            $settings = instanceSettings();
            $settings->is_registration_enabled = false;
            $settings->save();
        } else {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => Hash::make($input['password']),
            ]);
            $team = $user->teams()->first();
            if (isCloud()) {
                $user->sendVerificationEmail();
            } else {
                $user->markEmailAsVerified();
            }

            // Auto-accept root team invitation after registration
            if ($invitation) {
                $this->acceptInvitation($user, $invitation);
                $team = $invitation->team;
            }
        }
        // Set session variable
        session(['currentTeam' => $team]);

        return $user;
    }

    /**
     * Resolve a valid root team invitation from input.
     */
    private function resolveRootTeamInvitation(array $input): ?TeamInvitation
    {
        $inviteUuid = $input['invite'] ?? null;
        if (! $inviteUuid) {
            return null;
        }

        $invitation = TeamInvitation::where('uuid', $inviteUuid)->first();
        if (! $invitation || ! $invitation->isValid() || $invitation->team_id !== 0) {
            return null;
        }

        return $invitation;
    }

    /**
     * Accept invitation and attach user to the team.
     */
    private function acceptInvitation(User $user, TeamInvitation $invitation): void
    {
        $pivotData = [
            'role' => $invitation->role ?? 'member',
            'invited_by' => $invitation->invited_by,
            'allowed_projects' => $invitation->allowed_projects,
        ];

        if ($invitation->permission_set_id) {
            $pivotData['permission_set_id'] = $invitation->permission_set_id;
        }

        $invitation->team->members()->attach($user->id, $pivotData);

        // Handle custom permissions
        /** @var array<int, array{permission_id: int, environment_restrictions: array<string, bool>}>|null $customPerms */
        $customPerms = $invitation->custom_permissions;
        if (! empty($customPerms)) {
            $personalSet = \App\Models\PermissionSet::create([
                'name' => "Personal - {$user->name}",
                'slug' => 'personal-'.$user->id.'-'.time(),
                'description' => 'Custom permissions assigned during invitation',
                'team_id' => $invitation->team_id,
                'is_system' => false,
            ]);

            foreach ($customPerms as $perm) {
                $personalSet->permissions()->attach($perm['permission_id'], [
                    'environment_restrictions' => json_encode($perm['environment_restrictions']),
                ]);
            }

            $invitation->team->members()->updateExistingPivot($user->id, [
                'permission_set_id' => $personalSet->id,
            ]);
        }

        $invitation->delete();
    }
}
