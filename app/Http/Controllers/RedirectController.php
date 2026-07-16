<?php

namespace App\Http\Controllers;

use App\Models\Click;
use App\Models\Link;
use App\Support\ClientIp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RedirectController extends Controller
{
    // Matches the `referrer`/`user_agent` column width (see the clicks migration);
    // truncating defensively keeps a merely long header from failing the insert.
    private const MAX_HEADER_LENGTH = 255;

    public function show(string $code, Request $request): Response
    {
        $link = Link::withTrashed()->where('code', $code)->first();

        if (! $link) {
            Log::warning('redirect.not_found', ['code' => $code]);
            abort(404);
        }

        if ($link->trashed()) {
            Log::info('redirect.deleted', ['link_id' => $link->id]);

            return response()->view('errors.410', [], 410);
        }

        if ($link->isExpired()) {
            Log::info('redirect.expired', ['link_id' => $link->id]);

            return response()->view('errors.expired', [], 410);
        }

        $this->recordClick($link, $request);

        Log::info('redirect.succeeded', ['link_id' => $link->id]);

        return redirect()->away($link->target_url, 302);
    }

    private function recordClick(Link $link, Request $request): void
    {
        try {
            Click::create([
                'link_id' => $link->id,
                'referrer' => $this->truncateHeader($request->header('referer')),
                'user_agent' => $this->truncateHeader($request->userAgent()),
                'ip' => ClientIp::hash($request->ip()),
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('click.record_failed', [
                'link_id' => $link->id,
                'reason' => $e->getMessage(),
            ]);
        }
    }

    private function truncateHeader(?string $value): ?string
    {
        return $value === null ? null : Str::limit($value, self::MAX_HEADER_LENGTH, '');
    }
}
