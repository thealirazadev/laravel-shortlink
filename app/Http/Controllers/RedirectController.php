<?php

namespace App\Http\Controllers;

use App\Models\Click;
use App\Models\Link;
use App\Support\ClientIp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class RedirectController extends Controller
{
    public function show(string $code, Request $request): RedirectResponse
    {
        $link = Link::where('code', $code)->first();

        if (! $link) {
            abort(404);
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
