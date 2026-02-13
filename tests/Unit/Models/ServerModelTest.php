<?php

use App\Models\Server;

// type() Tests
test('type returns server string', function () {
    $server = new Server;

    expect($server->type())->toBe('server');
});

// isSwarm() Tests
test('isSwarm returns true when is_swarm_manager is true', function () {
    $server = new Server;
    $server->setRelation('settings', (object) ['is_swarm_manager' => true, 'is_swarm_worker' => false]);

    expect($server->isSwarm())->toBeTrue();
});

test('isSwarm returns true when is_swarm_worker is true', function () {
    $server = new Server;
    $server->setRelation('settings', (object) ['is_swarm_manager' => false, 'is_swarm_worker' => true]);

    expect($server->isSwarm())->toBeTrue();
});

test('isSwarm returns true when both is_swarm_manager and is_swarm_worker are true', function () {
    $server = new Server;
    $server->setRelation('settings', (object) ['is_swarm_manager' => true, 'is_swarm_worker' => true]);

    expect($server->isSwarm())->toBeTrue();
});

test('isSwarm returns false when neither is_swarm_manager nor is_swarm_worker are true', function () {
    $server = new Server;
    $server->setRelation('settings', (object) ['is_swarm_manager' => false, 'is_swarm_worker' => false]);

    expect($server->isSwarm())->toBeFalse();
});

// isSwarmManager() Tests
test('isSwarmManager returns true when is_swarm_manager is true', function () {
    $server = new Server;
    $server->setRelation('settings', (object) ['is_swarm_manager' => true]);

    expect($server->isSwarmManager())->toBeTrue();
});

test('isSwarmManager returns false when is_swarm_manager is false', function () {
    $server = new Server;
    $server->setRelation('settings', (object) ['is_swarm_manager' => false]);

    expect($server->isSwarmManager())->toBeFalse();
});

// isSwarmWorker() Tests
test('isSwarmWorker returns true when is_swarm_worker is true', function () {
    $server = new Server;
    $server->setRelation('settings', (object) ['is_swarm_worker' => true]);

    expect($server->isSwarmWorker())->toBeTrue();
});

test('isSwarmWorker returns false when is_swarm_worker is false', function () {
    $server = new Server;
    $server->setRelation('settings', (object) ['is_swarm_worker' => false]);

    expect($server->isSwarmWorker())->toBeFalse();
});

// isNonRoot() Tests
test('isNonRoot returns true when user is not root', function () {
    $server = new Server;
    $server->user = 'ubuntu';

    expect($server->isNonRoot())->toBeTrue();
});

test('isNonRoot returns false when user is root', function () {
    $server = new Server;
    $server->user = 'root';

    expect($server->isNonRoot())->toBeFalse();
});

test('isNonRoot returns true when user is Stringable and not root', function () {
    $server = new Server;
    $server->user = str('ubuntu');

    expect($server->isNonRoot())->toBeTrue();
});

test('isNonRoot returns false when user is Stringable root', function () {
    $server = new Server;
    $server->user = str('root');

    expect($server->isNonRoot())->toBeFalse();
});

// isBuildServer() Tests
test('isBuildServer returns true when is_build_server is true', function () {
    $server = new Server;
    $server->setRelation('settings', (object) ['is_build_server' => true]);

    expect($server->isBuildServer())->toBeTrue();
});

test('isBuildServer returns false when is_build_server is false', function () {
    $server = new Server;
    $server->setRelation('settings', (object) ['is_build_server' => false]);

    expect($server->isBuildServer())->toBeFalse();
});

// isMasterServer() Tests
test('isMasterServer returns true when is_master_server is true', function () {
    $server = new Server;
    $server->setRelation('settings', (object) ['is_master_server' => true]);

    expect($server->isMasterServer())->toBeTrue();
});

test('isMasterServer returns false when is_master_server is false', function () {
    $server = new Server;
    $server->setRelation('settings', (object) ['is_master_server' => false]);

    expect($server->isMasterServer())->toBeFalse();
});

// isIpv6() Tests
test('isIpv6 returns true for IPv6 address with colons', function () {
    $server = new Server;
    $server->ip = '2001:0db8:85a3:0000:0000:8a2e:0370:7334';

    expect($server->isIpv6())->toBeTrue();
});

test('isIpv6 returns true for shortened IPv6 address', function () {
    $server = new Server;
    $server->ip = '2001:db8::1';

    expect($server->isIpv6())->toBeTrue();
});

