<?php

use App\Support\ClientIp;

it('never returns the raw ip', function () {
    expect(ClientIp::hash('203.0.113.10'))->not->toBe('203.0.113.10');
});

it('hashes the same ip to the same value', function () {
    expect(ClientIp::hash('203.0.113.10'))->toBe(ClientIp::hash('203.0.113.10'));
});

it('hashes different ips to different values', function () {
    expect(ClientIp::hash('203.0.113.10'))->not->toBe(ClientIp::hash('203.0.113.11'));
});

it('returns null for a null ip', function () {
    expect(ClientIp::hash(null))->toBeNull();
});
