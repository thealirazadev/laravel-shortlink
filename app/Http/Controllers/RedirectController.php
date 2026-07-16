<?php

namespace App\Http\Controllers;

use App\Models\Click;
use App\Models\Link;
use App\Support\ClientIp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RedirectController extends Controller
{
    public function show(string $code, Request $request): Response
    {
        $link = Link::withTrashed()->where('code', $code)->first();

        if (! $link) {
            abort(404);
        }

        if ($link->trashed()) {
            return response()->view('errors.410', [], 410);
        }

        if ($link->isExpired()) {
            return response()->view('errors.410', [], 410);
        }

        $this->recordClick($link, $request);

        return redirect()->away($link->target_url, 302);
    }

    private function recordClick(Link $link, Request $request): void
    {
        try {
            Click::create([
                'link_id' => $link->id,
                'referrer' => $request->header('referer'),
                'user_agent' => $request->userAgent(),
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
}
