<?php

use App\Models\Link;
use App\Models\User;

it('lists only the caller\'s links, paginated', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    Link::factory()->for($user)->count(2)->create();
    Link::factory()->for($other)->create();

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/links');

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 2);
});

it('returns an empty list for an account with no links', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')->getJson('/api/links')
        ->assertOk()
        ->assertJsonPath('data', [])
        ->assertJsonPath('meta.total', 0);
});

it('shows a link owned by the caller', function () {
    $user = User::factory()->create();
    $link = Link::factory()->for($user)->create();

    $this->actingAs($user, 'sanctum')->getJson("/api/links/{$link->id}")
        ->assertOk()
        ->assertJsonPath('data.code', $link->code);
});

it('rejects showing another user\'s link with 403', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $link = Link::factory()->for($owner)->create();

    $this->actingAs($other, 'sanctum')->getJson("/api/links/{$link->id}")
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'forbidden');
});

it('returns 404 for a missing link id', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')->getJson('/api/links/999999')
        ->assertStatus(404)
        ->assertJsonPath('error.code', 'not_found');
});

it('soft deletes a link and the code then returns 410', function () {
    $user = User::factory()->create();
    $link = Link::factory()->for($user)->create();

    $this->actingAs($user, 'sanctum')->deleteJson("/api/links/{$link->id}")
        ->assertNoContent();

    expect($link->fresh()->trashed())->toBeTrue();

    $this->get('/'.$link->code)->assertStatus(410);
});
