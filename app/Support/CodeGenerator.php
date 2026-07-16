<?php

namespace App\Support;

use App\Models\Link;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Generates a unique short code for links. Codes are checked against all
 * links, including soft-deleted ones, so a removed code is never reissued.
 */
class CodeGenerator
{
    private const LENGTH = 8;

    private const MAX_ATTEMPTS = 5;

    public static function generate(): string
    {
        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $code = Str::random(self::LENGTH);

            if (! Link::withTrashed()->where('code', $code)->exists()) {
                return $code;
            }
        }

        throw new RuntimeException('Unable to generate a unique short code after '.self::MAX_ATTEMPTS.' attempts.');
    }
}
