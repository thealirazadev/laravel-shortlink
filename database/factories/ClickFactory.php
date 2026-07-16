<?php

namespace Database\Factories;

use App\Models\Click;
use App\Models\Link;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Click>
 */
class ClickFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'link_id' => Link::factory(),
            'referrer' => $this->faker->url(),
            'user_agent' => $this->faker->userAgent(),
            'ip' => hash('sha256', $this->faker->ipv4()),
            'created_at' => now(),
        ];
    }
}
