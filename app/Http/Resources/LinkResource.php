<?php

namespace App\Http\Resources;

use App\Models\Link;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Link
 */
class LinkResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'short_url' => rtrim(config('app.url'), '/').'/'.$this->code,
            'target_url' => $this->target_url,
            'expires_at' => $this->expires_at?->toIso8601ZuluString(),
            'created_at' => $this->created_at->toIso8601ZuluString(),
        ];
    }
}