test('isIpv6 returns false for IPv4 address', function () {
    $server = new Server;
    $server->ip = '192.168.1.100';

    expect($server->isIpv6())->toBeFalse();
});

test('isIpv6 returns false for localhost', function () {
    $server = new Server;
    $server->ip = '127.0.0.1';

    expect($server->isIpv6())->toBeFalse();
});

// proxyType() Tests
test('proxyType returns traefik when proxy.type is traefik', function () {
    $server = new Server;
    $server->proxy = (object) ['type' => 'traefik'];

    expect($server->proxyType())->toBe('traefik');
});

test('proxyType returns caddy when proxy.type is caddy', function () {
    $server = new Server;
    $server->proxy = (object) ['type' => 'caddy'];

    expect($server->proxyType())->toBe('caddy');
});

test('proxyType returns none when proxy.type is none', function () {
    $server = new Server;
    $server->proxy = (object) ['type' => 'none'];

    expect($server->proxyType())->toBe('none');
});

test('proxyType returns null when proxy.type is not set', function () {
    $server = new Server;
    $server->proxy = (object) [];

    expect($server->proxyType())->toBeNull();
});

// isForceDisabled() Tests
test('isForceDisabled returns true when force_disabled is true', function () {
    $server = new Server;
    $server->setRelation('settings', (object) ['force_disabled' => true]);

    expect($server->isForceDisabled())->toBeTrue();
});

test('isForceDisabled returns false when force_disabled is false', function () {
    $server = new Server;
    $server->setRelation('settings', (object) ['force_disabled' => false]);

    expect($server->isForceDisabled())->toBeFalse();
});

// port() Attribute Tests
test('port attribute converts string to int', function () {
    $server = new Server;
    $server->setRawAttributes(['port' => '22']);
    expect($server->port)->toBe(22);

    $server->setRawAttributes(['port' => '8080']);
    expect($server->port)->toBe(8080);

    $server->setRawAttributes(['port' => '443']);
    expect($server->port)->toBe(443);
});

test('port attribute strips non-numeric characters', function () {
    $server = new Server;
    $server->setRawAttributes(['port' => '22abc']);
    expect($server->port)->toBe(22);

    $server->setRawAttributes(['port' => 'port22']);
    expect($server->port)->toBe(22);
});

// isLocalhost Attribute Tests
test('isLocalhost returns true for host.docker.internal', function () {
    $server = new Server;
    $server->ip = 'host.docker.internal';
    $server->id = 5;

    expect($server->checkIsLocalhost())->toBeTrue();
});

test('isLocalhost returns true for id 0', function () {
    $server = new Server;
    $server->ip = '1.2.3.4';
    $server->id = 0;

    expect($server->checkIsLocalhost())->toBeTrue();
});

test('isLocalhost returns false for regular IP', function () {
    $server = new Server;
    $server->ip = '192.168.1.100';
    $server->id = 5;

    expect($server->checkIsLocalhost())->toBeFalse();
});

// isSaturnHost Attribute Tests
test('isSaturnHost attribute returns true for id 0', function () {
    $server = new Server;
    $server->id = 0;

    expect($server->is_saturn_host)->toBeTrue();
});

test('isSaturnHost attribute returns false for non-zero id', function () {
    $server = new Server;
    $server->id = 5;

    expect($server->is_saturn_host)->toBeFalse();
});

// Fillable Security Tests
test('model uses fillable array for mass assignment protection', function () {
    $server = new Server;

    expect($server->getFillable())->not->toBeEmpty();
});

test('fillable does not include id or uuid', function () {
    $fillable = (new Server)->getFillable();

    expect($fillable)
        ->not->toContain('id')
        ->not->toContain('uuid');
});

test('fillable includes expected fields', function () {
    $fillable = (new Server)->getFillable();

    expect($fillable)
        ->toContain('name')
        ->toContain('ip')
        ->toContain('port')
        ->toContain('user')
        ->toContain('description')
        ->toContain('private_key_id')
        ->toContain('team_id');
});

// Hidden Attributes Tests
test('hidden includes sensitive API keys', function () {
    $server = new Server;
    $hidden = $server->getHidden();

    expect($hidden)
        ->toContain('logdrain_axiom_api_key')
        ->toContain('logdrain_newrelic_license_key');
});

