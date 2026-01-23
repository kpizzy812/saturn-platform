<?php

namespace Database\Factories;

use App\Models\PrivateKey;
use Illuminate\Database\Eloquent\Factories\Factory;
use phpseclib3\Crypt\RSA;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PrivateKey>
 */
class PrivateKeyFactory extends Factory
{
    protected $model = PrivateKey::class;

    /**
     * Generate a unique RSA private key for testing.
     */
    public static function generateTestKey(): string
    {
        $key = RSA::createKey(2048);

        return $key->toString('PKCS8');
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true).' Key',
            'description' => $this->faker->sentence(),
            'private_key' => self::generateTestKey(),
            'team_id' => 1,
            'is_git_related' => false,
        ];
    }

    /**
     * Indicate that the key is git-related.
     */
    public function gitRelated(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_git_related' => true,
        ]);
    }
}
