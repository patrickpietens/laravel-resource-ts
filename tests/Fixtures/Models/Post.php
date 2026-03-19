<?php

namespace ResourceTs\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    protected $casts = [
        'id' => 'integer',
        'title' => 'string',
        'body' => 'string',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];
}
