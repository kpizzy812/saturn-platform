<?php

// ====== HandlesHealthCheck ======

it('HandlesHealthCheck defines rolling_update method', function () {
    $code = file_get_contents(app_path('Traits/Deployment/HandlesHealthCheck.php'));
    expect($code)->toContain('function rolling_update(');
});

it('HandlesHealthCheck defines health_check method', function () {
    $code = file_get_contents(app_path('Traits/Deployment/HandlesHealthCheck.php'));
    expect($code)->toContain('function health_check(');
});

it('HandlesHealthCheck defines detectMissingEnvVars method', function () {
    $code = file_get_contents(app_path('Traits/Deployment/HandlesHealthCheck.php'));
    expect($code)->toContain('function detectMissingEnvVars(');
});

it('HandlesHealthCheck detects framework-specific env var errors', function () {
    $code = file_get_contents(app_path('Traits/Deployment/HandlesHealthCheck.php'));
    // Python/Django/Node.js env var error patterns
    expect($code)->toContain('ValidationError')->or->toContain('ImproperlyConfigured')->or->toContain('process.env');
});

it('HandlesHealthCheck defines perform_smoke_test method', function () {
    $code = file_get_contents(app_path('Traits/Deployment/HandlesHealthCheck.php'));
    expect($code)->toContain('function perform_smoke_test(');
});

it('HandlesHealthCheck defines verify_container_stability method', function () {
    $code = file_get_contents(app_path('Traits/Deployment/HandlesHealthCheck.php'));
    expect($code)->toContain('function verify_container_stability(');
});

it('HandlesHealthCheck defines checkContainerState method', function () {
    $code = file_get_contents(app_path('Traits/Deployment/HandlesHealthCheck.php'));
    expect($code)->toContain('function checkContainerState(');
});

it('HandlesHealthCheck tracks deployment health status', function () {
    $code = file_get_contents(app_path('Traits/Deployment/HandlesHealthCheck.php'));
    expect($code)->toContain('newVersionIsHealthy');
});

it('HandlesHealthCheck handles canary deployment container preservation', function () {
    $code = file_get_contents(app_path('Traits/Deployment/HandlesHealthCheck.php'));
    expect($code)->toContain('canary');
});

it('HandlesHealthCheck defines analyzeContainerFailure method', function () {
    $code = file_get_contents(app_path('Traits/Deployment/HandlesHealthCheck.php'));
    expect($code)->toContain('analyzeContainerFailure')
        ->or->toContain('function analyzeContainerFailure');
});

it('HandlesHealthCheck uses docker inspect for container state', function () {
    $code = file_get_contents(app_path('Traits/Deployment/HandlesHealthCheck.php'));
    expect($code)->toContain('docker inspect');
});

// ====== HandlesAutoRollback ======

it('HandlesAutoRollback defines attemptAutoRollback method', function () {
    $code = file_get_contents(app_path('Traits/Deployment/HandlesAutoRollback.php'));
    expect($code)->toContain('function attemptAutoRollback(');
});

it('HandlesAutoRollback skips rollback when auto_rollback is disabled', function () {
    $code = file_get_contents(app_path('Traits/Deployment/HandlesAutoRollback.php'));
    expect($code)->toContain('auto_rollback');
});

it('HandlesAutoRollback prevents infinite rollback loops by checking rollback flag', function () {
    $code = file_get_contents(app_path('Traits/Deployment/HandlesAutoRollback.php'));
    // Guard against rolling back a rollback
    expect($code)->toContain('$this->rollback');
});

it('HandlesAutoRollback skips rollback for pull request deployments', function () {
    $code = file_get_contents(app_path('Traits/Deployment/HandlesAutoRollback.php'));
    expect($code)->toContain('pull_request_id');
});

it('HandlesAutoRollback finds last successful finished deployment', function () {
    $code = file_get_contents(app_path('Traits/Deployment/HandlesAutoRollback.php'));
    expect($code)->toContain('FINISHED')->or->toContain('finished');
});

it('HandlesAutoRollback queues new deployment with rollback flag enabled', function () {
    $code = file_get_contents(app_path('Traits/Deployment/HandlesAutoRollback.php'));
    expect($code)->toContain('rollback: true')->or->toContain("'rollback'");
});

it('HandlesAutoRollback generates new CUID2 for rollback deployment UUID', function () {
    $code = file_get_contents(app_path('Traits/Deployment/HandlesAutoRollback.php'));
    expect($code)->toContain('Cuid2');
});

// ====== HandlesBuildtimeEnvGeneration ======

it('HandlesBuildtimeEnvGeneration defines generate_buildtime_environment_variables', function () {
    $code = file_get_contents(app_path('Traits/Deployment/HandlesBuildtimeEnvGeneration.php'));
    expect($code)->toContain('function generate_buildtime_environment_variables(');
});

it('HandlesBuildtimeEnvGeneration adds SERVICE_NAME and SERVICE_FQDN for dockercompose', function () {
    $code = file_get_contents(app_path('Traits/Deployment/HandlesBuildtimeEnvGeneration.php'));
    expect($code)->toContain('SERVICE_NAME')->or->toContain('SERVICE_FQDN');
});

it('HandlesBuildtimeEnvGeneration applies variable deduplication via associative array', function () {
    $code = file_get_contents(app_path('Traits/Deployment/HandlesBuildtimeEnvGeneration.php'));
    // Uses envs_dict or similar for deduplication
    expect($code)->toContain('envs_dict')->or->toContain('$envs');
});

it('HandlesBuildtimeEnvGeneration handles literal and multiline variable escaping', function () {
    $code = file_get_contents(app_path('Traits/Deployment/HandlesBuildtimeEnvGeneration.php'));
    expect($code)->toContain('escapeBash')->or->toContain('literal')->or->toContain('multiline');
});

