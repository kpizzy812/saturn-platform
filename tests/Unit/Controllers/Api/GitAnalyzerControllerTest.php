<?php

namespace Tests\Unit\Controllers\Api;

use App\Http\Controllers\Api\GitAnalyzerController;
use App\Services\RepositoryAnalyzer\InfrastructureProvisioner;
use App\Services\RepositoryAnalyzer\RepositoryAnalyzer;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class GitAnalyzerControllerTest extends TestCase
{
    private GitAnalyzerController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $analyzer = $this->createMock(RepositoryAnalyzer::class);
        $provisioner = $this->createMock(InfrastructureProvisioner::class);

        $this->controller = new GitAnalyzerController($analyzer, $provisioner);
    }

    /**
     * Make private method accessible for testing
     */
    private function getValidateMethod(): ReflectionMethod
    {
        $method = new ReflectionMethod(GitAnalyzerController::class, 'validateRepositoryUrl');
        $method->setAccessible(true);

        return $method;
    }

    public function test_validates_valid_github_https_url(): void
    {
        $method = $this->getValidateMethod();

        // Should not throw exception
        $method->invoke($this->controller, 'https://github.com/owner/repo');
        $method->invoke($this->controller, 'https://github.com/owner/repo.git');
        $method->invoke($this->controller, 'https://github.com/owner/repo-name');
        $method->invoke($this->controller, 'https://github.com/owner/repo_name');

        $this->assertTrue(true);
    }

    public function test_validates_valid_gitlab_https_url(): void
    {
        $method = $this->getValidateMethod();

        $method->invoke($this->controller, 'https://gitlab.com/owner/repo');
        $method->invoke($this->controller, 'https://gitlab.com/group/subgroup/repo');

        $this->assertTrue(true);
    }

    public function test_validates_valid_ssh_url(): void
    {
        $method = $this->getValidateMethod();

        $method->invoke($this->controller, 'git@github.com:owner/repo.git');
        $method->invoke($this->controller, 'git@gitlab.com:group/repo.git');

        $this->assertTrue(true);
    }

    public function test_rejects_url_with_query_parameters(): void
    {
        $method = $this->getValidateMethod();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('URL contains query parameters');

        $method->invoke($this->controller, 'https://github.com/owner?tab=repositories');
    }

    public function test_rejects_github_user_profile_page(): void
    {
        $method = $this->getValidateMethod();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('This appears to be a user profile page');

        $method->invoke($this->controller, 'https://github.com/kpizzy812');
    }

    public function test_rejects_gitlab_user_profile_page(): void
    {
        $method = $this->getValidateMethod();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('This appears to be a user profile page');

        $method->invoke($this->controller, 'https://gitlab.com/username');
    }

    public function test_rejects_bitbucket_user_profile_page(): void
    {
        $method = $this->getValidateMethod();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('This appears to be a user profile page');

        $method->invoke($this->controller, 'https://bitbucket.org/workspace');
    }

    public function test_rejects_invalid_ssh_url(): void
    {
        $method = $this->getValidateMethod();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SSH URL must be in format');

        $method->invoke($this->controller, 'git@github.com:owner');
    }

    public function test_allows_custom_git_server_urls(): void
    {
        $method = $this->getValidateMethod();

        // Custom git servers should be allowed (less strict validation)
        $method->invoke($this->controller, 'https://git.company.com/repo');
        $method->invoke($this->controller, 'https://custom-gitlab.example.org/owner/repo');

        $this->assertTrue(true);
    }
}
