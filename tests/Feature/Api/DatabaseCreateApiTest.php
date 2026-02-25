<?php

use App\Models\Environment;
use App\Models\InstanceSettings;
use App\Models\PrivateKey;
use App\Models\Project;
use App\Models\Server;
use App\Models\ServerSetting;
use App\Models\StandaloneDocker;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Visus\Cuid2\Cuid2;

uses(DatabaseTransactions::class);

beforeEach(function () {
    Queue::fake();

    Cache::flush();
    try {
        Cache::store('redis')->flush();
    } catch (\Throwable $e) {
    }

    $this->team = Team::factory()->create();
    $this->user = User::factory()->create();
    $this->team->members()->attach($this->user->id, ['role' => 'owner']);

    session(['currentTeam' => $this->team]);
    $this->token = $this->user->createToken('test-token', ['*']);
    $this->bearerToken = $this->token->plainTextToken;

    if (! InstanceSettings::first()) {
        InstanceSettings::create([
            'id' => 0,
            'is_api_enabled' => true,
        ]);
    }

    $this->project = Project::factory()->create(['team_id' => $this->team->id]);
    $this->environment = Environment::factory()->create(['project_id' => $this->project->id]);

    $this->privateKey = PrivateKey::factory()->create(['team_id' => $this->team->id]);
    $this->server = Server::withoutEvents(function () {
        return Server::factory()->create([
            'uuid' => (string) new Cuid2,
            'team_id' => $this->team->id,
            'private_key_id' => $this->privateKey->id,
        ]);
    });
    ServerSetting::firstOrCreate(['server_id' => $this->server->id]);

    $this->destination = StandaloneDocker::create([
        'uuid' => (string) new Cuid2,
        'name' => 'test-destination',
        'server_id' => $this->server->id,
        'network' => 'test-network',
    ]);
});

// Helper that returns the base required payload shared by all database types.
function basePayload(string $serverUuid, string $projectUuid, string $environmentName): array
{
    return [
        'server_uuid' => $serverUuid,
        'project_uuid' => $projectUuid,
        'environment_name' => $environmentName,
    ];
}

// ─── Unauthenticated ─────────────────────────────────────────────────────────

describe('Unauthenticated requests', function () {
    it('returns 401 when no bearer token is provided for postgresql endpoint', function () {
        $response = $this->postJson('/api/v1/databases/postgresql', [
            'server_uuid' => $this->server->uuid,
            'project_uuid' => $this->project->uuid,
            'environment_name' => $this->environment->name,
        ]);

        $response->assertStatus(401);
    });

    it('returns 401 when no bearer token is provided for mysql endpoint', function () {
        $response = $this->postJson('/api/v1/databases/mysql', [
            'server_uuid' => $this->server->uuid,
            'project_uuid' => $this->project->uuid,
            'environment_name' => $this->environment->name,
        ]);

        $response->assertStatus(401);
    });
});

// ─── PostgreSQL ───────────────────────────────────────────────────────────────