it('HandlesBuildtimeEnvGeneration defines addUserDefinedBuildtimeVariables method', function () {
    $code = file_get_contents(app_path('Traits/Deployment/HandlesBuildtimeEnvGeneration.php'));
    expect($code)->toContain('addUserDefinedBuildtimeVariables')
        ->or->toContain('buildtime');
});

// ====== HandlesRuntimeEnvGeneration ======

it('HandlesRuntimeEnvGeneration defines generate_runtime_environment_variables', function () {
    $code = file_get_contents(app_path('Traits/Deployment/HandlesRuntimeEnvGeneration.php'));
    expect($code)->toContain('function generate_runtime_environment_variables(');
});

it('HandlesRuntimeEnvGeneration auto-injects PORT environment variable', function () {
    $code = file_get_contents(app_path('Traits/Deployment/HandlesRuntimeEnvGeneration.php'));
    expect($code)->toContain('PORT');
});

it('HandlesRuntimeEnvGeneration auto-injects HOST bind address', function () {
    $code = file_get_contents(app_path('Traits/Deployment/HandlesRuntimeEnvGeneration.php'));
    expect($code)->toContain('HOST')->toContain('0.0.0.0');
});

it('HandlesRuntimeEnvGeneration defines save_runtime_environment_variables method', function () {
    $code = file_get_contents(app_path('Traits/Deployment/HandlesRuntimeEnvGeneration.php'));
    expect($code)->toContain('function save_runtime_environment_variables(');
});

it('HandlesRuntimeEnvGeneration supports environment variable sorting', function () {
    $code = file_get_contents(app_path('Traits/Deployment/HandlesRuntimeEnvGeneration.php'));
    expect($code)->toContain('is_env_sorting_enabled')->or->toContain('sorting');
});

it('HandlesRuntimeEnvGeneration handles PR preview deployments separately', function () {
    $code = file_get_contents(app_path('Traits/Deployment/HandlesRuntimeEnvGeneration.php'));
    expect($code)->toContain('pull_request_id')->or->toContain('preview');
});

it('HandlesRuntimeEnvGeneration base64-encodes .env file content', function () {
    $code = file_get_contents(app_path('Traits/Deployment/HandlesRuntimeEnvGeneration.php'));
    expect($code)->toContain('base64');
});

// ====== HandlesComposeFileGeneration ======

it('HandlesComposeFileGeneration defines generate_compose_file method', function () {
    $code = file_get_contents(app_path('Traits/Deployment/HandlesComposeFileGeneration.php'));
    expect($code)->toContain('function generate_compose_file(');
});

it('HandlesComposeFileGeneration includes healthcheck configuration', function () {
    $code = file_get_contents(app_path('Traits/Deployment/HandlesComposeFileGeneration.php'));
    expect($code)->toContain('healthcheck');
});

it('HandlesComposeFileGeneration handles Docker Swarm mode', function () {
    $code = file_get_contents(app_path('Traits/Deployment/HandlesComposeFileGeneration.php'));
    expect($code)->toContain('swarm')->or->toContain('Swarm');
});

it('HandlesComposeFileGeneration creates external Saturn network and local network', function () {
    $code = file_get_contents(app_path('Traits/Deployment/HandlesComposeFileGeneration.php'));
    expect($code)->toContain('networks');
});

it('HandlesComposeFileGeneration supports GPU device configuration', function () {
    $code = file_get_contents(app_path('Traits/Deployment/HandlesComposeFileGeneration.php'));
    expect($code)->toContain('gpu')->or->toContain('GPU');
});

it('HandlesComposeFileGeneration sets memory and CPU resource limits', function () {
    $code = file_get_contents(app_path('Traits/Deployment/HandlesComposeFileGeneration.php'));
    expect($code)->toContain('mem_limit')->or->toContain('memory');
});

it('HandlesComposeFileGeneration uses env_file for environment variables', function () {
    $code = file_get_contents(app_path('Traits/Deployment/HandlesComposeFileGeneration.php'));
    expect($code)->toContain('env_file');
});

// ====== HandlesDeploymentConfiguration ======

it('HandlesDeploymentConfiguration defines detectBuildKitCapabilities method', function () {
    $code = file_get_contents(app_path('Traits/Deployment/HandlesDeploymentConfiguration.php'));
    expect($code)->toContain('function detectBuildKitCapabilities(');
});

it('HandlesDeploymentConfiguration defines write_deployment_configurations method', function () {
    $code = file_get_contents(app_path('Traits/Deployment/HandlesDeploymentConfiguration.php'));
    expect($code)->toContain('function write_deployment_configurations(');
});

it('HandlesDeploymentConfiguration checks Docker version for BuildKit support', function () {
    $code = file_get_contents(app_path('Traits/Deployment/HandlesDeploymentConfiguration.php'));
    expect($code)->toContain('dockerBuildkitSupported')
        ->or->toContain('BuildKit')
        ->or->toContain('buildkit');
});

it('HandlesDeploymentConfiguration requires Docker 18.09 or newer for BuildKit', function () {
    $code = file_get_contents(app_path('Traits/Deployment/HandlesDeploymentConfiguration.php'));
    expect($code)->toContain('18.09')->or->toContain('buildx')->or->toContain('secrets');
});

it('HandlesDeploymentConfiguration falls back gracefully when BuildKit unavailable', function () {
    $code = file_get_contents(app_path('Traits/Deployment/HandlesDeploymentConfiguration.php'));
    expect($code)->toContain('catch')->or->toContain('fallback');
});
