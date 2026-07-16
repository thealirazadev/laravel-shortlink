<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLinkRequest;
use App\Http\Resources\LinkResource;
use App\Models\Link;
use App\Support\ApiResponse;
use App\Support\CodeGenerator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LinkController extends Controller
{
    public function index(Request $request): Response
    {
        $perPage = min((int) $request->integer('per_page', 15), 100);
        $perPage = $perPage > 0 ? $perPage : 15;

        $paginator = $request->user()->links()->latest()->paginate($perPage);

        return ApiResponse::success(LinkResource::collection($paginator->items()), [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ]);
    }

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

    public function show(Request $request, string $id): Response
    {
        $link = $this->findOwnedOrFail($request, $id);

        return ApiResponse::success(new LinkResource($link));
    }

    public function destroy(Request $request, string $id): Response
    {
        $link = $this->findOwnedOrFail($request, $id);
        $link->delete();

        Log::info('link.deleted', ['link_id' => $link->id, 'user_id' => $request->user()->id]);

        return response()->noContent();
    }

    private function findOwnedOrFail(Request $request, string $id): Link
    {
        $link = Link::findOrFail($id);

        if ($link->user_id !== $request->user()->id) {
            throw new AuthorizationException;
        }

        return $link;
    }
}
