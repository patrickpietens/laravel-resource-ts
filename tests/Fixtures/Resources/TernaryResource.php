<?php

namespace ResourceTs\Tests\Fixtures\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TernaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'status' => $this->is_active ? 'active' : 'inactive',
            'value' => $this->value ?? 'default',
        ];
    }
}
