<?php

use App\Models\Application;
use App\Models\CloudProviderToken;
use App\Models\DiscordNotificationSettings;
use App\Models\EmailNotificationSettings;
use App\Models\PrivateKey;
use App\Models\Project;
use App\Models\PushoverNotificationSettings;
use App\Models\S3Storage;
use App\Models\Server;
use App\Models\SharedEnvironmentVariable;
use App\Models\SlackNotificationSettings;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\TeamUser;
use App\Models\TeamWebhook;
use App\Models\TelegramNotificationSettings;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\WebhookNotificationSettings;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

// Fillable Attributes Security Tests
test('fillable attributes are defined and not empty', function () {
    $team = new Team;

    $fillable = $team->getFillable();

    expect($fillable)->not->toBeEmpty()
        ->and($fillable)->toContain('name')
        ->and($fillable)->toContain('description')
        ->and($fillable)->toContain('show_boarding')
        ->and($fillable)->toContain('custom_server_limit')
        ->and($fillable)->toContain('logo')
        ->and($fillable)->toContain('timezone')
        ->and($fillable)->toContain('workspace_locale')
        ->and($fillable)->toContain('workspace_date_format')
        ->and($fillable)->toContain('default_project_id');
});

test('fillable does not contain sensitive fields', function () {
    $team = new Team;

    $fillable = $team->getFillable();

    expect($fillable)->not->toContain('id')
        ->and($fillable)->not->toContain('personal_team');
});

// Casts Tests
test('casts personal_team to boolean', function () {
    $team = new Team;

    $casts = $team->getCasts();

    expect($casts)->toHaveKey('personal_team')
        ->and($casts['personal_team'])->toBe('boolean');
});

