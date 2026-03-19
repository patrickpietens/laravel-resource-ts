<?php

namespace ResourceTs\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $casts = [
        'id' => 'integer',
        'name' => 'string',
        'email' => 'string',
        'is_admin' => 'boolean',
        'balance' => 'float',
        'email_verified_at' => 'datetime',
        'metadata' => 'array',
        'settings' => 'object',
    ];
}
