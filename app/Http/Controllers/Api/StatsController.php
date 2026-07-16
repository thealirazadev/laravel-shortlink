<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClickResource;
use App\Models\Link;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StatsController extends Controller
{
    private const RECENT_LIMIT = 20;

    public function show(Request $request, string $id): Response
    {
        $link = Link::findOrFail($id);

        if ($link->user_id !== $request->user()->id) {
            throw new AuthorizationException;
        }

        $clicksByDay = $link->clicks()
            ->orderBy('created_at')
            ->pluck('created_at')
            ->groupBy(fn ($date) => $date->toDateString())
            ->map(fn ($group, $date) => ['date' => $date, 'clicks' => $group->count()])
            ->values();

        $recent = $link->clicks()->latest('created_at')->take(self::RECENT_LIMIT)->get();

        return ApiResponse::success([
            'link' => [
                'id' => $link->id,
                'code' => $link->code,
                'short_url' => rtrim(config('app.url'), '/').'/'.$link->code,
                'target_url' => $link->target_url,
            ],
            'totals' => [
                'clicks' => $link->clicks()->count(),
                'unique_ips' => $link->clicks()->whereNotNull('ip')->distinct('ip')->count('ip'),
            ],
            'clicks_by_day' => $clicksByDay,
            'recent' => ClickResource::collection($recent),
        ]);
    }
}
