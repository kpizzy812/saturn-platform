<?php

/**
 * Tests that status-related fields are mass-assignable on all models
 * that GetContainersStatus and PushServerUpdateJob update via $model->update().
 *
 * Without 'status' in $fillable, Eloquent's mass assignment protection
 * silently drops status changes, causing containers to appear "exited"
 * even when running.
 */
it('Application model has status in fillable', function () {
    $app = new App\Models\Application;
    expect($app->isFillable('status'))->toBeTrue('Application.status must be mass-assignable for GetContainersStatus');
    expect($app->isFillable('last_online_at'))->toBeTrue('Application.last_online_at must be mass-assignable');
});

it('ApplicationPreview model has status in fillable', function () {
    $preview = new App\Models\ApplicationPreview;
    expect($preview->isFillable('status'))->toBeTrue('ApplicationPreview.status must be mass-assignable');
    expect($preview->isFillable('last_online_at'))->toBeTrue('ApplicationPreview.last_online_at must be mass-assignable');
});

it('ServiceApplication model has status in fillable', function () {
    $sa = new App\Models\ServiceApplication;
    expect($sa->isFillable('status'))->toBeTrue('ServiceApplication.status must be mass-assignable');
    expect($sa->isFillable('last_online_at'))->toBeTrue('ServiceApplication.last_online_at must be mass-assignable');
});

it('ServiceDatabase model has status in fillable', function () {
    $sd = new App\Models\ServiceDatabase;
    expect($sd->isFillable('status'))->toBeTrue('ServiceDatabase.status must be mass-assignable');
    expect($sd->isFillable('last_online_at'))->toBeTrue('ServiceDatabase.last_online_at must be mass-assignable');
});

it('all standalone database models have status in fillable', function () {
    $models = [
        App\Models\StandalonePostgresql::class,
        App\Models\StandaloneMysql::class,
        App\Models\StandaloneRedis::class,
        App\Models\StandaloneMongodb::class,
        App\Models\StandaloneMariadb::class,
        App\Models\StandaloneKeydb::class,
        App\Models\StandaloneDragonfly::class,
        App\Models\StandaloneClickhouse::class,
    ];

    foreach ($models as $modelClass) {
        $model = new $modelClass;
        $shortName = class_basename($modelClass);
        expect($model->isFillable('status'))->toBeTrue("{$shortName}.status must be mass-assignable");
        expect($model->isFillable('last_online_at'))->toBeTrue("{$shortName}.last_online_at must be mass-assignable");
    }
});
