<?php

use App\Actions\Environment\CloneEnvironmentAction;
use App\Models\Application;
use App\Models\Environment;
use App\Models\EnvironmentVariable;
use App\Models\LocalPersistentVolume;
use App\Models\ResourceLink;
use App\Models\ScheduledDatabaseBackup;
use App\Models\ScheduledTask;
use App\Models\Service;
use App\Models\StandalonePostgresql;
use Illuminate\Support\Facades\DB;
use Visus\Cuid2\Cuid2;

beforeEach(function () {
    $this->action = new CloneEnvironmentAction;
});

it('creates new environment with correct attributes', function () {
    $source = Mockery::mock(Environment::class)->makePartial();
    $source->id = 1;
    $source->name = 'production';
    $source->project_id = 10;
    $source->default_server_id = 5;
    $source->default_git_branch = 'main';

    // Empty collections for all resource types
    $emptyCollection = collect();
    $source->shouldReceive('getAttribute')->with('applications')->andReturn($emptyCollection);
    $source->shouldReceive('getAttribute')->with('services')->andReturn($emptyCollection);
    $source->shouldReceive('getAttribute')->with('environment_variables')->andReturn($emptyCollection);
    foreach (['postgresqls', 'mysqls', 'mariadbs', 'mongodbs', 'redis', 'keydbs', 'dragonflies', 'clickhouses'] as $type) {
        $source->shouldReceive('getAttribute')->with($type)->andReturn($emptyCollection);
    }

    $createdEnv = null;
    DB::shouldReceive('transaction')->andReturnUsing(function ($callback) use (&$createdEnv) {
        return $callback();
    });

    $newEnv = Mockery::mock(Environment::class);
    $newEnv->id = 2;
    $newEnv->name = 'staging-clone';
    $newEnv->shouldReceive('fresh')->andReturn($newEnv);

    Environment::shouldReceive('create')
        ->once()
        ->with(Mockery::on(function ($data) {
            return $data['name'] === 'staging-clone'
                && $data['project_id'] === 10
                && $data['cloned_from_id'] === 1
                && $data['type'] === 'development'
                && $data['requires_approval'] === false;
        }))
        ->andReturn($newEnv);

    ResourceLink::shouldReceive('where->get')->andReturn(collect());

    $result = $this->action->execute($source, [
        'name' => 'staging-clone',
    ]);

    expect($result)->toBe($newEnv);
});

it('uses target_server_id when provided', function () {
    $source = Mockery::mock(Environment::class)->makePartial();
    $source->id = 1;
    $source->name = 'prod';
    $source->project_id = 10;
    $source->default_server_id = 5;
    $source->default_git_branch = 'main';

    $emptyCollection = collect();
    $source->shouldReceive('getAttribute')->with('applications')->andReturn($emptyCollection);
    $source->shouldReceive('getAttribute')->with('services')->andReturn($emptyCollection);
    $source->shouldReceive('getAttribute')->with('environment_variables')->andReturn($emptyCollection);
    foreach (['postgresqls', 'mysqls', 'mariadbs', 'mongodbs', 'redis', 'keydbs', 'dragonflies', 'clickhouses'] as $type) {
        $source->shouldReceive('getAttribute')->with($type)->andReturn($emptyCollection);
    }

    DB::shouldReceive('transaction')->andReturnUsing(fn ($cb) => $cb());

    $newEnv = Mockery::mock(Environment::class);
    $newEnv->id = 2;
    $newEnv->shouldReceive('fresh')->andReturn($newEnv);

    Environment::shouldReceive('create')
        ->once()
        ->with(Mockery::on(function ($data) {
            return $data['default_server_id'] === 99;
        }))
        ->andReturn($newEnv);

    ResourceLink::shouldReceive('where->get')->andReturn(collect());

    $this->action->execute($source, [
        'name' => 'clone',
        'target_server_id' => 99,
    ]);
});

it('generates description when not provided', function () {
    $source = Mockery::mock(Environment::class)->makePartial();
    $source->id = 1;
    $source->name = 'production';
    $source->project_id = 10;
    $source->default_server_id = 5;
    $source->default_git_branch = 'main';

    $emptyCollection = collect();
    $source->shouldReceive('getAttribute')->with('applications')->andReturn($emptyCollection);
    $source->shouldReceive('getAttribute')->with('services')->andReturn($emptyCollection);
    $source->shouldReceive('getAttribute')->with('environment_variables')->andReturn($emptyCollection);
    foreach (['postgresqls', 'mysqls', 'mariadbs', 'mongodbs', 'redis', 'keydbs', 'dragonflies', 'clickhouses'] as $type) {
        $source->shouldReceive('getAttribute')->with($type)->andReturn($emptyCollection);
    }

    DB::shouldReceive('transaction')->andReturnUsing(fn ($cb) => $cb());

    $newEnv = Mockery::mock(Environment::class);
    $newEnv->id = 2;
    $newEnv->shouldReceive('fresh')->andReturn($newEnv);

    Environment::shouldReceive('create')
        ->once()
        ->with(Mockery::on(function ($data) {
            return $data['description'] === 'Cloned from production';
        }))
        ->andReturn($newEnv);

    ResourceLink::shouldReceive('where->get')->andReturn(collect());

    $this->action->execute($source, ['name' => 'clone']);
});