// Relationship Type Tests
test('members relationship returns BelongsToMany', function () {
    $team = new Team;

    $relation = $team->members();

    expect($relation)->toBeInstanceOf(BelongsToMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(User::class);
});

test('members relationship uses custom pivot', function () {
    $team = new Team;

    $relation = $team->members();

    expect($relation->getPivotClass())->toBe(TeamUser::class);
});

test('subscription relationship returns HasOne', function () {
    $team = new Team;

    $relation = $team->subscription();

    expect($relation)->toBeInstanceOf(HasOne::class)
        ->and($relation->getRelated())->toBeInstanceOf(Subscription::class);
});

test('applications method returns Builder for Application', function () {
    $team = new Team;
    $team->id = 1;

    $builder = $team->applications();

    expect($builder)->toBeInstanceOf(\Illuminate\Database\Eloquent\Builder::class)
        ->and($builder->getModel())->toBeInstanceOf(Application::class);
});

test('invitations relationship returns HasMany', function () {
    $team = new Team;

    $relation = $team->invitations();

    expect($relation)->toBeInstanceOf(HasMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(TeamInvitation::class);
});

test('projects relationship returns HasMany', function () {
    $team = new Team;

    $relation = $team->projects();

    expect($relation)->toBeInstanceOf(HasMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(Project::class);
});

test('servers relationship returns HasMany', function () {
    $team = new Team;

    $relation = $team->servers();

    expect($relation)->toBeInstanceOf(HasMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(Server::class);
});

test('privateKeys relationship returns HasMany', function () {
    $team = new Team;

    $relation = $team->privateKeys();

    expect($relation)->toBeInstanceOf(HasMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(PrivateKey::class);
});

test('cloudProviderTokens relationship returns HasMany', function () {
    $team = new Team;

    $relation = $team->cloudProviderTokens();

    expect($relation)->toBeInstanceOf(HasMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(CloudProviderToken::class);
});

test('s3s relationship returns HasMany', function () {
    $team = new Team;

    $relation = $team->s3s();

    expect($relation)->toBeInstanceOf(HasMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(S3Storage::class);
});

test('emailNotificationSettings relationship returns HasOne', function () {
    $team = new Team;

    $relation = $team->emailNotificationSettings();

    expect($relation)->toBeInstanceOf(HasOne::class)
        ->and($relation->getRelated())->toBeInstanceOf(EmailNotificationSettings::class);
});

test('discordNotificationSettings relationship returns HasOne', function () {
    $team = new Team;

    $relation = $team->discordNotificationSettings();

    expect($relation)->toBeInstanceOf(HasOne::class)
        ->and($relation->getRelated())->toBeInstanceOf(DiscordNotificationSettings::class);
});

test('telegramNotificationSettings relationship returns HasOne', function () {
    $team = new Team;

    $relation = $team->telegramNotificationSettings();

    expect($relation)->toBeInstanceOf(HasOne::class)
        ->and($relation->getRelated())->toBeInstanceOf(TelegramNotificationSettings::class);
});

test('slackNotificationSettings relationship returns HasOne', function () {
    $team = new Team;

    $relation = $team->slackNotificationSettings();

    expect($relation)->toBeInstanceOf(HasOne::class)
        ->and($relation->getRelated())->toBeInstanceOf(SlackNotificationSettings::class);
});

test('pushoverNotificationSettings relationship returns HasOne', function () {
    $team = new Team;

    $relation = $team->pushoverNotificationSettings();

    expect($relation)->toBeInstanceOf(HasOne::class)
        ->and($relation->getRelated())->toBeInstanceOf(PushoverNotificationSettings::class);
});

test('webhookNotificationSettings relationship returns HasOne', function () {
    $team = new Team;

    $relation = $team->webhookNotificationSettings();

    expect($relation)->toBeInstanceOf(HasOne::class)
        ->and($relation->getRelated())->toBeInstanceOf(WebhookNotificationSettings::class);
});

test('userNotifications relationship returns HasMany', function () {
    $team = new Team;

    $relation = $team->userNotifications();

    expect($relation)->toBeInstanceOf(HasMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(UserNotification::class);
});

test('webhooks relationship returns HasMany', function () {
    $team = new Team;

    $relation = $team->webhooks();

    expect($relation)->toBeInstanceOf(HasMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(TeamWebhook::class);
});

test('environment_variables relationship returns HasMany', function () {
    $team = new Team;

    $relation = $team->environment_variables();

    expect($relation)->toBeInstanceOf(HasMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(SharedEnvironmentVariable::class);
});

// Notification Routing Tests
test('routeNotificationForDiscord returns discord_webhook_url', function () {
    $team = new Team;
    $team->setRawAttributes(['discord_webhook_url' => 'https://discord.com/webhook'], true);

    $result = $team->routeNotificationForDiscord();

    expect($result)->toBe('https://discord.com/webhook');
});

test('routeNotificationForDiscord returns null when not set', function () {
    $team = new Team;
    $team->setRawAttributes([], true);

    $result = $team->routeNotificationForDiscord();

    expect($result)->toBeNull();
});

test('routeNotificationForTelegram returns token and chat_id', function () {
    $team = new Team;
    $team->setRawAttributes([
        'telegram_token' => 'test-token',
        'telegram_chat_id' => '123456',
    ], true);

    $result = $team->routeNotificationForTelegram();

    expect($result)->toBe([
        'token' => 'test-token',
        'chat_id' => '123456',
    ]);
});

test('routeNotificationForSlack returns slack_webhook_url', function () {
    $team = new Team;
    $team->setRawAttributes(['slack_webhook_url' => 'https://slack.com/webhook'], true);

    $result = $team->routeNotificationForSlack();

    expect($result)->toBe('https://slack.com/webhook');
});

test('routeNotificationForPushover returns user and token', function () {
    $team = new Team;
    $team->setRawAttributes([
        'pushover_user_key' => 'user-key',
        'pushover_api_token' => 'api-token',
    ], true);

    $result = $team->routeNotificationForPushover();

    expect($result)->toBe([
        'user' => 'user-key',
        'token' => 'api-token',
    ]);
});

// Subscription Tests
test('subscriptionPastOverDue returns false when not cloud', function () {
    $team = new Team;

    $result = $team->subscriptionPastOverDue();

    expect($result)->toBeFalse();
});

// Limits Accessor Tests
test('limits accessor exists', function () {
    $team = new Team;

    // Verify the attribute accessor is defined using reflection
    $reflection = new ReflectionClass($team);
    $attributes = collect($reflection->getMethods())
        ->filter(fn ($method) => $method->getName() === 'limits')
        ->isNotEmpty();

    expect($attributes)->toBeTrue();
});

// Method Existence Tests
test('isEmpty method exists', function () {
    $team = new Team;

    expect(method_exists($team, 'isEmpty'))->toBeTrue();
});

test('userHasRestrictedAccess method exists', function () {
    $team = new Team;

    expect(method_exists($team, 'userHasRestrictedAccess'))->toBeTrue();
});

test('serverOverflow method exists', function () {
    $team = new Team;

    expect(method_exists($team, 'serverOverflow'))->toBeTrue();
});

test('getAllowedProjectsForUser method exists', function () {
    $team = new Team;

    expect(method_exists($team, 'getAllowedProjectsForUser'))->toBeTrue();
});

test('setAllowedProjectsForUser method exists', function () {
    $team = new Team;

    expect(method_exists($team, 'setAllowedProjectsForUser'))->toBeTrue();
});

test('subscriptionEnded method exists', function () {
    $team = new Team;

    expect(method_exists($team, 'subscriptionEnded'))->toBeTrue();
});

test('isAnyNotificationEnabled method exists', function () {
    $team = new Team;

    expect(method_exists($team, 'isAnyNotificationEnabled'))->toBeTrue();
});

test('getRecipients method exists', function () {
    $team = new Team;

    expect(method_exists($team, 'getRecipients'))->toBeTrue();
});

test('sources method exists', function () {
    $team = new Team;

    expect(method_exists($team, 'sources'))->toBeTrue();
});

// Trait Usage Tests
test('uses Auditable trait', function () {
    $team = new Team;

    expect(class_uses_recursive($team))->toContain(\App\Traits\Auditable::class);
});

test('uses HasNotificationSettings trait', function () {
    $team = new Team;

    expect(class_uses_recursive($team))->toContain(\App\Traits\HasNotificationSettings::class);
});

test('uses HasSafeStringAttribute trait', function () {
    $team = new Team;

    expect(class_uses_recursive($team))->toContain(\App\Traits\HasSafeStringAttribute::class);
});

test('uses LogsActivity trait', function () {
    $team = new Team;

    expect(class_uses_recursive($team))->toContain(\Spatie\Activitylog\Traits\LogsActivity::class);
});

test('uses Notifiable trait', function () {
    $team = new Team;

    expect(class_uses_recursive($team))->toContain(\Illuminate\Notifications\Notifiable::class);
});

// Interface Implementation Tests
test('implements SendsDiscord interface', function () {
    $team = new Team;

    expect($team)->toBeInstanceOf(\App\Notifications\Channels\SendsDiscord::class);
});

test('implements SendsEmail interface', function () {
    $team = new Team;

    expect($team)->toBeInstanceOf(\App\Notifications\Channels\SendsEmail::class);
});

test('implements SendsPushover interface', function () {
    $team = new Team;

    expect($team)->toBeInstanceOf(\App\Notifications\Channels\SendsPushover::class);
});

test('implements SendsSlack interface', function () {
    $team = new Team;

    expect($team)->toBeInstanceOf(\App\Notifications\Channels\SendsSlack::class);
});

// Activity Log Tests
test('getActivitylogOptions returns LogOptions', function () {
    $team = new Team;

    $options = $team->getActivitylogOptions();

    expect($options)->toBeInstanceOf(\Spatie\Activitylog\LogOptions::class);
});
