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
