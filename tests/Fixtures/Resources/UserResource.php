<?php

namespace ResourceTs\Tests\Fixtures\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use ResourceTs\Attributes\Typescript;
use ResourceTs\Tests\Fixtures\Models\User;

#[Typescript(model: User::class)]
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'is_admin' => $this->is_admin,
            'balance' => $this->balance,
            'email_verified_at' => $this->email_verified_at,
            'metadata' => $this->metadata,
            'settings' => $this->settings,
        ];
    }
}
