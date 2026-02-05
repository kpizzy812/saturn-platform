<?php

test('AutoProvisionServerJob imports StartProxy', function () {
    $content = file_get_contents(app_path('Jobs/AutoProvisionServerJob.php'));

    expect($content)->toContain('use App\Actions\Proxy\StartProxy;');
});

test('AutoProvisionServerJob calls StartProxy after Docker install', function () {
    $content = file_get_contents(app_path('Jobs/AutoProvisionServerJob.php'));

    // Verify StartProxy::run is called after InstallDocker::run
    $dockerPos = strpos($content, 'InstallDocker::run($server)');
    $proxyPos = strpos($content, 'StartProxy::run($server, async: false)');

    expect($dockerPos)->toBeInt();
    expect($proxyPos)->toBeInt();
    expect($proxyPos)->toBeGreaterThan($dockerPos);
});
