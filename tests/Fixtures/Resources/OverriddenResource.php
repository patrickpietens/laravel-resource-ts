<?php

namespace ResourceTs\Tests\Fixtures\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use ResourceTs\Attributes\Typescript;

#[Typescript(
    name: 'CustomType',
    overrides: [
        'metadata' => 'Record<string, string>',
        'tags' => 'string[]',
    ],
    exclude: ['internal'],
)]
class OverriddenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'metadata' => $this->metadata,
            'tags' => $this->tags,
            'internal' => $this->internal,
        ];
    }
}
