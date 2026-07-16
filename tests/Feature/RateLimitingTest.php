<?php

it('returns 429 with the rate limited envelope and headers once the auth limit is exceeded', function () {
    for ($i = 0; $i < 10; $i++) {
        $this->postJson('/api/login', ['email' => 'nope@example.com', 'password' => 'wrong']);
    }

    $response = $this->postJson('/api/login', ['email' => 'nope@example.com', 'password' => 'wrong']);

    $response->assertStatus(429)
        ->assertJsonPath('error.code', 'rate_limited')
        ->assertHeader('X-RateLimit-Limit', '10')
        ->assertHeader('X-RateLimit-Remaining', '0');

    expect($response->headers->has('Retry-After'))->toBeTrue();
});

it('does not rate limit a different ip once one ip is exhausted', function () {
    for ($i = 0; $i < 10; $i++) {
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
            ->postJson('/api/login', ['email' => 'nope@example.com', 'password' => 'wrong']);
    }

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
        ->postJson('/api/login', ['email' => 'nope@example.com', 'password' => 'wrong'])
        ->assertStatus(429);

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
        ->postJson('/api/login', ['email' => 'nope@example.com', 'password' => 'wrong'])
        ->assertStatus(422);
});