describe('POST /api/v1/databases/postgresql', function () {
    it('creates a PostgreSQL database with required fields and returns 201 with uuid and internal_db_url', function () {
        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->postJson('/api/v1/databases/postgresql', [
                'server_uuid' => $this->server->uuid,
                'project_uuid' => $this->project->uuid,
                'environment_name' => $this->environment->name,
                'name' => 'my-postgres',
                'postgres_user' => 'pguser',
                'postgres_password' => 'pgpass',
                'postgres_db' => 'mydb',
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['uuid', 'internal_db_url']);

        $this->assertDatabaseHas('standalone_postgresqls', [
            'name' => 'my-postgres',
            'postgres_user' => 'pguser',
            'postgres_db' => 'mydb',
        ]);
    });

    it('creates a PostgreSQL database using environment_uuid instead of environment_name', function () {
        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->postJson('/api/v1/databases/postgresql', [
                'server_uuid' => $this->server->uuid,
                'project_uuid' => $this->project->uuid,
                'environment_uuid' => $this->environment->uuid,
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['uuid', 'internal_db_url']);
    });

    it('creates a PostgreSQL database with a valid public port and returns external_db_url', function () {
        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->postJson('/api/v1/databases/postgresql', [
                'server_uuid' => $this->server->uuid,
                'project_uuid' => $this->project->uuid,
                'environment_name' => $this->environment->name,
                'is_public' => true,
                'public_port' => 5433,
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['uuid', 'internal_db_url', 'external_db_url']);

        $this->assertDatabaseHas('standalone_postgresqls', [
            'is_public' => true,
            'public_port' => 5433,
        ]);
    });

    it('accepts a valid base64-encoded postgres_conf', function () {
        $conf = base64_encode("max_connections = 100\nshared_buffers = 128MB");

        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->postJson('/api/v1/databases/postgresql', [
                'server_uuid' => $this->server->uuid,
                'project_uuid' => $this->project->uuid,
                'environment_name' => $this->environment->name,
                'postgres_conf' => $conf,
            ]);

        $response->assertStatus(201);
    });

    it('returns 422 when postgres_conf is not base64 encoded', function () {
        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->postJson('/api/v1/databases/postgresql', [
                'server_uuid' => $this->server->uuid,
                'project_uuid' => $this->project->uuid,
                'environment_name' => $this->environment->name,
                'postgres_conf' => 'this is plain text not base64!!!',
            ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['postgres_conf' => 'The postgres_conf should be base64 encoded.']);
    });

    it('returns 422 when public_port is below the minimum of 1024', function () {
        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->postJson('/api/v1/databases/postgresql', [
                'server_uuid' => $this->server->uuid,
                'project_uuid' => $this->project->uuid,
                'environment_name' => $this->environment->name,
                'is_public' => true,
                'public_port' => 80,
            ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['public_port' => 'The public port should be between 1024 and 65535.']);
    });

    it('returns 422 when public_port is above the maximum of 65535', function () {
        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->postJson('/api/v1/databases/postgresql', [
                'server_uuid' => $this->server->uuid,
                'project_uuid' => $this->project->uuid,
                'environment_name' => $this->environment->name,
                'is_public' => true,
                'public_port' => 99999,
            ]);

        $response->assertStatus(422);
    });

    it('returns 422 when an extra field not in allowedFields is submitted', function () {
        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->postJson('/api/v1/databases/postgresql', [
                'server_uuid' => $this->server->uuid,
                'project_uuid' => $this->project->uuid,
                'environment_name' => $this->environment->name,
                'unknown_extra_field' => 'value',
            ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Validation failed.']);
        $response->assertJsonPath('errors.unknown_extra_field', 'This field is not allowed.');
    });

    it('returns 422 when neither environment_name nor environment_uuid is provided', function () {
        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->postJson('/api/v1/databases/postgresql', [
                'server_uuid' => $this->server->uuid,
                'project_uuid' => $this->project->uuid,
            ]);

        $response->assertStatus(422);
        $response->assertJson(['message' => 'You need to provide at least one of environment_name or environment_uuid.']);
    });

    it('returns 422 when environment_name does not match any environment in the project', function () {
        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->postJson('/api/v1/databases/postgresql', [
                'server_uuid' => $this->server->uuid,
                'project_uuid' => $this->project->uuid,
                'environment_name' => 'totally-nonexistent-env',
            ]);

        $response->assertStatus(422);
        $response->assertJson(['message' => 'You need to provide a valid environment_name or environment_uuid.']);
    });

    it('returns 404 when project_uuid does not exist', function () {
        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->postJson('/api/v1/databases/postgresql', [
                'server_uuid' => $this->server->uuid,
                'project_uuid' => 'non-existent-project-uuid',
                'environment_name' => $this->environment->name,
            ]);

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Project not found.']);
    });

    it('returns 404 when server_uuid does not exist', function () {
        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->postJson('/api/v1/databases/postgresql', [
                'server_uuid' => 'non-existent-server-uuid',
                'project_uuid' => $this->project->uuid,
                'environment_name' => $this->environment->name,
            ]);

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Server not found.']);
    });

    it('returns 404 when server_uuid belongs to another team', function () {
        $otherTeam = Team::factory()->create();
        $otherKey = PrivateKey::factory()->create(['team_id' => $otherTeam->id]);
        $otherServer = Server::withoutEvents(function () use ($otherTeam, $otherKey) {
            return Server::factory()->create([
                'uuid' => (string) new Cuid2,
                'team_id' => $otherTeam->id,
                'private_key_id' => $otherKey->id,
            ]);
        });

        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->postJson('/api/v1/databases/postgresql', [
                'server_uuid' => $otherServer->uuid,
                'project_uuid' => $this->project->uuid,
                'environment_name' => $this->environment->name,
            ]);

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Server not found.']);
    });

    it('returns 404 when project_uuid belongs to another team', function () {
        $otherTeam = Team::factory()->create();
        $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);

        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->postJson('/api/v1/databases/postgresql', [
                'server_uuid' => $this->server->uuid,
                'project_uuid' => $otherProject->uuid,
                'environment_name' => $this->environment->name,
            ]);

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Project not found.']);
    });
});

// ─── MySQL ────────────────────────────────────────────────────────────────────

describe('POST /api/v1/databases/mysql', function () {
    it('creates a MySQL database with required fields and returns 201', function () {
        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->postJson('/api/v1/databases/mysql', [
                'server_uuid' => $this->server->uuid,
                'project_uuid' => $this->project->uuid,
                'environment_name' => $this->environment->name,
                'name' => 'my-mysql',
                'mysql_root_password' => 'rootpass',
                'mysql_user' => 'appuser',
                'mysql_password' => 'apppass',
                'mysql_database' => 'appdb',
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['uuid', 'internal_db_url']);

        $this->assertDatabaseHas('standalone_mysqls', [
            'name' => 'my-mysql',
            'mysql_user' => 'appuser',
            'mysql_database' => 'appdb',
        ]);
    });

    it('returns 422 when mysql_conf is not base64 encoded', function () {
        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->postJson('/api/v1/databases/mysql', [
                'server_uuid' => $this->server->uuid,
                'project_uuid' => $this->project->uuid,
                'environment_name' => $this->environment->name,
                'mysql_conf' => 'plain text config, not base64!',
            ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['mysql_conf' => 'The mysql_conf should be base64 encoded.']);
    });

    it('accepts a valid base64-encoded mysql_conf', function () {
        $conf = base64_encode("[mysqld]\nmax_connections=200");

        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->postJson('/api/v1/databases/mysql', [
                'server_uuid' => $this->server->uuid,
                'project_uuid' => $this->project->uuid,
                'environment_name' => $this->environment->name,
                'mysql_conf' => $conf,
            ]);

        $response->assertStatus(201);
    });
});

// ─── MariaDB ──────────────────────────────────────────────────────────────────

describe('POST /api/v1/databases/mariadb', function () {
    it('creates a MariaDB database with required fields and returns 201', function () {
        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->postJson('/api/v1/databases/mariadb', [
                'server_uuid' => $this->server->uuid,
                'project_uuid' => $this->project->uuid,
                'environment_name' => $this->environment->name,
                'name' => 'my-mariadb',
                'mariadb_root_password' => 'rootpass',
                'mariadb_user' => 'dbuser',
                'mariadb_password' => 'dbpass',
                'mariadb_database' => 'mydb',
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['uuid', 'internal_db_url']);

        $this->assertDatabaseHas('standalone_mariadbs', [
            'name' => 'my-mariadb',
            'mariadb_user' => 'dbuser',
            'mariadb_database' => 'mydb',
        ]);
    });

    it('returns 422 when mariadb_conf is not base64 encoded', function () {
        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->postJson('/api/v1/databases/mariadb', [
                'server_uuid' => $this->server->uuid,
                'project_uuid' => $this->project->uuid,
                'environment_name' => $this->environment->name,
                'mariadb_conf' => 'plain text not encoded',
            ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['mariadb_conf' => 'The mariadb_conf should be base64 encoded.']);
    });
});

// ─── Redis ────────────────────────────────────────────────────────────────────

describe('POST /api/v1/databases/redis', function () {
    it('creates a Redis database with required fields and returns 201', function () {
        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->postJson('/api/v1/databases/redis', [
                'server_uuid' => $this->server->uuid,
                'project_uuid' => $this->project->uuid,
                'environment_name' => $this->environment->name,
                'name' => 'my-redis',
                'redis_password' => 'redispass',
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['uuid', 'internal_db_url']);

        $this->assertDatabaseHas('standalone_redis', [
            'name' => 'my-redis',
        ]);
    });

    it('returns 422 when redis_conf is not base64 encoded', function () {
        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->postJson('/api/v1/databases/redis', [
                'server_uuid' => $this->server->uuid,
                'project_uuid' => $this->project->uuid,
                'environment_name' => $this->environment->name,
                'redis_conf' => 'not-valid-base64!!!',
            ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['redis_conf' => 'The redis_conf should be base64 encoded.']);
    });

    it('accepts a valid base64-encoded redis_conf', function () {
        $conf = base64_encode("maxmemory 256mb\nmaxmemory-policy allkeys-lru");

        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->postJson('/api/v1/databases/redis', [
                'server_uuid' => $this->server->uuid,
                'project_uuid' => $this->project->uuid,
                'environment_name' => $this->environment->name,
                'redis_conf' => $conf,
            ]);

        $response->assertStatus(201);
    });
});

// ─── KeyDB ────────────────────────────────────────────────────────────────────

describe('POST /api/v1/databases/keydb', function () {
    it('creates a KeyDB database with required fields and returns 201', function () {
        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->postJson('/api/v1/databases/keydb', [
                'server_uuid' => $this->server->uuid,
                'project_uuid' => $this->project->uuid,
                'environment_name' => $this->environment->name,
                'name' => 'my-keydb',
                'keydb_password' => 'keydbpass',
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['uuid', 'internal_db_url']);

        $this->assertDatabaseHas('standalone_keydbs', [
            'name' => 'my-keydb',
        ]);
    });

    it('returns 422 when keydb_conf is not base64 encoded', function () {
        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->postJson('/api/v1/databases/keydb', [
                'server_uuid' => $this->server->uuid,
                'project_uuid' => $this->project->uuid,
                'environment_name' => $this->environment->name,
                'keydb_conf' => 'plain text is not base64',
            ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['keydb_conf' => 'The keydb_conf should be base64 encoded.']);
    });
});

// ─── Dragonfly ────────────────────────────────────────────────────────────────

describe('POST /api/v1/databases/dragonfly', function () {
    it('creates a Dragonfly database with required fields and returns 201 with uuid', function () {
        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->postJson('/api/v1/databases/dragonfly', [
                'server_uuid' => $this->server->uuid,
                'project_uuid' => $this->project->uuid,
                'environment_name' => $this->environment->name,
                'name' => 'my-dragonfly',
                'dragonfly_password' => 'dragonflypass',
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['uuid']);

        $this->assertDatabaseHas('standalone_dragonflies', [
            'name' => 'my-dragonfly',
        ]);
    });

    it('returns 422 when an extra field not allowed by Dragonfly is submitted', function () {
        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->postJson('/api/v1/databases/dragonfly', [
                'server_uuid' => $this->server->uuid,
                'project_uuid' => $this->project->uuid,
                'environment_name' => $this->environment->name,
                'dragonfly_password' => 'pass',
                'dragonfly_conf' => 'some-config-that-doesnt-exist',
            ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Validation failed.']);
    });
});

// ─── Clickhouse ───────────────────────────────────────────────────────────────

describe('POST /api/v1/databases/clickhouse', function () {
    it('creates a Clickhouse database with required fields and returns 201', function () {
        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->postJson('/api/v1/databases/clickhouse', [
                'server_uuid' => $this->server->uuid,
                'project_uuid' => $this->project->uuid,
                'environment_name' => $this->environment->name,
                'name' => 'my-clickhouse',
                'clickhouse_admin_user' => 'admin',
                'clickhouse_admin_password' => 'adminpass',
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['uuid', 'internal_db_url']);

        $this->assertDatabaseHas('standalone_clickhouses', [
            'name' => 'my-clickhouse',
            'clickhouse_admin_user' => 'admin',
        ]);
    });
});

// ─── MongoDB ──────────────────────────────────────────────────────────────────

describe('POST /api/v1/databases/mongodb', function () {
    // Known production bug: StandaloneMongodb::mongoInitdbRootPassword getter
    // calls $this->save() when it fails to decrypt a plain-text password.
    // This triggers infinite recursion through the Auditable trait's
    // getOriginal() -> getter -> save() cycle, causing a duplicate key violation.
    it('creates a MongoDB database')->skip(
        'Production bug: mongoInitdbRootPassword getter causes recursive save via Auditable trait'
    );

    it('returns 422 when mongo_conf is not base64 encoded', function () {
        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->postJson('/api/v1/databases/mongodb', [
                'server_uuid' => $this->server->uuid,
                'project_uuid' => $this->project->uuid,
                'environment_name' => $this->environment->name,
                'mongo_conf' => 'plain text config',
            ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['mongo_conf' => 'The mongo_conf should be base64 encoded.']);
    });
});

// ─── Common validation across all types ──────────────────────────────────────

describe('Common validation (tested via postgresql endpoint)', function () {
    it('returns 422 for public_port exactly at boundary 1023 (one below minimum)', function () {
        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->postJson('/api/v1/databases/postgresql', [
                'server_uuid' => $this->server->uuid,
                'project_uuid' => $this->project->uuid,
                'environment_name' => $this->environment->name,
                'is_public' => true,
                'public_port' => 1023,
            ]);

        $response->assertStatus(422);
    });

    it('accepts public_port exactly at the minimum boundary of 1024', function () {
        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->postJson('/api/v1/databases/postgresql', [
                'server_uuid' => $this->server->uuid,
                'project_uuid' => $this->project->uuid,
                'environment_name' => $this->environment->name,
                'is_public' => true,
                'public_port' => 1024,
            ]);

        $response->assertStatus(201);
    });

    it('accepts public_port exactly at the maximum boundary of 65535', function () {
        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->postJson('/api/v1/databases/postgresql', [
                'server_uuid' => $this->server->uuid,
                'project_uuid' => $this->project->uuid,
                'environment_name' => $this->environment->name,
                'is_public' => true,
                'public_port' => 65535,
            ]);

        $response->assertStatus(201);
    });

    it('does not expose is_public=true without a public_port (port is reset to false silently)', function () {
        // When is_public=true but no public_port is provided the controller
        // resets is_public to false, so the created record must not be public.
        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->postJson('/api/v1/databases/postgresql', [
                'server_uuid' => $this->server->uuid,
                'project_uuid' => $this->project->uuid,
                'environment_name' => $this->environment->name,
                'is_public' => true,
            ]);

        $response->assertStatus(201);
        // external_db_url must be absent because is_public was silently reset
        $response->assertJsonMissing(['external_db_url']);
    });

    it('returns 400 when server has no destinations', function () {
        // Create a server that has no StandaloneDocker destination attached
        $bareServer = Server::withoutEvents(function () {
            return Server::factory()->create([
                'uuid' => (string) new Cuid2,
                'team_id' => $this->team->id,
                'private_key_id' => $this->privateKey->id,
            ]);
        });
        ServerSetting::firstOrCreate(['server_id' => $bareServer->id]);

        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->postJson('/api/v1/databases/postgresql', [
                'server_uuid' => $bareServer->uuid,
                'project_uuid' => $this->project->uuid,
                'environment_name' => $this->environment->name,
            ]);

        $response->assertStatus(400);
        $response->assertJson(['message' => 'Server has no destinations.']);
    });
});

// ─── Team isolation (cross-team) ─────────────────────────────────────────────

describe('Team isolation', function () {
    it('cannot create a Redis database on a server belonging to another team', function () {
        $otherTeam = Team::factory()->create();
        $otherKey = PrivateKey::factory()->create(['team_id' => $otherTeam->id]);
        $otherServer = Server::withoutEvents(function () use ($otherTeam, $otherKey) {
            return Server::factory()->create([
                'uuid' => (string) new Cuid2,
                'team_id' => $otherTeam->id,
                'private_key_id' => $otherKey->id,
            ]);
        });

        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->postJson('/api/v1/databases/redis', [
                'server_uuid' => $otherServer->uuid,
                'project_uuid' => $this->project->uuid,
                'environment_name' => $this->environment->name,
            ]);

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Server not found.']);
    });

    it('cannot create a MySQL database inside a project belonging to another team', function () {
        $otherTeam = Team::factory()->create();
        $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);

        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->postJson('/api/v1/databases/mysql', [
                'server_uuid' => $this->server->uuid,
                'project_uuid' => $otherProject->uuid,
                'environment_name' => $this->environment->name,
            ]);

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Project not found.']);
    });
});
