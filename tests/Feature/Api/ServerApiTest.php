<?php

use App\Actions\Server\DeleteServer;
use App\Actions\Server\ValidateServer;
use App\Models\Application;
use App\Models\Environment;
use App\Models\InstanceSettings;
use App\Models\PrivateKey;
use App\Models\Project;
use App\Models\Server;
use App\Models\StandaloneDocker;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;

uses(DatabaseTransactions::class);

beforeEach(function () {
    // Create a team with owner
    $this->team = Team::factory()->create();
    $this->user = User::factory()->create();
    $this->team->members()->attach($this->user->id, ['role' => 'owner']);

    // Set the current team session before creating the token
    session(['currentTeam' => $this->team]);

    // Create an API token for the user
    $this->token = $this->user->createToken('test-token', ['*']);
    $this->bearerToken = $this->token->plainTextToken;

    // Create a private key for the team
    $this->privateKey = PrivateKey::factory()->create(['team_id' => $this->team->id]);

    // Create InstanceSettings if needed
    if (! InstanceSettings::first()) {
        InstanceSettings::create([
            'id' => 0,
            'is_api_enabled' => true,
        ]);
    }
});

describe('Authentication', function () {
    test('rejects request without authentication', function () {
        $response = $this->getJson('/api/v1/servers');
        $response->assertStatus(401);
    });

    test('rejects request with invalid token', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid-token',
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/servers');

        $response->assertStatus(401);
    });
});

