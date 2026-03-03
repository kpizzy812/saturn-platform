<?php

use App\Models\InstanceSettings;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redis;

uses(DatabaseTransactions::class);

/**
 * Clean up per-token Redis keys created during tests to avoid interference.
 */
function tokenRateLimitKey(int $tokenId): string
{
    return 'saturn:token_ratelimit:'.hash('sha256', (string) $tokenId);
}

beforeEach(function () {
    // Set a low limit so tests don't need to fire 60 requests
    Config::set('api.token_rate_limit', 3);

    $this->team = Team::factory()->create(['name' => 'Rate Limit Test Team']);
    $this->user = User::factory()->create();
    $this->team->members()->attach($this->user->id, ['role' => 'owner']);
    session(['currentTeam' => $this->team]);

    $tokenResult = $this->user->createToken('token-a', ['*']);
    $this->tokenA = $tokenResult->plainTextToken;
    $this->tokenAId = $tokenResult->accessToken->id;

    $secondUser = User::factory()->create();
    $this->team->members()->attach($secondUser->id, ['role' => 'owner']);
    session(['currentTeam' => $this->team]);
    $tokenResult2 = $secondUser->createToken('token-b', ['*']);
    $this->tokenB = $tokenResult2->plainTextToken;
    $this->tokenBId = $tokenResult2->accessToken->id;

    if (! InstanceSettings::find(0)) {
        InstanceSettings::create(['id' => 0, 'is_api_enabled' => true]);
    } else {
        InstanceSettings::where('id', 0)->update(['is_api_enabled' => true]);
    }
});

afterEach(function () {
    // Clean up Redis keys to prevent test pollution
    if (isset($this->tokenAId)) {
        Redis::del(tokenRateLimitKey($this->tokenAId));
    }
    if (isset($this->tokenBId)) {
        Redis::del(tokenRateLimitKey($this->tokenBId));
    }
});

describe('ApiTokenRateLimiter middleware', function () {
    test('allows requests under the per-token limit', function () {
        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->tokenA])
            ->getJson('/api/v1/version');

        $response->assertStatus(200);
        $response->assertHeader('X-RateLimit-Limit', '3');
        $response->assertHeader('X-RateLimit-Remaining', '2');
    });

    test('returns 429 when per-token limit is exceeded', function () {
        // Exhaust all 3 allowed requests
        for ($i = 0; $i < 3; $i++) {
            $this->withHeaders(['Authorization' => 'Bearer '.$this->tokenA])
                ->getJson('/api/v1/version');
        }

        // 4th request should be blocked
        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->tokenA])
            ->getJson('/api/v1/version');

        $response->assertStatus(429);
        $response->assertHeader('X-RateLimit-Remaining', '0');
        expect($response->headers->has('Retry-After'))->toBeTrue();
        $retryAfter = (int) $response->headers->get('Retry-After');
        expect($retryAfter)->toBeGreaterThanOrEqual(1);
        expect($retryAfter)->toBeLessThanOrEqual(60);
    });

    test('429 response contains correct JSON message', function () {
        for ($i = 0; $i < 4; $i++) {
            $this->withHeaders(['Authorization' => 'Bearer '.$this->tokenA])
                ->getJson('/api/v1/version');
        }

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->tokenA])
            ->getJson('/api/v1/version');

        $response->assertStatus(429);
        $response->assertJson(['message' => 'Too Many Requests.']);
    });

    test('two tokens are rate-limited independently and do not interfere', function () {
        // Exhaust token A's quota
        for ($i = 0; $i < 4; $i++) {
            $this->withHeaders(['Authorization' => 'Bearer '.$this->tokenA])
                ->getJson('/api/v1/version');
        }

        // Token A is now blocked
        $responseA = $this->withHeaders(['Authorization' => 'Bearer '.$this->tokenA])
            ->getJson('/api/v1/version');
        $responseA->assertStatus(429);

        // Token B must still be allowed (different Redis key)
        $responseB = $this->withHeaders(['Authorization' => 'Bearer '.$this->tokenB])
            ->getJson('/api/v1/version');
        $responseB->assertStatus(200);
        $responseB->assertHeader('X-RateLimit-Limit', '3');
    });

    test('remaining count decrements correctly across requests', function () {
        $r1 = $this->withHeaders(['Authorization' => 'Bearer '.$this->tokenA])
            ->getJson('/api/v1/version');
        $r1->assertStatus(200);
        expect((int) $r1->headers->get('X-RateLimit-Remaining'))->toBe(2);

        $r2 = $this->withHeaders(['Authorization' => 'Bearer '.$this->tokenA])
            ->getJson('/api/v1/version');
        $r2->assertStatus(200);
        expect((int) $r2->headers->get('X-RateLimit-Remaining'))->toBe(1);

        $r3 = $this->withHeaders(['Authorization' => 'Bearer '.$this->tokenA])
            ->getJson('/api/v1/version');
        $r3->assertStatus(200);
        expect((int) $r3->headers->get('X-RateLimit-Remaining'))->toBe(0);

        $r4 = $this->withHeaders(['Authorization' => 'Bearer '.$this->tokenA])
            ->getJson('/api/v1/version');
        $r4->assertStatus(429);
    });

    test('X-RateLimit-Limit header reflects configured limit', function () {
        Config::set('api.token_rate_limit', 5);

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->tokenA])
            ->getJson('/api/v1/version');

        $response->assertStatus(200);
        $response->assertHeader('X-RateLimit-Limit', '5');
    });
});
