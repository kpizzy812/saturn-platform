<?php

use App\Enums\BuildPackTypes;
use App\Jobs\ApplicationDeploymentJob;
use App\Traits\Deployment\HandlesRailpackBuildpack;

afterEach(function () {
    Mockery::close();
});

// -------------------------------------------------------------------------
// BuildPackTypes enum
// -------------------------------------------------------------------------

test('BuildPackTypes enum contains railpack case', function () {
    $cases = array_map(fn ($c) => $c->value, BuildPackTypes::cases());

    expect($cases)->toContain('railpack');
});

test('BuildPackTypes railpack is the first case', function () {
    $cases = BuildPackTypes::cases();

    expect($cases[0]->value)->toBe('railpack');
});

test('all original buildpack types are preserved', function () {
    $cases = array_map(fn ($c) => $c->value, BuildPackTypes::cases());

    expect($cases)->toContain('nixpacks');
    expect($cases)->toContain('static');
    expect($cases)->toContain('dockerfile');
    expect($cases)->toContain('dockercompose');
});

// -------------------------------------------------------------------------
// ApplicationDeploymentJob routing (source-level assertions)
// -------------------------------------------------------------------------

test('decide_what_to_do routes railpack to deploy_railpack_buildpack', function () {
    $source = file_get_contents(app_path('Jobs/ApplicationDeploymentJob.php'));

    expect($source)->toContain("build_pack === 'railpack'");
    expect($source)->toContain('deploy_railpack_buildpack');
});

test('railpack case is checked before nixpacks fallback', function () {
    $source = file_get_contents(app_path('Jobs/ApplicationDeploymentJob.php'));

    $railpackPos = strpos($source, "build_pack === 'railpack'");
    $nixpacksFallbackPos = strpos($source, 'deploy_nixpacks_buildpack');

    // Railpack check must appear before the nixpacks fallback in decide_what_to_do
    expect($railpackPos)->toBeLessThan($nixpacksFallbackPos);
});

test('nixpacks remains as else fallback (not removed)', function () {
    $source = file_get_contents(app_path('Jobs/ApplicationDeploymentJob.php'));

    expect($source)->toContain('deploy_nixpacks_buildpack');
});

test('job uses HandlesRailpackBuildpack trait', function () {
    $source = file_get_contents(app_path('Jobs/ApplicationDeploymentJob.php'));

    expect($source)->toContain('HandlesRailpackBuildpack');
});

test('RAILPACK_PLAN_PATH constant is defined', function () {
    $source = file_get_contents(app_path('Jobs/ApplicationDeploymentJob.php'));

    expect($source)->toContain("RAILPACK_PLAN_PATH = '/artifacts/railpack-plan.json'");
});

// -------------------------------------------------------------------------
// HandlesRailpackBuildpack trait is importable
// -------------------------------------------------------------------------

test('HandlesRailpackBuildpack trait exists and is valid PHP', function () {
    $traitPath = app_path('Traits/Deployment/HandlesRailpackBuildpack.php');

    expect(file_exists($traitPath))->toBeTrue();

    $source = file_get_contents($traitPath);
    expect($source)->toContain('trait HandlesRailpackBuildpack');
});

test('HandlesRailpackBuildpack can be used by ApplicationDeploymentJob', function () {
    $uses = class_uses_recursive(ApplicationDeploymentJob::class);

    expect($uses)->toContain(HandlesRailpackBuildpack::class);
});
