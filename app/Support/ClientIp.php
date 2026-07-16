<?php

namespace App\Support;

/**
 * Hashes a client IP for storage. Never persist a raw IP; the app key is
 * used as an HMAC salt so hashes cannot be reversed without it.
 */
class ClientIp
{
    public static function hash(?string $ip): ?string
    {
        if ($ip === null) {
            return null;
        }

        return hash_hmac('sha256', $ip, (string) config('app.key'));
    }
}