// Casts Tests
test('delete_unused_volumes is cast to boolean', function () {
    $casts = (new Server)->getCasts();

    expect($casts['delete_unused_volumes'])->toBe('boolean');
});

test('delete_unused_networks is cast to boolean', function () {
    $casts = (new Server)->getCasts();

    expect($casts['delete_unused_networks'])->toBe('boolean');
});

test('logdrain_axiom_api_key is cast to encrypted', function () {
    $casts = (new Server)->getCasts();

    expect($casts['logdrain_axiom_api_key'])->toBe('encrypted');
});

test('logdrain_newrelic_license_key is cast to encrypted', function () {
    $casts = (new Server)->getCasts();

    expect($casts['logdrain_newrelic_license_key'])->toBe('encrypted');
});

test('traefik_outdated_info is cast to array', function () {
    $casts = (new Server)->getCasts();

    expect($casts['traefik_outdated_info'])->toBe('array');
});

// Relationship Type Tests
test('settings relationship returns hasOne', function () {
    $relation = (new Server)->settings();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasOne::class);
});

test('dockerCleanupExecutions relationship returns hasMany', function () {
    $relation = (new Server)->dockerCleanupExecutions();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});

test('healthChecks relationship returns hasMany', function () {
    $relation = (new Server)->healthChecks();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});

// Appends Tests
test('appends includes expected attributes', function () {
    $server = new Server;
    $appends = (new \ReflectionProperty($server, 'appends'))->getValue($server);

    expect($appends)
        ->toContain('is_saturn_host')
        ->toContain('is_localhost')
        ->toContain('is_reachable')
        ->toContain('is_usable');
});

// SoftDeletes Tests
test('server uses soft deletes', function () {
    $server = new Server;

    expect(method_exists($server, 'trashed'))->toBeTrue()
        ->and(method_exists($server, 'restore'))->toBeTrue();
});

// waitBeforeDoingSshCheck Tests
test('waitBeforeDoingSshCheck returns at least 120', function () {
    $server = new Server;
    $server->setRelation('settings', (object) ['sentinel_push_interval_seconds' => 10]);

    expect($server->waitBeforeDoingSshCheck())->toBe(120);
});

test('waitBeforeDoingSshCheck returns 3x sentinel_push_interval when above 40', function () {
    $server = new Server;
    $server->setRelation('settings', (object) ['sentinel_push_interval_seconds' => 60]);

    expect($server->waitBeforeDoingSshCheck())->toBe(180);
});

// isSentinelEnabled Tests
test('isSentinelEnabled returns true when metrics enabled and not build server', function () {
    $server = new Server;
    $server->setRelation('settings', (object) [
        'is_metrics_enabled' => true,
        'is_sentinel_enabled' => false,
        'is_build_server' => false,
    ]);

    expect($server->isSentinelEnabled())->toBeTrue();
});

test('isSentinelEnabled returns true when server api enabled and not build server', function () {
    $server = new Server;
    $server->setRelation('settings', (object) [
        'is_metrics_enabled' => false,
        'is_sentinel_enabled' => true,
        'is_build_server' => false,
    ]);

    expect($server->isSentinelEnabled())->toBeTrue();
});

test('isSentinelEnabled returns false when build server', function () {
    $server = new Server;
    $server->setRelation('settings', (object) [
        'is_metrics_enabled' => true,
        'is_sentinel_enabled' => true,
        'is_build_server' => true,
    ]);

    expect($server->isSentinelEnabled())->toBeFalse();
});

test('isSentinelEnabled returns false when neither metrics nor api enabled', function () {
    $server = new Server;
    $server->setRelation('settings', (object) [
        'is_metrics_enabled' => false,
        'is_sentinel_enabled' => false,
        'is_build_server' => false,
    ]);

    expect($server->isSentinelEnabled())->toBeFalse();
});

// isMetricsEnabled Tests
test('isMetricsEnabled returns setting value', function () {
    $server = new Server;
    $server->setRelation('settings', (object) ['is_metrics_enabled' => true]);

    expect($server->isMetricsEnabled())->toBeTrue();
});

// isServerApiEnabled Tests
test('isServerApiEnabled returns setting value', function () {
    $server = new Server;
    $server->setRelation('settings', (object) ['is_sentinel_enabled' => true]);

    expect($server->isServerApiEnabled())->toBeTrue();
});

