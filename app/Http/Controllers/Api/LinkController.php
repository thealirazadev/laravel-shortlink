<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLinkRequest;
use App\Http\Resources\LinkResource;
use App\Support\ApiResponse;
use App\Support\CodeGenerator;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LinkController extends Controller
{
    public function store(StoreLinkRequest $request): Response
    {
        $link = $request->user()->links()->create([
            'code' => CodeGenerator::generate(),
            'target_url' => $request->input('target_url'),
            'expires_at' => $request->input('expires_at'),
        ]);

        Log::info('link.created', ['link_id' => $link->id, 'user_id' => $request->user()->id]);

        return ApiResponse::success(new LinkResource($link), status: 201);
    }
}
