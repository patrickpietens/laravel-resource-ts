<?php

namespace ResourceTs\Tests\Fixtures\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SimpleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'count' => (int) $this->count,
            'label' => (string) $this->label,
            'active' => (bool) $this->active,
            'score' => (float) $this->score,
            'literal_string' => 'hello',
            'literal_int' => 42,
            'literal_float' => 3.14,
            'literal_bool' => true,
            'literal_null' => null,
        ];
    }
}
