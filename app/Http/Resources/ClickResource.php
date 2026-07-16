<?php

namespace App\Http\Resources;

use App\Models\Click;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shapes a click for the "recent" list in stats. Never includes the ip.
 *
 * @mixin Click
 */
class ClickResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'referrer' => $this->referrer,
            'user_agent' => $this->user_agent,
            'created_at' => $this->created_at->toIso8601ZuluString(),
        ];
    }
}
