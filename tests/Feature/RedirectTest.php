<?php

use App\Models\Click;
use App\Models\Link;
use Illuminate\Support\Facades\Log;

it('redirects an active link and records exactly one click with a hashed ip', function () {
    $link = Link::factory()->create(['target_url' => 'https://example.com/target']);

    $response = $this->withHeaders(['Referer' => 'https://referrer.example'])
        ->get('/'.$link->code);

    $response->assertStatus(302)->assertRedirect('https://example.com/target');

    $this->assertDatabaseCount('clicks', 1);

    $click = Click::first();
    expect($click->link_id)->toBe($link->id)
        ->and($click->referrer)->toBe('https://referrer.example')
        ->and($click->ip)->not->toBeNull()
        ->and($click->ip)->not->toBe('127.0.0.1');
});

it('returns 404 for an unknown code', function () {
    $this->get('/does-not-exist')->assertStatus(404);
});

it('returns 410 for a soft-deleted link and does not redirect', function () {
    $link = Link::factory()->create();
    $link->delete();

    $response = $this->get('/'.$link->code);

    $response->assertStatus(410);
    $this->assertDatabaseCount('clicks', 0);
});

it('returns 410 with the expired page for an expired link and does not redirect', function () {
    $link = Link::factory()->expired()->create();

    $response = $this->get('/'.$link->code);

    $response->assertStatus(410);
    $response->assertSee('Link expired');
    $this->assertDatabaseCount('clicks', 0);
});

it('still redirects when click recording fails, and logs the failure', function () {
    Log::spy();

    $link = Link::factory()->create(['target_url' => 'https://example.com/target']);

    Click::creating(function () {
        throw new RuntimeException('simulated database failure');
    });

    $response = $this->get('/'.$link->code);

    $response->assertStatus(302)->assertRedirect('https://example.com/target');
    $this->assertDatabaseCount('clicks', 0);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn ($message, $context) => $message === 'click.record_failed' && $context['link_id'] === $link->id)
        ->once();
});

it('truncates an overly long user agent instead of failing the insert', function () {
    $link = Link::factory()->create(['target_url' => 'https://example.com/target']);
    $longUserAgent = str_repeat('A', 500);

    $response = $this->withHeaders(['User-Agent' => $longUserAgent])->get('/'.$link->code);

    $response->assertStatus(302);
    expect(Click::first()->user_agent)->toHaveLength(255);
});
