<?php

use App\Models\Link;
use App\Support\CodeGenerator;

it('produces codes of the expected length and alphabet', function () {
    $code = CodeGenerator::generate();

    expect($code)->toHaveLength(8)
        ->and($code)->toMatch('/^[A-Za-z0-9]+$/');
});

it('retries until it finds a code that does not already exist', function () {
    $existing = Link::factory()->create(['code' => 'AAAAAAAA']);

    $code = CodeGenerator::generate();

    expect($code)->not->toBe($existing->code);
});
