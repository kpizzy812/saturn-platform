<?php

use App\Models\Team;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

beforeEach(function () {
    $this->team = Team::factory()->create();
    $this->user = User::factory()->create();
    $this->team->members()->attach($this->user->id, ['role' => 'owner']);

    session(['currentTeam' => $this->team]);
    $this->token = $this->user->createToken('test-token', ['*']);
    $this->bearerToken = $this->token->plainTextToken;

    if (! \App\Models\InstanceSettings::first()) {
        \App\Models\InstanceSettings::create([
            'id' => 0,
            'is_api_enabled' => true,
        ]);
    }
});

function notifHeaders($bearerToken): array
{
    return [
        'Authorization' => 'Bearer '.$bearerToken,
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
    ];
}

function createNotification(int $teamId, array $overrides = []): UserNotification
{
    return UserNotification::create(array_merge([
        'team_id' => $teamId,
        'type' => 'info',
        'title' => 'Test Notification',
        'description' => 'Test description',
        'is_read' => false,
    ], $overrides));
}

// ─── GET /api/v1/notifications ───

describe('GET /api/v1/notifications', function () {
    test('returns 401 without authentication', function () {
        $this->getJson('/api/v1/notifications')
            ->assertStatus(401);
    });

    test('returns empty list when no notifications', function () {
        $response = $this->withHeaders(notifHeaders($this->bearerToken))
            ->getJson('/api/v1/notifications');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 0)
            ->assertJsonPath('data', []);
    });

    test('returns notifications for the team', function () {
        createNotification($this->team->id);
        createNotification($this->team->id, ['title' => 'Second']);

        $response = $this->withHeaders(notifHeaders($this->bearerToken))
            ->getJson('/api/v1/notifications');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 2);
        expect(count($response->json('data')))->toBe(2);
    });

    test('does not return notifications from other teams', function () {
        $otherTeam = Team::factory()->create();
        createNotification($this->team->id);
        createNotification($otherTeam->id);

        $response = $this->withHeaders(notifHeaders($this->bearerToken))
            ->getJson('/api/v1/notifications');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1);
    });

    test('filters by type', function () {
        createNotification($this->team->id, ['type' => 'deployment_success']);
        createNotification($this->team->id, ['type' => 'security_alert']);

        $response = $this->withHeaders(notifHeaders($this->bearerToken))
            ->getJson('/api/v1/notifications?type=deployment_success');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1);
    });

    test('filters by read status', function () {
        createNotification($this->team->id, ['is_read' => false]);
        createNotification($this->team->id, ['is_read' => true]);

        $response = $this->withHeaders(notifHeaders($this->bearerToken))
            ->getJson('/api/v1/notifications?is_read=false');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1);
    });

    test('paginates results', function () {
        for ($i = 0; $i < 25; $i++) {
            createNotification($this->team->id, ['title' => "Notification $i"]);
        }

        $response = $this->withHeaders(notifHeaders($this->bearerToken))
            ->getJson('/api/v1/notifications?per_page=10');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 25)
            ->assertJsonPath('meta.per_page', 10);
        expect(count($response->json('data')))->toBe(10);
    });

    test('includes unread_count in meta', function () {
        createNotification($this->team->id, ['is_read' => false]);
        createNotification($this->team->id, ['is_read' => false]);
        createNotification($this->team->id, ['is_read' => true]);

        $response = $this->withHeaders(notifHeaders($this->bearerToken))
            ->getJson('/api/v1/notifications');

        $response->assertStatus(200)
            ->assertJsonPath('meta.unread_count', 2);
    });
});

// ─── GET /api/v1/notifications/unread-count ───

describe('GET /api/v1/notifications/unread-count', function () {
    test('returns unread count', function () {
        createNotification($this->team->id, ['is_read' => false]);
        createNotification($this->team->id, ['is_read' => false]);
        createNotification($this->team->id, ['is_read' => true]);

        $response = $this->withHeaders(notifHeaders($this->bearerToken))
            ->getJson('/api/v1/notifications/unread-count');

        $response->assertStatus(200)
            ->assertJson(['count' => 2]);
    });

    test('returns 0 when all are read', function () {
        createNotification($this->team->id, ['is_read' => true]);

        $response = $this->withHeaders(notifHeaders($this->bearerToken))
            ->getJson('/api/v1/notifications/unread-count');

        $response->assertStatus(200)
            ->assertJson(['count' => 0]);
    });
});

// ─── GET /api/v1/notifications/{id} ───

