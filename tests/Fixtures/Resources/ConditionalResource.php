<?php

namespace ResourceTs\Tests\Fixtures\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConditionalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'secret' => $this->when($this->isAdmin(), 'secret-value'),
            'posts' => UserResource::collection($this->posts),
            'profile' => $this->whenLoaded('profile'),
            'avatar' => $this->whenLoaded('avatar', fn () => UserResource::make($this->avatar)),
            'nickname' => $this->whenNotNull($this->nickname),
        ];
    }
}