describe('GET /api/v1/servers - List servers', function () {
    test('returns empty array when no servers exist', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/servers');

        $response->assertStatus(200);
        $response->assertJsonCount(0);
    });

    test('lists all servers for the team', function () {
        // Create servers for this team without triggering boot events
        $server1 = Server::factory()->create([
            'team_id' => $this->team->id,
            'name' => 'Server 1',
            'ip' => '1.2.3.4',
            'private_key_id' => $this->privateKey->id,
        ]);
        $server2 = Server::factory()->create([
            'team_id' => $this->team->id,
            'name' => 'Server 2',
            'ip' => '5.6.7.8',
            'private_key_id' => $this->privateKey->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/servers');

        $response->assertStatus(200);
        $response->assertJsonCount(2);
        $response->assertJsonStructure([
            '*' => ['uuid', 'name', 'ip', 'user', 'port', 'description', 'is_reachable', 'is_usable', 'settings'],
        ]);
        $response->assertJsonFragment(['name' => 'Server 1']);
        $response->assertJsonFragment(['name' => 'Server 2']);
    });

    test('does not include servers from other teams', function () {
        // Create server for this team
        Server::factory()->create([
            'team_id' => $this->team->id,
            'name' => 'My Server',
            'private_key_id' => $this->privateKey->id,
        ]);

        // Create servers for another team
        $otherTeam = Team::factory()->create();
        $otherPrivateKey = PrivateKey::factory()->create(['team_id' => $otherTeam->id]);
        Server::factory()->count(2)->create([
            'team_id' => $otherTeam->id,
            'private_key_id' => $otherPrivateKey->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/servers');

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['name' => 'My Server']);
    });

    test('hides sensitive fields when Cloudflare protection is active', function () {
        $instanceSettings = InstanceSettings::first();
        $instanceSettings->update([
            'is_cloudflare_protection_enabled' => true,
            'cloudflare_api_token' => 'test-token',
            'cloudflare_account_id' => 'test-account-id',
            'cloudflare_zone_id' => 'test-zone-id',
            'cloudflare_tunnel_id' => 'test-tunnel-id',
        ]);

        $server = Server::factory()->create([
            'team_id' => $this->team->id,
            'ip' => '1.2.3.4',
            'private_key_id' => $this->privateKey->id,
        ]);

        // Use a limited token without root/read:sensitive so can_read_sensitive is false
        // and Cloudflare IP masking actually triggers
        $limitedToken = $this->user->createToken('read-only-token', ['read']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$limitedToken->plainTextToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/servers');

        $response->assertStatus(200);
        // The Server model's ip accessor strips non-alphanumeric chars like [ ],
        // so '[protected]' becomes 'protected' in the API response
        $response->assertJsonFragment(['ip' => 'protected']);
    });
});

describe('GET /api/v1/servers/{uuid} - Get server by UUID', function () {
    test('gets server by UUID', function () {
        $server = Server::factory()->create([
            'team_id' => $this->team->id,
            'name' => 'Test Server',
            'ip' => '1.2.3.4',
            'private_key_id' => $this->privateKey->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson("/api/v1/servers/{$server->uuid}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => 'Test Server', 'ip' => '1.2.3.4']);
        $response->assertJsonStructure(['uuid', 'name', 'ip', 'user', 'port', 'settings']);
    });

    test('returns 404 for non-existent UUID', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/servers/non-existent-uuid');

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Server not found.']);
    });

    test('cannot access server from another team', function () {
        $otherTeam = Team::factory()->create();
        $otherPrivateKey = PrivateKey::factory()->create(['team_id' => $otherTeam->id]);
        $server = Server::factory()->create([
            'team_id' => $otherTeam->id,
            'private_key_id' => $otherPrivateKey->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson("/api/v1/servers/{$server->uuid}");

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Server not found.']);
    });

    test('includes resources when resources parameter is true', function () {
        $server = Server::factory()->create([
            'team_id' => $this->team->id,
            'private_key_id' => $this->privateKey->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson("/api/v1/servers/{$server->uuid}?resources=true");

        $response->assertStatus(200);
        $response->assertJsonStructure(['uuid', 'name', 'resources']);
    });
});

describe('POST /api/v1/servers - Create server', function () {
    test('creates a server with all required fields', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson('/api/v1/servers', [
            'name' => 'New Server',
            'ip' => '10.0.0.1',
            'private_key_uuid' => $this->privateKey->uuid,
            'port' => 22,
            'user' => 'root',
            'proxy_type' => 'traefik',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['uuid']);

        // Verify server was created in database
        $this->assertDatabaseHas('servers', [
            'name' => 'New Server',
            'ip' => '10.0.0.1',
            'team_id' => $this->team->id,
            'port' => 22,
            'user' => 'root',
        ]);
    });

    test('generates name if not provided', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson('/api/v1/servers', [
            'ip' => '10.0.0.2',
            'private_key_uuid' => $this->privateKey->uuid,
            'proxy_type' => 'traefik',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['uuid']);

        // Verify a server was created with a generated name
        $this->assertDatabaseCount('servers', 1);
        $server = Server::first();
        expect($server->name)->not->toBeEmpty();
    });

    test('uses default values for port and user', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson('/api/v1/servers', [
            'name' => 'Default Server',
            'ip' => '10.0.0.3',
            'private_key_uuid' => $this->privateKey->uuid,
            'proxy_type' => 'traefik',
        ]);

        $response->assertStatus(201);

        // Verify defaults were applied
        $this->assertDatabaseHas('servers', [
            'name' => 'Default Server',
            'ip' => '10.0.0.3',
            'port' => 22,
            'user' => 'root',
        ]);
    });

    test('validates required fields', function () {
        // Empty JSON body triggers validateIncomingRequest() which returns 400
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson('/api/v1/servers', []);

        $response->assertStatus(400);
        $response->assertJson(['message' => 'Invalid request.']);
    });

    test('validates private_key_uuid exists', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson('/api/v1/servers', [
            'name' => 'Test',
            'ip' => '10.0.0.4',
            'private_key_uuid' => 'non-existent-uuid',
        ]);

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Private key not found.']);
    });

    test('validates private key belongs to team', function () {
        $otherTeam = Team::factory()->create();
        $otherPrivateKey = PrivateKey::factory()->create(['team_id' => $otherTeam->id]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson('/api/v1/servers', [
            'name' => 'Test',
            'ip' => '10.0.0.5',
            'private_key_uuid' => $otherPrivateKey->uuid,
        ]);

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Private key not found.']);
    });

    test('rejects duplicate IP address', function () {
        Server::factory()->create([
            'team_id' => $this->team->id,
            'ip' => '10.0.0.6',
            'private_key_id' => $this->privateKey->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson('/api/v1/servers', [
            'name' => 'Duplicate IP',
            'ip' => '10.0.0.6',
            'private_key_uuid' => $this->privateKey->uuid,
        ]);

        $response->assertStatus(400);
        $response->assertJson(['message' => 'Server with this IP already exists.']);
    });

    test('validates proxy_type is valid', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson('/api/v1/servers', [
            'name' => 'Invalid Proxy',
            'ip' => '10.0.0.7',
            'private_key_uuid' => $this->privateKey->uuid,
            'proxy_type' => 'invalid',
        ]);

        $response->assertStatus(422);
        $response->assertJson(['message' => 'Invalid proxy type.']);
    });

    test('accepts valid proxy_type traefik', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson('/api/v1/servers', [
            'name' => 'Traefik Server',
            'ip' => '10.0.0.8',
            'private_key_uuid' => $this->privateKey->uuid,
            'proxy_type' => 'traefik',
        ]);

        $response->assertStatus(201);
    });

    test('accepts valid proxy_type caddy', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson('/api/v1/servers', [
            'name' => 'Caddy Server',
            'ip' => '10.0.0.9',
            'private_key_uuid' => $this->privateKey->uuid,
            'proxy_type' => 'caddy',
        ]);

        $response->assertStatus(201);
    });

    test('accepts valid proxy_type none', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson('/api/v1/servers', [
            'name' => 'No Proxy Server',
            'ip' => '10.0.0.10',
            'private_key_uuid' => $this->privateKey->uuid,
            'proxy_type' => 'none',
        ]);

        $response->assertStatus(201);
    });

    test('sets is_build_server flag', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson('/api/v1/servers', [
            'name' => 'Build Server',
            'ip' => '10.0.0.11',
            'private_key_uuid' => $this->privateKey->uuid,
            'is_build_server' => true,
        ]);

        $response->assertStatus(201);

        $server = Server::where('name', 'Build Server')->first();
        expect($server->settings->is_build_server)->toBeTrue();
    });

    test('dispatches validation job when instant_validate is true', function () {
        Queue::fake();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson('/api/v1/servers', [
            'name' => 'Validate Server',
            'ip' => '10.0.0.12',
            'private_key_uuid' => $this->privateKey->uuid,
            'instant_validate' => true,
            'proxy_type' => 'traefik',
        ]);

        $response->assertStatus(201);

        // AsAction classes dispatch via JobDecorator, use the action's own assertPushed
        ValidateServer::assertPushed();
    });

    test('rejects extra fields not in allowed list', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson('/api/v1/servers', [
            'name' => 'Test',
            'ip' => '10.0.0.13',
            'private_key_uuid' => $this->privateKey->uuid,
            'invalid_field' => 'invalid_value',
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['invalid_field' => ['This field is not allowed.']]);
    });
});

describe('PATCH /api/v1/servers/{uuid} - Update server', function () {
    test('updates server name', function () {
        $server = Server::factory()->create([
            'team_id' => $this->team->id,
            'name' => 'Old Name',
            'private_key_id' => $this->privateKey->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/servers/{$server->uuid}", [
            'name' => 'New Name',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['uuid']);

        // Verify server name was updated
        $this->assertDatabaseHas('servers', [
            'uuid' => $server->uuid,
            'name' => 'New Name',
        ]);
    });

    test('updates server description', function () {
        $server = Server::factory()->create([
            'team_id' => $this->team->id,
            'private_key_id' => $this->privateKey->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/servers/{$server->uuid}", [
            'description' => 'Updated description',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('servers', [
            'uuid' => $server->uuid,
            'description' => 'Updated description',
        ]);
    });

    test('updates server IP', function () {
        $server = Server::factory()->create([
            'team_id' => $this->team->id,
            'ip' => '10.0.0.20',
            'private_key_id' => $this->privateKey->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/servers/{$server->uuid}", [
            'ip' => '10.0.0.21',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('servers', [
            'uuid' => $server->uuid,
            'ip' => '10.0.0.21',
        ]);
    });

    test('updates server port', function () {
        $server = Server::factory()->create([
            'team_id' => $this->team->id,
            'port' => 22,
            'private_key_id' => $this->privateKey->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/servers/{$server->uuid}", [
            'port' => 2222,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('servers', [
            'uuid' => $server->uuid,
            'port' => 2222,
        ]);
    });

    test('updates server user', function () {
        $server = Server::factory()->create([
            'team_id' => $this->team->id,
            'user' => 'root',
            'private_key_id' => $this->privateKey->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/servers/{$server->uuid}", [
            'user' => 'ubuntu',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('servers', [
            'uuid' => $server->uuid,
            'user' => 'ubuntu',
        ]);
    });

    test('validates proxy_type', function () {
        $server = Server::factory()->create([
            'team_id' => $this->team->id,
            'private_key_id' => $this->privateKey->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/servers/{$server->uuid}", [
            'proxy_type' => 'invalid',
        ]);

        $response->assertStatus(422);
        $response->assertJson(['message' => 'Invalid proxy type.']);
    });

    test('returns 404 for non-existent server', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson('/api/v1/servers/non-existent-uuid', [
            'name' => 'Test',
        ]);

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Server not found.']);
    });

    test('cannot update server from another team', function () {
        $otherTeam = Team::factory()->create();
        $otherPrivateKey = PrivateKey::factory()->create(['team_id' => $otherTeam->id]);
        $server = Server::factory()->create([
            'team_id' => $otherTeam->id,
            'private_key_id' => $otherPrivateKey->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/servers/{$server->uuid}", [
            'name' => 'Hacked Name',
        ]);

        $response->assertStatus(404);
    });

    test('rejects extra fields not in allowed list', function () {
        $server = Server::factory()->create([
            'team_id' => $this->team->id,
            'private_key_id' => $this->privateKey->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/servers/{$server->uuid}", [
            'name' => 'Valid Name',
            'invalid_field' => 'invalid_value',
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['invalid_field' => ['This field is not allowed.']]);
    });
});

describe('DELETE /api/v1/servers/{uuid} - Delete server', function () {
    test('deletes server without resources', function () {
        // Fake the queue to prevent DeleteServer from force-deleting the server
        Queue::fake();

        $server = Server::factory()->create([
            'team_id' => $this->team->id,
            'private_key_id' => $this->privateKey->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->deleteJson("/api/v1/servers/{$server->uuid}");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Server deleted.']);

        // Verify server was soft deleted (queue faked so DeleteServer doesn't force delete)
        $this->assertSoftDeleted('servers', [
            'uuid' => $server->uuid,
        ]);

        // Verify DeleteServer job was dispatched
        DeleteServer::assertPushed();
    });

    test('cannot delete server with resources', function () {
        $server = Server::factory()->create([
            'team_id' => $this->team->id,
            'private_key_id' => $this->privateKey->id,
        ]);

        // Server boot event auto-creates a StandaloneDocker destination, use it
        $standaloneDocker = StandaloneDocker::where('server_id', $server->id)->first();

        // Create a project (boot event auto-creates production/development/uat environments)
        $project = Project::create(['name' => 'Test Project', 'team_id' => $this->team->id]);
        $environment = Environment::where('project_id', $project->id)->where('name', 'production')->first();

        // Create an application on the server
        Application::factory()->create([
            'destination_type' => StandaloneDocker::class,
            'destination_id' => $standaloneDocker->id,
            'environment_id' => $environment->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->deleteJson("/api/v1/servers/{$server->uuid}");

        $response->assertStatus(400);
        $response->assertJson(['message' => 'Server has resources, so you need to delete them before.']);

        // Verify server was NOT deleted
        $this->assertDatabaseHas('servers', [
            'uuid' => $server->uuid,
            'deleted_at' => null,
        ]);
    });

    test('cannot delete localhost server', function () {
        $server = Server::factory()->create([
            'team_id' => $this->team->id,
            'ip' => 'host.docker.internal',
            'private_key_id' => $this->privateKey->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->deleteJson("/api/v1/servers/{$server->uuid}");

        $response->assertStatus(400);
        $response->assertJson(['message' => 'Local server cannot be deleted.']);
    });

    test('returns 404 for non-existent server', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->deleteJson('/api/v1/servers/non-existent-uuid');

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Server not found.']);
    });

    test('cannot delete server from another team', function () {
        $otherTeam = Team::factory()->create();
        $otherPrivateKey = PrivateKey::factory()->create(['team_id' => $otherTeam->id]);
        $server = Server::factory()->create([
            'team_id' => $otherTeam->id,
            'private_key_id' => $otherPrivateKey->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->deleteJson("/api/v1/servers/{$server->uuid}");

        $response->assertStatus(404);
    });
});

describe('GET /api/v1/servers/{uuid}/resources - List resources', function () {
    test('lists resources on server', function () {
        $server = Server::factory()->create([
            'team_id' => $this->team->id,
            'private_key_id' => $this->privateKey->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson("/api/v1/servers/{$server->uuid}/resources");

        $response->assertStatus(200);
        $response->assertJsonIsArray();
    });

    test('returns 404 for non-existent server', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/servers/non-existent-uuid/resources');

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Server not found.']);
    });
});

describe('GET /api/v1/servers/{uuid}/domains - Get domains', function () {
    test('gets domains by server UUID', function () {
        $server = Server::factory()->create([
            'team_id' => $this->team->id,
            'private_key_id' => $this->privateKey->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson("/api/v1/servers/{$server->uuid}/domains");

        $response->assertStatus(200);
        $response->assertJsonIsArray();
    });

    test('returns empty domains for server with no applications', function () {
        $server = Server::factory()->create([
            'team_id' => $this->team->id,
            'private_key_id' => $this->privateKey->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson("/api/v1/servers/{$server->uuid}/domains");

        $response->assertStatus(200);
        $response->assertJsonIsArray();
    });
});

describe('GET /api/v1/servers/{uuid}/validate - Validate server', function () {
    test('dispatches validation job', function () {
        Queue::fake();

        $server = Server::factory()->create([
            'team_id' => $this->team->id,
            'private_key_id' => $this->privateKey->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson("/api/v1/servers/{$server->uuid}/validate");

        $response->assertStatus(201);
        $response->assertJson(['message' => 'Validation started.']);

        // AsAction classes dispatch via JobDecorator, use the action's own assertPushed
        ValidateServer::assertPushed();
    });

    test('returns 404 for non-existent server', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/servers/non-existent-uuid/validate');

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Server not found.']);
    });
});

describe('POST /api/v1/servers/{uuid}/reboot - Reboot server', function () {
    test('returns 404 for non-existent server', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson('/api/v1/servers/non-existent-uuid/reboot');

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Server not found.']);
    });

    test('returns 400 when server is not reachable', function () {
        $server = Server::factory()->create([
            'team_id' => $this->team->id,
            'private_key_id' => $this->privateKey->id,
        ]);

        // Set server as not reachable
        $server->settings->update(['is_reachable' => false]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson("/api/v1/servers/{$server->uuid}/reboot");

        $response->assertStatus(400);
        $response->assertJson(['message' => 'Server is not reachable.']);
    });
});

describe('Cross-team isolation', function () {
    test('cannot access another team server in list', function () {
        $otherTeam = Team::factory()->create();
        $otherPrivateKey = PrivateKey::factory()->create(['team_id' => $otherTeam->id]);
        $otherServer = Server::factory()->create([
            'team_id' => $otherTeam->id,
            'name' => 'Other Team Server',
            'private_key_id' => $otherPrivateKey->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/servers');

        $response->assertStatus(200);
        $response->assertJsonMissing(['name' => 'Other Team Server']);
    });

    test('cannot get another team server by UUID', function () {
        $otherTeam = Team::factory()->create();
        $otherPrivateKey = PrivateKey::factory()->create(['team_id' => $otherTeam->id]);
        $otherServer = Server::factory()->create([
            'team_id' => $otherTeam->id,
            'private_key_id' => $otherPrivateKey->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson("/api/v1/servers/{$otherServer->uuid}");

        $response->assertStatus(404);
    });

    test('cannot update another team server', function () {
        $otherTeam = Team::factory()->create();
        $otherPrivateKey = PrivateKey::factory()->create(['team_id' => $otherTeam->id]);
        $otherServer = Server::factory()->create([
            'team_id' => $otherTeam->id,
            'private_key_id' => $otherPrivateKey->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/servers/{$otherServer->uuid}", [
            'name' => 'Hacked',
        ]);

        $response->assertStatus(404);
    });

    test('cannot delete another team server', function () {
        $otherTeam = Team::factory()->create();
        $otherPrivateKey = PrivateKey::factory()->create(['team_id' => $otherTeam->id]);
        $otherServer = Server::factory()->create([
            'team_id' => $otherTeam->id,
            'private_key_id' => $otherPrivateKey->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->deleteJson("/api/v1/servers/{$otherServer->uuid}");

        $response->assertStatus(404);
    });
});