describe('GET /api/v1/notifications/{id}', function () {
    test('returns notification by ID', function () {
        $notification = createNotification($this->team->id);

        $response = $this->withHeaders(notifHeaders($this->bearerToken))
            ->getJson("/api/v1/notifications/{$notification->id}");

        $response->assertStatus(200)
            ->assertJsonPath('title', 'Test Notification');
    });

    test('returns 404 for non-existent notification', function () {
        $this->withHeaders(notifHeaders($this->bearerToken))
            ->getJson('/api/v1/notifications/00000000-0000-0000-0000-000000000000')
            ->assertStatus(404)
            ->assertJson(['message' => 'Notification not found.']);
    });

    test('returns 404 for notification from another team', function () {
        $otherTeam = Team::factory()->create();
        $notification = createNotification($otherTeam->id);

        $this->withHeaders(notifHeaders($this->bearerToken))
            ->getJson("/api/v1/notifications/{$notification->id}")
            ->assertStatus(404);
    });
});

// ─── POST /api/v1/notifications/{id}/read ───

describe('POST /api/v1/notifications/{id}/read', function () {
    test('marks notification as read', function () {
        $notification = createNotification($this->team->id, ['is_read' => false]);

        $response = $this->withHeaders(notifHeaders($this->bearerToken))
            ->postJson("/api/v1/notifications/{$notification->id}/read");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Notification marked as read.']);

        $notification->refresh();
        expect($notification->is_read)->toBeTrue();
        expect($notification->read_at)->not->toBeNull();
    });

    test('returns 404 for non-existent notification', function () {
        $this->withHeaders(notifHeaders($this->bearerToken))
            ->postJson('/api/v1/notifications/00000000-0000-0000-0000-000000000000/read')
            ->assertStatus(404);
    });
});

// ─── POST /api/v1/notifications/read-all ───

describe('POST /api/v1/notifications/read-all', function () {
    test('marks all notifications as read', function () {
        createNotification($this->team->id, ['is_read' => false]);
        createNotification($this->team->id, ['is_read' => false]);
        createNotification($this->team->id, ['is_read' => false]);

        $response = $this->withHeaders(notifHeaders($this->bearerToken))
            ->postJson('/api/v1/notifications/read-all');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'All notifications marked as read.',
                'count' => 3,
            ]);

        expect(UserNotification::where('team_id', $this->team->id)->where('is_read', false)->count())->toBe(0);
    });

    test('does not affect notifications from other teams', function () {
        $otherTeam = Team::factory()->create();
        createNotification($this->team->id, ['is_read' => false]);
        createNotification($otherTeam->id, ['is_read' => false]);

        $this->withHeaders(notifHeaders($this->bearerToken))
            ->postJson('/api/v1/notifications/read-all');

        expect(UserNotification::where('team_id', $otherTeam->id)->where('is_read', false)->count())->toBe(1);
    });
});

// ─── DELETE /api/v1/notifications/{id} ───

describe('DELETE /api/v1/notifications/{id}', function () {
    test('deletes a notification', function () {
        $notification = createNotification($this->team->id);

        $this->withHeaders(notifHeaders($this->bearerToken))
            ->deleteJson("/api/v1/notifications/{$notification->id}")
            ->assertStatus(200)
            ->assertJson(['message' => 'Notification deleted.']);

        expect(UserNotification::find($notification->id))->toBeNull();
    });

    test('returns 404 for non-existent notification', function () {
        $this->withHeaders(notifHeaders($this->bearerToken))
            ->deleteJson('/api/v1/notifications/00000000-0000-0000-0000-000000000000')
            ->assertStatus(404);
    });

    test('cannot delete notification from another team', function () {
        $otherTeam = Team::factory()->create();
        $notification = createNotification($otherTeam->id);

        $this->withHeaders(notifHeaders($this->bearerToken))
            ->deleteJson("/api/v1/notifications/{$notification->id}")
            ->assertStatus(404);
    });
});

// ─── GET /api/v1/notifications/preferences ───

describe('GET /api/v1/notifications/preferences', function () {
    test('returns notification preferences', function () {
        $response = $this->withHeaders(notifHeaders($this->bearerToken))
            ->getJson('/api/v1/notifications/preferences');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'email',
                'inApp',
                'digest',
            ]);
    });
});

// ─── PUT /api/v1/notifications/preferences ───

describe('PUT /api/v1/notifications/preferences', function () {
    test('updates notification preferences', function () {
        $response = $this->withHeaders(notifHeaders($this->bearerToken))
            ->putJson('/api/v1/notifications/preferences', [
                'email' => [
                    'deployments' => false,
                    'team' => true,
                    'billing' => false,
                    'security' => true,
                ],
                'inApp' => [
                    'deployments' => true,
                    'team' => true,
                    'billing' => false,
                    'security' => true,
                ],
                'digest' => 'daily',
            ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Preferences updated successfully.']);
    });
});
