<?php

use App\Models\ProjectSetting;
use App\Models\Server;

it('has defaultServer relationship defined', function () {
    $setting = new ProjectSetting;
    $relation = $setting->defaultServer();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class);
    expect($relation->getForeignKeyName())->toBe('default_server_id');
    expect($relation->getRelated())->toBeInstanceOf(Server::class);
});

it('has project relationship defined', function () {
    $setting = new ProjectSetting;
    $relation = $setting->project();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});