// proxyPath Tests
test('proxyPath returns traefik path for traefik proxy', function () {
    $server = new Server;
    $server->proxy = (object) ['type' => 'TRAEFIK'];

    $path = $server->proxyPath();

    expect($path)->toEndWith('/proxy/');
});

test('proxyPath returns caddy path for caddy proxy', function () {
    $server = new Server;
    $server->proxy = (object) ['type' => 'CADDY'];

    $path = $server->proxyPath();

    expect($path)->toEndWith('/proxy/caddy');
});

// Attribute Tests
test('server has name attribute', function () {
    $server = new Server;
    $server->name = 'production-01';

    expect($server->name)->toBe('production-01');
});

test('server has ip attribute', function () {
    $server = new Server;
    $server->ip = '192.168.1.100';

    expect($server->ip)->toBe('192.168.1.100');
});

test('server has user attribute', function () {
    $server = new Server;
    $server->user = 'root';

    expect($server->user)->toBe('root');
});

test('server has description attribute', function () {
    $server = new Server;
    $server->description = 'Main production server';

    expect($server->description)->toBe('Main production server');
});

// Input Validation Tests (validateAndSanitizeConnection)
test('validateAndSanitizeConnection rejects user with shell injection', function () {
    $server = new Server;
    $server->user = 'root; rm -rf /';
    $server->ip = '1.2.3.4';
    $server->validateAndSanitizeConnection();
})->throws(\InvalidArgumentException::class, 'Server user contains invalid characters');

test('validateAndSanitizeConnection rejects user with spaces', function () {
    $server = new Server;
    $server->user = 'my user';
    $server->ip = '1.2.3.4';
    $server->validateAndSanitizeConnection();
})->throws(\InvalidArgumentException::class, 'Server user contains invalid characters');

test('validateAndSanitizeConnection accepts valid unix usernames', function () {
    $validUsers = ['root', 'ubuntu', 'deploy-user', 'user_123', 'ec2-user'];
    foreach ($validUsers as $user) {
        $server = new Server;
        $server->user = $user;
        $server->ip = '1.2.3.4';
        $server->validateAndSanitizeConnection();
        expect($server->user)->toBe($user);
    }
});

test('validateAndSanitizeConnection rejects IP with shell injection', function () {
    $server = new Server;
    $server->user = 'root';
    $server->ip = '1.2.3.4; cat /etc/passwd';
    $server->validateAndSanitizeConnection();
})->throws(\InvalidArgumentException::class, 'Server IP must be a valid IP address or hostname');

test('validateAndSanitizeConnection rejects IP with backticks', function () {
    $server = new Server;
    $server->user = 'root';
    $server->ip = '`whoami`.example.com';
    $server->validateAndSanitizeConnection();
})->throws(\InvalidArgumentException::class, 'Server IP must be a valid IP address or hostname');

test('validateAndSanitizeConnection accepts valid IPv4 addresses', function () {
    $validIps = ['192.168.1.1', '10.0.0.1', '127.0.0.1', '255.255.255.255'];
    foreach ($validIps as $ip) {
        $server = new Server;
        $server->user = 'root';
        $server->ip = $ip;
        $server->validateAndSanitizeConnection();
        expect($server->ip)->toBe($ip);
    }
});

test('validateAndSanitizeConnection accepts valid IPv6 addresses', function () {
    $validIps = ['2001:0db8:85a3:0000:0000:8a2e:0370:7334', '::1', '2001:db8::1'];
    foreach ($validIps as $ip) {
        $server = new Server;
        $server->user = 'root';
        $server->ip = $ip;
        $server->validateAndSanitizeConnection();
        expect($server->ip)->toBe($ip);
    }
});

test('validateAndSanitizeConnection accepts valid hostnames', function () {
    $validHostnames = ['host.docker.internal', 'my-server', 'server01.example.com', 'a'];
    foreach ($validHostnames as $hostname) {
        $server = new Server;
        $server->user = 'root';
        $server->ip = $hostname;
        $server->validateAndSanitizeConnection();
        expect($server->ip)->toBe($hostname);
    }
});

test('validateAndSanitizeConnection trims whitespace from ip and user', function () {
    $server = new Server;
    $server->user = '  root  ';
    $server->ip = '  192.168.1.1  ';
    $server->validateAndSanitizeConnection();
    expect($server->user)->toBe('root');
    expect($server->ip)->toBe('192.168.1.1');
});
