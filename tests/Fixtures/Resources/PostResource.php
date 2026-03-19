<?php

namespace ResourceTs\Tests\Fixtures\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use ResourceTs\Attributes\Typescript;
use ResourceTs\Tests\Fixtures\Models\Post;

#[Typescript(model: Post::class)]
class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'is_published' => $this->is_published,
            'published_at' => $this->published_at,
            'author' => UserResource::make($this->author),
        ];
    }
}
