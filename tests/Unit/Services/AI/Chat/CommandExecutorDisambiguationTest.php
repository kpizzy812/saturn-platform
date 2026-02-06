<?php

namespace Tests\Unit\Services\AI\Chat;

use App\Models\User;
use App\Services\AI\Chat\CommandExecutor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

class CommandExecutorDisambiguationTest extends TestCase
{
    private CommandExecutor $executor;

    private ReflectionMethod $resolveMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $user = Mockery::mock(User::class);
        $user->shouldReceive('getAttribute')->with('id')->andReturn(1);

        $this->executor = new CommandExecutor($user, 1);

        $this->resolveMethod = new ReflectionMethod(CommandExecutor::class, 'resolveUniqueMatch');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeModel(string $name): Model
    {
        $model = Mockery::mock(Model::class)->shouldIgnoreMissing();
        $model->shouldReceive('getAttribute')->with('name')->andReturn($name);
        $model->shouldReceive('offsetGet')->with('name')->andReturn($name);
        $model->shouldReceive('__get')->with('name')->andReturn($name);

        return $model;
    }

    private function resolve(Collection $matches, string $cleanName): ?Model
    {
        return $this->resolveMethod->invoke($this->executor, $matches, $cleanName);
    }

    public function test_returns_null_for_empty_collection(): void
    {
        $result = $this->resolve(collect(), 'frontend');

        $this->assertNull($result);
    }

    public function test_returns_single_match(): void
    {
        $model = $this->makeModel('frontend');

        $result = $this->resolve(collect([$model]), 'frontend');

        $this->assertSame($model, $result);
    }

    public function test_exact_name_wins_over_partial(): void
    {
        $clone = $this->makeModel('frontend (Clone)');
        $exact = $this->makeModel('frontend');

        $result = $this->resolve(collect([$clone, $exact]), 'frontend');

        $this->assertSame($exact, $result);
    }

    public function test_exact_name_case_insensitive(): void
    {
        $clone = $this->makeModel('Frontend (Clone)');
        $exact = $this->makeModel('Frontend');

        $result = $this->resolve(collect([$clone, $exact]), 'frontend');

        $this->assertSame($exact, $result);
    }

    public function test_returns_null_when_multiple_exact_matches(): void
    {
        $first = $this->makeModel('frontend');
        $second = $this->makeModel('frontend');

        $result = $this->resolve(collect([$first, $second]), 'frontend');

        $this->assertNull($result);
    }

    public function test_returns_null_when_no_exact_match_in_multiple(): void
    {
        $a = $this->makeModel('frontend-app');
        $b = $this->makeModel('frontend-web');

        $result = $this->resolve(collect([$a, $b]), 'frontend');

        $this->assertNull($result);
    }
}
